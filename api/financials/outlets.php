<?php
/**
 * ATIERA FINANCIALS - Outlet Management API
 */

require_once '../../../includes/auth.php';
require_once '../../../includes/database.php';
require_once '../../../includes/logger.php';
require_once '../../../includes/coa_validation.php';

header('Content-Type: application/json');
session_start();

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
    Logger::getInstance()->logDatabaseError('Outlet API', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}

function handleGet($auth, $db) {
    if (!$auth->hasPermission('departments.view')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            $stmt = $db->query("
                SELECT
                    o.*,
                    d.dept_name as department_name,
                    rc.center_name as revenue_center_name,
                    coa.account_code as revenue_account_code,
                    coa.account_name as revenue_account_name
                FROM outlets o
                LEFT JOIN departments d ON o.department_id = d.id
                LEFT JOIN revenue_centers rc ON o.revenue_center_id = rc.id
                LEFT JOIN chart_of_accounts coa ON o.revenue_account_id = coa.id
                ORDER BY o.outlet_code
            ");
            $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'outlets' => $outlets]);
            break;

        case 'revenue_centers':
            $stmt = $db->query("
                SELECT id, center_code, center_name
                FROM revenue_centers
                WHERE is_active = 1
                ORDER BY center_code
            ");
            $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'revenue_centers' => $centers]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

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
    if ($action !== 'create') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        return;
    }

    if (empty($data['outlet_code']) || empty($data['outlet_name']) || empty($data['outlet_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    if (empty($data['revenue_account_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Revenue account is required']);
        return;
    }

    $invalidAccounts = findInvalidChartOfAccountsIds($db, [$data['revenue_account_id']]);
    if (!empty($invalidAccounts)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Selected account is invalid or inactive.',
            'invalid_account_ids' => $invalidAccounts
        ]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM outlets WHERE outlet_code = ?");
    $stmt->execute([$data['outlet_code']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Outlet code already exists']);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO outlets
        (outlet_code, outlet_name, outlet_type, department_id, revenue_center_id, revenue_account_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['outlet_code'],
        $data['outlet_name'],
        $data['outlet_type'],
        $data['department_id'] ?? null,
        $data['revenue_center_id'] ?? null,
        $data['revenue_account_id'] ?? null,
        $data['is_active'] ?? 1
    ]);

    $outletId = $db->lastInsertId();

    Logger::getInstance()->logUserAction(
        'Created outlet',
        'outlets',
        $outletId,
        null,
        ['code' => $data['outlet_code'], 'name' => $data['outlet_name']]
    );

    echo json_encode(['success' => true, 'id' => $outletId, 'message' => 'Outlet created successfully']);
}

function handlePut($auth, $db) {
    if (!$auth->hasPermission('departments.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        parse_str(file_get_contents('php://input'), $data);
    }

    $id = $data['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Outlet ID required']);
        return;
    }

    if (empty($data['revenue_account_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Revenue account is required']);
        return;
    }

    $invalidAccounts = findInvalidChartOfAccountsIds($db, [$data['revenue_account_id']]);
    if (!empty($invalidAccounts)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Selected account is invalid or inactive.',
            'invalid_account_ids' => $invalidAccounts
        ]);
        return;
    }

    $stmt = $db->prepare("
        UPDATE outlets
        SET outlet_name = ?,
            outlet_type = ?,
            department_id = ?,
            revenue_center_id = ?,
            revenue_account_id = ?,
            is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $data['outlet_name'],
        $data['outlet_type'],
        $data['department_id'] ?? null,
        $data['revenue_center_id'] ?? null,
        $data['revenue_account_id'] ?? null,
        $data['is_active'] ?? 1,
        $id
    ]);

    Logger::getInstance()->logUserAction(
        'Updated outlet',
        'outlets',
        $id,
        null,
        ['name' => $data['outlet_name']]
    );

    echo json_encode(['success' => true, 'message' => 'Outlet updated successfully']);
}

function handleDelete($auth, $db) {
    if (!$auth->hasPermission('departments.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Outlet ID required']);
        return;
    }

    $stmt = $db->prepare("UPDATE outlets SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    Logger::getInstance()->logUserAction(
        'Deactivated outlet',
        'outlets',
        $id,
        null,
        null
    );

    echo json_encode(['success' => true, 'message' => 'Outlet deactivated successfully']);
}
?>

