<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Reports</title>
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

        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
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

        /* Reports Cards Enhancement */
        .reports-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .reports-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #1e2936, #2c3e50);
        }

        .reports-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .reports-card h3 {
            color: #1e2936;
            font-weight: 800;
            font-size: 2rem;
            margin: 0.5rem 0;
        }

        .reports-card h6 {
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(30, 41, 54, 0.3);
            border-radius: 50%;
            border-top-color: #1e2936;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Enhanced Responsive Design */
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

            .nav-tabs {
                flex-direction: column;
                padding: 0.25rem;
            }

            .nav-tabs .nav-link {
                margin-right: 0;
                margin-bottom: 0.25rem;
                text-align: center;
            }

            .card-body {
                padding: 1rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .reports-card {
                margin-bottom: 1rem;
            }
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-paid .status-dot { background-color: #28a745; }
        .status-overdue .status-dot { background-color: #dc3545; }
        .status-sent .status-dot { background-color: #ffc107; }
        .status-draft .status-dot { background-color: #6c757d; }

        /* Enhanced Footer */
        .footer-enhanced {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 3px solid #1e2936;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
        }

        /* Financial Statement Styles */
        .financial-statement {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .statement-header {
            border-bottom: 2px solid #1e2936;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .statement-title {
            color: #1e2936;
            font-weight: 800;
            font-size: 1.5rem;
            margin: 0;
        }

        .statement-period {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0.5rem 0 0 0;
        }

        .account-category {
            background: #f8f9fa;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 6px;
            border-left: 4px solid #1e2936;
        }

        .account-category h6 {
            color: #1e2936;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .account-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .account-item:last-child {
            border-bottom: none;
        }

        .account-name {
            font-weight: 500;
            color: #495057;
        }

        .account-amount {
            font-weight: 600;
            color: #1e2936;
        }

        .total-row {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 1rem;
        }

        .total-row .account-name,
        .total-row .account-amount {
            color: white;
        }

        .positive-amount {
            color: #28a745 !important;
        }

        .negative-amount {
            color: #dc3545 !important;
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
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
            <a class="nav-link" href="disbursements.php">
                <i class="fas fa-money-check me-2"></i><span>Disbursements</span>
            </a>
            <a class="nav-link" href="budget_management.php">
                <i class="fas fa-chart-line me-2"></i><span>Budget Management</span>
            </a>
            <a class="nav-link active" href="reports.php">
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
                <span class="navbar-brand mb-0 h1 me-4">Reports</span>
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

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="reportsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="income-tab" data-bs-toggle="tab" data-bs-target="#income" type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i>Profit & Loss
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="balance-tab" data-bs-toggle="tab" data-bs-target="#balance" type="button" role="tab">
                    <i class="fas fa-balance-scale me-2"></i>Balance Sheet
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cashflow-tab" data-bs-toggle="tab" data-bs-target="#cashflow" type="button" role="tab">
                    <i class="fas fa-money-bill-wave me-2"></i>Cash Flow
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                    <i class="fas fa-chart-bar me-2"></i>Analytics & Export
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="integration-tab" data-bs-toggle="tab" data-bs-target="#integration" type="button" role="tab">
                    <i class="fas fa-link me-2"></i>Integration
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="reportsTabContent">
            <!-- Profit & Loss Statement Tab -->
            <div class="tab-pane fade show active" id="income" role="tabpanel" aria-labelledby="income-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Profit & Loss Statement (Income Statement)</h6>
                    <div>
                        <select class="form-select form-select-sm me-2" id="incomePeriodSelect" style="width: auto;" onchange="updateIncomeStatementPeriod()">
                            <option value="current_month">Current Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="year_to_date">Year to Date</option>
                            <option value="custom">Custom Range</option>
                        </select>
                        <button class="btn btn-outline-secondary me-2" onclick="exportIncomeStatement('csv')"><i class="fas fa-download me-2"></i>Export CSV</button>
                        <button class="btn btn-primary" onclick="generateIncomeStatement()"><i class="fas fa-sync me-2"></i>Generate Report</button>
                    </div>
                </div>

                <!-- Custom Date Range (Hidden by default) -->
                <div id="incomeCustomRange" class="card mb-3" style="display: none;">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="incomeFromDate">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="incomeToDate">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button class="btn btn-primary w-100" onclick="generateIncomeStatement()">Apply Custom Range</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="financial-statement" id="incomeStatementContainer">
                            <div class="statement-header">
                                <h1 class="statement-title">Profit & Loss Statement</h1>
                                <p class="statement-period" id="incomeStatementPeriod">Loading...</p>
                            </div>
                            <div class="text-center py-5">
                                <div class="loading mb-3"></div>
                                <p class="text-muted">Generating income statement...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Sheet Tab -->
            <div class="tab-pane fade" id="balance" role="tabpanel" aria-labelledby="balance-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Balance Sheet</h6>
                    <div>
                        <select class="form-select form-select-sm me-2" style="width: auto;" id="balanceDateSelect" onchange="updateBalanceSheet()">
                            <option value="current">Current Date</option>
                            <option value="last_month">Last Month</option>
                            <option value="last_quarter">Last Quarter</option>
                        </select>
                        <button class="btn btn-outline-secondary me-2" onclick="exportBalanceSheet('csv')"><i class="fas fa-download me-2"></i>Export CSV</button>
                        <button class="btn btn-primary" onclick="generateBalanceSheet()"><i class="fas fa-sync me-2"></i>Generate Report</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="financial-statement" id="balanceSheetContainer">
                            <div class="statement-header">
                                <h1 class="statement-title">Balance Sheet</h1>
                                <p class="statement-period">Loading...</p>
                            </div>
                            <div class="text-center py-5">
                                <div class="loading mb-3"></div>
                                <p class="text-muted">Generating balance sheet...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Flow Statement Tab -->
            <div class="tab-pane fade" id="cashflow" role="tabpanel" aria-labelledby="cashflow-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Cash Flow Statement</h6>
                    <div>
                        <select class="form-select form-select-sm me-2" style="width: auto;" id="cashFlowPeriodSelect">
                            <option value="last_quarter">Last Quarter</option>
                            <option value="last_6_months">Last 6 Months</option>
                            <option value="year_to_date">Year to Date</option>
                        </select>
                        <button class="btn btn-outline-secondary me-2" onclick="exportCashFlow('csv')"><i class="fas fa-download me-2"></i>Export CSV</button>
                        <button class="btn btn-primary" onclick="generateCashFlow()"><i class="fas fa-sync me-2"></i>Generate Report</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="financial-statement" id="cashFlowContainer">
                            <div class="statement-header">
                                <h1 class="statement-title">Cash Flow Statement</h1>
                                <p class="statement-period">Loading...</p>
                            </div>
                            <div class="text-center py-5">
                                <div class="loading mb-3"></div>
                                <p class="text-muted">Generating cash flow statement...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics & Export Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel" aria-labelledby="analytics-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Analytics & Export Features</h6>
                    <div>
                        <button class="btn btn-outline-secondary me-2" onclick="viewCharts()"><i class="fas fa-chart-line me-2"></i>View Charts</button>
                        <button class="btn btn-primary" onclick="exportAllReports()"><i class="fas fa-download me-2"></i>Export All</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-chart-bar fa-3x mb-4 text-muted"></i>
                                <h4>No Analytics Data Available</h4>
                                <p class="text-muted">Reports and analytics will be populated once the system has been in use.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6>Report Generation Trends</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="reportTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6>Export Options</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-danger" onclick="exportCurrentReportPDF()">
                                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                                    </button>
                                    <button class="btn btn-outline-success" onclick="exportCurrentReportExcel()">
                                        <i class="fas fa-file-excel me-2"></i>Export as Excel
                                    </button>
                                    <button class="btn btn-outline-info" onclick="printCurrentReport()">
                                        <i class="fas fa-print me-2"></i>Print Report
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="emailCurrentReport()">
                                        <i class="fas fa-envelope me-2"></i>Email Report
                                    </button>
                                </div>
                                <hr>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeCharts">
                                    <label class="form-check-label" for="includeCharts">
                                        Include charts in export
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeDetails">
                                    <label class="form-check-label" for="includeDetails">
                                        Include detailed breakdowns
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integration Tab -->
            <div class="tab-pane fade" id="integration" role="tabpanel" aria-labelledby="integration-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Module Integration</h6>
                    <button class="btn btn-outline-secondary"><i class="fas fa-sync me-2"></i>Sync All Data</button>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Data Sources for Reports</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                            <h6>General Ledger</h6>
                                            <p class="text-muted small">Master financial postings and account balances</p>
                                            <span class="badge bg-success">Primary Source</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-credit-card fa-3x text-success mb-3"></i>
                                            <h6>Accounts Payable</h6>
                                            <p class="text-muted small">Vendor invoices and payment obligations</p>
                                            <span class="badge bg-success">Connected</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-money-bill-wave fa-3x text-info mb-3"></i>
                                            <h6>Accounts Receivable</h6>
                                            <p class="text-muted small">Customer invoices and collections</p>
                                            <span class="badge bg-success">Connected</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-money-check fa-3x text-warning mb-3"></i>
                                            <h6>Disbursements</h6>
                                            <p class="text-muted small">Cash payments and expenditures</p>
                                            <span class="badge bg-success">Connected</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-chart-line fa-3x text-secondary mb-3"></i>
                                            <h6>Budget Management</h6>
                                            <p class="text-muted small">Budget planning and variance analysis</p>
                                            <span class="badge bg-success">Connected</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-tachometer-alt fa-3x text-dark mb-3"></i>
                                            <h6>Dashboard</h6>
                                            <p class="text-muted small">Real-time metrics and KPIs</p>
                                            <span class="badge bg-success">Connected</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Data Synchronization</h6>
                            </div>
                            <div class="card-body text-center py-5">
                                <i class="fas fa-sync-alt fa-3x mb-4 text-muted"></i>
                                <h5>No Sync History Available</h5>
                                <p class="text-muted small">Sync status will be displayed once integrations are active and data syncing starts.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Report Users & Access</h6>
                            </div>
                            <div class="card-body text-center py-5">
                                <i class="fas fa-users fa-3x mb-4 text-muted"></i>
                                <h5>No User Activity Data</h5>
                                <p class="text-muted small">User access and permissions will be displayed once the system is in active use.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer id="footer" class="py-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-top: 2px solid #1e2936; position: fixed; bottom: 0; left: 120px; width: calc(100% - 120px); z-index: 998; font-weight: 500;">
        <div class="container-fluid">
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-4">
                    <span class="text-muted"><i class="fas fa-shield-alt me-1 text-primary"></i>© 2025 ATIERA Finance — Confidential</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">
                        <span class="badge bg-success me-2">PROD</span> v1.0.0 • Updated: Sep 25, 2025
                        <span class="ms-3 text-success fw-bold"><i class="fas fa-sync-alt me-1"></i>Sync OK</span>
                    </span>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="text-muted">
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link">Terms</a>
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link">Privacy</a>
                        <a href="mailto:support@atiera.com" class="text-decoration-none text-muted hover-link"><i class="fas fa-envelope me-1"></i>Support</a>
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

        // Global variables
        let currentIncomeStatementData = null;
        let currentBalanceSheetData = null;
        let currentCashFlowData = null;

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

            // Load initial income statement
            generateIncomeStatement();

            // Auto-generate reports when tabs are shown
            const balanceTab = document.getElementById('balance-tab');
            const cashflowTab = document.getElementById('cashflow-tab');

            let balanceGenerated = false;
            let cashflowGenerated = false;

            balanceTab.addEventListener('shown.bs.tab', function() {
                if (!balanceGenerated) {
                    generateBalanceSheet();
                    balanceGenerated = true;
                }
            });

            cashflowTab.addEventListener('shown.bs.tab', function() {
                if (!cashflowGenerated) {
                    generateCashFlow();
                    cashflowGenerated = true;
                }
            });
        });

        // Update income statement period selector
        function updateIncomeStatementPeriod() {
            const periodSelect = document.getElementById('incomePeriodSelect');
            const customRange = document.getElementById('incomeCustomRange');

            if (periodSelect.value === 'custom') {
                customRange.style.display = 'block';
            } else {
                customRange.style.display = 'none';
            }
        }

        // Generate income statement
        async function generateIncomeStatement() {
            const container = document.getElementById('incomeStatementContainer');
            const periodElement = document.getElementById('incomeStatementPeriod');

            // Show loading state
            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Profit & Loss Statement</h1>
                    <p class="statement-period">Loading...</p>
                </div>
                <div class="text-center py-5">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating income statement...</p>
                </div>
            `;

            try {
                // Get date range based on selection
                const periodSelect = document.getElementById('incomePeriodSelect');
                let dateFrom, dateTo;

                if (periodSelect.value === 'custom') {
                    dateFrom = document.getElementById('incomeFromDate').value;
                    dateTo = document.getElementById('incomeToDate').value;
                } else {
                    const dates = getDateRange(periodSelect.value);
                    dateFrom = dates.from;
                    dateTo = dates.to;
                }

                // Fetch income statement data
                const response = await fetch(`../api/reports.php?type=income_statement&date_from=${dateFrom}&date_to=${dateTo}`);
                const data = await response.json();

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate income statement');
                }

                // Store data globally for export
                currentIncomeStatementData = data;

                // Render the income statement
                renderIncomeStatement(data);

            } catch (error) {
                console.error('Error generating income statement:', error);
                container.innerHTML = `
                    <div class="statement-header">
                        <h1 class="statement-title">Profit & Loss Statement</h1>
                        <p class="statement-period">Error loading report</p>
                    </div>
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating income statement: ${error.message}
                        </div>
                        <button class="btn btn-primary" onclick="generateIncomeStatement()">Try Again</button>
                    </div>
                `;
            }
        }

        // Render income statement
        function renderIncomeStatement(data) {
            const container = document.getElementById('incomeStatementContainer');

            // Build HTML content
            let html = `
                <div class="statement-header">
                    <h1 class="statement-title">Profit & Loss Statement</h1>
                    <p class="statement-period">For the period ${formatDate(data.date_from)} to ${formatDate(data.date_to)}</p>
                </div>

                <div class="account-category">
                    <h6>Revenue</h6>
            `;

            // Add revenue accounts
            if (data.revenue.accounts && data.revenue.accounts.length > 0) {
                data.revenue.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Total Revenue</strong></span>
                        <span class="account-amount"><strong>₱${parseFloat(data.revenue.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Cost of Goods Sold</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Total COGS</strong></span>
                        <span class="account-amount"><strong>₱0.00</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Gross Profit</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Gross Profit</strong></span>
                        <span class="account-amount positive-amount"><strong>₱${parseFloat(data.revenue.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Operating Expenses</h6>
            `;

            // Add expense accounts
            if (data.expenses.accounts && data.expenses.accounts.length > 0) {
                data.expenses.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Total Operating Expenses</strong></span>
                        <span class="account-amount"><strong>₱${parseFloat(data.expenses.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Net Profit</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Profit</strong></span>
                        <span class="account-amount ${parseFloat(data.net_profit || 0) >= 0 ? 'positive-amount' : 'negative-amount'}"><strong>₱${parseFloat(data.net_profit || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>
            `;

            container.innerHTML = html;
        }

        // Export income statement
        function exportIncomeStatement(format) {
            if (!currentIncomeStatementData) {
                showAlert('Please generate the report first', 'warning');
                return;
            }

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Profit & Loss Statement\n';
            csvContent += `Period: ${currentIncomeStatementData.date_from} to ${currentIncomeStatementData.date_to}\n\n`;

            csvContent += 'Revenue\n';
            csvContent += 'Account,Amount\n';
            if (currentIncomeStatementData.revenue.accounts) {
                currentIncomeStatementData.revenue.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount}"\n`;
                });
            }
            csvContent += `"Total Revenue","${currentIncomeStatementData.revenue.total}"\n\n`;

            csvContent += 'Expenses\n';
            csvContent += 'Account,Amount\n';
            if (currentIncomeStatementData.expenses.accounts) {
                currentIncomeStatementData.expenses.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount}"\n`;
                });
            }
            csvContent += `"Total Expenses","${currentIncomeStatementData.expenses.total}"\n\n`;

            csvContent += `"Net Profit","${currentIncomeStatementData.net_profit}"\n`;

            // Download CSV
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `income_statement_${currentIncomeStatementData.date_from}_to_${currentIncomeStatementData.date_to}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showAlert('Income statement exported successfully', 'success');
        }

        // Get date range based on period
        function getDateRange(period) {
            const now = new Date();
            let from, to;

            switch (period) {
                case 'current_month':
                    from = new Date(now.getFullYear(), now.getMonth(), 1);
                    to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    break;
                case 'last_month':
                    from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    to = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;
                case 'last_quarter':
                    const quarterStart = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3 - 3, 1);
                    const quarterEnd = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 0);
                    from = quarterStart;
                    to = quarterEnd;
                    break;
                case 'year_to_date':
                    from = new Date(now.getFullYear(), 0, 1);
                    to = now;
                    break;
                default:
                    from = new Date(now.getFullYear(), now.getMonth(), 1);
                    to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            }

            return {
                from: from.toISOString().split('T')[0],
                to: to.toISOString().split('T')[0]
            };
        }

        // Format date helper function
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Update balance sheet when period changes
        function updateBalanceSheet() {
            if (typeof generateBalanceSheet === 'function') {
                generateBalanceSheet();
            }
        }

        // Generate balance sheet
        async function generateBalanceSheet() {
            const container = document.getElementById('balanceSheetContainer');
            const dateSelect = document.getElementById('balanceDateSelect');
            const asOfDate = dateSelect ? dateSelect.value : 'current';

            // Show loading state
            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Balance Sheet</h1>
                    <p class="statement-period">Loading...</p>
                </div>
                <div class="text-center py-5">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating balance sheet...</p>
                </div>
            `;

            try {
                // Fetch balance sheet data
                const response = await fetch(`../api/reports.php?type=balance_sheet&as_of_date=${asOfDate}`);
                const data = await response.json();

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate balance sheet');
                }

                // Store data globally for export
                currentBalanceSheetData = data;

                // Render the balance sheet
                renderBalanceSheet(data);

            } catch (error) {
                console.error('Error generating balance sheet:', error);
                container.innerHTML = `
                    <div class="statement-header">
                        <h1 class="statement-title">Balance Sheet</h1>
                        <p class="statement-period">Error loading report</p>
                    </div>
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating balance sheet: ${error.message}
                        </div>
                        <button class="btn btn-primary" onclick="generateBalanceSheet()">Try Again</button>
                    </div>
                `;
            }
        }

        // Render balance sheet
        function renderBalanceSheet(data) {
            const container = document.getElementById('balanceSheetContainer');

            let assetsHtml = '<div class="account-category"><h6>Assets</h6>';
            if (data.assets.accounts && data.assets.accounts.length > 0) {
                data.assets.accounts.forEach(account => {
                    assetsHtml += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.account_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }
            assetsHtml += `
                <div class="account-item total-row">
                    <span class="account-name"><strong>Total Assets</strong></span>
                    <span class="account-amount"><strong>₱${parseFloat(data.assets.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                </div>
            </div>`;

            let liabilitiesHtml = '<div class="account-category"><h6>Liabilities & Equity</h6>';
            if (data.liabilities.accounts && data.liabilities.accounts.length > 0) {
                data.liabilities.accounts.forEach(account => {
                    liabilitiesHtml += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.account_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            if (data.equity.accounts && data.equity.accounts.length > 0) {
                data.equity.accounts.forEach(account => {
                    liabilitiesHtml += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.account_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            liabilitiesHtml += `
                <div class="account-item total-row">
                    <span class="account-name"><strong>Total Liabilities & Equity</strong></span>
                    <span class="account-amount"><strong>₱${parseFloat((data.liabilities.total || 0) + (data.equity.total || 0)).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                </div>
            </div>`;

            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Balance Sheet</h1>
                    <p class="statement-period">As of ${formatDate(data.as_of_date)}</p>
                </div>
                <div class="row">
                    <div class="col-md-6">${assetsHtml}</div>
                    <div class="col-md-6">${liabilitiesHtml}</div>
                </div>
            `;
        }

        // Generate cash flow statement
        async function generateCashFlow() {
            const container = document.getElementById('cashFlowContainer');
            const periodSelect = document.getElementById('cashFlowPeriodSelect');
            const period = periodSelect ? periodSelect.value : 'last_quarter';

            // Show loading state
            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Cash Flow Statement</h1>
                    <p class="statement-period">Loading...</p>
                </div>
                <div class="text-center py-5">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating cash flow statement...</p>
                </div>
            `;

            try {
                // Fetch cash flow data
                const response = await fetch(`../api/reports.php?type=cash_flow&period=${period}`);
                const data = await response.json();

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate cash flow statement');
                }

                // Check if cash_flow data exists
                if (!data.cash_flow) {
                    throw new Error('Invalid response format: missing cash_flow data');
                }

                // Store data globally for export
                currentCashFlowData = data;

                // Render the cash flow statement
                renderCashFlow(data);

            } catch (error) {
                console.error('Error generating cash flow:', error);
                container.innerHTML = `
                    <div class="statement-header">
                        <h1 class="statement-title">Cash Flow Statement</h1>
                        <p class="statement-period">Error loading report</p>
                    </div>
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error: ${error.message}
                        </div>
                        <button class="btn btn-primary" onclick="generateCashFlow()">Try Again</button>
                    </div>
                `;
            }
        }

        // Render cash flow statement
        function renderCashFlow(data) {
            const container = document.getElementById('cashFlowContainer');

            // Check if we have cash_flow data
            if (!data.cash_flow) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error: Invalid cash flow data format. Missing cash flow information.
                        </div>
                    </div>
                `;
                return;
            }

            const operating = data.cash_flow.operating_activities;
            const investing = data.cash_flow.investing_activities;
            const financing = data.cash_flow.financing_activities;

            // Default values if data is missing
            const operatingAmount = operating?.amount || 0;
            const investingAmount = investing?.amount || 0;
            const financingAmount = financing?.amount || 0;
            const netCashFlow = operatingAmount + investingAmount + financingAmount;

            // Check if all activities have the expected structure
            if (!operating || !investing || !financing ||
                typeof operating.amount !== 'number' ||
                typeof investing.amount !== 'number' ||
                typeof financing.amount !== 'number') {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            Cash flow data is incomplete. Some activities may not be available.
                        </div>
                    <div class="text-center py-3">
                        <div class="alert alert-info">
                            Net Cash Flow: ₱${parseFloat(netCashFlow).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                    </div>
                `;
                return; // Exit early for incomplete data
            }

            let html = `
                <div class="statement-header">
                    <h1 class="statement-title">Cash Flow Statement</h1>
                    <p class="statement-period">For the period ${formatDate(data.start_date)} to ${formatDate(data.end_date)}</p>
                </div>

                <div class="account-category">
                    <h6>Operating Activities</h6>
                    <div class="ps-3">
                        <div class="fw-bold text-success mb-2">Cash Inflows (Revenue):</div>
            `;

            // Revenue breakdown
            if (operating.revenue && operating.revenue.length > 0) {
                operating.revenue.forEach(rev => {
                    html += `
                        <div class="account-item ps-3">
                            <span class="account-name">${rev.name}</span>
                            <span class="account-amount text-success">₱${parseFloat(rev.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="account-item ps-3">
                        <span class="account-name text-muted">No revenue recorded</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                        <div class="account-item ps-3 border-bottom pb-2">
                            <span class="account-name"><strong>Total Revenue</strong></span>
                            <span class="account-amount text-success"><strong>₱${parseFloat(operating.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                        </div>

                        <div class="fw-bold text-danger mb-2 mt-3">Cash Outflows (Operating Expenses):</div>
            `;

            // Expense category breakdown
            if (operating.expenses_by_category && operating.expenses_by_category.length > 0) {
                operating.expenses_by_category.forEach(expense => {
                    const sourceBadges = expense.sources ? expense.sources.split(',').map(src => {
                        const badgeClass = src === 'HR_SYSTEM' ? 'bg-primary' : src === 'LOGISTICS1' ? 'bg-warning' : src === 'LOGISTICS2' ? 'bg-info' : 'bg-secondary';
                        return `<span class="badge ${badgeClass} text-xs">${src}</span>`;
                    }).join(' ') : '';

                    html += `
                        <div class="account-item ps-3">
                            <span class="account-name">
                                ${expense.name}
                                ${sourceBadges}
                                <button class="btn btn-sm btn-link text-decoration-none p-0 ms-2"
                                        onclick="toggleExpenseDetail('${expense.subcategory}')"
                                        title="View detailed breakdown">
                                    <i class="fas fa-chevron-down" id="icon-${expense.subcategory}"></i>
                                </button>
                            </span>
                            <span class="account-amount text-danger">₱${parseFloat(expense.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                        <div id="detail-${expense.subcategory}" class="ps-5 d-none" style="background-color: #f8f9fa; margin: 5px 0; padding: 10px; border-radius: 4px;">
                    `;

                    // Show detailed breakdown for this category
                    if (operating.expense_details && operating.expense_details.length > 0) {
                        const categoryDetails = operating.expense_details.filter(d => d.expense_category === expense.subcategory);
                        if (categoryDetails.length > 0) {
                            categoryDetails.forEach(detail => {
                                const sourceLabel = detail.source_system === 'HR_SYSTEM' ? 'HR4 Payroll' :
                                                  detail.source_system === 'LOGISTICS1' ? 'Logistics 1' :
                                                  detail.source_system === 'LOGISTICS2' ? 'Logistics 2' : detail.source_system;
                                html += `
                                    <div class="account-item" style="font-size: 0.9em;">
                                        <span class="account-name text-muted">
                                            ${detail.department || 'General'}
                                            <span class="badge bg-light text-dark">${sourceLabel}</span>
                                            <small>(${detail.transaction_count} transactions)</small>
                                        </span>
                                        <span class="account-amount">₱${parseFloat(detail.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                    </div>
                                `;
                            });
                        }
                    }

                    html += `</div>`;
                });
            } else {
                html += `
                    <div class="account-item ps-3">
                        <span class="account-name text-muted">No operating expenses recorded</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                        <div class="account-item ps-3 border-bottom pb-2">
                            <span class="account-name"><strong>Total Operating Expenses</strong></span>
                            <span class="account-amount text-danger"><strong>₱${parseFloat(operating.total_expenses || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                        </div>
                    </div>

                    <div class="account-item total-row mt-2">
                        <span class="account-name"><strong>Net Cash from Operating Activities</strong></span>
                        <span class="account-amount ${operating.amount >= 0 ? 'text-success' : 'text-danger'}"><strong>₱${parseFloat(operating.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Investing Activities</h6>
            `;

            if (investing.accounts && investing.accounts.length > 0) {
                investing.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="account-item">
                        <span class="account-name">No investing activities</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Cash from Investing Activities</strong></span>
                        <span class="account-amount ${investing.amount < 0 ? 'negative-amount' : ''}"><strong>₱${parseFloat(investing.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Financing Activities</h6>
            `;

            if (financing.accounts && financing.accounts.length > 0) {
                financing.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="account-item">
                        <span class="account-name">No financing activities</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Cash from Financing Activities</strong></span>
                        <span class="account-amount"><strong>₱${parseFloat(financing.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Net Cash Flow & Change in Cash</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Increase/(Decrease) in Cash</strong></span>
                        <span class="account-amount ${netCashFlow >= 0 ? 'positive-amount' : 'negative-amount'}"><strong>₱${parseFloat(netCashFlow).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>
            `;

            container.innerHTML = html;
        }

        // Generate budget report
        async function generateBudgetReport() {
            const container = document.getElementById('budgetVsActualContainer');

            // Show loading state
            container.innerHTML = `
                <div class="text-center py-3">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating budget report...</p>
                </div>
            `;

            try {
                const response = await fetch('../api/reports.php?type=budget_vs_actual');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                // For now, just show a message that budget reporting is not implemented
                container.innerHTML = `
                    <div class="text-center py-3">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Budget vs Actual reporting requires budget data setup first.
                        </div>
                    </div>
                `;

            } catch (error) {
                console.error('Error generating budget report:', error);
                container.innerHTML = `
                    <div class="text-center py-3">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating budget report: ${error.message}
                        </div>
                    </div>
                `;
            }
        }

        // Generate cash flow summary
        async function generateCashFlowSummary() {
            const container = document.getElementById('cashFlowSummaryContainer');

            // Show loading state
            container.innerHTML = `
                <div class="text-center py-3">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating cash flow summary...</p>
                </div>
            `;

            try {
                const response = await fetch('../api/reports.php?type=cash_flow_summary');
                const data = await response.json();

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate cash flow summary');
                }

                const summary = data.summary;
                container.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <span>Payroll Expenses (30 days)</span>
                        <span class="text-danger">-₱${parseFloat(summary.payroll_expenses || 0).toLocaleString()}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Operating Expenses (30 days)</span>
                        <span class="text-danger">-₱${parseFloat(summary.operating_expenses || 0).toLocaleString()}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Days Monitored</span>
                        <span>${summary.days_counted || 0} days</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span><strong>Total Cash Outflow</strong></span>
                        <span class="text-danger"><strong>-₱${parseFloat(summary.total_expenses || 0).toLocaleString()}</strong></span>
                    </div>
                `;

            } catch (error) {
                console.error('Error generating cash flow summary:', error);
                container.innerHTML = `
                    <div class="text-center py-3">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating cash flow summary: ${error.message}
                        </div>
                    </div>
                `;
            }
        }

        // Export functions
        function exportBalanceSheet(format) {
            if (!currentBalanceSheetData) {
                showAlert('Please generate the balance sheet first', 'warning');
                return;
            }

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Balance Sheet\n';
            csvContent += `As of: ${currentBalanceSheetData.as_of_date}\n\n`;

            // Assets section
            csvContent += 'ASSETS\n';
            csvContent += 'Account,Amount\n';
            if (currentBalanceSheetData.assets.accounts) {
                currentBalanceSheetData.assets.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.account_balance || 0}"\n`;
                });
            }
            csvContent += `"Total Assets","${currentBalanceSheetData.assets.total}"\n\n`;

            // Liabilities section
            csvContent += 'LIABILITIES\n';
            csvContent += 'Account,Amount\n';
            if (currentBalanceSheetData.liabilities.accounts) {
                currentBalanceSheetData.liabilities.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.account_balance || 0}"\n`;
                });
            }
            csvContent += `"Total Liabilities","${currentBalanceSheetData.liabilities.total}"\n\n`;

            // Equity section
            csvContent += 'EQUITY\n';
            csvContent += 'Account,Amount\n';
            if (currentBalanceSheetData.equity.accounts) {
                currentBalanceSheetData.equity.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.account_balance || 0}"\n`;
                });
            }
            csvContent += `"Total Equity","${currentBalanceSheetData.equity.total}"\n\n`;

            csvContent += `"Total Liabilities & Equity","${currentBalanceSheetData.liabilities.total + currentBalanceSheetData.equity.total}"\n`;

            // Download CSV
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `balance_sheet_${currentBalanceSheetData.as_of_date}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showAlert('Balance sheet exported successfully', 'success');
        }

        function exportCashFlow(format) {
            if (!currentCashFlowData) {
                showAlert('Please generate the cash flow statement first', 'warning');
                return;
            }

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Cash Flow Statement\n';
            csvContent += `Period: ${currentCashFlowData.start_date} to ${currentCashFlowData.end_date}\n\n`;

            // Operating Activities
            csvContent += 'OPERATING ACTIVITIES\n\n';
            csvContent += 'Cash Inflows (Revenue)\n';
            csvContent += 'Account,Amount\n';
            const operating = currentCashFlowData.cash_flow.operating_activities;

            if (operating.revenue && operating.revenue.length > 0) {
                operating.revenue.forEach(rev => {
                    csvContent += `"${rev.name}","${rev.amount || 0}"\n`;
                });
            }
            csvContent += `"Total Revenue","${operating.total_revenue || 0}"\n\n`;

            csvContent += 'Cash Outflows (Operating Expenses)\n';
            csvContent += 'Category,Source System,Amount\n';
            if (operating.expenses_by_category && operating.expenses_by_category.length > 0) {
                operating.expenses_by_category.forEach(expense => {
                    csvContent += `"${expense.name}","${expense.sources || 'N/A'}","${expense.amount || 0}"\n`;
                });
            }
            csvContent += `"Total Operating Expenses","","${operating.total_expenses || 0}"\n\n`;

            csvContent += 'Detailed Expense Breakdown by Department\n';
            csvContent += 'Department,Category,Source,Amount,Transactions\n';
            if (operating.expense_details && operating.expense_details.length > 0) {
                operating.expense_details.forEach(detail => {
                    csvContent += `"${detail.department || 'General'}","${detail.expense_category}","${detail.source_system}","${detail.amount || 0}","${detail.transaction_count}"\n`;
                });
            }

            csvContent += `\n"Net Cash from Operating Activities","","","${operating.amount}"\n\n`;

            // Investing Activities
            csvContent += 'INVESTING ACTIVITIES\n';
            csvContent += 'Account,Amount\n';
            if (currentCashFlowData.cash_flow.investing_activities.accounts) {
                currentCashFlowData.cash_flow.investing_activities.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount || 0}"\n`;
                });
            }
            csvContent += `"Net Cash from Investing Activities","${currentCashFlowData.cash_flow.investing_activities.amount}"\n\n`;

            // Financing Activities
            csvContent += 'FINANCING ACTIVITIES\n';
            csvContent += 'Account,Amount\n';
            if (currentCashFlowData.cash_flow.financing_activities.accounts) {
                currentCashFlowData.cash_flow.financing_activities.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount || 0}"\n`;
                });
            }
            csvContent += `"Net Cash from Financing Activities","${currentCashFlowData.cash_flow.financing_activities.amount}"\n\n`;

            csvContent += `"Net Change in Cash","${currentCashFlowData.cash_flow.net_change}"\n`;

            // Download CSV
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `cash_flow_${currentCashFlowData.start_date}_to_${currentCashFlowData.end_date}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showAlert('Cash flow statement exported successfully', 'success');
        }

        // Export all reports at once
        function exportAllReports() {
            const format = 'csv';
            let exportCount = 0;

            // Export Income Statement if available
            if (currentIncomeStatementData) {
                exportIncomeStatement(format);
                exportCount++;
            }

            // Export Balance Sheet if available
            if (currentBalanceSheetData) {
                setTimeout(() => exportBalanceSheet(format), 500);
                exportCount++;
            }

            // Export Cash Flow if available
            if (currentCashFlowData) {
                setTimeout(() => exportCashFlow(format), 1000);
                exportCount++;
            }

            if (exportCount === 0) {
                showAlert('Please generate at least one report first', 'warning');
            } else {
                showAlert(`Exporting ${exportCount} report(s) as CSV files...`, 'success');
            }
        }

        // Export current active tab report as PDF (actually CSV)
        function exportCurrentReportPDF() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');

            switch(tabId) {
                case 'income-tab':
                    exportIncomeStatement('csv');
                    break;
                case 'balance-tab':
                    exportBalanceSheet('csv');
                    break;
                case 'cashflow-tab':
                    exportCashFlow('csv');
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
            }
        }

        // Export current active tab report as Excel (CSV format)
        function exportCurrentReportExcel() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');

            switch(tabId) {
                case 'income-tab':
                    if (!currentIncomeStatementData) {
                        showAlert('Please generate the Income Statement first', 'warning');
                        return;
                    }
                    exportIncomeStatement('csv');
                    break;
                case 'balance-tab':
                    if (!currentBalanceSheetData) {
                        showAlert('Please generate the Balance Sheet first', 'warning');
                        return;
                    }
                    exportBalanceSheet('csv');
                    break;
                case 'cashflow-tab':
                    if (!currentCashFlowData) {
                        showAlert('Please generate the Cash Flow Statement first', 'warning');
                        return;
                    }
                    exportCashFlow('csv');
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
            }
        }

        // Print current active report
        function printCurrentReport() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');
            let printContainer;

            switch(tabId) {
                case 'income-tab':
                    printContainer = document.getElementById('incomeStatementContainer');
                    if (!currentIncomeStatementData) {
                        showAlert('Please generate the Income Statement first', 'warning');
                        return;
                    }
                    break;
                case 'balance-tab':
                    printContainer = document.getElementById('balanceSheetContainer');
                    if (!currentBalanceSheetData) {
                        showAlert('Please generate the Balance Sheet first', 'warning');
                        return;
                    }
                    break;
                case 'cashflow-tab':
                    printContainer = document.getElementById('cashFlowContainer');
                    if (!currentCashFlowData) {
                        showAlert('Please generate the Cash Flow Statement first', 'warning');
                        return;
                    }
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
                    return;
            }

            // Open print dialog with the report content
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Report</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: Arial, sans-serif; padding: 20px; }
                .financial-statement { max-width: 800px; margin: 0 auto; }
                .statement-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .statement-period { font-size: 14px; color: #666; margin-bottom: 20px; }
                .account-category { margin-bottom: 20px; }
                .account-category h6 { font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 5px; }
                .account-item { display: flex; justify-content: space-between; padding: 5px 0; }
                .total-row { font-weight: bold; border-top: 1px solid #000; margin-top: 5px; padding-top: 5px; }
                @media print {
                    .no-print { display: none; }
                }
            `);
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(printContainer.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();

            setTimeout(() => {
                printWindow.print();
            }, 250);

            showAlert('Opening print dialog...', 'info');
        }

        // Toggle expense detail breakdown in cash flow statement
        function toggleExpenseDetail(categoryId) {
            const detailDiv = document.getElementById(`detail-${categoryId}`);
            const icon = document.getElementById(`icon-${categoryId}`);

            if (detailDiv && icon) {
                if (detailDiv.classList.contains('d-none')) {
                    detailDiv.classList.remove('d-none');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    detailDiv.classList.add('d-none');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        }

        // Email current report
        function emailCurrentReport() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');
            let reportName = '';
            let hasData = false;

            switch(tabId) {
                case 'income-tab':
                    reportName = 'Income Statement';
                    hasData = !!currentIncomeStatementData;
                    break;
                case 'balance-tab':
                    reportName = 'Balance Sheet';
                    hasData = !!currentBalanceSheetData;
                    break;
                case 'cashflow-tab':
                    reportName = 'Cash Flow Statement';
                    hasData = !!currentCashFlowData;
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
                    return;
            }

            if (!hasData) {
                showAlert(`Please generate the ${reportName} first`, 'warning');
                return;
            }

            // For now, show a modal or prompt for email functionality
            // In a real implementation, this would send the report via backend email service
            const email = prompt(`Enter email address to send ${reportName}:`);

            if (email && email.trim()) {
                // TODO: Implement actual email sending via API
                showAlert(`Email functionality coming soon! Would send ${reportName} to: ${email}`, 'info');

                // Placeholder for future implementation:
                // fetch('/api/reports/email', {
                //     method: 'POST',
                //     headers: { 'Content-Type': 'application/json' },
                //     body: JSON.stringify({
                //         report_type: tabId.replace('-tab', ''),
                //         email: email,
                //         data: getCurrentReportData()
                //     })
                // });
            }
        }

        // View charts functionality
        function viewCharts() {
            showAlert('Chart visualization coming soon! This will display financial trends and analytics.', 'info');
        }



    <!-- Privacy Mode - Hide amounts with asterisks + Eye button -->
    <script src="../includes/privacy_mode.js?v=4"></script>

    <!-- Inactivity Timeout - Blur screen + Auto logout -->
    <script src="../includes/inactivity_timeout.js?v=3"></script>

</body>
</html>
