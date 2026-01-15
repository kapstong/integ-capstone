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
    <title>Financial Management System - Budget Management</title>
    <link rel="icon" type="image/png" href="../logo2.png">
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
            height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
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

        .tracking-card {
            display: grid;
            grid-template-rows: auto auto auto;
            place-items: center;
            min-height: 190px;
            gap: 0.35rem;
        }

        .tracking-card i {
            line-height: 1;
        }

        .tracking-card h6 {
            margin: 0;
        }

        .tracking-card h3 {
            margin: 0;
            width: 100%;
            text-align: center;
            line-height: 1.05;
            font-variant-numeric: tabular-nums;
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

        /* Budget-specific styles */
        .budget-progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }

        .budget-progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .budget-over .budget-progress-bar {
            background: linear-gradient(90deg, #dc3545, #fd7e14);
        }

        .budget-under .budget-progress-bar {
            background: linear-gradient(90deg, #28a745, #20c997);
        }

        .budget-on-track .budget-progress-bar {
            background: linear-gradient(90deg, #ffc107, #fd7e14);
        }

        .variance-positive {
            color: #dc3545;
            font-weight: 600;
        }

        .variance-negative {
            color: #28a745;
            font-weight: 600;
        }

        .forecast-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .forecast-card .card-body {
            padding: 2.5rem;
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

            <a class="nav-link active" href="budget_management.php">
                <i class="fas fa-chart-line me-2"></i><span>Budget Management</span>
            </a>
              <a class="nav-link" href="reports.php">
                  <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
              </a>
              <hr class="my-3">
              <a class="nav-link" href="financials/departments.php">
                  <i class="fas fa-building me-2"></i><span>Departments</span>
              </a>
              <a class="nav-link" href="financials/outlets.php">
                  <i class="fas fa-store me-2"></i><span>Outlets</span>
              </a>
              <a class="nav-link" href="financials/daily_revenue.php">
                  <i class="fas fa-receipt me-2"></i><span>Daily Revenue</span>
              </a>
              <a class="nav-link" href="financials/cashier.php">
                  <i class="fas fa-cash-register me-2"></i><span>Cashier/Collection</span>
              </a>
              <a class="nav-link" href="financials/financial_reports.php">
                  <i class="fas fa-file-invoice-dollar me-2"></i><span>Financial Reports</span>
              </a>
              <a class="nav-link" href="financials/integration_management.php">
                  <i class="fas fa-exchange-alt me-2"></i><span>Integrations</span>
              </a>
              <a class="nav-link" href="financials/financial_setup.php">
                  <i class="fas fa-cogs me-2"></i><span>Financial Setup</span>
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
                <span class="navbar-brand mb-0 h1 me-4">Budget Management</span>
                <div class="d-flex align-items-center me-4">
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
        <ul class="nav nav-tabs mb-4" id="budgetTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="planning-tab" data-bs-toggle="tab" data-bs-target="#planning" type="button" role="tab">
                    <i class="fas fa-calendar-plus me-2"></i>Budget Planning
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="allocation-tab" data-bs-toggle="tab" data-bs-target="#allocation" type="button" role="tab">
                    <i class="fas fa-sitemap me-2"></i>Budget Allocation
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tracking-tab" data-bs-toggle="tab" data-bs-target="#tracking" type="button" role="tab">
                    <i class="fas fa-chart-line me-2"></i>Actual vs Budget
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">
                    <i class="fas fa-exclamation-triangle me-2"></i>Budget Alerts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="forecasting-tab" data-bs-toggle="tab" data-bs-target="#forecasting" type="button" role="tab">
                    <i class="fas fa-crystal-ball me-2"></i>Forecasting
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adjustments-tab" data-bs-toggle="tab" data-bs-target="#adjustments" type="button" role="tab">
                    <i class="fas fa-sliders-h me-2"></i>Budget Adjustments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                    <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab">
                    <i class="fas fa-history me-2"></i>Audit Trail
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="budgetTabContent">
                        <!-- Budget Planning Tab -->
            <div class="tab-pane fade show active" id="planning" role="tabpanel" aria-labelledby="planning-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Planning & Setup</h6>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBudgetModal"><i class="fas fa-plus me-2"></i>Create Budget</button>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Active Budgets & Cycles</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="trackingCategoryTable">
                                        <thead>
                                            <tr>
                                                <th>Budget</th>
                                                <th>Cycle</th>
                                                <th>Owner</th>
                                                <th>Total</th>
                                                <th>Utilized</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="trackingCategoryBody">
                                            <tr>
                                                <td>FY 2025 Master Budget</td>
                                                <td>Jan-Dec 2025</td>
                                                <td>Finance & Admin</td>
                                                <td>PHP 4,200,000.00</td>
                                                <td>PHP 1,980,000.00</td>
                                                <td><span class="badge bg-success">Approved</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Open</button>
                                                    <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Q2 2025 Ops Budget</td>
                                                <td>Apr-Jun 2025</td>
                                                <td>Hotel Operations</td>
                                                <td>PHP 1,150,000.00</td>
                                                <td>PHP 520,000.00</td>
                                                <td><span class="badge bg-warning">In Review</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Open</button>
                                                    <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Events Program 2025</td>
                                                <td>Jan-Dec 2025</td>
                                                <td>Events</td>
                                                <td>PHP 680,000.00</td>
                                                <td>PHP 210,000.00</td>
                                                <td><span class="badge bg-info">Draft</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Open</button>
                                                    <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Restaurant Growth Plan</td>
                                                <td>May-Dec 2025</td>
                                                <td>Restaurant</td>
                                                <td>PHP 920,000.00</td>
                                                <td>PHP 310,000.00</td>
                                                <td><span class="badge bg-success">Approved</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Open</button>
                                                    <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6>Planning Checklist</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Confirm revenue assumptions</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Lock baseline payroll costs</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Review vendor pricing updates</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Align department targets</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i>Finalize approval workflow</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6>Category Mix</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="text-muted">Revenue Focus</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-circle text-success me-2"></i>Rooms & Suites</li>
                                        <li><i class="fas fa-circle text-success me-2"></i>Dining & Beverage</li>
                                        <li><i class="fas fa-circle text-success me-2"></i>Events & Catering</li>
                                    </ul>
                                </div>
                                <div>
                                    <h6 class="text-muted">Expense Focus</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-circle text-danger me-2"></i>Payroll & Benefits</li>
                                        <li><i class="fas fa-circle text-danger me-2"></i>Supplies & Inventory</li>
                                        <li><i class="fas fa-circle text-danger me-2"></i>Utilities & Maintenance</li>
                                        <li><i class="fas fa-circle text-danger me-2"></i>Marketing & Promotions</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6>Budget Calendar</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><strong>Week 1:</strong> Collect department drafts</li>
                                    <li class="mb-2"><strong>Week 2:</strong> Finance review and revisions</li>
                                    <li class="mb-2"><strong>Week 3:</strong> Leadership alignment</li>
                                    <li class="mb-2"><strong>Week 4:</strong> Final approval and lock</li>
                                    <li><strong>Monthly:</strong> Variance checkpoint</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Budget Allocation Tab -->
            <div class="tab-pane fade" id="allocation" role="tabpanel" aria-labelledby="allocation-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Allocation & Distribution</h6>
                    <div>
                        <button class="btn btn-outline-secondary me-2"><i class="fas fa-lock me-2"></i>Lock Allocations</button>
                        <button class="btn btn-primary"><i class="fas fa-plus me-2"></i>Allocate Funds</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Department Allocations - FY 2025</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Allocated</th>
                                                <th>Reserved</th>
                                                <th>Utilized</th>
                                                <th>Remaining</th>
                                                <th>Progress</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>Hotel Operations</strong></td>
                                                <td>PHP 1,500,000.00</td>
                                                <td>PHP 120,000.00</td>
                                                <td>PHP 980,000.00</td>
                                                <td>PHP 400,000.00</td>
                                                <td>
                                                    <div class="budget-progress budget-on-track">
                                                        <div class="budget-progress-bar" style="width: 65%"></div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-success">On Track</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Restaurant</strong></td>
                                                <td>PHP 900,000.00</td>
                                                <td>PHP 90,000.00</td>
                                                <td>PHP 760,000.00</td>
                                                <td>PHP 50,000.00</td>
                                                <td>
                                                    <div class="budget-progress budget-over">
                                                        <div class="budget-progress-bar" style="width: 92%"></div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-warning">Tight</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Events</strong></td>
                                                <td>PHP 420,000.00</td>
                                                <td>PHP 45,000.00</td>
                                                <td>PHP 210,000.00</td>
                                                <td>PHP 165,000.00</td>
                                                <td>
                                                    <div class="budget-progress budget-under">
                                                        <div class="budget-progress-bar" style="width: 50%"></div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-info">Under Budget</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Finance & Admin</strong></td>
                                                <td>PHP 260,000.00</td>
                                                <td>PHP 25,000.00</td>
                                                <td>PHP 180,000.00</td>
                                                <td>PHP 55,000.00</td>
                                                <td>
                                                    <div class="budget-progress budget-on-track">
                                                        <div class="budget-progress-bar" style="width: 70%"></div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-success">On Track</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>HR3 Claims Reserve</strong></td>
                                                <td>PHP 180,000.00</td>
                                                <td>PHP 40,000.00</td>
                                                <td>PHP 98,000.00</td>
                                                <td>PHP 42,000.00</td>
                                                <td>
                                                    <div class="budget-progress budget-on-track">
                                                        <div class="budget-progress-bar" style="width: 55%"></div>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-success">On Track</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actual vs Budget Tracking Tab -->
            <div class="tab-pane fade" id="tracking" role="tabpanel" aria-labelledby="tracking-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Actual vs Budget Tracking</h6>
                    <div>
                        <select class="form-select form-select-sm me-2" style="width: auto;">
                            <option>Last 30 Days</option>
                            <option>Last Quarter</option>
                            <option>Year to Date</option>
                        </select>
                        <button class="btn btn-outline-secondary"><i class="fas fa-sync me-2"></i>Refresh</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-chart-pie fa-2x mb-3 text-primary"></i>
                            <h6>Total Budget</h6>
                            <h3>PHP 3,950,000</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-coins fa-2x mb-3 text-success"></i>
                            <h6>Actual Spent</h6>
                            <h3>PHP 2,440,000</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-percentage fa-2x mb-3 text-warning"></i>
                            <h6>Variance</h6>
                            <h3 class="variance-negative">-38.2%</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-clock fa-2x mb-3 text-info"></i>
                            <h6>Remaining</h6>
                            <h3>PHP 1,510,000</h3>
                        </div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>HR3 Claims vs Budget</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Claims Approved</th>
                                                <th>Claims Pending</th>
                                                <th>Claims Amount</th>
                                                <th>Budget Remaining</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Hotel Operations</td>
                                                <td>18</td>
                                                <td>3</td>
                                                <td>PHP 64,500.00</td>
                                                <td>PHP 35,500.00</td>
                                                <td><span class="badge bg-success">Within Limit</span></td>
                                            </tr>
                                            <tr>
                                                <td>Restaurant</td>
                                                <td>22</td>
                                                <td>6</td>
                                                <td>PHP 92,000.00</td>
                                                <td>PHP 8,000.00</td>
                                                <td><span class="badge bg-warning">Near Limit</span></td>
                                            </tr>
                                            <tr>
                                                <td>Events</td>
                                                <td>7</td>
                                                <td>1</td>
                                                <td>PHP 21,300.00</td>
                                                <td>PHP 28,700.00</td>
                                                <td><span class="badge bg-success">Within Limit</span></td>
                                            </tr>
                                            <tr>
                                                <td>Finance & Admin</td>
                                                <td>4</td>
                                                <td>0</td>
                                                <td>PHP 12,800.00</td>
                                                <td>PHP 42,200.00</td>
                                                <td><span class="badge bg-success">Within Limit</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-outline-primary btn-sm"><i class="fas fa-link me-2"></i>View HR3 Claims Feed</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Budget vs Actual by Category</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Budget Amount</th>
                                                <th>Actual Amount</th>
                                                <th>Variance</th>
                                                <th>Variance %</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Staff Salaries</td>
                                                <td>PHP 1,980,000.00</td>
                                                <td>PHP 1,720,000.00</td>
                                                <td class="variance-negative">PHP 260,000.00</td>
                                                <td class="variance-negative">-13.1%</td>
                                                <td><span class="badge bg-success">Under Budget</span></td>
                                            </tr>
                                            <tr>
                                                <td>Supplies & Inventory</td>
                                                <td>PHP 720,000.00</td>
                                                <td>PHP 810,000.00</td>
                                                <td class="variance-positive">PHP 90,000.00</td>
                                                <td class="variance-positive">+12.5%</td>
                                                <td><span class="badge bg-warning">Over Budget</span></td>
                                            </tr>
                                            <tr>
                                                <td>Utilities</td>
                                                <td>PHP 320,000.00</td>
                                                <td>PHP 290,000.00</td>
                                                <td class="variance-negative">PHP 30,000.00</td>
                                                <td class="variance-negative">-9.4%</td>
                                                <td><span class="badge bg-success">Under Budget</span></td>
                                            </tr>
                                            <tr>
                                                <td>Marketing</td>
                                                <td>PHP 280,000.00</td>
                                                <td>PHP 310,000.00</td>
                                                <td class="variance-positive">PHP 30,000.00</td>
                                                <td class="variance-positive">+10.7%</td>
                                                <td><span class="badge bg-warning">Over Budget</span></td>
                                            </tr>
                                            <tr>
                                                <td>Employee Claims (HR3)</td>
                                                <td>PHP 180,000.00</td>
                                                <td>PHP 98,000.00</td>
                                                <td class="variance-negative">PHP 82,000.00</td>
                                                <td class="variance-negative">-45.6%</td>
                                                <td><span class="badge bg-success">Under Budget</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Alerts Tab -->
            <div class="tab-pane fade" id="alerts" role="tabpanel" aria-labelledby="alerts-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Alerts & Notifications</h6>
                    <div>
                        <select class="form-select form-select-sm me-2" style="width: auto;" id="alertsFilter">
                            <option value="all">All Alerts</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                        </select>
                        <button class="btn btn-outline-secondary" onclick="loadAlerts()"><i class="fas fa-sync me-2"></i>Refresh</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3 text-danger"></i>
                            <h6>Critical Alerts</h6>
                            <h3 id="criticalCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-exclamation-circle fa-2x mb-3 text-warning"></i>
                            <h6>High Priority</h6>
                            <h3 id="highCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-info-circle fa-2x mb-3 text-info"></i>
                            <h6>Medium Alerts</h6>
                            <h3 id="mediumCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-bell fa-2x mb-3 text-secondary"></i>
                            <h6>Total Alerts</h6>
                            <h3 id="totalAlerts">0</h3>
                        </div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>HR3 Claims Over Budget Queue</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Claim ID</th>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <th>Amount</th>
                                                <th>Budget Remaining</th>
                                                <th>Action Needed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>HR3-CLM-1024</td>
                                                <td>Maria Santos</td>
                                                <td>Restaurant</td>
                                                <td>PHP 12,500.00</td>
                                                <td>PHP 8,000.00</td>
                                                <td><span class="badge bg-warning">Finance Review</span></td>
                                            </tr>
                                            <tr>
                                                <td>HR3-CLM-1029</td>
                                                <td>Jon Reyes</td>
                                                <td>Restaurant</td>
                                                <td>PHP 9,200.00</td>
                                                <td>PHP 8,000.00</td>
                                                <td><span class="badge bg-danger">Hold Approval</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-outline-primary btn-sm"><i class="fas fa-link me-2"></i>Open HR3 Claims Review</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Departments Over Budget</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="alertsTable">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Budget Year</th>
                                                <th>Budgeted Amount</th>
                                                <th>Actual Amount</th>
                                                <th>Over Amount</th>
                                                <th>Over %</th>
                                                <th>Severity</th>
                                                <th>Alert Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="alertsTableBody">
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">Loading alerts...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Forecasting Tab -->
            <div class="tab-pane fade" id="forecasting" role="tabpanel" aria-labelledby="forecasting-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Forecasting</h6>
                    <button class="btn btn-outline-secondary"><i class="fas fa-rotate me-2"></i>Refresh Forecast</button>
                </div>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card forecast-card">
                            <div class="card-body">
                                <h6>Projected Year-End Spend</h6>
                                <h3 class="mb-2">PHP 3,620,000</h3>
                                <p class="mb-0">Based on current run rate and seasonality.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card forecast-card">
                            <div class="card-body">
                                <h6>Expected Variance</h6>
                                <h3 class="mb-2">-8.4%</h3>
                                <p class="mb-0">Favorable due to staffing optimization.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card forecast-card">
                            <div class="card-body">
                                <h6>Claims Pressure (HR3)</h6>
                                <h3 class="mb-2">+6.2%</h3>
                                <p class="mb-0">Higher claim volume in Restaurant unit.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Forecast Drivers</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Driver</th>
                                                <th>Trend</th>
                                                <th>Impact</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Occupancy Levels</td>
                                                <td>Upward</td>
                                                <td>+PHP 120,000</td>
                                                <td>Peak season demand driving labor and supply usage.</td>
                                            </tr>
                                            <tr>
                                                <td>Supplier Pricing</td>
                                                <td>Stable</td>
                                                <td>+PHP 35,000</td>
                                                <td>Minor increases in beverage costs.</td>
                                            </tr>
                                            <tr>
                                                <td>HR3 Claims</td>
                                                <td>Rising</td>
                                                <td>+PHP 60,000</td>
                                                <td>Pending claims may require supplemental budget.</td>
                                            </tr>
                                            <tr>
                                                <td>Utilities</td>
                                                <td>Downward</td>
                                                <td>-PHP 25,000</td>
                                                <td>Energy efficiency initiative impacts Q3.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Adjustments Tab -->
            <div class="tab-pane fade" id="adjustments" role="tabpanel" aria-labelledby="adjustments-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Adjustments</h6>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustmentRequestModal"><i class="fas fa-plus me-2"></i>Request Adjustment</button>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Active Adjustment Requests</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Request ID</th>
                                                <th>Department</th>
                                                <th>Requested By</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Reason</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>ADJ-011</td>
                                                <td>Restaurant</td>
                                                <td>F&B Manager</td>
                                                <td>Increase</td>
                                                <td>PHP 60,000.00</td>
                                                <td>HR3 claims spike during peak season</td>
                                                <td><span class="badge bg-warning">Pending</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Review</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>ADJ-012</td>
                                                <td>Hotel Operations</td>
                                                <td>Ops Director</td>
                                                <td>Transfer</td>
                                                <td>PHP 35,000.00</td>
                                                <td>Shift to maintenance initiatives</td>
                                                <td><span class="badge bg-info">In Review</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">Review</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>ADJ-013</td>
                                                <td>Events</td>
                                                <td>Events Lead</td>
                                                <td>Decrease</td>
                                                <td>PHP 20,000.00</td>
                                                <td>Vendor discounts applied</td>
                                                <td><span class="badge bg-success">Approved</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">View</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Adjustment Guidelines</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Provide clear financial justification.</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Attach supporting HR3 claim data if applicable.</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Allow 3-5 business days for review.</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Approved adjustments update allocations automatically.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Recent Approvals</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><strong>ADJ-009:</strong> +PHP 40,000 (Restaurant claims)</li>
                                    <li class="mb-2"><strong>ADJ-010:</strong> -PHP 15,000 (Utilities savings)</li>
                                    <li><strong>ADJ-008:</strong> +PHP 25,000 (Events staffing)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports & Analytics Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Reports & Analytics</h6>
                    <button class="btn btn-outline-secondary"><i class="fas fa-download me-2"></i>Export Reports</button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Budget vs Actual Report</h6>
                            </div>
                            <div class="card-body">
                                <p>Detailed variance breakdown by department and category with month-over-month trends.</p>
                                <button class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>HR3 Claims Impact Report</h6>
                            </div>
                            <div class="card-body">
                                <p>Summarizes HR3 claim volumes, approvals, and budget impact across departments.</p>
                                <button class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Department Performance</h6>
                            </div>
                            <div class="card-body">
                                <p>Highlights departments trending over budget with recommended actions.</p>
                                <button class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Forecast Snapshot</h6>
                            </div>
                            <div class="card-body">
                                <p>Rolling 90-day forecast with risk flags for claims-heavy departments.</p>
                                <button class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Trail Tab -->
            <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Audit Trail</h6>
                    <button class="btn btn-outline-secondary"><i class="fas fa-filter me-2"></i>Filter Logs</button>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Budget/Item</th>
                                        <th>Details</th>
                                        <th>Source</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2025-09-25 08:15:22</td>
                                        <td>Admin</td>
                                        <td>Created</td>
                                        <td>FY 2025 Master Budget</td>
                                        <td>Initial baseline approved</td>
                                        <td>Finance Module</td>
                                        <td>192.168.1.100</td>
                                    </tr>
                                    <tr>
                                        <td>2025-09-25 11:42:10</td>
                                        <td>Finance Lead</td>
                                        <td>Adjusted</td>
                                        <td>Restaurant Growth Plan</td>
                                        <td>+PHP 40,000 for HR3 claims</td>
                                        <td>Budget Adjustments</td>
                                        <td>192.168.1.121</td>
                                    </tr>
                                    <tr>
                                        <td>2025-09-25 14:06:44</td>
                                        <td>System</td>
                                        <td>Alert Triggered</td>
                                        <td>HR3 Claims Reserve</td>
                                        <td>Claims exceeded threshold</td>
                                        <td>HR3 Integration</td>
                                        <td>192.168.1.200</td>
                                    </tr>
                                    <tr>
                                        <td>2025-09-25 15:31:19</td>
                                        <td>Accounting</td>
                                        <td>Reviewed</td>
                                        <td>Department Allocations</td>
                                        <td>Monthly variance review completed</td>
                                        <td>Finance Module</td>
                                        <td>192.168.1.132</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

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
        let currentBudgets = [];
        let currentAllocations = [];
        let currentTrackingData = [];
        let currentAlerts = [];

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

            // Load initial data
            loadBudgets();
            loadAllocations();
            loadTrackingData();
        });

        // Load budgets
        async function loadBudgets() {
            try {
                const response = await fetch('api/budgets.php');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                currentBudgets = data.budgets || [];
                renderBudgetsTable();

            } catch (error) {
                console.error('Error loading budgets:', error);
                showAlert('Error loading budgets: ' + error.message, 'danger');
            }
        }

        // Render budgets table
        function renderBudgetsTable() {
            const tbody = document.querySelector('#planning .table tbody');
            tbody.innerHTML = '';

            if (currentBudgets.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No budgets found. Create your first budget.</td></tr>';
                return;
            }

            currentBudgets.forEach(budget => {
                const statusBadge = getStatusBadge(budget.status);
                const row = `
                    <tr>
                        <td>${budget.name}</td>
                        <td>${formatBudgetPeriod(budget.start_date, budget.end_date)}</td>
                        <td>${budget.department || 'All Departments'}</td>
                        <td>Gé¦${parseFloat(budget.total_amount || 0).toLocaleString()}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewBudget(${budget.id})">View</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editBudget(${budget.id})">Edit</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Load allocations
        async function loadAllocations() {
            try {
                const response = await fetch('api/budgets.php?action=allocations');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                currentAllocations = data.allocations || [];
                renderAllocationsTable();

            } catch (error) {
                console.error('Error loading allocations:', error);
                showAlert('Error loading allocations: ' + error.message, 'danger');
            }
        }

        // Render allocations table
        function renderAllocationsTable() {
            const tbody = document.querySelector('#allocation .table tbody');
            tbody.innerHTML = '';

            if (currentAllocations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No allocations found.</td></tr>';
                return;
            }

            currentAllocations.forEach(allocation => {
                const progressPercent = allocation.total_amount > 0 ? (allocation.utilized_amount / allocation.total_amount) * 100 : 0;
                const progressClass = progressPercent > 90 ? 'budget-over' : progressPercent > 75 ? 'budget-on-track' : 'budget-under';
                const statusBadge = getAllocationStatusBadge(progressPercent);

                const row = `
                    <tr>
                        <td><strong>${allocation.department}</strong></td>
                        <td>Gé¦${parseFloat(allocation.total_amount || 0).toLocaleString()}</td>
                        <td>Gé¦${parseFloat(allocation.utilized_amount || 0).toLocaleString()}</td>
                        <td>Gé¦${parseFloat((allocation.total_amount || 0) - (allocation.utilized_amount || 0)).toLocaleString()}</td>
                        <td>
                            <div class="budget-progress ${progressClass}">
                                <div class="budget-progress-bar" style="width: ${Math.min(progressPercent, 100)}%"></div>
                            </div>
                        </td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="adjustAllocation(${allocation.id})">Adjust</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Load tracking data
        async function loadTrackingData() {
            try {
                const response = await fetch('api/budgets.php?action=tracking');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                currentTrackingData = data.tracking || [];
                renderTrackingTable();
                updateTrackingCards(data.summary);

            } catch (error) {
                console.error('Error loading tracking data:', error);
                showAlert('Error loading tracking data: ' + error.message, 'danger');
            }
        }

        // Render tracking table
        function renderTrackingTable() {
            const tbody = document.getElementById('trackingCategoryBody');
            tbody.innerHTML = '';

            if (currentTrackingData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No tracking data available.</td></tr>';
                return;
            }

            currentTrackingData.forEach(item => {
                const variance = (item.actual_amount || 0) - (item.budget_amount || 0);
                const variancePercent = item.budget_amount > 0 ? (variance / item.budget_amount) * 100 : 0;
                const varianceClass = variance >= 0 ? 'variance-positive' : 'variance-negative';
                const statusBadge = getVarianceStatusBadge(variancePercent);

                const row = `
                    <tr>
                        <td>${item.category}</td>
                        <td>Gé¦${parseFloat(item.budget_amount || 0).toLocaleString()}</td>
                        <td>Gé¦${parseFloat(item.actual_amount || 0).toLocaleString()}</td>
                        <td class="${varianceClass}">Gé¦${Math.abs(variance).toLocaleString()}</td>
                        <td class="${varianceClass}">${variancePercent >= 0 ? '+' : ''}${variancePercent.toFixed(1)}%</td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Update tracking cards
        function updateTrackingCards(summary) {
            if (!summary) return;

            // Update the cards with real data
            const cards = document.querySelectorAll('#tracking .reports-card h3');
            if (cards.length >= 4) {
                cards[0].textContent = `Gé¦${parseFloat(summary.total_budget || 0).toLocaleString()}`;
                cards[1].textContent = `Gé¦${parseFloat(summary.actual_spent || 0).toLocaleString()}`;
                cards[2].textContent = `${parseFloat(summary.variance_percent || 0).toFixed(1)}%`;
                cards[3].textContent = `Gé¦${parseFloat(summary.remaining || 0).toLocaleString()}`;

                // Update variance color
                const varianceCard = cards[2].closest('.reports-card');
                const varianceValue = parseFloat(summary.variance_percent || 0);
                if (varianceValue < 0) {
                    cards[2].className = 'variance-negative';
                } else {
                    cards[2].className = 'variance-positive';
                }
            }
        }

        // Create budget
        async function createBudget(formData) {
            try {
                const response = await fetch('api/budgets.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                showAlert('Budget created successfully', 'success');
                loadBudgets(); // Refresh the list

                // Close modal
                const modalEl = document.getElementById('createBudgetModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }

            } catch (error) {
                console.error('Error creating budget:', error);
                showAlert('Error creating budget: ' + error.message, 'danger');
            }
        }

        // View budget details
        function viewBudget(budgetId) {
            // Find the budget
            const budget = currentBudgets.find(b => b.id == budgetId);
            if (!budget) {
                showAlert('Budget not found', 'warning');
                return;
            }

            // Show budget details modal or redirect to detail page
            showAlert(`Viewing budget: ${budget.name}`, 'info');
        }

        // Edit budget
        function editBudget(budgetId) {
            // Find the budget
            const budget = currentBudgets.find(b => b.id == budgetId);
            if (!budget) {
                showAlert('Budget not found', 'warning');
                return;
            }

            // Populate edit modal with budget data
            showAlert(`Editing budget: ${budget.name}`, 'info');
        }

        // Adjust allocation
        function adjustAllocation(allocationId) {
            // Find the allocation
            const allocation = currentAllocations.find(a => a.id == allocationId);
            if (!allocation) {
                showAlert('Allocation not found', 'warning');
                return;
            }

            // Show adjustment modal
            showAlert(`Adjusting allocation for: ${allocation.department}`, 'info');
        }

        // Utility functions
        function getStatusBadge(status) {
            const statusMap = {
                'draft': 'bg-info',
                'pending': 'bg-warning',
                'approved': 'bg-success',
                'active': 'bg-primary',
                'completed': 'bg-secondary'
            };

            const badgeClass = statusMap[status] || 'bg-secondary';
            return `<span class="badge ${badgeClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
        }

        function getAllocationStatusBadge(progressPercent) {
            if (progressPercent > 90) {
                return '<span class="badge bg-danger">Over Budget</span>';
            } else if (progressPercent > 75) {
                return '<span class="badge bg-warning">On Track</span>';
            } else {
                return '<span class="badge bg-success">Under Budget</span>';
            }
        }

        function getVarianceStatusBadge(variancePercent) {
            if (variancePercent > 10) {
                return '<span class="badge bg-danger">Over Budget</span>';
            } else if (variancePercent > 5) {
                return '<span class="badge bg-warning">Slightly Over</span>';
            } else if (variancePercent < -10) {
                return '<span class="badge bg-success">Under Budget</span>';
            } else {
                return '<span class="badge bg-info">On Target</span>';
            }
        }

        function formatBudgetPeriod(startDate, endDate) {
            if (!startDate || !endDate) return 'N/A';

            const start = new Date(startDate);
            const end = new Date(endDate);

            const startMonth = start.toLocaleDateString('en-US', { month: 'short' });
            const endMonth = end.toLocaleDateString('en-US', { month: 'short' });
            const startYear = start.getFullYear();
            const endYear = end.getFullYear();

            if (startYear === endYear) {
                return `${startMonth}-${endMonth} ${startYear}`;
            } else {
                return `${startMonth} ${startYear} - ${endMonth} ${endYear}`;
            }
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

        // Event listeners for dynamic functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle create budget form submission
            const createBudgetForm = document.getElementById('createBudgetForm');
            if (createBudgetForm) {
                createBudgetForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const budgetData = {
                        name: formData.get('budgetName'),
                        department: formData.get('department'),
                        start_date: formData.get('startDate'),
                        end_date: formData.get('endDate'),
                        total_amount: parseFloat(formData.get('totalAmount')),
                        description: formData.get('description')
                    };

                    createBudget(budgetData);
                });
            }

            // Handle tracking period change
            const trackingPeriodSelect = document.querySelector('#tracking select');
            if (trackingPeriodSelect) {
                trackingPeriodSelect.addEventListener('change', function() {
                    loadTrackingData();
                });
            }
        });

        // Load vendors for dropdowns
        async function loadVendors() {
            try {
                const response = await fetch('api/vendors.php');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                vendors = data; // Store globally for dropdown population
                populateVendorDropdowns(data);

            } catch (error) {
                console.error('Error loading vendors:', error);
                showAlert('Error loading vendors: ' + error.message, 'danger');
            }
        }

        // Populate vendor dropdowns in modals
        function populateVendorDropdowns(vendors) {
            const vendorSelects = [
                'budgetVendor', 'allocationVendor', 'adjustmentVendor', 'trackingVendor'
            ];

            vendorSelects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.innerHTML = '<option value="">Select Vendor</option>';
                    vendors.forEach(vendor => {
                        if (vendor.status === 'active') {
                            select.innerHTML += `<option value="${vendor.id}">${vendor.company_name}</option>`;
                        }
                    });
                }
            });
        }

        // Load alerts
        async function loadAlerts() {
            try {
                const response = await fetch('api/budgets.php?action=alerts');
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                currentAlerts = data.alerts || [];
                renderAlertsTable();
                updateAlertsCards();

            } catch (error) {
                console.error('Error loading alerts:', error);
                showAlert('Error loading alerts: ' + error.message, 'danger');
            }
        }

        // Render alerts table
        function renderAlertsTable() {
            const tbody = document.getElementById('alertsTableBody');
            tbody.innerHTML = '';

            if (currentAlerts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No budget alerts at this time.</td></tr>';
                return;
            }

            // Apply filter if selected
            const filterValue = document.getElementById('alertsFilter').value;
            let filteredAlerts = currentAlerts;

            if (filterValue !== 'all') {
                filteredAlerts = currentAlerts.filter(alert => alert.severity === filterValue);
            }

            filteredAlerts.forEach(alert => {
                const severityClass = {
                    'critical': 'bg-danger text-white',
                    'high': 'bg-warning text-dark',
                    'medium': 'bg-info text-white'
                }[alert.severity] || 'bg-secondary';

                const row = `
                    <tr>
                        <td><strong>${alert.department}</strong></td>
                        <td>${alert.budget_year}</td>
                        <td>Gé¦${parseFloat(alert.budgeted_amount).toLocaleString()}</td>
                        <td>Gé¦${parseFloat(alert.actual_amount).toLocaleString()}</td>
                        <td class="variance-positive">Gé¦${parseFloat(alert.over_amount).toLocaleString()}</td>
                        <td class="variance-positive">${parseFloat(alert.over_percent).toFixed(1)}%</td>
                        <td><span class="badge ${severityClass}">${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}</span></td>
                        <td>${alert.alert_date}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewAlertDetails(${alert.id})">View Details</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Update alerts cards
        function updateAlertsCards() {
            const criticalCount = currentAlerts.filter(a => a.severity === 'critical').length;
            const highCount = currentAlerts.filter(a => a.severity === 'high').length;
            const mediumCount = currentAlerts.filter(a => a.severity === 'medium').length;

            document.getElementById('criticalCount').textContent = criticalCount;
            document.getElementById('highCount').textContent = highCount;
            document.getElementById('mediumCount').textContent = mediumCount;
            document.getElementById('totalAlerts').textContent = currentAlerts.length;
        }

        // View alert details
        function viewAlertDetails(alertId) {
            const alert = currentAlerts.find(a => a.id == alertId);
            if (!alert) {
                showAlert('Alert not found', 'warning');
                return;
            }

            showAlert(`Alert Details: ${alert.department} is ${alert.over_percent.toFixed(1)}% over budget`, 'warning');
        }

        // Update initialize section to load vendors and start polling
        document.addEventListener('DOMContentLoaded', function() {
            // ... existing code ...

            // Load initial data including vendors
            loadBudgets();
            loadAllocations();
            loadTrackingData();
            loadAlerts(); // Add alerts loading
            loadVendors(); // Add vendor loading

            // Start polling for vendor updates (check every 10 seconds)
            startVendorPolling();
        });

        // Event listeners for dynamic functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle create budget form submission
            const createBudgetForm = document.getElementById('createBudgetForm');
            if (createBudgetForm) {
                createBudgetForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const budgetData = {
                        name: formData.get('budgetName'),
                        department: formData.get('department'),
                        start_date: formData.get('startDate'),
                        end_date: formData.get('endDate'),
                        total_amount: parseFloat(formData.get('totalAmount')),
                        description: formData.get('description')
                    };

                    createBudget(budgetData);
                });
            }

            // Handle tracking period change
            const trackingPeriodSelect = document.querySelector('#tracking select');
            if (trackingPeriodSelect) {
                trackingPeriodSelect.addEventListener('change', function() {
                    loadTrackingData();
                });
            }

            // Handle alerts filter change
            const alertsFilter = document.getElementById('alertsFilter');
            if (alertsFilter) {
                alertsFilter.addEventListener('change', function() {
                    renderAlertsTable();
                });
            }
        });

        // Polling function to check for vendor updates
        function startVendorPolling() {
            setInterval(async function() {
                try {
                    // Check if vendor data has been updated
                    const lastUpdate = localStorage.getItem('vendorsLastUpdate');
                    const currentTimestamp = Date.now();

                    // If no last update or if it's been more than 2 seconds since last check,
                    // refresh vendor data to catch any cross-module changes
                    if (!lastUpdate || (currentTimestamp - parseInt(lastUpdate)) > 2000) {
                        await loadVendors();
                    }
                } catch (error) {
                    console.error('Error checking for vendor updates:', error);
                }
            }, 10000); // Check every 10 seconds
        }
    </script>

    

<!-- Create Budget Modal -->
    <div class="modal fade" id="createBudgetModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createBudgetForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="budgetName" class="form-label">Budget Name *</label>
                                    <input type="text" class="form-control" id="budgetName" name="budgetName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="budgetDepartment" class="form-label">Department</label>
                                    <select class="form-select" id="budgetDepartment" name="budgetDepartment">
                                        <option value="">Select Department</option>
                                        <option value="Hotel Operations">Hotel Operations</option>
                                        <option value="Restaurant">Restaurant</option>
                                        <option value="Events">Events</option>
                                        <option value="Finance & Admin">Finance & Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="startDate" class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="endDate" class="form-label">End Date *</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="totalAmount" class="form-label">Total Budget Amount *</label>
                                    <input type="number" class="form-control" id="totalAmount" name="totalAmount" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="budgetVendor" class="form-label">Primary Vendor (Optional)</label>
                                    <select class="form-select" id="budgetVendor" name="budgetVendor">
                                        <option value="">Loading vendors...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="budgetDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="budgetDescription" name="budgetDescription" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="createBudgetForm">Create Budget</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjustment Request Modal -->
    <div class="modal fade" id="adjustmentRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Budget Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="adjustmentRequestForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentType" class="form-label">Adjustment Type *</label>
                                    <select class="form-select" id="adjustmentType" name="adjustmentType" required>
                                        <option value="">Select Type</option>
                                        <option value="increase">Increase Budget</option>
                                        <option value="decrease">Decrease Budget</option>
                                        <option value="transfer">Transfer Funds</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentAmount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="adjustmentAmount" name="adjustmentAmount" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentDepartment" class="form-label">Department *</label>
                                    <select class="form-select" id="adjustmentDepartment" name="adjustmentDepartment" required>
                                        <option value="">Select Department</option>
                                        <option value="Hotel Operations">Hotel Operations</option>
                                        <option value="Restaurant">Restaurant</option>
                                        <option value="Events">Events</option>
                                        <option value="Finance & Admin">Finance & Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentVendor" class="form-label">Related Vendor</label>
                                    <select class="form-select" id="adjustmentVendor" name="adjustmentVendor">
                                        <option value="">Loading vendors...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="adjustmentReason" class="form-label">Reason for Adjustment *</label>
                            <textarea class="form-control" id="adjustmentReason" name="adjustmentReason" rows="4" required placeholder="Please provide detailed reason for this budget adjustment"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="requestedBy" class="form-label">Requested By</label>
                                    <input type="text" class="form-control" id="requestedBy" name="requestedBy" value="Admin" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expectedDate" class="form-label">Effective Date</label>
                                    <input type="date" class="form-control" id="expectedDate" name="expectedDate">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="adjustmentRequestForm">Submit Request</button>
                </div>
            </div>
        </div>

    <!-- Privacy Mode - Hide amounts with asterisks + Eye button -->
    <script src="../includes/privacy_mode.js?v=6"></script>

    <!-- Inactivity Timeout - Blur screen + Auto logout -->
    <script src="../includes/inactivity_timeout.js?v=3"></script>

    </div>
</body>
</html>
    <script src="../includes/inactivity_timeout.js?v=3"></script>
</body>
                            </div>
                            </div>













