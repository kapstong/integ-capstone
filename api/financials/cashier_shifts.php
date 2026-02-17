<?php
/**
 * ATIERA FINANCIALS - Cashier Shifts API
 */

require_once '../../../includes/auth.php';
require_once '../../../includes/database.php';
require_once '../../../includes/logger.php';

header('Content-Type: application/json');
session_start();
$auth = new Auth();
ensure_api_auth($method, [
    'GET' => ['cashier.operate', 'cashier.view_all'],
    'PUT' => 'cashier.operate',
    'DELETE' => 'cashier.operate',
    'POST' => 'cashier.operate',
    'PATCH' => 'cashier.operate',
]);


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
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Cashier Shifts API', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}

function handleGet($auth, $db) {
    if (!$auth->hasAnyPermission(['cashier.operate', 'cashier.view_all'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $stmt = $db->query("
        SELECT
            cs.*,
            o.outlet_name,
            u.full_name as cashier_name
        FROM cashier_shifts cs
        LEFT JOIN outlets o ON cs.outlet_id = o.id
        LEFT JOIN users u ON cs.cashier_id = u.id
        ORDER BY cs.shift_date DESC, cs.opened_at DESC
    ");
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'shifts' => $shifts]);
}

function handlePost($auth, $db) {
    if (!$auth->hasPermission('cashier.operate')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $action = $data['action'] ?? '';
    switch ($action) {
        case 'open_shift':
            openShift($db, $data);
            break;
        case 'close_shift':
            closeShift($db, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

function openShift($db, $data) {
    if (empty($data['outlet_id']) || empty($data['shift_date']) || $data['opening_cash'] === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO cashier_shifts
        (shift_date, outlet_id, cashier_id, opened_at, opening_cash, notes, status)
        VALUES (?, ?, ?, NOW(), ?, ?, 'open')
    ");
    $stmt->execute([
        $data['shift_date'],
        $data['outlet_id'],
        $_SESSION['user']['id'],
        $data['opening_cash'],
        $data['notes'] ?? ''
    ]);

    $shiftId = $db->lastInsertId();
    Logger::getInstance()->logUserAction('Opened cashier shift', 'cashier_shifts', $shiftId, null, [
        'shift_date' => $data['shift_date'],
        'outlet_id' => $data['outlet_id']
    ]);

    echo json_encode(['success' => true, 'id' => $shiftId]);
}

function closeShift($db, $data) {
    if (empty($data['id']) || $data['closing_cash'] === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    $expectedCash = isset($data['expected_cash']) && $data['expected_cash'] !== ''
        ? floatval($data['expected_cash'])
        : null;
    $closingCash = floatval($data['closing_cash']);
    $variance = $expectedCash !== null ? ($closingCash - $expectedCash) : null;

    $stmt = $db->prepare("
        UPDATE cashier_shifts
        SET closed_at = NOW(),
            closing_cash = ?,
            expected_cash = ?,
            variance = ?,
            notes = ?,
            status = 'closed'
        WHERE id = ?
    ");
    $stmt->execute([
        $closingCash,
        $expectedCash,
        $variance,
        $data['notes'] ?? '',
        $data['id']
    ]);

    Logger::getInstance()->logUserAction('Closed cashier shift', 'cashier_shifts', $data['id'], null, [
        'variance' => $variance
    ]);

    echo json_encode(['success' => true, 'id' => $data['id']]);
}
?>


