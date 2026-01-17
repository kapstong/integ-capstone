<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

$monthsBack = intval($_GET['months_back'] ?? 6);
if ($monthsBack < 1 || $monthsBack > 24) {
    $monthsBack = 6;
}

$revenueStmt = $db->prepare("    SELECT DATE_FORMAT(business_date, '%Y-%m') as month_key,
           COALESCE(SUM(net_revenue), 0) as total
    FROM daily_revenue_summary
    WHERE business_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
    GROUP BY month_key
    ORDER BY month_key
");
$revenueStmt->execute([$monthsBack]);
$revenueRows = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);

$expenseStmt = $db->prepare("    SELECT DATE_FORMAT(business_date, '%Y-%m') as month_key,
           COALESCE(SUM(total_amount), 0) as total
    FROM daily_expense_summary
    WHERE business_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
    GROUP BY month_key
    ORDER BY month_key
");
$expenseStmt->execute([$monthsBack]);
$expenseRows = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

$revenueTotals = array_map(function($row) { return floatval($row['total']); }, $revenueRows);
$expenseTotals = array_map(function($row) { return floatval($row['total']); }, $expenseRows);

$avgRevenue = count($revenueTotals) ? array_sum($revenueTotals) / count($revenueTotals) : 0.0;
$avgExpense = count($expenseTotals) ? array_sum($expenseTotals) / count($expenseTotals) : 0.0;

$forecast = [];
for ($i = 1; $i <= 3; $i++) {
    $monthKey = date('Y-m', strtotime("+{$i} months"));
    $forecast[] = [
        'month' => $monthKey,
        'forecast_revenue' => $avgRevenue,
        'forecast_expenses' => $avgExpense,
        'forecast_profit' => $avgRevenue - $avgExpense
    ];
}

api_send([
    'success' => true,
    'endpoint' => '/api/financial/financial-forecast',
    'model' => 'baseline_average',
    'months_back' => $monthsBack,
    'history' => [
        'revenue' => $revenueRows,
        'expenses' => $expenseRows
    ],
    'forecast' => $forecast
]);
