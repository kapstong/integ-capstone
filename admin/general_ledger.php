<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch summary data and actual data for all modules
try {
    // Basic stats
    $totalAccounts = $db->query("SELECT COUNT(*) as count FROM chart_of_accounts WHERE is_active = 1")->fetch()['count'];
    $totalEntries = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE status != 'voided'")->fetch()['count'] ?? 0;

    // Calculate balances from journal entries
    $balanceQuery = $db->query("
        SELECT
            coa.account_type,
            SUM(COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)) as balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.status = 'posted'
        WHERE coa.is_active = 1
        GROUP BY coa.id, coa.account_type
    ")->fetchAll();

    // Initialize balance variables
    $totalAssets = 0;
    $totalLiabilities = 0;
    $totalEquity = 0;
    $totalRevenue = 0;
    $totalExpenses = 0;

    foreach ($balanceQuery as $row) {
        $balance = intval($row['balance']);
        switch ($row['account_type']) {
            case 'asset':
                $totalAssets += $balance;
                break;
            case 'liability':
                $totalLiabilities += $balance;
                break;
            case 'equity':
                $totalEquity += $balance;
                break;
            case 'revenue':
                $totalRevenue += $balance;
                break;
            case 'expense':
                $totalExpenses += $balance;
                break;
        }
    }

    $netProfit = $totalRevenue - $totalExpenses;

    // Fetch actual Chart of Accounts with calculated balances
    $chartOfAccountsQuery = $db->query("
        SELECT
            coa.id,
            coa.account_code,
            coa.account_name,
            coa.account_type,
            coa.description,
            coa.category,
            COALESCE(SUM(jel.debit - jel.credit), 0) as balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.status = 'posted'
        WHERE coa.is_active = 1
        GROUP BY coa.id, coa.account_code, coa.account_name, coa.account_type, coa.description, coa.category
        ORDER BY coa.account_code ASC
    ")->fetchAll();

    // Fetch actual Journal Entries with proper formatting
    $journalEntriesQuery = $db->query("
        SELECT
            je.entry_date as date,
            je.entry_number as reference,
            je.description,
            jel.debit,
            jel.credit,
            coa.account_name,
            je.status
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        ORDER BY je.entry_date DESC, je.id DESC
        LIMIT 50
    ")->fetchAll();

    // Fetch trial balance data with proper calculations
    $trialBalanceQuery = $db->query("
        SELECT
            coa.account_code,
            coa.account_name,
            COALESCE(SUM(jel.debit), 0) as debit_balance,
            COALESCE(SUM(jel.credit), 0) as credit_balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id AND je.status = 'posted'
        WHERE coa.is_active = 1
        GROUP BY coa.id, coa.account_code, coa.account_name
        HAVING (debit_balance > 0 OR credit_balance > 0)
        ORDER BY coa.account_code ASC
    ")->fetchAll();

    // Fetch audit trail (if table exists)
    try {
        $auditTrail = $db->query("
            SELECT created_at as date_time, user_id as user, action, table_name as details, record_id
            FROM audit_log
            ORDER BY created_at DESC
            LIMIT 10
        ")->fetchAll() ?? [];
    } catch (Exception $e) {
        $auditTrail = [];
    }

    // Assign results to variables
    $chartOfAccounts = $chartOfAccountsQuery;
    $journalEntries = $journalEntriesQuery;
    $trialBalance = $trialBalanceQuery;

} catch (Exception $e) {
    error_log("Database error in general_ledger.php: " . $e->getMessage());
    $totalAccounts = 0;
    $totalEntries = 0;
    $totalAssets = 0;
    $totalLiabilities = 0;
    $totalEquity = 0;
    $totalRevenue = 0;
    $totalExpenses = 0;
    $netProfit = 0;
    $chartOfAccounts = [];
    $journalEntries = [];
    $trialBalance = [];
    $auditTrail = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - General Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            <a class="nav-link" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
            </a>
            <div class="nav-item">
                <a class="nav-link active" href="general_ledger.php">
                    <i class="fas fa-book me-2"></i><span>General Ledger</span>
                </a>
                <i class="fas fa-chevron-right" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu" aria-expanded="true" style="cursor: pointer; color: white; padding: 5px 10px;"></i>
                <div class="collapse show" id="generalLedgerMenu">
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
            <a class="nav-link" href="disbursements.php">
                <i class="fas fa-money-check me-2"></i><span>Disbursements</span>
            </a>
            <a class="nav-link" href="budget_management.php">
                <i class="fas fa-chart-line me-2"></i><span>Budget Management</span>
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
                <span class="navbar-brand mb-0 h1 me-4">General Ledger</span>
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
                            <li><a class="dropdown-item" href="../index.php?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($totalAccounts); ?></h3>
                    <p>Total Accounts</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo number_format($totalEntries); ?></h3>
                    <p>Journal Entries</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>₱<?php echo number_format($totalAssets, 2); ?></h3>
                    <p>Total Assets</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3>₱<?php echo number_format($netProfit, 2); ?></h3>
                    <p>Net Profit</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="glTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="coa-tab" data-bs-toggle="tab" href="#coa" role="tab" aria-controls="coa" aria-selected="true" data-bs-toggle="tooltip" title="Master list of all accounts"><i class="fas fa-list me-1"></i>Chart of Accounts</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="journal-tab" data-bs-toggle="tab" href="#journal" role="tab" aria-controls="journal" aria-selected="false" data-bs-toggle="tooltip" title="Record financial transactions"><i class="fas fa-edit me-1"></i>Journal Entries</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="trial-tab" data-bs-toggle="tab" href="#trial" role="tab" aria-controls="trial" aria-selected="false" data-bs-toggle="tooltip" title="Check that debits equal credits"><i class="fas fa-balance-scale me-1"></i>Trial Balance</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="financial-tab" data-bs-toggle="tab" href="#financial" role="tab" aria-controls="financial" aria-selected="false" data-bs-toggle="tooltip" title="Balance Sheet, Income Statement, Cash Flow"><i class="fas fa-chart-line me-1"></i>Financial Statements</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="audit-tab" data-bs-toggle="tab" href="#audit" role="tab" aria-controls="audit" aria-selected="false" data-bs-toggle="tooltip" title="User activity logs"><i class="fas fa-history me-1"></i>Audit Trail</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="glTabsContent">
                            <!-- Chart of Accounts Tab -->
                            <div class="tab-pane fade show active" id="coa" role="tabpanel" aria-labelledby="coa-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Master List of Accounts</h6>
                                    <button class="btn btn-primary" onclick="showAddAccountModal()"><i class="fas fa-plus me-2"></i>Add Account</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Account Code</th>
                                                <th>Account Name</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($chartOfAccounts)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle me-2"></i>No accounts found. Click "Add Account" to create the first account.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($chartOfAccounts as $account): ?>
                                                    <tr>
                                                        <td data-account-id="<?php echo htmlspecialchars($account['id']); ?>"><?php echo htmlspecialchars($account['account_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                                                        <td><span class="account-type <?php echo $account['account_type']; ?>"><?php echo ucfirst($account['account_type']); ?></span></td>
                                                        <td><?php echo htmlspecialchars($account['description'] ?? 'No description'); ?></td>
                                                        <td><button class="btn btn-sm btn-outline-primary" onclick="editAccount()">Edit</button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Journal Entries Tab -->
                            <div class="tab-pane fade" id="journal" role="tabpanel" aria-labelledby="journal-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Record Financial Transactions</h6>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJournalModal"><i class="fas fa-plus me-2"></i>Add Journal Entry</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Reference</th>
                                                <th>Account</th>
                                                <th>Description</th>
                                                <th>Debit</th>
                                                <th>Credit</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($journalEntries)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle me-2"></i>No journal entries found. Click "Add Journal Entry" to record your first transaction.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($journalEntries as $entry): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($entry['date']))); ?></td>
                                                        <td><?php echo htmlspecialchars($entry['reference']); ?></td>
                                                        <td><?php echo htmlspecialchars($entry['account_name'] ?? 'Unknown Account'); ?></td>
                                                        <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                                        <td><?php echo $entry['debit'] > 0 ? '₱' . number_format($entry['debit'], 2) : '-'; ?></td>
                                                        <td><?php echo $entry['credit'] > 0 ? '₱' . number_format($entry['credit'], 2) : '-'; ?></td>
                <td>
                    <?php if ($entry['status'] === 'draft'): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="editEntry('<?php echo htmlspecialchars($entry['reference']); ?>')">Edit</button>
                        <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteEntry('<?php echo htmlspecialchars($entry['reference']); ?>')">Delete</button>
                    <?php else: ?>
                        <span class="text-muted small"><?php echo ucfirst($entry['status']); ?> - Read-only</span>
                    <?php endif; ?>
                </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Trial Balance Tab -->
                            <div class="tab-pane fade" id="trial" role="tabpanel" aria-labelledby="trial-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Trial Balance Report</h6>
                                    <button class="btn btn-outline-secondary" onclick="exportTrialBalance()"><i class="fas fa-download me-2"></i>Export</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th>Debit Balance</th>
                                                <th>Credit Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($trialBalance)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle me-2"></i>No balances to display. Add accounts and journal entries first.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php
                                                $debit_total = 0;
                                                $credit_total = 0;
                                                foreach ($trialBalance as $account):
                                                    $debit_total += $account['debit_balance'];
                                                    $credit_total += $account['credit_balance'];
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                                                        <td><?php echo $account['debit_balance'] > 0 ? '₱' . number_format($account['debit_balance'], 2) : '-'; ?></td>
                                                        <td><?php echo $account['credit_balance'] > 0 ? '₱' . number_format($account['credit_balance'], 2) : '-'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="table-dark">
                                                    <td><strong>Total</strong></td>
                                                    <td><strong>₱<?php echo number_format($debit_total, 2); ?></strong></td>
                                                    <td><strong>₱<?php echo number_format($credit_total, 2); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <strong>Note:</strong> Trial Balance shows that Debits do not equal Credits. This indicates an error in the journal entries that needs to be corrected.
                                </div>
                            </div>
                            <!-- Financial Statements Tab -->
                            <div class="tab-pane fade" id="financial" role="tabpanel" aria-labelledby="financial-tab">
                                <ul class="nav nav-pills mb-3" id="financialSubTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="balance-sheet-tab" data-bs-toggle="pill" href="#balance-sheet" role="tab">Balance Sheet</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="income-statement-tab" data-bs-toggle="pill" href="#income-statement" role="tab">Income Statement</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="cash-flow-tab" data-bs-toggle="pill" href="#cash-flow" role="tab">Cash Flow</a>
                                    </li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="balance-sheet" role="tabpanel">
                                        <h6>Balance Sheet - As of <?php echo date('F j, Y'); ?></h6>
                                        <table class="table financial-table">
                                            <tr><th>Assets</th><th></th><th>₱<?php echo number_format($totalAssets, 2); ?></th></tr>
                                            <?php
                                            // Get asset accounts for breakdown
                                            $assetAccounts = array_filter($chartOfAccounts, function($account) {
                                                return $account['account_type'] === 'asset';
                                            });
                                            foreach ($assetAccounts as $asset):
                                            ?>
                                                <tr><td>&nbsp;&nbsp;<?php echo htmlspecialchars($asset['account_name']); ?></td><td></td><td>₱<?php echo number_format($asset['balance'] ?? 0, 2); ?></td></tr>
                                            <?php endforeach; ?>
                                            <tr><th>Liabilities</th><th></th><th>₱<?php echo number_format($totalLiabilities, 2); ?></th></tr>
                                            <tr><th>Equity</th><th></th><th>₱<?php echo number_format($totalAssets - $totalLiabilities, 2); ?></th></tr>
                                            <?php
                                            // Get equity accounts for breakdown
                                            $equityAccounts = array_filter($chartOfAccounts, function($account) {
                                                return $account['account_type'] === 'equity';
                                            });
                                            foreach ($equityAccounts as $equity):
                                            ?>
                                                <tr><td>&nbsp;&nbsp;<?php echo htmlspecialchars($equity['account_name']); ?></td><td></td><td>₱<?php echo number_format($equity['balance'] ?? 0, 2); ?></td></tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row"><td>&nbsp;&nbsp;Retained Earnings</td><td></td><td>₱<?php echo number_format($netProfit, 2); ?></td></tr>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="income-statement" role="tabpanel">
                                        <h6>Income Statement - For the period ending <?php echo date('F j, Y'); ?></h6>
                                        <table class="table financial-table">
                                            <tr><th>Revenue</th><th></th><th>₱<?php echo number_format($totalRevenue, 2); ?></th></tr>
                                            <?php
                                            // Get revenue accounts
                                            $revenueAccounts = array_filter($chartOfAccounts, function($account) {
                                                return $account['account_type'] === 'revenue';
                                            });
                                            foreach ($revenueAccounts as $revenue):
                                            ?>
                                                <tr><td>&nbsp;&nbsp;<?php echo htmlspecialchars($revenue['account_name']); ?></td><td></td><td>₱<?php echo number_format($revenue['balance'] ?? 0, 2); ?></td></tr>
                                            <?php endforeach; ?>
                                            <tr><th>Expenses</th><th></th><th>₱<?php echo number_format($totalExpenses, 2); ?></th></tr>
                                            <?php
                                            // Get expense accounts
                                            $expenseAccounts = array_filter($chartOfAccounts, function($account) {
                                                return $account['account_type'] === 'expense';
                                            });
                                            foreach ($expenseAccounts as $expense):
                                            ?>
                                                <tr><td>&nbsp;&nbsp;<?php echo htmlspecialchars($expense['account_name']); ?></td><td></td><td>₱<?php echo number_format($expense['balance'] ?? 0, 2); ?></td></tr>
                                            <?php endforeach; ?>
                                            <tr class="total-row"><th>Net Profit</th><th></th><th>₱<?php echo number_format($netProfit, 2); ?></th></tr>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="cash-flow" role="tabpanel">
                                        <h6>Cash Flow Statement - For the period ending <?php echo date('F j, Y'); ?></h6>
                                        <table class="table financial-table">
                                            <tr><th>Operating Activities</th><th></th><th>₱<?php echo number_format($netProfit, 2); ?></th></tr>
                                            <tr><td>&nbsp;&nbsp;Net Income</td><td></td><td>₱<?php echo number_format($netProfit, 2); ?></td></tr>
                                            <?php
                                            $operatingCashFlow = $netProfit; // Simplified - would include other adjustments
                                            ?>
                                            <tr class="total-row"><th>Net Operating Cash Flow</th><th></th><th>₱<?php echo number_format($operatingCashFlow, 2); ?></th></tr>
                                            <tr><th>Investing Activities</th><th></th><th>₱0.00</th></tr>
                                            <tr><th>Financing Activities</th><th></th><th>₱0.00</th></tr>
                                            <tr class="total-row"><th>Net Cash Flow</th><th></th><th>₱<?php echo number_format($operatingCashFlow, 2); ?></th></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Audit Trail Tab -->
                            <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">User Activity Logs</h6>
                                    <button class="btn btn-outline-secondary" onclick="showFilterModal()"><i class="fas fa-filter me-2"></i>Filter</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($auditTrail)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle me-2"></i>No audit records found.
                                                    </td>
                                                </tr>
                            <?php else: ?>
                                                <?php foreach ($auditTrail as $log): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($log['date_time']))); ?></td>
                                                        <td><?php echo htmlspecialchars($log['user'] ?? 'Unknown'); ?></td>
                                                        <td><?php echo htmlspecialchars($log['action'] ?? 'Unknown'); ?></td>
                                                        <td><?php echo htmlspecialchars(($log['details'] ?? 'Unknown') . ' - ' . ($log['record_id'] ?? 'N/A')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Journal Entry Modal -->
        <div class="modal fade" id="addJournalModal" tabindex="-1" aria-labelledby="addJournalModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addJournalModalLabel">Add Journal Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                            <div class="modal-body">
                                <form id="journalEntryForm">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="entryDate" class="form-label">Date *</label>
                                            <input type="date" class="form-control" id="entryDate" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="reference" class="form-label">Reference</label>
                                            <input type="text" class="form-control" id="reference" placeholder="Auto-generated" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label for="description" class="form-label">Description *</label>
                                        <input type="text" class="form-control" id="description" placeholder="Transaction description" required>
                                    </div>

                                    <div id="journalLines" class="mb-3">
                                        <h6 class="mb-3">Journal Lines</h6>

                                        <!-- Line 1 -->
                                        <div class="journal-line border rounded p-3 mb-3 bg-light">
                                            <div class="row mb-2">
                                                <div class="col-md-5">
                                                    <label class="form-label">Account *</label>
                                                    <select class="form-select account-select" required>
                                                        <option value="">Select Account</option>
                                                        <option value="loading">Loading accounts...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Debit</label>
                                                    <input type="number" class="form-control debit-amount" step="0.01" placeholder="0.00">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Credit</label>
                                                    <input type="number" class="form-control credit-amount" step="0.01" placeholder="0.00">
                                                </div>
                                                <div class="col-md-3 d-flex align-items-end">
                                                    <input type="text" class="form-control" placeholder="Line description (optional)">
                                                    <button type="button" class="btn btn-outline-danger ms-2" onclick="removeJournalLine(this)" title="Remove line">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Line 2 -->
                                        <div class="journal-line border rounded p-3 mb-3 bg-light">
                                            <div class="row mb-2">
                                                <div class="col-md-5">
                                                    <label class="form-label">Account *</label>
                                                    <select class="form-select account-select" required>
                                                        <option value="">Select Account</option>
                                                        <option value="loading">Loading accounts...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Debit</label>
                                                    <input type="number" class="form-control debit-amount" step="0.01" placeholder="0.00">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Credit</label>
                                                    <input type="number" class="form-control credit-amount" step="0.01" placeholder="0.00">
                                                </div>
                                                <div class="col-md-3 d-flex align-items-end">
                                                    <input type="text" class="form-control" placeholder="Line description (optional)">
                                                    <button type="button" class="btn btn-outline-danger ms-2" onclick="removeJournalLine(this)" title="Remove line">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <button type="button" class="btn btn-outline-primary" onclick="addJournalLine()">
                                            <i class="fas fa-plus me-2"></i>Add Another Line
                                        </button>
                                    </div>

                                    <div class="alert alert-info">
                                        <strong>Note:</strong> Debits must equal credits. At least 2 lines are required for a journal entry.
                                    </div>
                                </form>
                            </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveJournalEntry()">Save Entry</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // General Ledger Functions
        function showAddAccountModal() {
            const modalHTML = `
            <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addAccountModalLabel">
                                <i class="fas fa-plus me-2"></i>Add New Account
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addAccountForm">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="accountCode" class="form-label">Account Code *</label>
                                        <input type="text" class="form-control" id="accountCode" placeholder="e.g. 1300" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="accountType" class="form-label">Account Type *</label>
                                        <select class="form-select" id="accountType" required>
                                            <option value="">Select Type</option>
                                            <option value="asset">Asset</option>
                                            <option value="liability">Liability</option>
                                            <option value="equity">Equity</option>
                                            <option value="revenue">Revenue</option>
                                            <option value="expense">Expense</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="accountName" class="form-label">Account Name *</label>
                                    <input type="text" class="form-control" id="accountName" placeholder="e.g. Equipment" required>
                                </div>
                                <div class="mb-3">
                                    <label for="accountDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="accountDescription" rows="3" placeholder="Optional description"></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="accountCategory" class="form-label">Category</label>
                                        <input type="text" class="form-control" id="accountCategory" placeholder="e.g. Current Assets">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="normalBalance" class="form-label">Normal Balance</label>
                                        <select class="form-select" id="normalBalance">
                                            <option value="debit">Debit</option>
                                            <option value="credit">Credit</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveNewAccount()">Create Account</button>
                        </div>
                    </div>
                </div>
            </div>`;

            // Remove existing modal if present
            const existingModal = document.getElementById('addAccountModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('addAccountModal'));
            modal.show();
        }

        function exportTrialBalance() {
            // Show loading state on the export button
            const exportBtn = document.querySelector('#trial .btn-outline-secondary');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
            exportBtn.disabled = true;

            // Make API call to get trial balance data
            const currentDate = new Date().toISOString().split('T')[0];
            fetch(`api/reports.php?type=trial_balance&date_to=${currentDate}&format=csv`, {
                method: 'GET'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to export trial balance');
                }
                return response.text();
            })
            .then(csvContent => {
                // Create and download the file
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);

                link.setAttribute('href', url);
                link.setAttribute('download', `trial_balance_${currentDate}.csv`);
                link.style.visibility = 'hidden';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Show success message
                showAlert('success', 'Trial Balance exported successfully!');
            })
            .catch(error => {
                console.error('Error exporting trial balance:', error);
                showAlert('error', error.message || 'Failed to export trial balance. Please try again.');
            })
            .finally(() => {
                // Restore button state
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            });
        }

        function showFilterModal() {
            const modalHTML = `
            <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="filterModalLabel">
                                <i class="fas fa-filter me-2"></i>Filter Audit Trail
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="auditFilterForm">
                                <div class="mb-3">
                                    <label for="filterDateFrom" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="filterDateFrom">
                                </div>
                                <div class="mb-3">
                                    <label for="filterDateTo" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="filterDateTo">
                                </div>
                                <div class="mb-3">
                                    <label for="filterUser" class="form-label">User</label>
                                    <select class="form-select" id="filterUser">
                                        <option value="">All Users</option>
                                        <option value="Admin">Admin</option>
                                        <option value="Accountant">Accountant</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="filterAction" class="form-label">Action</label>
                                    <select class="form-select" id="filterAction">
                                        <option value="">All Actions</option>
                                        <option value="Created">Created</option>
                                        <option value="Edited">Edited</option>
                                        <option value="Deleted">Deleted</option>
                                        <option value="Generated">Generated</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()" data-bs-dismiss="modal">Clear Filters</button>
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>
            </div>`;

            // Remove existing modal if present
            const existingModal = document.getElementById('filterModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('filterModal'));
            modal.show();
        }

        function saveNewAccount() {
            const form = document.getElementById('addAccountForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Get form values
            const accountCode = document.getElementById('accountCode').value;
            const accountType = document.getElementById('accountType').value;
            const accountName = document.getElementById('accountName').value;
            const accountDescription = document.getElementById('accountDescription').value;
            const accountCategory = document.getElementById('accountCategory').value;
            const normalBalance = document.getElementById('normalBalance').value;

            // Build form data
            const formData = new FormData();
            formData.append('account_code', accountCode);
            formData.append('account_name', accountName);
            formData.append('account_type', accountType);
            if (accountDescription) formData.append('description', accountDescription);
            if (accountCategory) formData.append('account_category', accountCategory);
            if (normalBalance) formData.append('normal_balance', normalBalance);

            // Show loading state
            const button = document.querySelector('#addAccountModal .btn-primary');
            const originalText = button.textContent;
            button.textContent = 'Creating...';
            button.disabled = true;

            // Make API call
            fetch('api/chart_of_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addAccountModal'));
                    modal.hide();

                    // Show success message
                    showAlert('success', 'Account created successfully!');

                    // Refresh the chart of accounts table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.error || 'Failed to create account');
                }
            })
            .catch(error => {
                console.error('Error creating account:', error);
                showAlert('error', error.message || 'Failed to create account. Please try again.');
            })
            .finally(() => {
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function applyFilters() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const user = document.getElementById('filterUser').value;
            const action = document.getElementById('filterAction').value;

            // Build query parameters
            const params = new URLSearchParams();
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (user) params.append('user', user);
            if (action) params.append('action', action);

            // Fetch filtered audit trail
            fetch(`api/audit.php?${params.toString()}`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.audit_trail)) {
                    // Update the audit trail table
                    updateAuditTrailTable(data.audit_trail);
                } else {
                    throw new Error(data.error || 'Failed to fetch filtered audit trail');
                }
            })
            .catch(error => {
                console.error('Error fetching filtered audit trail:', error);
                showAlert('error', error.message || 'Failed to apply filters.');
            });

            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
            modal.hide();
        }

        function clearFilters() {
            // Reset filter form
            document.getElementById('auditFilterForm').reset();

            // Reload original audit trail
            location.reload();
        }

        function updateAuditTrailTable(auditTrail) {
            const tbody = document.querySelector('#audit tbody');

            if (!auditTrail || auditTrail.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle me-2"></i>No audit records found with the applied filters.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = auditTrail.map(log => `
                <tr>
                    <td>${new Date(log.date_time).toLocaleString()}</td>
                    <td>${log.user || 'Unknown'}</td>
                    <td>${log.action || ''}</td>
                    <td>${log.details || ''}</td>
                </tr>
            `).join('');
        }

        function editAccount() {
            // Get account data from the clicked row
            const row = event.target.closest('tr');
            if (!row) return;

            const accountId = row.cells[0].dataset.accountId || row.cells[0].textContent; // Fallback to account code
            const accountCode = row.cells[0].textContent;
            const accountName = row.cells[1].textContent;
            const accountType = row.cells[2].querySelector('.account-type')?.textContent.toLowerCase() || row.cells[2].textContent.toLowerCase();
            const description = row.cells[3].textContent !== 'No description' ? row.cells[3].textContent : '';

            showEditAccountModal(accountId, accountCode, accountName, accountType, description);
        }

        function showEditAccountModal(accountId, accountCode, accountName, accountType, description) {
            const modalHTML = `
            <div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editAccountModalLabel">
                                <i class="fas fa-edit me-2"></i>Edit Account
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editAccountForm">
                                <input type="hidden" id="editAccountId" value="${accountId}">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="editAccountCode" class="form-label">Account Code *</label>
                                        <input type="text" class="form-control" id="editAccountCode" value="${accountCode}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="editAccountType" class="form-label">Account Type *</label>
                                        <select class="form-select" id="editAccountType" required>
                                            <option value="">Select Type</option>
                                            <option value="asset" ${accountType === 'asset' ? 'selected' : ''}>Asset</option>
                                            <option value="liability" ${accountType === 'liability' ? 'selected' : ''}>Liability</option>
                                            <option value="equity" ${accountType === 'equity' ? 'selected' : ''}>Equity</option>
                                            <option value="revenue" ${accountType === 'revenue' ? 'selected' : ''}>Revenue</option>
                                            <option value="expense" ${accountType === 'expense' ? 'selected' : ''}>Expense</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="editAccountName" class="form-label">Account Name *</label>
                                    <input type="text" class="form-control" id="editAccountName" value="${accountName}" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editAccountDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="editAccountDescription" rows="3">${description}</textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="updateAccount()">Update Account</button>
                        </div>
                    </div>
                </div>
            </div>`;

            // Remove existing modal if present
            const existingModal = document.getElementById('editAccountModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editAccountModal'));
            modal.show();
        }

        function updateAccount() {
            const form = document.getElementById('editAccountForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const accountId = document.getElementById('editAccountId').value;
            const accountCode = document.getElementById('editAccountCode').value;
            const accountType = document.getElementById('editAccountType').value;
            const accountName = document.getElementById('editAccountName').value;
            const description = document.getElementById('editAccountDescription').value;

            // Build form data
            const formData = new FormData();
            formData.append('account_code', accountCode);
            formData.append('account_name', accountName);
            formData.append('account_type', accountType);
            formData.append('description', description || '');

            // Show loading state
            const button = document.querySelector('#editAccountModal .btn-primary');
            const originalText = button.textContent;
            button.textContent = 'Updating...';
            button.disabled = true;

            // Make API call
            fetch(`api/chart_of_accounts.php?id=${accountId}`, {
                method: 'PUT',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editAccountModal'));
                    modal.hide();

                    // Show success message
                    showAlert('success', 'Account updated successfully!');

                    // Refresh the chart of accounts table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.error || 'Failed to update account');
                }
            })
            .catch(error => {
                console.error('Error updating account:', error);
                showAlert('error', error.message || 'Failed to update account. Please try again.');
            })
            .finally(() => {
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function deleteEntry(entryReference) {
            if (!confirm('Are you sure you want to delete this journal entry? This action cannot be undone.')) {
                return;
            }

            // Show loading alert
            showAlert('info', 'Deleting journal entry...');

            // First get the journal entry by reference to obtain the ID
            fetch(`api/journal_entries.php?reference=${encodeURIComponent(entryReference)}`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.journal_entry && data.journal_entry.id) {
                    // Now delete using the actual ID
                    return fetch(`api/journal_entries.php?id=${data.journal_entry.id}`, {
                        method: 'DELETE'
                    });
                } else {
                    throw new Error('Journal entry not found');
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Journal entry deleted successfully!');
                    // Refresh the journal entries table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.error || 'Failed to delete journal entry');
                }
            })
            .catch(error => {
                console.error('Error deleting journal entry:', error);
                showAlert('error', error.message || 'Failed to delete journal entry. Please try again.');
            });
        }

        // Utility function to show alerts
        function showAlert(type, message) {
            // Remove any existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" role="alert"
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            // Add to body
            document.body.insertAdjacentHTML('beforeend', alertHTML);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alertElement = document.querySelector('.alert');
                if (alertElement) {
                    alertElement.remove();
                }
            }, 5000);
        }

        // Journal Entry Functions
        function addJournalLine() {
            const journalLines = document.getElementById('journalLines');
            const lineCount = journalLines.querySelectorAll('.journal-line').length;

            if (lineCount >= 10) {
                showAlert('error', 'Maximum 10 journal lines allowed');
                return;
            }

            const newLineHTML = `
                <div class="journal-line border rounded p-3 mb-3 bg-light">
                    <div class="row mb-2">
                        <div class="col-md-5">
                            <label class="form-label">Account *</label>
                            <select class="form-select account-select" required>
                                <option value="">Select Account</option>
                                <option value="loading">Loading accounts...</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Debit</label>
                            <input type="number" class="form-control debit-amount" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Credit</label>
                            <input type="number" class="form-control credit-amount" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <input type="text" class="form-control" placeholder="Line description (optional)">
                            <button type="button" class="btn btn-outline-danger ms-2" onclick="removeJournalLine(this)" title="Remove line">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            journalLines.insertAdjacentHTML('beforeend', newLineHTML);

            // Load accounts for the new dropdown
            const newSelects = journalLines.querySelectorAll('.journal-line:last-child .account-select');
            if (newSelects.length > 0) {
                loadAccountsForSelect(newSelects[0]);
            }
        }

        function removeJournalLine(button) {
            const journalLine = button.closest('.journal-line');
            const journalLines = document.getElementById('journalLines');
            const lineCount = journalLines.querySelectorAll('.journal-line').length;

            if (lineCount <= 2) {
                showAlert('error', 'At least 2 journal lines are required');
                return;
            }

            journalLine.remove();
        }

        function loadAccountsForModal() {
            const accountSelects = document.querySelectorAll('.account-select');

            accountSelects.forEach(select => {
                loadAccountsForSelect(select);
            });
        }

        function loadAccountsForSelect(select) {
            fetch('api/chart_of_accounts.php', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data && Array.isArray(data)) {
                    // Clear existing options except the first one
                    select.innerHTML = '<option value="">Select Account</option>';

                    // Add account options
                    data.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = `${account.account_code} - ${account.account_name}`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading accounts:', error);
                select.innerHTML = '<option value="">Error loading accounts</option>';
            });
        }

        function validateJournalEntry() {
            const lines = document.querySelectorAll('.journal-line');
            if (lines.length < 2) {
                return { valid: false, message: 'At least 2 journal lines are required' };
            }

            let totalDebit = 0;
            let totalCredit = 0;
            let hasEmptyAccount = false;
            let hasEmptyAmounts = false;

            lines.forEach((line, index) => {
                const accountSelect = line.querySelector('.account-select');
                const debitInput = line.querySelector('.debit-amount');
                const creditInput = line.querySelector('.credit-amount');

                // Check account selection
                if (!accountSelect.value) {
                    hasEmptyAccount = true;
                    accountSelect.classList.add('is-invalid');
                } else {
                    accountSelect.classList.remove('is-invalid');
                }

                // Check amounts
                const debit = parseFloat(debitInput.value) || 0;
                const credit = parseFloat(creditInput.value) || 0;

                if (debit > 0 && credit > 0) {
                    debitInput.classList.add('is-invalid');
                    creditInput.classList.add('is-invalid');
                    hasEmptyAmounts = true;
                } else if (debit === 0 && credit === 0) {
                    debitInput.classList.add('is-invalid');
                    creditInput.classList.add('is-invalid');
                    hasEmptyAmounts = true;
                } else {
                    debitInput.classList.remove('is-invalid');
                    creditInput.classList.remove('is-invalid');
                }

                totalDebit += debit;
                totalCredit += credit;
            });

            if (hasEmptyAccount) {
                return { valid: false, message: 'Please select an account for all lines' };
            }

            if (hasEmptyAmounts) {
                return { valid: false, message: 'Each line must have either debit or credit amount, but not both' };
            }

            const difference = Math.abs(totalDebit - totalCredit);
            if (difference > 0.01) {
                return { valid: false, message: `Debits must equal credits. Current difference: ₱${difference.toFixed(2)}` };
            }

            return { valid: true };
        }

        function saveJournalEntry() {
            const form = document.getElementById('journalEntryForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Validate journal entry rules
            const validation = validateJournalEntry();
            if (!validation.valid) {
                showAlert('error', validation.message);
                return;
            }

            // Collect journal entry data
            const entryDate = document.getElementById('entryDate').value;
            const description = document.getElementById('description').value;

            const lines = document.querySelectorAll('.journal-line');
            const journalLines = [];

            lines.forEach(line => {
                const accountId = line.querySelector('.account-select').value;
                const debit = parseFloat(line.querySelector('.debit-amount').value) || 0;
                const credit = parseFloat(line.querySelector('.credit-amount').value) || 0;
                const lineDescription = line.querySelector('input[type="text"]').value || '';

                journalLines.push({
                    account_id: accountId,
                    debit: debit,
                    credit: credit,
                    description: lineDescription
                });
            });

            const journalData = {
                entry_date: entryDate,
                description: description,
                lines: journalLines
            };

            // Show loading state
            const button = document.querySelector('#addJournalModal .btn-primary');
            const originalText = button.textContent;
            button.textContent = 'Saving...';
            button.disabled = true;

            // Make API call
            fetch('api/journal_entries.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(journalData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addJournalModal'));
                    modal.hide();

                    // Show success message
                    showAlert('success', `Journal entry ${data.entry_number || ''} saved successfully!`);

                    // Refresh the journal entries table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.error || 'Failed to save journal entry');
                }
            })
            .catch(error => {
                console.error('Error saving journal entry:', error);
                showAlert('error', error.message || 'Failed to save journal entry. Please try again.');
            })
            .finally(() => {
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function editEntry(entryReference) {
            // Fetch journal entry data
            fetch(`api/journal_entries.php?reference=${encodeURIComponent(entryReference)}`, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.journal_entry) {
                    showEditJournalEntryModal(data.journal_entry);
                } else {
                    throw new Error(data.error || data.journal_entry || 'Failed to load journal entry');
                }
            })
            .catch(error => {
                console.error('Error loading journal entry:', error);
                showAlert('error', error.message || 'Failed to load journal entry for editing.');
            });
        }

        function showEditJournalEntryModal(journalEntry) {
            const modalHTML = `
            <div class="modal fade" id="editJournalEntryModal" tabindex="-1" aria-labelledby="editJournalEntryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editJournalEntryModalLabel">Edit Journal Entry - ${journalEntry.entry_number}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editJournalEntryForm">
                                <input type="hidden" id="editJournalEntryId" value="${journalEntry.id}">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="editEntryDate" class="form-label">Date *</label>
                                        <input type="date" class="form-control" id="editEntryDate" value="${journalEntry.entry_date}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="editReference" class="form-label">Reference</label>
                                        <input type="text" class="form-control" id="editReference" value="${journalEntry.entry_number}" readonly>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="editDescription" class="form-label">Description *</label>
                                    <input type="text" class="form-control" id="editDescription" value="${journalEntry.description}" required>
                                </div>

                                <div id="editJournalLines" class="mb-3">
                                    <h6 class="mb-3">Journal Lines</h6>
                                    ${journalEntry.lines.map((line, index) => `
                                        <div class="journal-line border rounded p-3 mb-3 bg-light">
                                            <div class="row mb-2">
                                                <div class="col-md-5">
                                                    <label class="form-label">Account *</label>
                                                    <select class="form-select account-select" required>
                                                        <option value="">Select Account</option>
                                                        <option value="loading">Loading accounts...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Debit</label>
                                                    <input type="number" class="form-control debit-amount" step="0.01" placeholder="0.00" value="${line.debit || ''}">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Credit</label>
                                                    <input type="number" class="form-control credit-amount" step="0.01" placeholder="0.00" value="${line.credit || ''}">
                                                </div>
                                                <div class="col-md-3 d-flex align-items-end">
                                                    <input type="text" class="form-control" placeholder="Line description (optional)" value="${line.description || ''}">
                                                    ${index >= 2 ? `<button type="button" class="btn btn-outline-danger ms-2" onclick="removeJournalLine(this)" title="Remove line">
                                                        <i class="fas fa-trash"></i>
                                                    </button>` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>

                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="addJournalLine()">
                                        <i class="fas fa-plus me-2"></i>Add Another Line
                                    </button>
                                </div>

                                <div class="alert alert-info">
                                    <strong>Note:</strong> Debits must equal credits. At least 2 lines are required for a journal entry.
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="updateJournalEntry()">Update Entry</button>
                        </div>
                    </div>
                </div>
            </div>`;

            // Remove existing modal if present
            const existingModal = document.getElementById('editJournalEntryModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editJournalEntryModal'));
            modal.show();

            // Load accounts and set selected values after modal is shown
            setTimeout(() => {
                loadAccountsForModal();

                // Set the selected accounts and values after accounts are loaded
                const accountSelects = document.querySelectorAll('#editJournalLines .account-select');
                journalEntry.lines.forEach((line, index) => {
                    if (accountSelects[index]) {
                        accountSelects[index].dataset.selectedValue = line.account_id;
                        // Wait a bit for accounts to load, then set the value
                        setTimeout(() => {
                            accountSelects[index].value = line.account_id;
                        }, 500);
                    }
                });
            }, 100);
        }

        function updateJournalEntry() {
            const form = document.getElementById('editJournalEntryForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Validate journal entry rules
            const validation = validateEditJournalEntry();
            if (!validation.valid) {
                showAlert('error', validation.message);
                return;
            }

            // Collect journal entry data
            const journalEntryId = document.getElementById('editJournalEntryId').value;
            const entryDate = document.getElementById('editEntryDate').value;
            const description = document.getElementById('editDescription').value;

            const lines = document.querySelectorAll('#editJournalLines .journal-line');
            const journalLines = [];

            lines.forEach(line => {
                const accountId = line.querySelector('.account-select').value;
                const debit = parseFloat(line.querySelector('.debit-amount').value) || 0;
                const credit = parseFloat(line.querySelector('.credit-amount').value) || 0;
                const lineDescription = line.querySelector('input[type="text"]').value || '';

                journalLines.push({
                    account_id: accountId,
                    debit: debit,
                    credit: credit,
                    description: lineDescription
                });
            });

            const journalData = {
                entry_date: entryDate,
                description: description,
                lines: journalLines
            };

            // Show loading state
            const button = document.querySelector('#editJournalEntryModal .btn-primary');
            const originalText = button.textContent;
            button.textContent = 'Updating...';
            button.disabled = true;

            // Make API call
            fetch(`api/journal_entries.php?id=${journalEntryId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(journalData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editJournalEntryModal'));
                    modal.hide();

                    // Show success message
                    showAlert('success', 'Journal entry updated successfully!');

                    // Refresh the journal entries table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.error || 'Failed to update journal entry');
                }
            })
            .catch(error => {
                console.error('Error updating journal entry:', error);
                showAlert('error', error.message || 'Failed to update journal entry. Please try again.');
            })
            .finally(() => {
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function validateEditJournalEntry() {
            const lines = document.querySelectorAll('#editJournalLines .journal-line');
            if (lines.length < 2) {
                return { valid: false, message: 'At least 2 journal lines are required' };
            }

            let totalDebit = 0;
            let totalCredit = 0;
            let hasEmptyAccount = false;
            let hasEmptyAmounts = false;

            lines.forEach((line, index) => {
                const accountSelect = line.querySelector('.account-select');
                const debitInput = line.querySelector('.debit-amount');
                const creditInput = line.querySelector('.credit-amount');

                // Check account selection
                if (!accountSelect.value) {
                    hasEmptyAccount = true;
                    accountSelect.classList.add('is-invalid');
                } else {
                    accountSelect.classList.remove('is-invalid');
                }

                // Check amounts
                const debit = parseFloat(debitInput.value) || 0;
                const credit = parseFloat(creditInput.value) || 0;

                if (debit > 0 && credit > 0) {
                    debitInput.classList.add('is-invalid');
                    creditInput.classList.add('is-invalid');
                    hasEmptyAmounts = true;
                } else if (debit === 0 && credit === 0) {
                    debitInput.classList.add('is-invalid');
                    creditInput.classList.add('is-invalid');
                    hasEmptyAmounts = true;
                } else {
                    debitInput.classList.remove('is-invalid');
                    creditInput.classList.remove('is-invalid');
                }

                totalDebit += debit;
                totalCredit += credit;
            });

            if (hasEmptyAccount) {
                return { valid: false, message: 'Please select an account for all lines' };
            }

            if (hasEmptyAmounts) {
                return { valid: false, message: 'Each line must have either debit or credit amount, but not both' };
            }

            const difference = Math.abs(totalDebit - totalCredit);
            if (difference > 0.01) {
                return { valid: false, message: `Debits must equal credits. Current difference: ₱${difference.toFixed(2)}` };
            }

            return { valid: true };
        }

        // Add event listener for journal modal when opened
        document.getElementById('addJournalModal').addEventListener('shown.bs.modal', function() {
            loadAccountsForModal();
            generateReferenceNumber();
        });

        function generateReferenceNumber() {
            // Generate a simple reference number
            const now = new Date();
            const year = now.getFullYear();
            const month = (now.getMonth() + 1).toString().padStart(2, '0');
            const day = now.getDate().toString().padStart(2, '0');
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');

            document.getElementById('reference').value = `JE-${year}${month}${day}-${random}`;
        }

        // Add input event listeners to journal lines for real-time validation
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('debit-amount') || e.target.classList.contains('credit-amount')) {
                // Clear previous validation
                e.target.classList.remove('is-invalid');

                // Ensure only one amount per line
                const line = e.target.closest('.journal-line');
                const debitInput = line.querySelector('.debit-amount');
                const creditInput = line.querySelector('.credit-amount');

                if (e.target === debitInput && parseFloat(debitInput.value) > 0) {
                    creditInput.value = '';
                } else if (e.target === creditInput && parseFloat(creditInput.value) > 0) {
                    debitInput.value = '';
                }
            }
        });

        // Make table action buttons functional
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for edit buttons in account table
            const editButtons = document.querySelectorAll('#coa table button[class*="btn-outline-primary"]');
            editButtons.forEach((button, index) => {
                button.onclick = function() {
                    // Get account code from the row
                    const row = this.closest('tr');
                    const accountCode = row.cells[0].textContent;
                    editAccount(accountCode);
                };
            });

            // Add click handlers for action buttons in journal table
            const journalButtons = document.querySelectorAll('#journal table button');
            journalButtons.forEach(button => {
                const action = button.textContent.trim().toLowerCase();
                const row = button.closest('tr');
                const reference = row.cells[1].textContent;

                if (action === 'edit') {
                    button.onclick = function() {
                        editEntry(reference);
                    };
                } else if (action === 'delete') {
                    button.onclick = function() {
                        deleteEntry(reference);
                    };
                }
            });
        });

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

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
