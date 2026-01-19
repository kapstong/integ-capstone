<?php
/**
 * ATIERA FINANCIALS - Daily Revenue API
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
    Logger::getInstance()->logDatabaseError('Daily Revenue API', $e->getMessage());
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
    if ($action !== 'list') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        return;
    }

    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $outletId = $_GET['outlet_id'] ?? null;

    $where = [];
    $params = [];

    if ($dateFrom) {
        $where[] = 'ods.business_date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'ods.business_date <= ?';
        $params[] = $dateTo;
    }
    if ($outletId) {
        $where[] = 'ods.outlet_id = ?';
        $params[] = $outletId;
    }

    $sql = "
        SELECT
            ods.*,
            o.outlet_name
        FROM outlet_daily_sales ods
        JOIN outlets o ON ods.outlet_id = o.id
        " . (!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY ods.business_date DESC, o.outlet_name
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'entries' => $entries]);
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

    $action = $data['action'] ?? 'save';
    if ($action !== 'save') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        return;
    }

    if (empty($data['business_date']) || empty($data['outlet_id']) || $data['gross_sales'] === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    $grossSales = floatval($data['gross_sales']);
    $discounts = floatval($data['discounts'] ?? 0);
    $serviceCharge = floatval($data['service_charge'] ?? 0);
    $taxes = floatval($data['taxes'] ?? 0);
    $netSales = isset($data['net_sales']) && $data['net_sales'] !== ''
        ? floatval($data['net_sales'])
        : ($grossSales - $discounts + $serviceCharge + $taxes);

    if (!empty($data['id'])) {
        $stmt = $db->prepare("
            UPDATE outlet_daily_sales
            SET business_date = ?,
                outlet_id = ?,
                gross_sales = ?,
                discounts = ?,
                service_charge = ?,
                taxes = ?,
                net_sales = ?,
                covers = ?,
                room_nights = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['business_date'],
            $data['outlet_id'],
            $grossSales,
            $discounts,
            $serviceCharge,
            $taxes,
            $netSales,
            $data['covers'] ?? null,
            $data['room_nights'] ?? null,
            $data['notes'] ?? '',
            $data['id']
        ]);
        $entryId = $data['id'];
        $actionLabel = 'Updated daily revenue entry';
    } else {
        $stmt = $db->prepare("
            INSERT INTO outlet_daily_sales
            (business_date, outlet_id, gross_sales, discounts, service_charge, taxes, net_sales, covers, room_nights, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['business_date'],
            $data['outlet_id'],
            $grossSales,
            $discounts,
            $serviceCharge,
            $taxes,
            $netSales,
            $data['covers'] ?? null,
            $data['room_nights'] ?? null,
            $data['notes'] ?? '',
            $_SESSION['user']['id']
        ]);
        $entryId = $db->lastInsertId();
        $actionLabel = 'Created daily revenue entry';
    }

    updateDailyRevenueSummary($db, $data['business_date'], $data['outlet_id']);

    Logger::getInstance()->logUserAction(
        $actionLabel,
        'outlet_daily_sales',
        $entryId,
        null,
        ['date' => $data['business_date'], 'outlet_id' => $data['outlet_id']]
    );

    echo json_encode(['success' => true, 'id' => $entryId]);
}

function updateDailyRevenueSummary($db, $businessDate, $outletId) {
    $stmt = $db->prepare("
        SELECT o.department_id, o.revenue_center_id
        FROM outlets o
        WHERE o.id = ?
    ");
    $stmt->execute([$outletId]);
    $outlet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$outlet) {
        return;
    }

    $sumStmt = $db->prepare("
        SELECT
            COUNT(*) as total_transactions,
            COALESCE(SUM(gross_sales), 0) as gross_revenue,
            COALESCE(SUM(discounts), 0) as discounts,
            COALESCE(SUM(service_charge), 0) as service_charge,
            COALESCE(SUM(taxes), 0) as taxes,
            COALESCE(SUM(net_sales), 0) as net_revenue
        FROM outlet_daily_sales
        WHERE business_date = ?
          AND outlet_id IN (
              SELECT id FROM outlets
              WHERE department_id <=> ?
                AND revenue_center_id <=> ?
          )
    ");
    $sumStmt->execute([
        $businessDate,
        $outlet['department_id'],
        $outlet['revenue_center_id']
    ]);
    $totals = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $upsert = $db->prepare("
        INSERT INTO daily_revenue_summary
        (business_date, department_id, revenue_center_id, source_system, total_transactions,
         gross_revenue, discounts, service_charge, taxes, net_revenue)
        VALUES (?, ?, ?, 'MANUAL', ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_transactions = VALUES(total_transactions),
            gross_revenue = VALUES(gross_revenue),
            discounts = VALUES(discounts),
            service_charge = VALUES(service_charge),
            taxes = VALUES(taxes),
            net_revenue = VALUES(net_revenue),
            updated_at = NOW()
    ");

    $upsert->execute([
        $businessDate,
        $outlet['department_id'],
        $outlet['revenue_center_id'],
        $totals['total_transactions'],
        $totals['gross_revenue'],
        $totals['discounts'],
        $totals['service_charge'],
        $totals['taxes'],
        $totals['net_revenue']
    ]);
}
?>

