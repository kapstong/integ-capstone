<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = ["o.outlet_type = 'rooms'"];
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
    SELECT ods.id, ods.business_date, ods.net_sales, ods.room_nights, ods.notes,
           o.outlet_name
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
    $roomNights = intval($row['room_nights'] ?? 0);
    $totalAmount = floatval($row['net_sales'] ?? 0);
    $roomRate = $roomNights > 0 ? $totalAmount / $roomNights : $totalAmount;

    $data[] = [
        'booking_id' => 'ROOM-' . $row['id'],
        'check_in_date' => $row['business_date'],
        'check_out_date' => $row['business_date'],
        'room_rate' => round($roomRate, 2),
        'total_booking_amount' => $totalAmount,
        'booking_status' => 'posted',
        'notes' => $row['notes'],
        'outlet_name' => $row['outlet_name']
    ];
}

api_send([
    'success' => true,
    'endpoint' => '/api/hotel/bookings',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'data' => $data
]);

