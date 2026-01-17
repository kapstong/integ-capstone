<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();
$where = [];
$params = [];

if ($dateFrom) {
    $where[] = 'des.business_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'des.business_date <= ?';
    $params[] = $dateTo;
}

$baseSql = "
    FROM daily_expense_summary des
    LEFT JOIN departments d ON des.department_id = d.id
";

$whereSql = '';
if (!empty($where)) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

$sqlDept = "
    SELECT COALESCE(d.dept_name, 'General') as department,
           COALESCE(SUM(des.total_amount), 0) as total_amount
" . $baseSql . $whereSql . " GROUP BY d.dept_name ORDER BY d.dept_name";

$stmtDept = $db->prepare($sqlDept);
$stmtDept->execute($params);
$byDepartment = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

$sqlCategory = "
    SELECT COALESCE(des.expense_category, 'uncategorized') as expense_category,
           COALESCE(SUM(des.total_amount), 0) as total_amount
" . $baseSql . $whereSql . " GROUP BY des.expense_category ORDER BY des.expense_category";

$stmtCategory = $db->prepare($sqlCategory);
$stmtCategory->execute($params);
$byCategory = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);

$totalExpenses = 0.0;
foreach ($byDepartment as $row) {
    $totalExpenses += floatval($row['total_amount']);
}

api_send([
    'success' => true,
    'endpoint' => '/api/financial/expense-summary',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'total_expenses' => $totalExpenses,
    'by_department' => $byDepartment,
    'by_category' => $byCategory
]);
