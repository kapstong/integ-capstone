<?php
/**
 * Budget Exchange API (single gateway)
 * - GET action=allocations (OAuth bearer required)
 * - POST action=allocate (OAuth bearer required)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/coa_validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS oauth_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            client_id VARCHAR(80) NOT NULL UNIQUE,
            client_secret VARCHAR(120) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT NOW()
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS oauth_access_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            access_token VARCHAR(120) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT NOW()
        )
    ");

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $tokenData = validateBearerToken($db);
        $action = $_GET['action'] ?? 'allocations';
        if ($action !== 'allocations') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
        }
        if ($tokenData) {
            $departmentId = (int) $tokenData['department_id'];
            echo json_encode(['allocations' => getAllocationsForDepartment($db, $departmentId)]);
            exit;
        }
        echo json_encode(['allocations' => getAllocations($db)]);
        exit;
    }

    if ($method === 'POST') {
        $tokenData = validateBearerToken($db);
        if (!$tokenData) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        $action = $input['action'] ?? '';
        if ($action !== 'allocate') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
        }

        $departmentId = (int) ($input['department_id'] ?? 0);
        $departmentName = trim((string) ($input['department_name'] ?? ''));
        if (!$departmentId && $departmentName !== '') {
            $departmentId = getOrCreateDepartment($db, $departmentName);
        }
        if (!$departmentId) {
            $departmentId = (int) $tokenData['department_id'];
        }

        if ((int) $tokenData['department_id'] !== $departmentId) {
            http_response_code(403);
            echo json_encode(['error' => 'Token not authorized for this department']);
            exit;
        }

        $allocatedAmount = (float) ($input['allocated_amount'] ?? 0);
        $period = $input['period'] ?? 'Yearly';
        $description = $input['description'] ?? 'External allocation';

        if ($allocatedAmount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'allocated_amount must be greater than 0']);
            exit;
        }

        $budgetYear = (int) date('Y');
        $startDate = $input['start_date'] ?? "{$budgetYear}-01-01";
        $endDate = $input['end_date'] ?? "{$budgetYear}-12-31";
        $budgetName = $input['budget_name'] ?? "External Allocation {$budgetYear}";

        $db->beginTransaction();

        $budgetStmt = $db->prepare("
            SELECT id FROM budgets
            WHERE department_id = ? AND budget_year = ? AND budget_name = ?
            LIMIT 1
        ");
        $budgetStmt->execute([$departmentId, $budgetYear, $budgetName]);
        $existingBudget = $budgetStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingBudget) {
            $budgetId = (int) $existingBudget['id'];
        } else {
            $insertBudget = $db->prepare("
                INSERT INTO budgets
                (budget_year, budget_name, description, total_budgeted, status, created_by, department_id, start_date, end_date)
                VALUES (?, ?, ?, ?, 'active', 1, ?, ?, ?)
            ");
            $insertBudget->execute([
                $budgetYear,
                $budgetName,
                $description,
                $allocatedAmount,
                $departmentId,
                $startDate,
                $endDate
            ]);
            $budgetId = (int) $db->lastInsertId();
        }

        $categoryId = getOrCreateDefaultCategory($db, $departmentId);
        $accountId = getFirstExpenseAccountId($db);

        if (!$accountId) {
            throw new Exception('No active expense account available');
        }

        $itemStmt = $db->prepare("
            SELECT id FROM budget_items
            WHERE budget_id = ? AND department_id = ? AND category_id = ? AND account_id = ?
            LIMIT 1
        ");
        $itemStmt->execute([$budgetId, $departmentId, $categoryId, $accountId]);
        $existingItem = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingItem) {
            $updateItem = $db->prepare("
                UPDATE budget_items
                SET budgeted_amount = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateItem->execute([$allocatedAmount, $existingItem['id']]);
        } else {
            $insertItem = $db->prepare("
                INSERT INTO budget_items
                (budget_id, category_id, department_id, account_id, budgeted_amount, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertItem->execute([$budgetId, $categoryId, $departmentId, $accountId, $allocatedAmount, $description]);
        }

        recalcBudgetTotals($db, $budgetId);

        $db->commit();

        echo json_encode([
            'success' => true,
            'budget_id' => $budgetId,
            'department_id' => $departmentId,
            'allocated_amount' => $allocatedAmount,
            'period' => $period
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $logger->log("Budget exchange error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function validateBearerToken($db) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        return null;
    }
    $token = trim(substr($authHeader, 7));
    if (!$token) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT department_id, expires_at
        FROM oauth_access_tokens
        WHERE access_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if (strtotime($row['expires_at']) < time()) {
        return null;
    }
    return $row;
}

function getAllocationsForDepartment($db, $departmentId) {
    $stmt = $db->prepare("
        SELECT
            d.dept_name as department,
            bi.department_id,
            SUM(bi.budgeted_amount) as total_amount,
            SUM(bi.actual_amount) as utilized_amount,
            COALESCE(SUM(
                CASE
                    WHEN ba.status = 'pending' THEN ba.amount
                    ELSE 0
                END
            ), 0) as reserved_amount
        FROM budget_items bi
        JOIN budgets b ON bi.budget_id = b.id
        LEFT JOIN departments d ON bi.department_id = d.id
        LEFT JOIN budget_adjustments ba ON ba.budget_id = b.id
            AND ba.department_id = bi.department_id
            AND ba.status = 'pending'
        WHERE b.status IN ('draft', 'pending', 'approved', 'active')
            AND bi.department_id = ?
        GROUP BY bi.department_id, d.dept_name
        ORDER BY d.dept_name
    ");
    $stmt->execute([$departmentId]);
    $rawAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allocations = [];
    foreach ($rawAllocations as $alloc) {
        $remaining = $alloc['total_amount'] - $alloc['utilized_amount'];
        $allocations[] = [
            'id' => count($allocations) + 1,
            'department' => $alloc['department'] ?: 'Unassigned',
            'department_id' => $alloc['department_id'],
            'total_amount' => (float) $alloc['total_amount'],
            'utilized_amount' => (float) $alloc['utilized_amount'],
            'reserved_amount' => (float) $alloc['reserved_amount'],
            'remaining' => (float) $remaining
        ];
    }

    return $allocations;
}

function getAllocations($db) {
    $stmt = $db->prepare("
        SELECT
            d.dept_name as department,
            bi.department_id,
            SUM(bi.budgeted_amount) as total_amount,
            SUM(bi.actual_amount) as utilized_amount,
            COALESCE(SUM(
                CASE
                    WHEN ba.status = 'pending' THEN ba.amount
                    ELSE 0
                END
            ), 0) as reserved_amount
        FROM budget_items bi
        JOIN budgets b ON bi.budget_id = b.id
        LEFT JOIN departments d ON bi.department_id = d.id
        LEFT JOIN budget_adjustments ba ON ba.budget_id = b.id
            AND ba.department_id = bi.department_id
            AND ba.status = 'pending'
        WHERE b.status IN ('draft', 'pending', 'approved', 'active')
        GROUP BY bi.department_id, d.dept_name
        ORDER BY d.dept_name
    ");
    $stmt->execute();
    $rawAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allocations = [];
    foreach ($rawAllocations as $alloc) {
        $remaining = $alloc['total_amount'] - $alloc['utilized_amount'];
        $allocations[] = [
            'id' => count($allocations) + 1,
            'department' => $alloc['department'] ?: 'Unassigned',
            'department_id' => $alloc['department_id'],
            'total_amount' => (float) $alloc['total_amount'],
            'utilized_amount' => (float) $alloc['utilized_amount'],
            'reserved_amount' => (float) $alloc['reserved_amount'],
            'remaining' => (float) $remaining
        ];
    }

    return $allocations;
}

function getFirstExpenseAccountId($db) {
    $stmt = $db->query("
        SELECT id FROM chart_of_accounts
        WHERE account_type = 'expense' AND is_active = 1
        ORDER BY account_code
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['id'] ?? null;
}

function getOrCreateDefaultCategory($db, $departmentId) {
    $stmt = $db->prepare("
        SELECT id FROM budget_categories
        WHERE category_type = 'expense' AND (department_id = ? OR department_id IS NULL)
        ORDER BY department_id DESC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$departmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int) $row['id'];
    }

    $categoryCode = 'EXT_ALLOC_' . $departmentId;
    $insert = $db->prepare("
        INSERT INTO budget_categories
        (category_code, category_name, category_type, department_id, is_active)
        VALUES (?, 'External Allocation', 'expense', ?, 1)
    ");
    $insert->execute([$categoryCode, $departmentId]);
    return (int) $db->lastInsertId();
}

function getOrCreateDepartment($db, $departmentName) {
    $name = trim($departmentName);
    if ($name === '') {
        throw new Exception('department_name is required');
    }

    $stmt = $db->prepare("SELECT id FROM departments WHERE dept_name = ? LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int) $row['id'];
    }

    $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $name));
    $code = trim($code, '_');
    if ($code === '') {
        $code = 'DEPT';
    }

    $codeBase = $code;
    $suffix = 1;
    $codeStmt = $db->prepare("SELECT COUNT(*) FROM departments WHERE dept_code = ?");
    while (true) {
        $codeStmt->execute([$code]);
        if ((int) $codeStmt->fetchColumn() === 0) {
            break;
        }
        $code = $codeBase . '_' . $suffix;
        $suffix++;
    }

    $insert = $db->prepare("
        INSERT INTO departments
        (dept_code, dept_name, dept_type, category, description, is_active, company_id)
        VALUES (?, ?, 'cost_center', 'admin', 'External integration', 1, 1)
    ");
    $insert->execute([$code, $name]);
    return (int) $db->lastInsertId();
}

function recalcBudgetTotals($db, $budgetId) {
    $recalcStmt = $db->prepare("
        UPDATE budgets b
        JOIN (
            SELECT budget_id, COALESCE(SUM(budgeted_amount), 0) as total
            FROM budget_items
            WHERE budget_id = ?
            GROUP BY budget_id
        ) bi ON b.id = bi.budget_id
        SET b.total_budgeted = bi.total
        WHERE b.id = ?
    ");
    $recalcStmt->execute([$budgetId, $budgetId]);
}
