<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = ["coa.category = 'Food & Beverage'"];
$params = [];

if ($dateFrom) {
    $where[] = 'pr.payment_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'pr.payment_date <= ?';
    $params[] = $dateTo;
}

$sql = "
    SELECT DISTINCT pr.id, pr.payment_number, pr.invoice_id, pr.amount, pr.payment_method, pr.payment_date,
           i.invoice_number
    FROM payments_received pr
    JOIN invoices i ON pr.invoice_id = i.id
    JOIN invoice_items ii ON i.id = ii.invoice_id
    JOIN chart_of_accounts coa ON ii.account_id = coa.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY pr.payment_date DESC, pr.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($rows as $row) {
    $data[] = [
        'payment_id' => $row['payment_number'],
        'invoice_id' => $row['invoice_number'] ?: $row['invoice_id'],
        'amount_paid' => floatval($row['amount']),
        'payment_method' => $row['payment_method'],
        'payment_date' => $row['payment_date']
    ];
}

api_send([
    'success' => true,
    'endpoint' => '/api/restaurant/payments',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'data' => $data
]);
