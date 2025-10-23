<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch summary data
try {
    // Total accounts
    $totalAccounts = $db->query("SELECT COUNT(*) as count FROM chart_of_accounts WHERE status = 'active'")->fetch()['count'];

    // Total journal entries
    $totalEntries = $db->query("SELECT COUNT(*) as count FROM journal_entries")->fetch()['count'];

    // Total assets
    $totalAssets = $db->query("
        SELECT COALESCE(SUM(balance), 0) as total
        FROM chart_of_accounts
        WHERE account_type = 'asset' AND status = 'active'
    ")->fetch()['total'];

    // Net profit calculation (simplified)
    $totalRevenue = $db->query("
        SELECT COALESCE(SUM(balance), 0) as total
        FROM chart_of_accounts
        WHERE account_type = 'revenue' AND status = 'active'
    ")->fetch()['total'];

    $totalExpenses = $db->query("
        SELECT COALESCE(SUM(balance), 0) as total
        FROM chart_of_accounts
        WHERE account_type = 'expense' AND status = 'active'
    ")->fetch()['total'];

    $netProfit = $totalRevenue - $totalExpenses;

} catch (Exception $e) {
    $totalAccounts = 0;
    $totalEntries = 0;
    $totalAssets = 0;
    $netProfit = 0;
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
            <a class="nav-link" href="dashboard.php">
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
                            <span><strong>User</strong></span>
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
                                    <button class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Account</button>
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
                                            <tr>
                                                <td>1000</td>
                                                <td>Cash</td>
                                                <td><span class="account-type asset">Asset</span></td>
                                                <td>Cash on hand and in bank</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                                            </tr>
                                            <tr>
                                                <td>1100</td>
                                                <td>Accounts Receivable</td>
                                                <td><span class="account-type asset">Asset</span></td>
                                                <td>Money owed by customers</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                                            </tr>
                                            <tr>
                                                <td>1200</td>
                                                <td>Office Supplies</td>
                                                <td><span class="account-type asset">Asset</span></td>
                                                <td>Supplies for office use</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                                            </tr>
                                            <tr>
                                                <td>3000</td>
                                                <td>Capital</td>
                                                <td><span class="account-type equity">Equity</span></td>
                                                <td>Owner's equity in the business</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                                            </tr>
                                            <tr>
                                                <td>4000</td>
                                                <td>Service Revenue</td>
                                                <td><span class="account-type revenue">Revenue</span></td>
                                                <td>Income from services</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                                            </tr>
                                            <tr>
                                                <td>5000</td>
                                                <td>Rent Expense</td>
                                                <td><span class="account-type expense">Expense</span></td>
                                                <td>Monthly rent payments</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                                            </tr>
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
                                            <tr>
                                                <td>2025-09-25</td>
                                                <td>JE001</td>
                                                <td>Cash</td>
                                                <td>Initial deposit</td>
                                                <td>₱10,000.00</td>
                                                <td>-</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button> <button class="btn btn-sm btn-outline-danger">Delete</button></td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-24</td>
                                                <td>JE002</td>
                                                <td>Office Supplies</td>
                                                <td>Purchase of supplies</td>
                                                <td>-</td>
                                                <td>₱2,500.00</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button> <button class="btn btn-sm btn-outline-danger">Delete</button></td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-23</td>
                                                <td>JE003</td>
                                                <td>Service Revenue</td>
                                                <td>Service income</td>
                                                <td>₱15,000.00</td>
                                                <td>-</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button> <button class="btn btn-sm btn-outline-danger">Delete</button></td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-22</td>
                                                <td>JE004</td>
                                                <td>Rent Expense</td>
                                                <td>Monthly rent</td>
                                                <td>-</td>
                                                <td>₱5,000.00</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button> <button class="btn btn-sm btn-outline-danger">Delete</button></td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-21</td>
                                                <td>JE005</td>
                                                <td>Accounts Receivable</td>
                                                <td>Invoice payment</td>
                                                <td>₱8,000.00</td>
                                                <td>-</td>
                                                <td><button class="btn btn-sm btn-outline-primary">Edit</button> <button class="btn btn-sm btn-outline-danger">Delete</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Trial Balance Tab -->
                            <div class="tab-pane fade" id="trial" role="tabpanel" aria-labelledby="trial-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Trial Balance Report</h6>
                                    <button class="btn btn-outline-secondary"><i class="fas fa-download me-2"></i>Export</button>
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
                                            <tr>
                                                <td>Cash</td>
                                                <td>₱10,000.00</td>
                                                <td>-</td>
                                            </tr>
                                            <tr>
                                                <td>Accounts Receivable</td>
                                                <td>₱8,000.00</td>
                                                <td>-</td>
                                            </tr>
                                            <tr>
                                                <td>Office Supplies</td>
                                                <td>-</td>
                                                <td>₱2,500.00</td>
                                            </tr>
                                            <tr>
                                                <td>Capital</td>
                                                <td>-</td>
                                                <td>₱10,000.00</td>
                                            </tr>
                                            <tr>
                                                <td>Service Revenue</td>
                                                <td>-</td>
                                                <td>₱15,000.00</td>
                                            </tr>
                                            <tr>
                                                <td>Rent Expense</td>
                                                <td>₱5,000.00</td>
                                                <td>-</td>
                                            </tr>
                                            <tr class="table-dark">
                                                <td><strong>Total</strong></td>
                                                <td><strong>₱23,000.00</strong></td>
                                                <td><strong>₱27,500.00</strong></td>
                                            </tr>
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
                                        <h6>Balance Sheet - As of September 25, 2025</h6>
                                        <table class="table financial-table">
                                            <tr><th>Assets</th><th></th><th>₱18,000.00</th></tr>
                                            <tr><td>&nbsp;&nbsp;Cash</td><td></td><td>₱10,000.00</td></tr>
                                            <tr><td>&nbsp;&nbsp;Accounts Receivable</td><td></td><td>₱8,000.00</td></tr>
                                            <tr><th>Liabilities</th><th></th><th>₱0.00</th></tr>
                                            <tr><th>Equity</th><th></th><th>₱18,000.00</th></tr>
                                            <tr><td>&nbsp;&nbsp;Capital</td><td></td><td>₱10,000.00</td></tr>
                                            <tr class="total-row"><td>&nbsp;&nbsp;Retained Earnings</td><td></td><td>₱8,000.00</td></tr>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="income-statement" role="tabpanel">
                                        <h6>Income Statement - For the period ending September 25, 2025</h6>
                                        <table class="table financial-table">
                                            <tr><th>Revenue</th><th></th><th>₱15,000.00</th></tr>
                                            <tr><td>&nbsp;&nbsp;Service Revenue</td><td></td><td>₱15,000.00</td></tr>
                                            <tr><th>Expenses</th><th></th><th>₱7,500.00</th></tr>
                                            <tr><td>&nbsp;&nbsp;Rent Expense</td><td></td><td>₱5,000.00</td></tr>
                                            <tr><td>&nbsp;&nbsp;Office Supplies</td><td></td><td>₱2,500.00</td></tr>
                                            <tr class="total-row"><th>Net Profit</th><th></th><th>₱7,500.00</th></tr>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="cash-flow" role="tabpanel">
                                        <h6>Cash Flow Statement - For the period ending September 25, 2025</h6>
                                        <table class="table financial-table">
                                            <tr><th>Operating Activities</th><th></th><th>₱10,000.00</th></tr>
                                            <tr><td>&nbsp;&nbsp;Net Income</td><td></td><td>₱7,500.00</td></tr>
                                            <tr><td>&nbsp;&nbsp;Adjustments</td><td></td><td>₱2,500.00</td></tr>
                                            <tr><th>Investing Activities</th><th></th><th>₱0.00</th></tr>
                                            <tr><th>Financing Activities</th><th></th><th>₱0.00</th></tr>
                                            <tr class="total-row"><th>Net Cash Flow</th><th></th><th>₱10,000.00</th></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Audit Trail Tab -->
                            <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">User Activity Logs</h6>
                                    <button class="btn btn-outline-secondary"><i class="fas fa-filter me-2"></i>Filter</button>
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
                                            <tr>
                                                <td>2025-09-25 10:00 AM</td>
                                                <td>Admin</td>
                                                <td>Created Journal Entry</td>
                                                <td>JE001 - Initial deposit</td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-24 2:30 PM</td>
                                                <td>Accountant</td>
                                                <td>Edited Account</td>
                                                <td>Updated Office Supplies description</td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-23 9:15 AM</td>
                                                <td>Admin</td>
                                                <td>Generated Report</td>
                                                <td>Trial Balance for September</td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-22 4:45 PM</td>
                                                <td>Accountant</td>
                                                <td>Deleted Journal Entry</td>
                                                <td>Removed duplicate entry JE006</td>
                                            </tr>
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
                        <form>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="entryDate" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="entryDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="reference" class="form-label">Reference</label>
                                    <input type="text" class="form-control" id="reference" placeholder="JE001" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="description" placeholder="Transaction description" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="account" class="form-label">Account</label>
                                    <select class="form-select" id="account" required>
                                        <option value="">Select Account</option>
                                        <option value="1000">Cash</option>
                                        <option value="1100">Accounts Receivable</option>
                                        <option value="1200">Office Supplies</option>
                                        <option value="3000">Capital</option>
                                        <option value="4000">Service Revenue</option>
                                        <option value="5000">Rent Expense</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="debit" class="form-label">Debit</label>
                                    <input type="number" class="form-control" id="debit" step="0.01" placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label for="credit" class="form-label">Credit</label>
                                    <input type="number" class="form-control" id="credit" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary">Save Entry</button>
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

        function changeModule() {
            const select = document.getElementById('moduleSelect');
            const value = select.value;

            document.getElementById('ledgerContent').style.display = value === 'ledger' ? 'block' : 'none';
            document.getElementById('payableContent').style.display = value === 'payable' ? 'block' : 'none';
            document.getElementById('receivableContent').style.display = value === 'receivable' ? 'block' : 'none';
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

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
