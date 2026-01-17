<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = ["o.outlet_type IN ('rooms', 'spa', 'other')"];
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
    SELECT ods.id, ods.business_date, ods.net_sales, ods.covers, ods.room_nights, ods.notes,
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
    $quantity = $row['room_nights'] ?? $row['covers'] ?? 1;
    $data[] = [
        'transaction_id' => $row['id'],
        'items_sold' => $row['notes'],
        'quantity' => intval($quantity),
        'total_amount' => floatval($row['net_sales']),
        'transaction_date' => $row['business_date'],
        'outlet_name' => $row['outlet_name'],
        'outlet_type' => $row['outlet_type']
    ];
}

api_send([
    'success' => true,
    'endpoint' => '/api/hotel/pos-sales',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'data' => $data
]);
