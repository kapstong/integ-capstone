<?php
// For API endpoints, we don't want to redirect on auth failure
// So we'll handle authentication differently
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

// Check authentication for API calls
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $logger);
            break;
        case 'POST':
            handlePost($db, $logger);
            break;
        case 'PUT':
            handlePut($db, $logger);
            break;
        case 'DELETE':
            handleDelete($db, $logger);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->log("API Error in budgets.php: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($db, $logger) {
    try {
        $action = isset($_GET['action']) ? $_GET['action'] : null;

        switch ($action) {
            case 'categories':
                getCategories($db);
                break;
            case 'allocations':
                getAllocations($db);
                break;
            case 'adjustments':
                getAdjustments($db);
                break;
            case 'tracking':
                getTrackingData($db);
                break;
            case 'alerts':
                getAlerts($db);
                break;
            default:
                getBudgets($db);
        }
    } catch (Exception $e) {
        $logger->log("Error in handleGet budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch budget data']);
    }
}

function getBudgets($db) {
    // Get all budgets
    $stmt = $db->prepare("
        SELECT b.*,
               u1.full_name as created_by_name,
               u2.full_name as approved_by_name,
               d.dept_name as department_name,
               v.company_name as vendor_name
        FROM budgets b
        LEFT JOIN users u1 ON b.created_by = u1.id
        LEFT JOIN users u2 ON b.approved_by = u2.id
        LEFT JOIN departments d ON b.department_id = d.id
        LEFT JOIN vendors v ON b.vendor_id = v.id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each budget, get the items and calculate totals
    foreach ($budgets as &$budget) {
        $budget['start_date'] = $budget['start_date'] ?: ($budget['budget_year'] . '-01-01');
        $budget['end_date'] = $budget['end_date'] ?: ($budget['budget_year'] . '-12-31');
        $budget['department'] = $budget['department_name'] ?: 'Unassigned';
        $budget['name'] = $budget['budget_name'];
        $budget['total_amount'] = $budget['total_budgeted'];
    }

    echo json_encode(['budgets' => $budgets]);
}

function getCategories($db) {
    $stmt = $db->prepare("
        SELECT bc.*,
               d.dept_name as department_name
        FROM budget_categories bc
        LEFT JOIN departments d ON bc.department_id = d.id
        WHERE bc.is_active = 1
        ORDER BY bc.category_type, bc.category_name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['categories' => $categories]);
}

function getAdjustments($db) {
    $stmt = $db->prepare("
        SELECT ba.*,
               d.dept_name as department_name,
               u.full_name as requested_by_name,
               ua.full_name as approved_by_name
        FROM budget_adjustments ba
        LEFT JOIN departments d ON ba.department_id = d.id
        LEFT JOIN users u ON ba.requested_by = u.id
        LEFT JOIN users ua ON ba.approved_by = ua.id
        ORDER BY ba.created_at DESC
    ");
    $stmt->execute();
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['adjustments' => $adjustments]);
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
        WHERE b.status IN ('approved', 'active')
        GROUP BY bi.department_id, d.dept_name
        ORDER BY d.dept_name
    ");
    $stmt->execute();
    $rawAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allocations = [];
    foreach ($rawAllocations as $alloc) {
        $remaining = $alloc['total_amount'] - $alloc['utilized_amount'];

        $allocations[] = [
            'id' => count($allocations) + 1, // Simple ID for frontend
            'department' => $alloc['department'] ?: 'Unassigned',
            'department_id' => $alloc['department_id'],
            'total_amount' => (float)$alloc['total_amount'],
            'utilized_amount' => (float)$alloc['utilized_amount'],
            'reserved_amount' => (float)$alloc['reserved_amount'],
            'remaining' => (float)$remaining
        ];
    }

    echo json_encode(['allocations' => $allocations]);
}

function getTrackingData($db) {
    $period = isset($_GET['period']) ? $_GET['period'] : 'year_to_date';
    $query = "
        SELECT
            bc.category_name as category,
            SUM(bi.budgeted_amount) as budget_amount,
            SUM(bi.actual_amount) as actual_amount,
            bc.category_type
        FROM budget_items bi
        JOIN budget_categories bc ON bi.category_id = bc.id
        JOIN budgets b ON bi.budget_id = b.id
        WHERE b.status IN ('approved', 'active')
    ";
    $params = [];

    if ($period === 'year_to_date') {
        $query .= " AND b.budget_year = YEAR(CURDATE())";
    }

    $query .= "
        GROUP BY bc.id, bc.category_name, bc.category_type
        ORDER BY bc.category_name
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $trackingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $totalBudget = 0;
    $totalActual = 0;
    foreach ($trackingData as $item) {
        $totalBudget += $item['budget_amount'];
        $totalActual += $item['actual_amount'];
    }

    $variance = $totalActual - $totalBudget;
    $variancePercent = $totalBudget > 0 ? ($variance / $totalBudget) * 100 : 0;

    $summary = [
        'total_budget' => (float)$totalBudget,
        'actual_spent' => (float)$totalActual,
        'variance_percent' => (float)$variancePercent,
        'remaining' => (float)($totalBudget - $totalActual)
    ];

    echo json_encode([
        'tracking' => $trackingData,
        'summary' => $summary
    ]);
}

function getAlerts($db) {
    // Get departments/categories that are over budget
    $stmt = $db->prepare("
        SELECT
            d.dept_name as department,
            SUM(bi.budgeted_amount) as budgeted_amount,
            SUM(bi.actual_amount) as actual_amount,
            b.budget_year
        FROM budget_items bi
        JOIN budgets b ON bi.budget_id = b.id
        LEFT JOIN departments d ON bi.department_id = d.id
        WHERE b.status IN ('approved', 'active')
        GROUP BY bi.department_id, d.dept_name, b.budget_year
        HAVING SUM(bi.actual_amount) > SUM(bi.budgeted_amount)
        ORDER BY (SUM(bi.actual_amount) - SUM(bi.budgeted_amount)) DESC
    ");
    $stmt->execute();
    $overBudgetItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts = [];
    foreach ($overBudgetItems as $item) {
        $overAmount = $item['actual_amount'] - $item['budgeted_amount'];
        $overPercent = $item['budgeted_amount'] > 0 ? ($overAmount / $item['budgeted_amount']) * 100 : 0;

        $alerts[] = [
            'id' => count($alerts) + 1,
            'department' => $item['department'] ?: 'Unassigned',
            'budget_year' => $item['budget_year'],
            'budgeted_amount' => (float)$item['budgeted_amount'],
            'actual_amount' => (float)$item['actual_amount'],
            'over_amount' => (float)$overAmount,
            'over_percent' => (float)$overPercent,
            'severity' => $overPercent > 50 ? 'critical' : ($overPercent > 20 ? 'high' : 'medium'),
            'alert_date' => date('Y-m-d H:i:s')
        ];
    }

    echo json_encode(['alerts' => $alerts]);
}

function handlePost($db, $logger) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        $action = $data['action'] ?? null;
        if ($action === 'item') {
            createBudgetItem($db, $logger, $data);
            return;
        }

        if ($action === 'adjustment') {
            createAdjustment($db, $logger, $data);
            return;
        }

        if ($action === 'category') {
            createCategory($db, $logger, $data);
            return;
        }

        // Validate required fields
        $required = ['name', 'start_date', 'end_date', 'total_amount'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                return;
            }
        }

        $db->beginTransaction();

        // Extract year from start_date
        $budgetYear = date('Y', strtotime($data['start_date']));

        // Insert budget
        $stmt = $db->prepare("
            INSERT INTO budgets (
                budget_year, budget_name, description, total_budgeted,
                status, created_by, department_id, vendor_id, start_date, end_date
            ) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $budgetYear,
            $data['name'],
            $data['description'] ?? '',
            $data['total_amount'],
            $_SESSION['user']['id'] ?? 1,
            $data['department_id'] ?? null,
            $data['vendor_id'] ?? null,
            $data['start_date'],
            $data['end_date']
        ]);

        $budgetId = $db->lastInsertId();

        $db->commit();

        $logger->log("Budget created: {$data['name']}", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Budget created successfully',
            'budget_id' => $budgetId
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handlePost budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create budget']);
    }
}

function handlePut($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Budget ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        if (isset($data['action']) && $data['action'] === 'adjustment') {
            updateAdjustmentStatus($db, $logger, $id, $data);
            return;
        }

        $db->beginTransaction();

        // Update budget
        $stmt = $db->prepare("
            UPDATE budgets SET
                budget_name = ?,
                description = ?,
                total_budgeted = ?,
                department_id = ?,
                vendor_id = ?,
                start_date = ?,
                end_date = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['total_amount'],
            $data['department_id'] ?? null,
            $data['vendor_id'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $id
        ]);

        $db->commit();

        $logger->log("Budget updated: $id", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Budget updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handlePut budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update budget']);
    }
}

function handleDelete($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Budget ID is required']);
            return;
        }

        $db->beginTransaction();

        // Delete budget (cascade will delete items)
        $stmt = $db->prepare("DELETE FROM budgets WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();

        $logger->log("Budget deleted: $id", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Budget deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handleDelete budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete budget']);
    }
}

function createBudgetItem($db, $logger, $data) {
    $required = ['budget_id', 'category_id', 'budgeted_amount'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO budget_items
        (budget_id, category_id, department_id, account_id, vendor_id, budgeted_amount, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['budget_id'],
        $data['category_id'],
        $data['department_id'] ?? null,
        $data['account_id'] ?? null,
        $data['vendor_id'] ?? null,
        $data['budgeted_amount'],
        $data['notes'] ?? ''
    ]);

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
    $recalcStmt->execute([$data['budget_id'], $data['budget_id']]);

    $db->commit();

    $logger->log("Budget item created for budget {$data['budget_id']}", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Budget item created successfully'
    ]);
}

function createAdjustment($db, $logger, $data) {
    $required = ['budget_id', 'adjustment_type', 'amount', 'department_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO budget_adjustments
        (budget_id, department_id, vendor_id, adjustment_type, amount, reason, status, requested_by, effective_date)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        $data['budget_id'],
        $data['department_id'],
        $data['vendor_id'] ?? null,
        $data['adjustment_type'],
        $data['amount'],
        $data['reason'] ?? '',
        $_SESSION['user']['id'] ?? 1,
        $data['effective_date'] ?? null
    ]);

    $db->commit();

    $logger->log("Budget adjustment requested for budget {$data['budget_id']}", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Adjustment request submitted'
    ]);
}

function updateAdjustmentStatus($db, $logger, $id, $data) {
    $status = $data['status'] ?? null;
    if (!$status || !in_array($status, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid adjustment status']);
        return;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        UPDATE budget_adjustments
        SET status = ?, approved_by = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([
        $status,
        $_SESSION['user']['id'] ?? 1,
        $id
    ]);

    if ($status === 'approved') {
        $adjustmentStmt = $db->prepare("
            SELECT budget_id, department_id, adjustment_type, amount
            FROM budget_adjustments
            WHERE id = ?
        ");
        $adjustmentStmt->execute([$id]);
        $adjustment = $adjustmentStmt->fetch(PDO::FETCH_ASSOC);

        if ($adjustment) {
            $amountDelta = $adjustment['adjustment_type'] === 'decrease'
                ? -1 * (float)$adjustment['amount']
                : (float)$adjustment['amount'];

            $updateItems = $db->prepare("
                UPDATE budget_items
                SET budgeted_amount = budgeted_amount + ?
                WHERE budget_id = ? AND department_id = ?
            ");
            $updateItems->execute([
                $amountDelta,
                $adjustment['budget_id'],
                $adjustment['department_id']
            ]);

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
            $recalcStmt->execute([$adjustment['budget_id'], $adjustment['budget_id']]);
        }
    }

    $db->commit();

    $logger->log("Budget adjustment updated: {$id}", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Adjustment updated'
    ]);
}

function createCategory($db, $logger, $data) {
    $required = ['category_name', 'category_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    $rawCode = $data['category_code'] ?? $data['category_name'];
    $categoryCode = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $rawCode));
    $categoryCode = trim($categoryCode, '_');
    if ($categoryCode === '') {
        $categoryCode = 'CAT_' . time();
    }
    $categoryCode = substr($categoryCode, 0, 30);

    $stmt = $db->prepare("
        INSERT INTO budget_categories
        (category_code, category_name, category_type, department_id, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $categoryCode,
        $data['category_name'],
        $data['category_type'],
        $data['department_id'] ?? null
    ]);

    $logger->log("Budget category created: {$data['category_name']}", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Category created'
    ]);
}
?>
