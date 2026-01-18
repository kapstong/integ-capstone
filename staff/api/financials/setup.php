<?php
/**
 * ATIERA FINANCIALS - Financial Setup API
 */

require_once '../../../includes/auth.php';
require_once '../../../includes/database.php';
require_once '../../../includes/logger.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$auth = new Auth();
if (!$auth->hasPermission('settings.edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$db = Database::getInstance()->getConnection();
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'departments':
            seedDepartments($db);
            echo json_encode(['success' => true, 'message' => 'Default departments created or already exist']);
            break;
        case 'outlets':
            seedOutlets($db);
            echo json_encode(['success' => true, 'message' => 'Default outlets created or already exist']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Financial setup', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function seedDepartments($db) {
    $departments = [
        ['ROOMS', 'Rooms Division', 'revenue_center', 'rooms', 'Room revenue and related costs'],
        ['FB', 'Food & Beverage', 'revenue_center', 'food_beverage', 'Restaurant and bar revenue'],
        ['BANQUET', 'Banquet & Events', 'revenue_center', 'events', 'Events and function revenue'],
        ['SPA', 'Spa & Wellness', 'revenue_center', 'spa', 'Spa and wellness services'],
        ['ADMIN', 'Administration', 'cost_center', 'admin', 'Administration and office'],
        ['MAINT', 'Engineering & Maintenance', 'cost_center', 'maintenance', 'Facilities and maintenance'],
        ['SALES', 'Sales & Marketing', 'cost_center', 'marketing', 'Sales and marketing activities'],
        ['FIN', 'Finance & Accounting', 'support', 'admin', 'Financial operations']
    ];

    $stmt = $db->prepare("
        INSERT INTO departments (dept_code, dept_name, dept_type, category, description, is_active)
        SELECT ?, ?, ?, ?, ?, 1
        WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_code = ?)
    ");

    foreach ($departments as $dept) {
        $stmt->execute([$dept[0], $dept[1], $dept[2], $dept[3], $dept[4], $dept[0]]);
    }
}

function seedOutlets($db) {
    $deptMap = [];
    $deptStmt = $db->query("SELECT id, dept_code FROM departments");
    foreach ($deptStmt->fetchAll(PDO::FETCH_ASSOC) as $dept) {
        $deptMap[$dept['dept_code']] = $dept['id'];
    }

    $outlets = [
        ['ROOMS', 'Rooms - Guest Accommodation', 'rooms', 'ROOMS'],
        ['RESTO', 'Main Restaurant', 'restaurant', 'FB'],
        ['BAR', 'Lobby Bar', 'bar', 'FB'],
        ['BANQ', 'Banquet Hall', 'banquet', 'BANQUET'],
        ['SPA', 'Spa & Wellness', 'spa', 'SPA']
    ];

    $stmt = $db->prepare("
        INSERT INTO outlets (outlet_code, outlet_name, outlet_type, department_id, is_active)
        SELECT ?, ?, ?, ?, 1
        WHERE NOT EXISTS (SELECT 1 FROM outlets WHERE outlet_code = ?)
    ");

    foreach ($outlets as $outlet) {
        $deptId = $deptMap[$outlet[3]] ?? null;
        $stmt->execute([$outlet[0], $outlet[1], $outlet[2], $deptId, $outlet[0]]);
    }
}
?>
