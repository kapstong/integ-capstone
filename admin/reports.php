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
                            <span><strong>User</strong></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin-profile-settings.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
                <button class="nav-link" id="custom-tab" data-bs-toggle="tab" data-bs-target="#custom" type="button" role="tab">
                    <i class="fas fa-cogs me-2"></i>Custom Reports
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
                        <button class="btn btn-outline-secondary me-2" onclick="exportIncomeStatement('pdf')"><i class="fas fa-download me-2"></i>Export PDF</button>
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
                        <select class="form-select form-select-sm me-2" style="width: auto;">
                            <option>As of Sep 30, 2025</option>
                            <option>As of Jun 30, 2025</option>
                            <option>As of Dec 31, 2024</option>
                        </select>
                        <button class="btn btn-outline-secondary me-2"><i class="fas fa-download me-2"></i>Export PDF</button>
                        <button class="btn btn-primary"><i class="fas fa-sync me-2"></i>Generate Report</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="financial-statement">
                            <div class="statement-header">
                                <h1 class="statement-title">Assets</h1>
                                <p class="statement-period">As of September 30, 2025</p>
                            </div>

                            <div class="account-category">
                                <h6>Current Assets</h6>
                                <div class="account-item">
                                    <span class="account-name">Cash & Cash Equivalents</span>
                                    <span class="account-amount">₱850,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Accounts Receivable</span>
                                    <span class="account-amount">₱320,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Inventory</span>
                                    <span class="account-amount">₱95,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Prepaid Expenses</span>
                                    <span class="account-amount">₱45,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Total Current Assets</strong></span>
                                    <span class="account-amount"><strong>₱1,310,000.00</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Fixed Assets</h6>
                                <div class="account-item">
                                    <span class="account-name">Property & Equipment</span>
                                    <span class="account-amount">₱3,200,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Accumulated Depreciation</span>
                                    <span class="account-amount">₱(800,000.00)</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Net Fixed Assets</strong></span>
                                    <span class="account-amount"><strong>₱2,400,000.00</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Total Assets</h6>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Total Assets</strong></span>
                                    <span class="account-amount"><strong>₱3,710,000.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="financial-statement">
                            <div class="statement-header">
                                <h1 class="statement-title">Liabilities & Equity</h1>
                                <p class="statement-period">As of September 30, 2025</p>
                            </div>

                            <div class="account-category">
                                <h6>Current Liabilities</h6>
                                <div class="account-item">
                                    <span class="account-name">Accounts Payable</span>
                                    <span class="account-amount">₱180,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Accrued Expenses</span>
                                    <span class="account-amount">₱95,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Short-term Loans</span>
                                    <span class="account-amount">₱150,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Total Current Liabilities</strong></span>
                                    <span class="account-amount"><strong>₱425,000.00</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Long-term Liabilities</h6>
                                <div class="account-item">
                                    <span class="account-name">Long-term Loans</span>
                                    <span class="account-amount">₱1,200,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Total Long-term Liabilities</strong></span>
                                    <span class="account-amount"><strong>₱1,200,000.00</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Equity</h6>
                                <div class="account-item">
                                    <span class="account-name">Owner's Capital</span>
                                    <span class="account-amount">₱1,500,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Retained Earnings</span>
                                    <span class="account-amount">₱585,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Total Equity</strong></span>
                                    <span class="account-amount"><strong>₱2,085,000.00</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Total Liabilities & Equity</h6>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Total Liabilities & Equity</strong></span>
                                    <span class="account-amount"><strong>₱3,710,000.00</strong></span>
                                </div>
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
                        <select class="form-select form-select-sm me-2" style="width: auto;">
                            <option>Last Quarter</option>
                            <option>Last 6 Months</option>
                            <option>Year to Date</option>
                        </select>
                        <button class="btn btn-outline-secondary me-2"><i class="fas fa-download me-2"></i>Export PDF</button>
                        <button class="btn btn-primary"><i class="fas fa-sync me-2"></i>Generate Report</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="financial-statement">
                            <div class="statement-header">
                                <h1 class="statement-title">Cash Flow Statement</h1>
                                <p class="statement-period">For the quarter ended September 30, 2025</p>
                            </div>

                            <div class="account-category">
                                <h6>Operating Activities</h6>
                                <div class="account-item">
                                    <span class="account-name">Net Income</span>
                                    <span class="account-amount">₱1,020,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Depreciation</span>
                                    <span class="account-amount">₱75,000.00</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Changes in Working Capital</span>
                                    <span class="account-amount">₱(45,000.00)</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Cash from Operating Activities</strong></span>
                                    <span class="account-amount positive-amount"><strong>₱1,050,000.00</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Investing Activities</h6>
                                <div class="account-item">
                                    <span class="account-name">Purchase of Equipment</span>
                                    <span class="account-amount">₱(150,000.00)</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Sale of Investments</span>
                                    <span class="account-amount">₱25,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Cash from Investing Activities</strong></span>
                                    <span class="account-amount negative-amount"><strong>₱(125,000.00)</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Financing Activities</h6>
                                <div class="account-item">
                                    <span class="account-name">Loan Repayments</span>
                                    <span class="account-amount">₱(75,000.00)</span>
                                </div>
                                <div class="account-item">
                                    <span class="account-name">Owner's Capital Contribution</span>
                                    <span class="account-amount">₱50,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Cash from Financing Activities</strong></span>
                                    <span class="account-amount negative-amount"><strong>₱(25,000.00)</strong></span>
                                </div>
                            </div>

                            <div class="account-category">
                                <h6>Net Cash Flow</h6>
                                <div class="account-item">
                                    <span class="account-name">Beginning Cash Balance</span>
                                    <span class="account-amount">₱750,000.00</span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Net Increase in Cash</strong></span>
                                    <span class="account-amount positive-amount"><strong>₱900,000.00</strong></span>
                                </div>
                                <div class="account-item total-row">
                                    <span class="account-name"><strong>Ending Cash Balance</strong></span>
                                    <span class="account-amount positive-amount"><strong>₱1,650,000.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Reports Tab -->
            <div class="tab-pane fade" id="custom" role="tabpanel" aria-labelledby="custom-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Custom Reports</h6>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomReportModal"><i class="fas fa-plus me-2"></i>Create Custom Report</button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Accounts Receivable Aging Summary</h6>
                            </div>
                            <div class="card-body">
                                <p>Consolidated view of outstanding receivables by age category.</p>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Current (0-30 days)</span>
                                        <span class="text-success">₱285,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>31-60 days</span>
                                        <span class="text-warning">₱25,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>61-90 days</span>
                                        <span class="text-danger">₱8,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>90+ days</span>
                                        <span class="text-danger">₱2,000</span>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100">Generate Aging Report</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Accounts Payable Aging Summary</h6>
                            </div>
                            <div class="card-body">
                                <p>Consolidated view of outstanding payables by age category.</p>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Current (0-30 days)</span>
                                        <span class="text-success">₱165,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>31-60 days</span>
                                        <span class="text-warning">₱12,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>61-90 days</span>
                                        <span class="text-danger">₱3,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>90+ days</span>
                                        <span class="text-danger">₱0</span>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100">Generate Aging Report</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Budget vs Actual Consolidated</h6>
                            </div>
                            <div class="card-body">
                                <p>Comparison of planned vs actual spending across all departments.</p>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Budget</span>
                                        <span>₱3,500,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Actual Spent</span>
                                        <span>₱2,870,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Variance</span>
                                        <span class="variance-negative">-₱630,000 (-18%)</span>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100">Generate Budget Report</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Collections & Disbursements Summary</h6>
                            </div>
                            <div class="card-body">
                                <p>Company-wide summary of cash inflows and outflows.</p>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Collections</span>
                                        <span class="text-success">₱3,250,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Total Disbursements</span>
                                        <span class="text-danger">₱2,350,000</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Net Cash Flow</span>
                                        <span class="text-success">₱900,000</span>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100">Generate Cash Flow Report</button>
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
                        <button class="btn btn-outline-secondary me-2"><i class="fas fa-chart-line me-2"></i>View Charts</button>
                        <button class="btn btn-primary"><i class="fas fa-download me-2"></i>Export All</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="reports-card">
                            <i class="fas fa-file-pdf fa-2x mb-3 text-danger"></i>
                            <h6>PDF Reports</h6>
                            <h3>24</h3>
                            <small>Generated this month</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card">
                            <i class="fas fa-file-excel fa-2x mb-3 text-success"></i>
                            <h6>Excel Exports</h6>
                            <h3>18</h3>
                            <small>Generated this month</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card">
                            <i class="fas fa-print fa-2x mb-3 text-info"></i>
                            <h6>Print Jobs</h6>
                            <h3>12</h3>
                            <small>Generated this month</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card">
                            <i class="fas fa-users fa-2x mb-3 text-warning"></i>
                            <h6>Active Users</h6>
                            <h3>8</h3>
                            <small>Report viewers</small>
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
                                    <button class="btn btn-outline-danger">
                                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                                    </button>
                                    <button class="btn btn-outline-success">
                                        <i class="fas fa-file-excel me-2"></i>Export as Excel
                                    </button>
                                    <button class="btn btn-outline-info">
                                        <i class="fas fa-print me-2"></i>Print Report
                                    </button>
                                    <button class="btn btn-outline-secondary">
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
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span>General Ledger</span>
                                    <span class="badge bg-success">Synced 2 min ago</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span>Accounts Payable</span>
                                    <span class="badge bg-success">Synced 5 min ago</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span>Accounts Receivable</span>
                                    <span class="badge bg-success">Synced 3 min ago</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span>Disbursements</span>
                                    <span class="badge bg-success">Synced 1 min ago</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span>Budget Management</span>
                                    <span class="badge bg-success">Synced 4 min ago</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Report Users & Access</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary">8</h4>
                                        <small class="text-muted">Admin/Managers</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success">12</h4>
                                        <small class="text-muted">Accountants</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info">5</h4>
                                        <small class="text-muted">Auditors</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-2">
                                    <strong>Report Permissions:</strong>
                                </div>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-check text-success me-2"></i>Financial statements access</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Export capabilities</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Custom report creation</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Audit trail viewing</li>
                                </ul>
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
                const response = await fetch(`api/reports.php?type=income_statement&date_from=${dateFrom}&date_to=${dateTo}`);
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
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

            // For now, just show CSV export
            if (format === 'pdf') {
                showAlert('PDF export not yet implemented. Use CSV format.', 'info');
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

        // Generate aging reports
        async function generateAgingReport(type) {
            try {
                const response = await fetch(`api/reports.php?type=aging_${type}&format=json`);
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                // Update the aging report display
                updateAgingDisplay(type, data);

                showAlert(`${type === 'receivable' ? 'Accounts Receivable' : 'Accounts Payable'} aging report generated successfully`, 'success');

            } catch (error) {
                console.error(`Error generating ${type} aging report:`, error);
                showAlert(`Error generating aging report: ${error.message}`, 'danger');
            }
        }

        // Update aging display
        function updateAgingDisplay(type, data) {
            const cardSelector = type === 'receivable' ? '.card:contains("Accounts Receivable Aging")' : '.card:contains("Accounts Payable Aging")';
            const card = document.querySelector(cardSelector);

            if (!card) return;

            const body = card.querySelector('.card-body');
            const summaryDiv = body.querySelector('.mb-3');

            // Update totals
            const totals = data.totals || {};
            summaryDiv.innerHTML = `
                <div class="d-flex justify-content-between">
                    <span>Current (0-30 days)</span>
                    <span class="text-success">₱${parseFloat(totals.current || 0).toLocaleString()}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>31-60 days</span>
                    <span class="text-warning">₱${parseFloat(totals['1-30'] || 0).toLocaleString()}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>61-90 days</span>
                    <span class="text-danger">₱${parseFloat(totals['31-60'] || 0).toLocaleString()}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>90+ days</span>
                    <span class="text-danger">₱${parseFloat(totals['61-90'] || 0).toLocaleString()}</span>
                </div>
            `;
        }

        // Utility functions
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Add event listeners for aging report buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Find and add click handlers for aging report buttons
            const buttons = document.querySelectorAll('button[onclick*="generateAgingReport"]');
            buttons.forEach(button => {
                const type = button.closest('.card').querySelector('h6').textContent.toLowerCase().includes('receivable') ? 'receivable' : 'payable';
                button.onclick = () => generateAgingReport(type);
            });
        });
    </script>
</body>
</html>
