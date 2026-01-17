<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = [];
$params = [];

if ($dateFrom) {
    $where[] = 'business_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'business_date <= ?';
    $params[] = $dateTo;
}

$revenueSql = 'SELECT COALESCE(SUM(net_revenue), 0) as total FROM daily_revenue_summary';
$expenseSql = 'SELECT COALESCE(SUM(total_amount), 0) as total FROM daily_expense_summary';

$whereSql = '';
if ($where) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

$revStmt = $db->prepare($revenueSql . $whereSql);
$revStmt->execute($params);
$totalRevenue = floatval($revStmt->fetch(PDO::FETCH_ASSOC)['total']);

$expStmt = $db->prepare($expenseSql . $whereSql);
$expStmt->execute($params);
$totalExpenses = floatval($expStmt->fetch(PDO::FETCH_ASSOC)['total']);

api_send([
    'success' => true,
    'endpoint' => '/api/financial/profit-loss',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'total_revenue' => $totalRevenue,
    'total_expenses' => $totalExpenses,
    'profit' => $totalRevenue - $totalExpenses
]);
