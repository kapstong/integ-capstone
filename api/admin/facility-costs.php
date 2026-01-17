<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = ["(
    coa.category = 'Property O&M'
    OR LOWER(d.purpose) LIKE '%facility%'
    OR LOWER(d.purpose) LIKE '%reservation%'
)"];
$params = [];

if ($dateFrom) {
    $where[] = 'd.disbursement_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'd.disbursement_date <= ?';
    $params[] = $dateTo;
}

$sql = "
    SELECT d.disbursement_number, d.disbursement_date, d.amount, d.purpose,
           coa.category, coa.account_name
    FROM disbursements d
    LEFT JOIN chart_of_accounts coa ON d.account_id = coa.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY d.disbursement_date DESC, d.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($rows as $row) {
    $data[] = [
        'facility_id' => $row['disbursement_number'],
        'cost_amount' => floatval($row['amount']),
        'date' => $row['disbursement_date'],
        'expense_category' => $row['category'] ?: 'facility',
        'description' => $row['purpose'],
        'account_name' => $row['account_name']
    ];
}

api_send([
    'success' => true,
    'endpoint' => '/api/admin/facility-costs',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'data' => $data
]);
