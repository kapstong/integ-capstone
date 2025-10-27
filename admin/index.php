<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch dashboard metrics
try {
    // Customer count
    $customerCount = $db->query("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")->fetch()['count'];

    // Vendor count
    $vendorCount = $db->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'")->fetch()['count'];

    // Total Income (payments received)
    $totalIncome = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments_received WHERE YEAR(payment_date) = YEAR(CURDATE())")->fetch()['total'];

    // Total Expenses (payments made)
    $totalExpenses = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments_made WHERE YEAR(payment_date) = YEAR(CURDATE())")->fetch()['total'];

    // Net Profit
    $netProfit = $totalIncome - $totalExpenses;

    // Cash Flow (current cash balance from journal entries)
    $cashBalance = $db->query("
        SELECT COALESCE(SUM(
            CASE
                WHEN jel.debit > 0 THEN jel.debit
                WHEN jel.credit > 0 THEN -jel.credit
                ELSE 0
            END
        ), 0) as balance
        FROM journal_entry_lines jel
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE coa.account_code = '1001' AND je.status = 'posted'
    ")->fetch()['balance'];

    // Accounts Receivable balance
    $totalReceivables = $db->query("SELECT COALESCE(SUM(balance), 0) as total FROM invoices WHERE status IN ('sent', 'overdue')")->fetch()['total'];

    // Accounts Payable balance
    $totalPayables = $db->query("SELECT COALESCE(SUM(balance), 0) as total FROM bills WHERE status IN ('approved', 'overdue')")->fetch()['total'];

    // Today's summary
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments_received WHERE DATE(payment_date) = ?");
    $stmt->execute([$today]);
    $todayIncome = $stmt->fetch()['total'];
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments_made WHERE DATE(payment_date) = ?");
    $stmt->execute([$today]);
    $todayExpenses = $stmt->fetch()['total'];
    $todayBalance = $todayIncome - $todayExpenses;

    // Recent transactions (last 10)
    $recentTransactions = $db->query("
        (SELECT
            'payment_received' as type,
            pr.payment_date as date,
            CONCAT('Payment from ', c.company_name) as description,
            pr.amount,
            pr.payment_method,
            pr.reference_number,
            pr.created_at
        FROM payments_received pr
        JOIN customers c ON pr.customer_id = c.id
        ORDER BY pr.created_at DESC LIMIT 5)
        UNION ALL
        (SELECT
            'payment_made' as type,
            pm.payment_date as date,
            CONCAT('Payment to ', v.company_name) as description,
            pm.amount,
            pm.payment_method,
            pm.reference_number,
            pm.created_at
        FROM payments_made pm
        JOIN vendors v ON pm.vendor_id = v.id
        ORDER BY pm.created_at DESC LIMIT 5)
        ORDER BY created_at DESC LIMIT 10
    ")->fetchAll();

    // Chart data - Revenue vs Expenses for last 6 months
    $chartData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments_received WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$monthStart, $monthEnd]);
        $revenue = $stmt->fetch()['total'];
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments_made WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$monthStart, $monthEnd]);
        $expenses = $stmt->fetch()['total'];

        $chartData[] = [
            'month' => date('M Y', strtotime($monthStart)),
            'revenue' => (float)$revenue,
            'expenses' => (float)$expenses
        ];
    }

    // Collections vs Disbursements (monthly for last 6 months)
    $cashFlowData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments_received WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$monthStart, $monthEnd]);
        $collections = $stmt->fetch()['total'];
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments_made WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$monthStart, $monthEnd]);
        $disbursements = $stmt->fetch()['total'];

        $cashFlowData[] = [
            'month' => date('M Y', strtotime($monthStart)),
            'collections' => (float)$collections,
            'disbursements' => (float)$disbursements
        ];
    }

    // Financial Health Score (improved calculation)
    $healthScore = 0;
    if ($totalIncome > 0) {
        $profitMargin = ($netProfit / $totalIncome) * 100;
        $liquidityRatio = $totalPayables > 0 ? ($cashBalance / $totalPayables) * 100 : 0;
        $receivablesRatio = $totalReceivables > 0 ? ($totalIncome / $totalReceivables) : 0;

        // Weighted score: 40% profit margin, 30% liquidity, 30% receivables turnover
        $healthScore = min(100, max(0,
            ($profitMargin * 0.4) +
            (min(100, $liquidityRatio) * 0.3) +
            (min(100, $receivablesRatio * 10) * 0.3)
        ));
    }

    // Invoice statistics
    $totalInvoices = $db->query("SELECT COUNT(*) as count FROM invoices")->fetch()['count'];
    $paidInvoices = $db->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'paid'")->fetch()['count'];
    $overdueInvoices = $db->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'overdue' AND due_date < CURDATE()")->fetch()['count'];

    // Bill statistics
    $totalBills = $db->query("SELECT COUNT(*) as count FROM bills")->fetch()['count'];
    $paidBills = $db->query("SELECT COUNT(*) as count FROM bills WHERE status = 'paid'")->fetch()['count'];
    $overdueBills = $db->query("SELECT COUNT(*) as count FROM bills WHERE status = 'overdue' AND due_date < CURDATE()")->fetch()['count'];

    // Budget vs Actual data for last 6 months
    $budgetActualData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        // Get budgeted amounts for the month (assuming budgets are annual, divide by 12)
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(bi.budgeted_amount / 12), 0) as budgeted
            FROM budget_items bi
            JOIN budgets b ON bi.budget_id = b.id
            WHERE b.budget_year = YEAR(?) AND bi.category_id IN (
                SELECT id FROM budget_categories WHERE category_type = 'expense'
            )
        ");
        $stmt->execute([$monthStart]);
        $budgetQuery = $stmt->fetch()['budgeted'];

        // Get actual expenses for the month
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as actual
            FROM payments_made
            WHERE payment_date BETWEEN ? AND ?
        ");
        $stmt->execute([$monthStart, $monthEnd]);
        $actualQuery = $stmt->fetch()['actual'];

        $budgetActualData[] = [
            'month' => date('M Y', strtotime($monthStart)),
            'budgeted' => (float)$budgetQuery,
            'actual' => (float)$actualQuery
        ];
    }

    // Income source breakdown data
    $incomeSourceData = $db->query("
        SELECT
            CASE
                WHEN LOWER(ii.description) LIKE '%room%' THEN 'Room Service'
                WHEN LOWER(ii.description) LIKE '%restaurant%' OR LOWER(ii.description) LIKE '%food%' THEN 'Restaurant'
                ELSE 'Other Services'
            END as source,
            SUM(ii.line_total) as amount
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        WHERE i.status = 'paid'
        GROUP BY
            CASE
                WHEN LOWER(ii.description) LIKE '%room%' THEN 'Room Service'
                WHEN LOWER(ii.description) LIKE '%restaurant%' OR LOWER(ii.description) LIKE '%food%' THEN 'Restaurant'
                ELSE 'Other Services'
            END
    ")->fetchAll();

    // Format for chart
    $incomeLabels = [];
    $incomeAmounts = [];
    foreach ($incomeSourceData as $item) {
        $incomeLabels[] = $item['source'];
        $incomeAmounts[] = (float)$item['amount'];
    }

    // If no data, provide default empty values
    if (empty($incomeLabels)) {
        $incomeLabels = ['Room Service', 'Restaurant', 'Other Services'];
        $incomeAmounts = [0, 0, 0];
    }

} catch (Exception $e) {
    // Handle database errors gracefully
    Logger::getInstance()->logDatabaseError('Dashboard metrics calculation', $e->getMessage());

    $customerCount = 0;
    $vendorCount = 0;
    $totalIncome = 0;
    $totalExpenses = 0;
    $netProfit = 0;
    $cashBalance = 0;
    $totalReceivables = 0;
    $totalPayables = 0;
    $todayIncome = 0;
    $todayExpenses = 0;
    $todayBalance = 0;
    $recentTransactions = [];
    $chartData = [];
    $cashFlowData = [];
    $healthScore = 0;
    $totalInvoices = 0;
    $paidInvoices = 0;
    $overdueInvoices = 0;
    $totalBills = 0;
    $paidBills = 0;
    $overdueBills = 0;
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    background-color: #F1F7EE;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
}
.sidebar {
    background-color: #1e2936;
    color: white;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    width: 300px;
    z-index: 1000;
    transition: transform 0.3s ease, width 0.3s ease;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}
.sidebar.sidebar-collapsed {
    width: 120px;
}
.sidebar.sidebar-collapsed span {
    display: none;
}
.sidebar.sidebar-collapsed .nav-link {
    padding: 10px;
    text-align: center;
}
.sidebar.sidebar-collapsed .navbar-brand {
    text-align: center;
}
.sidebar.sidebar-collapsed .nav-item i[data-bs-toggle="collapse"] {
    display: none;
}
.sidebar.sidebar-collapsed .submenu {
    display: none;
}
.sidebar .nav-link {
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 1.1em;
}
.sidebar .nav-link i {
    font-size: 1.4em;
}
.sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}
.sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.2);
}
.sidebar .submenu {
    padding-left: 20px;
}
.sidebar .submenu .nav-link {
    padding: 5px 20px;
    font-size: 0.9em;
}
.sidebar .nav-item {
    position: relative;
}
.sidebar .nav-item i[data-bs-toggle="collapse"] {
    position: absolute;
    right: 20px;
    top: 10px;
    transition: transform 0.3s ease;
}
.sidebar .nav-item i[aria-expanded="true"] {
    transform: rotate(90deg);
}
.sidebar .nav-item i[aria-expanded="false"] {
    transform: rotate(0deg);
}
.content {
    margin-left: 120px;
    padding: 20px;
    transition: margin-left 0.3s ease;
    position: relative;
    z-index: 1;
}
.sidebar .navbar-brand {
    color: white !important;
    font-weight: bold;
}
.sidebar .navbar-brand img {
    height: 50px;
    width: auto;
    max-width: 100%;
    transition: height 0.3s ease;
}
.sidebar.sidebar-collapsed .navbar-brand img {
    height: 80px;
}
.sidebar-toggle {
    position: fixed;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: white;
    font-size: 1.5em;
    width: 40px;
    height: 40px;
    background-color: #1e2936;
    border: 2px solid white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: left 0.3s ease, background-color 0.3s ease;
    z-index: 1001;
}
.sidebar-toggle:hover {
    background-color: rgba(255, 255, 255, 0.1);
}
.toggle-btn {
    display: none;
}
.navbar .dropdown-toggle {
    text-decoration: none !important;
}
.navbar .dropdown-toggle:focus {
    box-shadow: none;
}
.navbar .btn-link {
    text-decoration: none !important;
}
.navbar .btn-link:focus {
    box-shadow: none;
}
.navbar {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e3e6ea;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 10000;
}
.navbar-brand {
    font-weight: 700;
    color: #2c3e50 !important;
    font-size: 1.4rem;
    letter-spacing: -0.02em;
}
.navbar .dropdown-toggle {
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    transition: all 0.2s ease;
}
.navbar .dropdown-toggle:hover {
    background-color: rgba(0,0,0,0.05);
}
.navbar .dropdown-toggle span {
    font-weight: 600;
    font-size: 1.1rem;
    color: #495057;
}
.navbar .btn-link {
    font-size: 1.1rem;
    border-radius: 8px;
    padding: 0.5rem;
    transition: all 0.2s ease;
    color: #6c757d;
}
.navbar .btn-link:hover {
    background-color: rgba(0,0,0,0.05);
    color: #495057;
}
.navbar .input-group {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}
.navbar .input-group:focus-within {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    border-color: #007bff;
}
.navbar .form-control {
    border: none;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    background-color: #ffffff;
}
.navbar .form-control:focus {
    box-shadow: none;
    border-color: transparent;
    background-color: #ffffff;
}
.navbar .btn-outline-secondary {
    border: none;
    background-color: #f8f9fa;
    color: #6c757d;
    border-left: 1px solid #e9ecef;
    padding: 0.75rem 1rem;
}
.navbar .btn-outline-secondary:hover {
    background-color: #e9ecef;
    color: #495057;
}
.navbar .dropdown-menu {
    z-index: 9999;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    border: none;
    border-radius: 8px;
    margin-top: 0.5rem;
}
.navbar .dropdown-item {
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}
.navbar .dropdown-item:hover {
    background-color: #f8f9fa;
    color: #495057;
}
.hover-link:hover {
    color: #007bff !important;
    transition: color 0.2s ease;
}
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem;
}

.card-header h5 {
    color: #1e2936;
    font-weight: 700;
    margin: 0;
    font-size: 1.25rem;
}

.card-body {
    padding: 2rem;
}
.btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
    border: none;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-primary {
    background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: #212529;
}

.btn-outline-primary {
    border: 2px solid #1e2936;
    color: #1e2936;
}

.btn-outline-primary:hover {
    background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
    color: white;
}

.btn-outline-danger {
    border: 2px solid #dc3545;
    color: #dc3545;
}

.btn-outline-danger:hover {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
}
.table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.table thead th {
    background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
    color: white;
    font-weight: 600;
    border: none;
    padding: 1rem;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid #f1f1f1;
}

.table tbody tr:hover {
    background-color: rgba(30, 41, 54, 0.02);
    transform: scale(1.01);
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    color: #495057;
}
.account-type {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
}
.asset { background-color: #d4edda; color: #155724; }
.liability { background-color: #f8d7da; color: #721c24; }
.equity { background-color: #d1ecf1; color: #0c5460; }
.revenue { background-color: #d4edda; color: #155724; }
.expense { background-color: #f8d7da; color: #721c24; }
.tab-pane {
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.stats-card {
    background: #f8f9fa;
    color: #1e2936;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.stats-card h3 {
    font-size: 2em;
    margin-bottom: 5px;
}
.stats-card p {
    margin: 0;
    opacity: 0.9;
}
/* Enhanced UI Styles */
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 8px 8px 0 0;
    padding: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    margin-right: 0.25rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-tabs .nav-link:hover {
    background-color: rgba(30, 41, 54, 0.05);
    color: #1e2936;
}

.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
    color: white;
    box-shadow: 0 4px 8px rgba(30, 41, 54, 0.2);
}

.nav-tabs .nav-link i {
    margin-right: 0.5rem;
    font-size: 0.9em;
}
.financial-table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    border-top: none;
}
.financial-table td {
    border: none;
    padding: 12px 15px;
}
.financial-table .total-row {
    background-color: #e9ecef;
    font-weight: bold;
}
.alert-custom {
    border-radius: 8px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus, .form-select:focus {
    border-color: #1e2936;
    box-shadow: 0 0 0 0.2rem rgba(30, 41, 54, 0.1);
    transform: translateY(-1px);
}

.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0;
    padding: 1.5rem 2rem;
}

.modal-title {
    color: #1e2936;
    font-weight: 700;
    font-size: 1.25rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 1.5rem 2rem;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}
.metric-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}
.metric-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: shimmer 3s ease-in-out infinite;
}
@keyframes shimmer {
    0%, 100% { transform: rotate(0deg) translate(-50%, -50%); }
    50% { transform: rotate(180deg) translate(-50%, -50%); }
}
.metric-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}
.metric-card.warning {
    background: linear-gradient(135deg, #fcb045 0%, #fd1d1d 100%);
}
.metric-card.info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.metric-card .card-body {
    padding: 1.5rem;
    position: relative;
    z-index: 2;
}
.metric-card h6 {
    font-size: 0.9rem;
    font-weight: 500;
    opacity: 0.9;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.metric-card h4 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.chart-container {
    position: relative;
    margin: auto;
    height: 300px;
    width: 100%;
}
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .content {
        margin-left: 0;
        padding: 20px;
    }
    .toggle-btn {
        display: block;
    }
    .stats-card h3 {
        font-size: 1.5em;
    }
    .table-responsive {
        font-size: 0.9em;
    }
}
/* Enhanced Footer */
.footer-enhanced {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 3px solid #1e2936;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .content {
        margin-left: 0;
        padding: 20px;
    }
    .toggle-btn {
        display: block;
    }
    .stats-card h3 {
        font-size: 1.5em;
    }
    .table-responsive {
        font-size: 0.9em;
    }
}
</style>
</head>
<body>
    <div class="sidebar sidebar-collapsed" id="sidebar">
        <div class="p-3">
            <h5 class="navbar-brand"><img src="atieralogo.png" alt="Atiera Logo" style="height: 100px;"></h5>
            <hr style="border-top: 2px solid white; margin: 10px 0;">
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
            </a>
            <div class="nav-item">
                <a class="nav-link" href="general_ledger.php">
                    <i class="fas fa-book me-2"></i><span>General Ledger</span>
                </a>
                <i class="fas fa-chevron-right" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu" aria-expanded="false" style="cursor: pointer; color: white; padding: 5px 10px;"></i>
                <div class="collapse" id="generalLedgerMenu">
                    <div class="submenu">
                        <a class="nav-link" href="accounts_payable.php">
                            <i class="fas fa-credit-card me-2"></i><span>Accounts Payable</span>
                        </a>
                        <a class="nav-link" href="accounts_receivable.php">
                            <i class="fas fa-money-bill-wave me-2"></i><span>Accounts Receivable</span>
                        </a>
                    </div>
                </div>
            </div>
            <a class="nav-link" href="payroll.php">
                <i class="fas fa-money-check-alt me-2"></i><span>Payroll</span>
            </a>
            <a class="nav-link" href="disbursements.php">
                <i class="fas fa-money-check me-2"></i><span>Disbursements</span>
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
            </a>
        </nav>
    </div>
    <div class="sidebar-toggle" onclick="toggleSidebarDesktop()">
        <i class="fas fa-chevron-right" id="sidebarArrow"></i>
    </div>

    <div class="content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary toggle-btn" type="button" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="navbar-brand mb-0 h1 me-4">Dashboard</span>
                <div class="d-flex align-items-center me-4">
                    <button class="btn btn-link text-dark me-3 position-relative" type="button">
                        <i class="fas fa-bell fa-lg"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7em;">
                            3
                        </span>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <span><strong><?php
                                // Display user name: first_name + last_name, or full_name, or username
                                $firstName = $_SESSION['user']['first_name'] ?? '';
                                $lastName = $_SESSION['user']['last_name'] ?? '';
                                $fullName = $_SESSION['user']['full_name'] ?? '';
                                $userName = $_SESSION['user']['username'] ?? '';

                                if (!empty($firstName) || !empty($lastName)) {
                                    echo htmlspecialchars(trim($firstName . ' ' . $lastName));
                                } elseif (!empty($fullName)) {
                                    echo htmlspecialchars($fullName);
                                } else {
                                    echo htmlspecialchars($userName);
                                }
                            ?></strong></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin-profile-settings.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="input-group mx-auto" style="width: 500px;">
                        <input type="text" class="form-control" placeholder="Search..." aria-label="Search">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border: none; box-shadow: 0 8px 25px rgba(30, 41, 54, 0.1);">
                    <div class="card-body py-5">
                        <div class="row align-items-center">
                            <div class="col-lg-8 text-center text-lg-start">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                    <div>
                                        <h2 class="mb-1" style="color: #1e2936; font-weight: 700;">Hello, <strong><?php echo htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username']); ?></strong></h2>
                                        <p class="text-muted mb-0">Ready to manage your finances efficiently</p>
                                    </div>
                                </div>
                                <p class="mb-4" style="color: #6c757d; font-size: 1.1rem; line-height: 1.6;">
                                    Your comprehensive financial management platform. Track income, monitor expenses,
                                    generate reports, and make informed business decisions with real-time insights.
                                </p>
                                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                                    <button class="btn btn-primary btn-lg px-4" onclick="window.location.href='accounts_receivable.php'">
                                        <i class="fas fa-plus me-2"></i>Add Invoice
                                    </button>
                                    <button class="btn btn-outline-primary btn-lg px-4" onclick="window.location.href='reports.php'">
                                        <i class="fas fa-chart-bar me-2"></i>View Reports
                                    </button>
                                    <button class="btn btn-outline-success btn-lg px-4" onclick="showQuickActionsModal()">
                                        <i class="fas fa-cog me-2"></i>Quick Setup
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-4 text-center mt-4 mt-lg-0">
                                <div class="position-relative">
                                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                        <i class="fas fa-peso-sign fa-4x text-primary"></i>
                                    </div>
                                    <div class="position-absolute" style="top: -10px; right: -10px;">
                                        <span class="badge bg-success rounded-pill px-3 py-2">
                                            <i class="fas fa-check me-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h5 class="text-muted mb-1">Today's Summary</h5>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="p-2">
                                                <i class="fas fa-arrow-up text-success fa-lg mb-1"></i>
                                                <div class="small text-muted">Income</div>
                                                <div class="fw-bold text-success">₱<?php echo number_format($todayIncome, 2); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2">
                                                <i class="fas fa-arrow-down text-danger fa-lg mb-1"></i>
                                                <div class="small text-muted">Expenses</div>
                                                <div class="fw-bold text-danger">₱<?php echo number_format($todayExpenses, 2); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2">
                                                <i class="fas fa-balance-scale text-primary fa-lg mb-1"></i>
                                                <div class="small text-muted">Balance</div>
                                                <div class="fw-bold text-primary">₱<?php echo number_format($todayBalance, 2); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true"><i class="fas fa-tachometer-alt me-1"></i>Overview</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="analytics-tab" data-bs-toggle="tab" href="#analytics" role="tab" aria-controls="analytics" aria-selected="false"><i class="fas fa-chart-line me-1"></i>Analytics</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="alerts-tab" data-bs-toggle="tab" href="#alerts" role="tab" aria-controls="alerts" aria-selected="false"><i class="fas fa-bell me-1"></i>Alerts</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="reports-tab" data-bs-toggle="tab" href="#reports" role="tab" aria-controls="reports" aria-selected="false"><i class="fas fa-chart-bar me-1"></i>Reports</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="dashboardTabsContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab" style="padding-bottom: 100px;">
                                <!-- Key Metrics Row -->
                                <div class="row mb-4">
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-peso-sign fa-2x text-success me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">₱<?php echo number_format($totalIncome, 2); ?></h5>
                                                        <small class="text-muted">Total Income</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-credit-card fa-2x text-danger me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">₱<?php echo number_format($totalExpenses, 2); ?></h5>
                                                        <small class="text-muted">Total Expenses</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-chart-line fa-2x text-primary me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">₱<?php echo number_format($netProfit, 2); ?></h5>
                                                        <small class="text-muted">Net Profit</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-balance-scale fa-2x text-warning me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">₱<?php echo number_format($cashBalance, 2); ?></h5>
                                                        <small class="text-muted">Cash Balance</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Activity and Quick Actions -->
                                <div class="row">
                                    <div class="col-lg-8 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-history text-primary me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Recent Activity</h6>
                                                    <small class="text-muted">Latest transactions and updates</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($recentTransactions)): ?>
                                                    <div class="list-group list-group-flush">
                                                        <?php foreach ($recentTransactions as $transaction): ?>
                                                            <div class="list-group-item px-0">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0 me-3">
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="mb-1"><?php echo htmlspecialchars($transaction['description']); ?></h6>
                                                                        <small class="text-muted">
                                                                            <?php echo date('M j, Y', strtotime($transaction['date'])); ?> •
                                                                            <?php echo htmlspecialchars($transaction['payment_method']); ?>
                                                                            <?php if (!empty($transaction['reference_number'])): ?>
                                                                                • Ref: <?php echo htmlspecialchars($transaction['reference_number']); ?>
                                                                            <?php endif; ?>
                                                                        </small>
                                                                    </div>
                                                                    <div class="flex-shrink-0 text-end">
                                                                        <div class="fw-bold text-<?php echo $transaction['type'] === 'payment_received' ? 'success' : 'danger'; ?>">
                                                                            <?php echo $transaction['type'] === 'payment_received' ? '+' : '-'; ?>₱<?php echo number_format($transaction['amount'], 2); ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-5">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <h6 class="text-muted">No Recent Activity</h6>
                                                        <p class="text-muted small">Your recent transactions will appear here</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Financial Health Indicator -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-heartbeat text-danger me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Financial Health</h6>
                                                    <small class="text-muted">Overall business performance indicator</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-8">
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $healthScore; ?>%" aria-valuenow="<?php echo $healthScore; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block">Health Score: <?php echo round($healthScore, 1); ?>/100</small>
                                                    </div>
                                                    <div class="col-md-4 text-center">
                                                        <h4 class="text-<?php echo $healthScore >= 70 ? 'success' : ($healthScore >= 40 ? 'warning' : 'danger'); ?> mb-0"><?php echo round($healthScore, 1); ?>%</h4>
                                                        <small class="text-muted">
                                                            <?php if ($healthScore >= 70): ?>
                                                                Excellent Health
                                                            <?php elseif ($healthScore >= 40): ?>
                                                                Good Health
                                                            <?php else: ?>
                                                                Needs Attention
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Analytics Tab -->
                            <div class="tab-pane fade" id="analytics" role="tabpanel" aria-labelledby="analytics-tab" style="padding-bottom: 100px;">
                                <div class="row g-4">
                                    <div class="col-lg-6 col-xl-6">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-chart-line text-primary me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Revenue vs Expenses</h6>
                                                    <small class="text-muted">Last 6 Months Performance</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="chart-container">
                                                    <canvas id="revenueExpensesChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-xl-6">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-exchange-alt text-success me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Collections vs Disbursements</h6>
                                                    <small class="text-muted">Monthly Cash Flow Analysis</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="chart-container">
                                                    <canvas id="collectionsDisbursementsChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-xl-6">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-balance-scale text-warning me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Budget vs Actual</h6>
                                                    <small class="text-muted">Financial Planning Overview</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="chart-container">
                                                    <canvas id="budgetActualChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-xl-6">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-chart-pie text-info me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Income Source Breakdown</h6>
                                                    <small class="text-muted">Revenue Distribution</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="chart-container">
                                                    <canvas id="incomeSourceChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Alerts Tab -->
                            <div class="tab-pane fade" id="alerts" role="tabpanel" aria-labelledby="alerts-tab" style="padding-bottom: 100px;">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="alert-icon me-3">
                                        <i class="fas fa-exclamation-triangle text-danger fa-lg"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-danger">Overdue Receivables</h6>
                                        <p class="mb-1 small text-muted">Invoices are past due</p>
                                        <small class="text-danger fw-bold">Action Required</small>
                                    </div>
                                </div>

                                <div class="d-flex align-items-start mb-3">
                                    <div class="alert-icon me-3">
                                        <i class="fas fa-clock text-warning fa-lg"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-warning">Upcoming Payables</h6>
                                        <p class="mb-1 small text-muted">Payments are due soon</p>
                                        <small class="text-warning">Due Soon</small>
                                    </div>
                                </div>

                                <div class="d-flex align-items-start mb-3">
                                    <div class="alert-icon me-3">
                                        <i class="fas fa-boxes text-info fa-lg"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-info">Low Stock Alert</h6>
                                        <p class="mb-1 small text-muted">Inventory low</p>
                                        <small class="text-info">Monitor Required</small>
                                    </div>
                                </div>

                                <div class="d-flex align-items-start mb-3">
                                    <div class="alert-icon me-3">
                                        <i class="fas fa-calendar-check text-success fa-lg"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-success">Payroll Reminder</h6>
                                        <p class="mb-1 small text-muted">Payroll due soon</p>
                                        <small class="text-success">Upcoming Task</small>
                                    </div>
                                </div>
                            </div>
                            <!-- Reports Tab -->
                            <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab" style="padding-bottom: 100px;">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <a href="accounts_receivable.php" class="btn btn-outline-primary btn-lg w-100 d-flex flex-column align-items-center justify-content-center p-3" style="height: 110px; border-radius: 12px;">
                                            <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                            <span class="small fw-bold">Accounts<br>Receivable</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="accounts_payable.php" class="btn btn-outline-success btn-lg w-100 d-flex flex-column align-items-center justify-content-center p-3" style="height: 110px; border-radius: 12px;">
                                            <i class="fas fa-credit-card fa-2x mb-2"></i>
                                            <span class="small fw-bold">Accounts<br>Payable</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="disbursements.php" class="btn btn-outline-info btn-lg w-100 d-flex flex-column align-items-center justify-content-center p-3" style="height: 110px; border-radius: 12px;">
                                            <i class="fas fa-money-check fa-2x mb-2"></i>
                                            <span class="small fw-bold">Disbursements</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="budget_management.php" class="btn btn-outline-warning btn-lg w-100 d-flex flex-column align-items-center justify-content-center p-3" style="height: 110px; border-radius: 12px;">
                                            <i class="fas fa-chart-line fa-2x mb-2"></i>
                                            <span class="small fw-bold">Budget<br>Management</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="reports.php" class="btn btn-outline-secondary btn-lg w-100 d-flex flex-column align-items-center justify-content-center p-3" style="height: 110px; border-radius: 12px;">
                                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                            <span class="small fw-bold">Reports</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="general_ledger.php" class="btn btn-outline-dark btn-lg w-100 d-flex flex-column align-items-center justify-content-center p-3" style="height: 110px; border-radius: 12px;">
                                            <i class="fas fa-book fa-2x mb-2"></i>
                                            <span class="small fw-bold">General<br>Ledger</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer id="footer" class="footer-enhanced py-3" style="position: fixed; bottom: 0; left: 120px; width: calc(100% - 120px); z-index: 998; font-weight: 500;">
        <div class="container-fluid">
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-4">
                    <span class="text-muted"><i class="fas fa-shield-alt me-1" style="color: #1e2936;"></i>© 2025 ATIERA Finance — Confidential</span>
                </div>
<div class="col-md-4">
                      <span class="text-muted">
                        <span class="badge" style="background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%); color: white;">PROD</span> v1.0.0 • Updated: Sep 25, 2025
                        <span class="ms-3 text-success fw-bold"><i class="fas fa-sync-alt me-1"></i>Sync OK</span>
                      </span>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="text-muted">
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link" style="color: #6c757d !important;">Terms</a>
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link" style="color: #6c757d !important;">Privacy</a>
                        <a href="mailto:support@atiera.com" class="text-decoration-none text-muted hover-link" style="color: #6c757d !important;"><i class="fas fa-envelope me-1"></i>Support</a>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            updateFooterPosition();
        }

        function toggleSidebarDesktop() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            sidebar.classList.toggle('sidebar-collapsed');
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            if (isCollapsed) {
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }
            updateFooterPosition();
        }

        function updateFooterPosition() {
            const content = document.querySelector('.content');
            const footer = document.getElementById('footer');
            const marginLeft = content.style.marginLeft || '120px';
            footer.style.left = marginLeft;
            footer.style.width = `calc(100% - ${marginLeft})`;
        }

        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('sidebar-collapsed');
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                sidebar.classList.remove('sidebar-collapsed');
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }
            updateFooterPosition();

            // Chart data from PHP
            const chartData = <?php echo json_encode($chartData); ?>;
            const cashFlowData = <?php echo json_encode($cashFlowData); ?>;
            const budgetActualData = <?php echo json_encode($budgetActualData); ?>;
            const incomeLabels = <?php echo json_encode($incomeLabels); ?>;
            const incomeAmounts = <?php echo json_encode($incomeAmounts); ?>;

            // Initialize Revenue vs Expenses Chart
            const revenueExpensesCtx = document.getElementById('revenueExpensesChart').getContext('2d');
            const revenueExpensesChart = new Chart(revenueExpensesCtx, {
                type: 'line',
                data: {
                    labels: chartData.map(item => item.month),
                    datasets: [{
                        label: 'Revenue',
                        data: chartData.map(item => item.revenue),
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Expenses',
                        data: chartData.map(item => item.expenses),
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Initialize Collections vs Disbursements Chart
            const collectionsDisbursementsCtx = document.getElementById('collectionsDisbursementsChart').getContext('2d');
            const collectionsDisbursementsChart = new Chart(collectionsDisbursementsCtx, {
                type: 'bar',
                data: {
                    labels: cashFlowData.map(item => item.month),
                    datasets: [{
                        label: 'Collections',
                        data: cashFlowData.map(item => item.collections),
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Disbursements',
                        data: cashFlowData.map(item => item.disbursements),
                        backgroundColor: 'rgba(220, 53, 69, 0.8)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Initialize Budget vs Actual Chart
            const budgetActualCtx = document.getElementById('budgetActualChart').getContext('2d');
            const budgetActualChart = new Chart(budgetActualCtx, {
                type: 'line',
                data: {
                    labels: budgetActualData.map(item => item.month),
                    datasets: [{
                        label: 'Budget',
                        data: budgetActualData.map(item => item.budgeted),
                        borderColor: 'rgba(255, 193, 7, 1)',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Actual',
                        data: budgetActualData.map(item => item.actual),
                        borderColor: 'rgba(23, 162, 184, 1)',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Initialize Income Source Breakdown Chart
            const incomeSourceCtx = document.getElementById('incomeSourceChart').getContext('2d');
            const incomeSourceChart = new Chart(incomeSourceCtx, {
                type: 'pie',
                data: {
                    labels: incomeLabels,
                    datasets: [{
                        data: incomeAmounts,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 205, 86, 0.8)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });

        function showQuickActionsModal() {
            const modalHTML = `
            <div class="modal fade" id="quickActionsModal" tabindex="-1" aria-labelledby="quickActionsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="quickActionsModalLabel">
                                <i class="fas fa-cog me-2"></i>Quick Setup Actions
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border-success h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                            <h6>Configure Budgets</h6>
                                            <p class="text-muted small">Set up annual budgets for departments</p>
                                            <button class="btn btn-success btn-sm" onclick="configureBudgets()">Setup Budgets</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-info h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-key fa-3x text-info mb-3"></i>
                                            <h6>Generate API Keys</h6>
                                            <p class="text-muted small">Create API credentials for integrations</p>
                                            <button class="btn btn-info btn-sm" onclick="generateAPIKeys()">Create API Key</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-warning h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-envelope fa-3x text-warning mb-3"></i>
                                            <h6>Email Configuration</h6>
                                            <p class="text-muted small">Configure email settings for notifications</p>
                                            <button class="btn btn-warning btn-sm" onclick="configureEmail()">Configure Email</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="runAllQuickSetup()">Run All Setup</button>
                        </div>
                    </div>
                </div>
            </div>`;

            // Remove existing modal if present
            const existingModal = document.getElementById('quickActionsModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('quickActionsModal'));
            modal.show();
        }


        function configureBudgets() {
            window.location.href = 'budget_management.php';
        }

        function generateAPIKeys() {
            window.location.href = 'api_clients.php';
        }

        function configureEmail() {
            window.location.href = 'settings.php';
        }

        function runAllQuickSetup() {
            alert('Complete quick setup will be implemented in a future update.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('quickActionsModal'));
            modal.hide();
        }
    </script>

    <!-- Privacy Mode - Hide amounts with asterisks + Eye button -->
    <script src="../includes/privacy_mode.js?v=4"></script>

    <!-- Inactivity Timeout - Blur screen + Auto logout -->
    <script src="../includes/inactivity_timeout.js?v=3"></script>

</body>
</html>
