<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = [];
$params = [];

if ($dateFrom) {
    $where[] = 'drs.business_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'drs.business_date <= ?';
    $params[] = $dateTo;
}

$sql = "
    SELECT COALESCE(d.dept_name, 'General') as department,
           COALESCE(SUM(drs.net_revenue), 0) as total_revenue
    FROM daily_revenue_summary drs
    LEFT JOIN departments d ON drs.department_id = d.id
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY d.dept_name ORDER BY d.dept_name';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRevenue = 0.0;
foreach ($rows as $row) {
    $totalRevenue += floatval($row['total_revenue']);
}

api_send([
    'success' => true,
    'endpoint' => '/api/financial/revenue-summary',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'total_revenue' => $totalRevenue,
    'by_department' => $rows
]);
