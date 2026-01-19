<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = ["it.source_system = 'LOGISTICS1'", "it.transaction_type = 'supplier_invoice'"];
$params = [];

if ($dateFrom) {
    $where[] = 'DATE(it.transaction_date) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'DATE(it.transaction_date) <= ?';
    $params[] = $dateTo;
}

$sql = "
    SELECT it.id, it.transaction_date, it.external_id, it.raw_data, it.description,
           d.category as department_category
    FROM imported_transactions it
    LEFT JOIN departments d ON it.department_id = d.id
    WHERE " . implode(' AND ', $where) . "
      AND (d.category = 'food_beverage' OR d.category IS NULL)
    ORDER BY it.transaction_date DESC, it.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($rows as $row) {
    $raw = json_decode($row['raw_data'] ?? '', true);
    $items = [];

    if (is_array($raw) && isset($raw['items']) && is_array($raw['items'])) {
        $items = $raw['items'];
    }

    if (!$items) {
        $data[] = [
            'item_name' => $row['description'] ?: $row['external_id'],
            'quantity_used' => null,
            'cost_per_item' => null,
            'date' => date('Y-m-d', strtotime($row['transaction_date']))
        ];
        continue;
    }

    foreach ($items as $item) {
        $data[] = [
            'item_name' => $item['description_item'] ?? $item['item_code'] ?? $row['external_id'],
            'quantity_used' => isset($item['qty']) ? floatval($item['qty']) : null,
            'cost_per_item' => isset($item['item_price']) ? floatval($item['item_price']) : null,
            'date' => $item['date_issued'] ?? date('Y-m-d', strtotime($row['transaction_date']))
        ];
    }
}

api_send([
    'success' => true,
    'endpoint' => '/api/restaurant/inventory-usage',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'data' => $data
]);

