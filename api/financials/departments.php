<?php
/**
 * ATIERA FINANCIALS - Department Management API
 * Manages financial departments and cost/revenue centers
 */

require_once '../../../includes/auth.php';
require_once '../../../includes/database.php';
require_once '../../../includes/logger.php';

header('Content-Type: application/json');
session_start();

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$auth = new Auth();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($auth, $db);
            break;

        case 'POST':
            handlePost($auth, $db);
            break;

        case 'PUT':
            handlePut($auth, $db);
            break;

        case 'DELETE':
            handleDelete($auth, $db);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Department API', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Handle GET requests
 */
function handleGet($auth, $db) {
    if (!$auth->hasPermission('departments.view')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            // Get all departments with additional info
            $stmt = $db->query("
                SELECT
                    d.*,
                    ra.account_code as revenue_account_code,
                    ra.account_name as revenue_account_name,
                    ea.account_code as expense_account_code,
                    ea.account_name as expense_account_name,
                    pd.dept_name as parent_dept_name,
                    COUNT(DISTINCT rc.id) as revenue_center_count
                FROM departments d
                LEFT JOIN chart_of_accounts ra ON d.revenue_account_id = ra.id
                LEFT JOIN chart_of_accounts ea ON d.expense_account_id = ea.id
                LEFT JOIN departments pd ON d.parent_dept_id = pd.id
                LEFT JOIN revenue_centers rc ON rc.department_id = d.id
                GROUP BY d.id
                ORDER BY d.dept_code
            ");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'departments' => $departments]);
            break;

        case 'get':
            // Get single department
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Department ID required']);
                return;
            }

            $stmt = $db->prepare("
                SELECT
                    d.*,
                    ra.account_code as revenue_account_code,
                    ra.account_name as revenue_account_name,
                    ea.account_code as expense_account_code,
                    ea.account_name as expense_account_name,
                    pd.dept_name as parent_dept_name
                FROM departments d
                LEFT JOIN chart_of_accounts ra ON d.revenue_account_id = ra.id
                LEFT JOIN chart_of_accounts ea ON d.expense_account_id = ea.id
                LEFT JOIN departments pd ON d.parent_dept_id = pd.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$department) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Department not found']);
                return;
            }

            echo json_encode(['success' => true, 'department' => $department]);
            break;

        case 'revenue_centers':
            // Get revenue centers for a department
            $deptId = $_GET['dept_id'] ?? null;
            if (!$deptId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Department ID required']);
                return;
            }

            $stmt = $db->prepare("
                SELECT
                    rc.*,
                    coa.account_code,
                    coa.account_name
                FROM revenue_centers rc
                LEFT JOIN chart_of_accounts coa ON rc.revenue_account_id = coa.id
                WHERE rc.department_id = ?
                ORDER BY rc.center_code
            ");
            $stmt->execute([$deptId]);
            $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'revenue_centers' => $centers]);
            break;

        case 'stats':
            // Get department statistics
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Department ID required']);
                return;
            }

            // Get current month revenue
            $stmt = $db->prepare("
                SELECT
                    COALESCE(SUM(net_revenue), 0) as current_month_revenue
                FROM daily_revenue_summary
                WHERE department_id = ?
                  AND YEAR(business_date) = YEAR(CURDATE())
                  AND MONTH(business_date) = MONTH(CURDATE())
            ");
            $stmt->execute([$id]);
            $revenue = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get current month expenses
            $stmt = $db->prepare("
                SELECT
                    COALESCE(SUM(total_amount), 0) as current_month_expenses
                FROM daily_expense_summary
                WHERE department_id = ?
                  AND YEAR(business_date) = YEAR(CURDATE())
                  AND MONTH(business_date) = MONTH(CURDATE())
            ");
            $stmt->execute([$id]);
            $expenses = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'stats' => [
                    'current_month_revenue' => $revenue['current_month_revenue'],
                    'current_month_expenses' => $expenses['current_month_expenses'],
                    'current_month_net' => $revenue['current_month_revenue'] - $expenses['current_month_expenses']
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle POST requests (Create)
 */
function handlePost($auth, $db) {
    if (!$auth->hasPermission('departments.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        $data = $_POST;
    }

    $action = $data['action'] ?? 'create';

    switch ($action) {
        case 'create':
            // Validate required fields
            if (empty($data['dept_code']) || empty($data['dept_name']) || empty($data['dept_type']) || empty($data['category'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }

            // Check for duplicate code
            $stmt = $db->prepare("SELECT id FROM departments WHERE dept_code = ?");
            $stmt->execute([$data['dept_code']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Department code already exists']);
                return;
            }

            // Insert department
            $stmt = $db->prepare("
                INSERT INTO departments
                (dept_code, dept_name, dept_type, category, description,
                 parent_dept_id, revenue_account_id, expense_account_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['dept_code'],
                $data['dept_name'],
                $data['dept_type'],
                $data['category'],
                $data['description'] ?? '',
                $data['parent_dept_id'] ?? null,
                $data['revenue_account_id'] ?? null,
                $data['expense_account_id'] ?? null,
                $data['is_active'] ?? 1
            ]);

            $deptId = $db->lastInsertId();

            Logger::getInstance()->logUserAction(
                'Created department',
                'departments',
                $deptId,
                null,
                ['code' => $data['dept_code'], 'name' => $data['dept_name']]
            );

            echo json_encode(['success' => true, 'id' => $deptId, 'message' => 'Department created successfully']);
            break;

        case 'create_revenue_center':
            // Create revenue center
            if (empty($data['center_code']) || empty($data['center_name']) || empty($data['department_id']) || empty($data['revenue_account_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }

            $stmt = $db->prepare("
                INSERT INTO revenue_centers
                (center_code, center_name, department_id, revenue_account_id, description, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['center_code'],
                $data['center_name'],
                $data['department_id'],
                $data['revenue_account_id'],
                $data['description'] ?? '',
                $data['is_active'] ?? 1
            ]);

            $centerId = $db->lastInsertId();

            Logger::getInstance()->logUserAction(
                'Created revenue center',
                'revenue_centers',
                $centerId,
                null,
                ['code' => $data['center_code'], 'name' => $data['center_name']]
            );

            echo json_encode(['success' => true, 'id' => $centerId, 'message' => 'Revenue center created successfully']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle PUT requests (Update)
 */
function handlePut($auth, $db) {
    if (!$auth->hasPermission('departments.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    parse_str(file_get_contents('php://input'), $data);

    if (empty($data)) {
        $data = json_decode(file_get_contents('php://input'), true);
    }

    $id = $data['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Department ID required']);
        return;
    }

    // Update department
    $stmt = $db->prepare("
        UPDATE departments
        SET dept_name = ?,
            description = ?,
            parent_dept_id = ?,
            revenue_account_id = ?,
            expense_account_id = ?,
            is_active = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['dept_name'],
        $data['description'] ?? '',
        $data['parent_dept_id'] ?? null,
        $data['revenue_account_id'] ?? null,
        $data['expense_account_id'] ?? null,
        $data['is_active'] ?? 1,
        $id
    ]);

    Logger::getInstance()->logUserAction(
        'Updated department',
        'departments',
        $id,
        null,
        ['name' => $data['dept_name']]
    );

    echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
}

/**
 * Handle DELETE requests
 */
function handleDelete($auth, $db) {
    if (!$auth->hasPermission('departments.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Department ID required']);
        return;
    }

    // Soft delete - set inactive instead of deleting
    $stmt = $db->prepare("UPDATE departments SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    Logger::getInstance()->logUserAction(
        'Deactivated department',
        'departments',
        $id,
        null,
        null
    );

    echo json_encode(['success' => true, 'message' => 'Department deactivated successfully']);
}
?>

