<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = ["o.outlet_type IN ('restaurant', 'bar', 'banquet')"];
$params = [];

if ($dateFrom) {
    $where[] = 'ods.business_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'ods.business_date <= ?';
    $params[] = $dateTo;
}

$sql = "
    SELECT ods.id, ods.business_date, ods.net_sales, ods.covers, ods.notes,
           o.outlet_name, o.outlet_type
    FROM outlet_daily_sales ods
    JOIN outlets o ON ods.outlet_id = o.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ods.business_date DESC, ods.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($rows as $row) {
    $data[] = [
        'order_id' => $row['id'],
        'total_order_amount' => floatval($row['net_sales']),
        'order_date' => $row['business_date'],
        'payment_status' => 'posted',
        'covers' => $row['covers'],
        'outlet_name' => $row['outlet_name'],
        'outlet_type' => $row['outlet_type'],
        'notes' => $row['notes']
    ];
}

api_send([
    'success' => true,
    'endpoint' => '/api/restaurant/pos-sales',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'data' => $data
]);
