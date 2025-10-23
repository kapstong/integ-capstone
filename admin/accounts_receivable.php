<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch summary data
try {
    // Total customers
    $totalCustomers = $db->query("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")->fetch()['count'];

    // Outstanding receivables
    $outstandingReceivables = $db->query("SELECT COALESCE(SUM(balance), 0) as total FROM invoices WHERE status IN ('sent', 'overdue')")->fetch()['total'];

    // Overdue amount
    $overdueAmount = $db->query("SELECT COALESCE(SUM(balance), 0) as total FROM invoices WHERE status = 'overdue' AND due_date < CURDATE()")->fetch()['total'];

    // Collections this month
    $collectionsThisMonth = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments_received WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())")->fetch()['total'];

} catch (Exception $e) {
    $totalCustomers = 0;
    $outstandingReceivables = 0;
    $overdueAmount = 0;
    $collectionsThisMonth = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Accounts Receivable</title>
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

        /* Invoice Items Styling */
        .invoice-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .invoice-item:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
                <a class="nav-link" href="general_ledger.php">
                    <i class="fas fa-book me-2"></i><span>General Ledger</span>
                </a>
                <i class="fas fa-chevron-right" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu" aria-expanded="true" style="cursor: pointer; color: white; padding: 5px 10px;"></i>
                <div class="collapse show" id="generalLedgerMenu">
                    <div class="submenu">
                        <a class="nav-link" href="accounts_payable.php">
                            <i class="fas fa-credit-card me-2"></i><span>Accounts Payable</span>
                        </a>
                        <a class="nav-link active" href="accounts_receivable.php">
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
                <span class="navbar-brand mb-0 h1 me-4">Accounts Receivable</span>
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

        <!-- Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h5 class="card-title">Total Customers</h5>
                        <p class="card-text display-6"><?php echo number_format($totalCustomers); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-2"></i>
                        <h5 class="card-title">Outstanding Receivables</h5>
                        <p class="card-text display-6">₱<?php echo number_format($outstandingReceivables, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                        <h5 class="card-title">Overdue</h5>
                        <p class="card-text display-6">₱<?php echo number_format($overdueAmount, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                        <h5 class="card-title">Collections This Month</h5>
                        <p class="card-text display-6">₱<?php echo number_format($collectionsThisMonth, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="arTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Customer Records
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab">
                    <i class="fas fa-file-invoice me-2"></i>Invoices
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="aging-tab" data-bs-toggle="tab" data-bs-target="#aging" type="button" role="tab">
                    <i class="fas fa-clock me-2"></i>Aging Report
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="collections-tab" data-bs-toggle="tab" data-bs-target="#collections" type="button" role="tab">
                    <i class="fas fa-money-bill-wave me-2"></i>Collections
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adjustments-tab" data-bs-toggle="tab" data-bs-target="#adjustments" type="button" role="tab">
                    <i class="fas fa-balance-scale me-2"></i>Adjustments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="arTabContent">
            <!-- Customer Records Tab -->
            <div class="tab-pane fade show active" id="customers" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Customer Records</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            <i class="fas fa-plus me-1"></i>Add Customer
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="customersTable">
                                <thead>
                                    <tr>
                                        <th>Customer ID</th>
                                        <th>Company Name</th>
                                        <th>Contact Person</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Credit Limit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Customer data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoices Tab -->
            <div class="tab-pane fade" id="invoices" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Invoices</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                            <i class="fas fa-plus me-1"></i>Create Invoice
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select class="form-select" id="invoiceStatusFilter">
                                    <option value="">All Status</option>
                                    <option value="draft">Draft</option>
                                    <option value="sent">Sent</option>
                                    <option value="paid">Paid</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="invoiceDateFrom">
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="invoiceDateTo">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-secondary" onclick="filterInvoices()">Filter</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="invoicesTable">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Invoice data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aging Report Tab -->
            <div class="tab-pane fade" id="aging" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Aging of Accounts Receivable</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select class="form-select" id="agingPeriod">
                                    <option value="30">30 Days</option>
                                    <option value="60">60 Days</option>
                                    <option value="90">90 Days</option>
                                    <option value="120">120+ Days</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary" onclick="generateAgingReport()">Generate Report</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="agingTable">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Current</th>
                                        <th>1-30 Days</th>
                                        <th>31-60 Days</th>
                                        <th>61-90 Days</th>
                                        <th>90+ Days</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Aging data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Collections Tab -->
            <div class="tab-pane fade" id="collections" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Collections</h5>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            <i class="fas fa-plus me-1"></i>Record Payment
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="collectionsTable">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Customer</th>
                                        <th>Invoice #</th>
                                        <th>Payment Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Payment data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adjustments Tab -->
            <div class="tab-pane fade" id="adjustments" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Adjustments & Credit Memos</h5>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addAdjustmentModal">
                            <i class="fas fa-plus me-1"></i>Add Adjustment
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="adjustmentsTable">
                                <thead>
                                    <tr>
                                        <th>Adjustment ID</th>
                                        <th>Type</th>
                                        <th>Customer</th>
                                        <th>Invoice #</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Adjustment data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="reports-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-peso-sign fa-2x mb-3 text-muted"></i>
                                        <h6>Total Receivables</h6>
                                        <h3>₱<?php echo number_format($outstandingReceivables, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="reports-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                                        <h6>Overdue Amount</h6>
                                        <h3>₱<?php echo number_format($overdueAmount, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="reports-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock fa-2x mb-3 text-info"></i>
                                        <h6>Collections This Month</h6>
                                        <h3>₱<?php echo number_format($collectionsThisMonth, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <button class="btn btn-primary me-3" onclick="exportReport('receivables')">
                                <i class="fas fa-download me-2"></i>Export Receivables Report
                            </button>
                            <button class="btn btn-secondary me-3" onclick="exportReport('collections')">
                                <i class="fas fa-download me-2"></i>Export Collections Report
                            </button>
                            <button class="btn btn-info" onclick="exportReport('aging')">
                                <i class="fas fa-download me-2"></i>Export Aging Report
                            </button>
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

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="customerForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customerName" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="customerName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contactPerson" class="form-label">Contact Person *</label>
                                    <input type="text" class="form-control" id="contactPerson" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customerEmail" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="customerEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customerPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="customerPhone">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customerAddress" class="form-label">Address</label>
                                    <textarea class="form-control" id="customerAddress" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="creditLimit" class="form-label">Credit Limit</label>
                                    <input type="number" class="form-control" id="creditLimit" step="0.01" min="0">
                                </div>
                                <div class="mb-3">
                                    <label for="customerStatus" class="form-label">Status</label>
                                    <select class="form-select" id="customerStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Invoice Modal -->
    <div class="modal fade" id="addInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="invoiceForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="invoiceCustomer" class="form-label">Customer *</label>
                                    <select class="form-select" id="invoiceCustomer" required>
                                        <option value="">Select Customer</option>
                                        <!-- Customer options will be loaded here -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="invoiceNumber" class="form-label">Invoice Number *</label>
                                    <input type="text" class="form-control" id="invoiceNumber" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="invoiceDate" class="form-label">Invoice Date *</label>
                                    <input type="date" class="form-control" id="invoiceDate" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="dueDate" class="form-label">Due Date *</label>
                                    <input type="date" class="form-control" id="dueDate" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="invoiceStatus" class="form-label">Status</label>
                                    <select class="form-select" id="invoiceStatus">
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="paid">Paid</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Invoice Items</label>
                            <div id="invoiceItems">
                                <div class="row mb-2 invoice-item">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" placeholder="Description" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control quantity" placeholder="Qty" step="1" min="1" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control unit-price" placeholder="Unit Price" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control line-total" placeholder="Total" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm remove-item">Remove</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="addItem">Add Item</button>
                        </div>
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="mb-2 d-flex justify-content-between">
                                    <strong>Subtotal:</strong>
                                    <span id="subtotal">$0.00</span>
                                </div>
                                <div class="mb-2 d-flex justify-content-between">
                                    <strong>Tax (10%):</strong>
                                    <span id="tax">$0.00</span>
                                </div>
                                <div class="mb-2 d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <span id="total">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="paymentForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="paymentCustomer" class="form-label">Customer *</label>
                                    <select class="form-select" id="paymentCustomer" required>
                                        <option value="">Select Customer</option>
                                        <!-- Customer options will be loaded here -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="paymentInvoice" class="form-label">Invoice</label>
                                    <select class="form-select" id="paymentInvoice">
                                        <option value="">Select Invoice (Optional)</option>
                                        <!-- Invoice options will be loaded here -->
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="paymentDate" class="form-label">Payment Date *</label>
                                    <input type="date" class="form-control" id="paymentDate" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="paymentAmount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="paymentAmount" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="paymentMethod" class="form-label">Payment Method *</label>
                                    <select class="form-select" id="paymentMethod" required>
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="paymentReference" class="form-label">Reference/Check Number</label>
                            <input type="text" class="form-control" id="paymentReference">
                        </div>
                        <div class="mb-3">
                            <label for="paymentNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="paymentNotes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Adjustment Modal -->
    <div class="modal fade" id="addAdjustmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="adjustmentForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentType" class="form-label">Adjustment Type *</label>
                                    <select class="form-select" id="adjustmentType" required>
                                        <option value="credit_memo">Credit Memo</option>
                                        <option value="debit_memo">Debit Memo</option>
                                        <option value="write_off">Write Off</option>
                                        <option value="discount">Discount</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentCustomer" class="form-label">Customer *</label>
                                    <select class="form-select" id="adjustmentCustomer" required>
                                        <option value="">Select Customer</option>
                                        <!-- Customer options will be loaded here -->
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentInvoice" class="form-label">Related Invoice</label>
                                    <select class="form-select" id="adjustmentInvoice">
                                        <option value="">Select Invoice (Optional)</option>
                                        <!-- Invoice options will be loaded here -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adjustmentAmount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="adjustmentAmount" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="adjustmentReason" class="form-label">Reason *</label>
                            <textarea class="form-control" id="adjustmentReason" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="adjustmentDate" class="form-label">Adjustment Date *</label>
                            <input type="date" class="form-control" id="adjustmentDate" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Save Adjustment</button>
                    </div>
                </form>
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

            // Initialize AR system
            initializeARSystem();
        });

        // AR System Functions
        function initializeARSystem() {
            loadCustomers();
            loadInvoices();
            loadPayments();
            loadAdjustments();
            loadReportsData();
            setupEventListeners();
        }

        function setupEventListeners() {
            // Customer form
            document.getElementById('customerForm').addEventListener('submit', handleCustomerSubmit);

            // Invoice form
            document.getElementById('invoiceForm').addEventListener('submit', handleInvoiceSubmit);
            document.getElementById('addItem').addEventListener('click', addInvoiceItem);

            // Payment form
            document.getElementById('paymentForm').addEventListener('submit', handlePaymentSubmit);

            // Adjustment form
            document.getElementById('adjustmentForm').addEventListener('submit', handleAdjustmentSubmit);

            // Invoice calculations
            document.addEventListener('input', handleInvoiceCalculations);
        }

        // Customer Functions
        async function loadCustomers() {
            try {
                const response = await fetch('api/customers.php');

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response is not JSON');
                }

                const data = await response.json();

                const tbody = document.querySelector('#customersTable tbody');
                tbody.innerHTML = '';

                if (response.ok) {
                    if (Array.isArray(data)) {
                        data.forEach(customer => {
                            const row = `
                                <tr>
                                    <td>${customer.customer_code || ''}</td>
                                    <td>${customer.company_name || ''}</td>
                                    <td>${customer.contact_person || ''}</td>
                                    <td>${customer.email || ''}</td>
                                    <td>${customer.phone || ''}</td>
                                    <td>₱${parseFloat(customer.credit_limit || 0).toLocaleString()}</td>
                                    <td><span class="badge bg-${customer.status === 'active' ? 'success' : 'secondary'}">${customer.status || 'unknown'}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editCustomer(${customer.id})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(${customer.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.innerHTML += row;
                        });

                        // Update customer dropdowns
                        updateCustomerDropdowns(data);
                    } else {
                        throw new Error('Invalid response format');
                    }
                } else {
                    // Handle API error
                    if (data.error) {
                        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">${data.error}</td></tr>`;
                        showAlert(data.error, 'warning');
                    } else {
                        throw new Error('API returned an error');
                    }
                }
            } catch (error) {
                console.error('Error loading customers:', error);
                const tbody = document.querySelector('#customersTable tbody');
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Error loading customers. Please try again.</td></tr>';
                showAlert('Error loading customers: ' + error.message, 'danger');
            }
        }

        async function handleCustomerSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const customerData = Object.fromEntries(formData);

            try {
                const response = await fetch('api/customers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(customerData)
                });

                const result = await response.json();

                if (result.success) {
                    // Close modal and reload data
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addCustomerModal'));
                    modal.hide();
                    loadCustomers();
                    showAlert('Customer saved successfully', 'success');

                    // Reset form
                    e.target.reset();
                } else {
                    showAlert(result.error || 'Error saving customer', 'danger');
                }
            } catch (error) {
                console.error('Error saving customer:', error);
                showAlert('Error saving customer', 'danger');
            }
        }

        async function editCustomer(id) {
            try {
                const response = await fetch(`api/customers.php?id=${id}`);
                const customer = await response.json();

                if (customer) {
                    // Populate form with customer data
                    document.getElementById('customerName').value = customer.company_name;
                    document.getElementById('contactPerson').value = customer.contact_person || '';
                    document.getElementById('customerEmail').value = customer.email || '';
                    document.getElementById('customerPhone').value = customer.phone || '';
                    document.getElementById('customerAddress').value = customer.address || '';
                    document.getElementById('creditLimit').value = customer.credit_limit;
                    document.getElementById('customerStatus').value = customer.status;

                    // Change form to update mode
                    const form = document.getElementById('customerForm');
                    form.setAttribute('data-mode', 'update');
                    form.setAttribute('data-id', id);

                    // Update modal title
                    document.querySelector('#addCustomerModal .modal-title').textContent = 'Edit Customer';

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('addCustomerModal'));
                    modal.show();
                }
            } catch (error) {
                console.error('Error loading customer:', error);
                showAlert('Error loading customer', 'danger');
            }
        }

        async function deleteCustomer(id) {
            if (confirm('Are you sure you want to delete this customer?')) {
                try {
                    const response = await fetch(`api/customers.php?id=${id}`, {
                        method: 'DELETE'
                    });

                    const result = await response.json();

                    if (result.success) {
                        loadCustomers();
                        showAlert('Customer deleted successfully', 'success');
                    } else {
                        showAlert(result.error || 'Error deleting customer', 'danger');
                    }
                } catch (error) {
                    console.error('Error deleting customer:', error);
                    showAlert('Error deleting customer', 'danger');
                }
            }
        }

        // Invoice Functions
        function loadInvoices() {
            // Mock data - replace with actual API calls
            const invoices = [
                { number: 'INV-001', customer: 'ABC Corp', date: '2025-09-01', dueDate: '2025-09-30', amount: 15000, status: 'paid' },
                { number: 'INV-002', customer: 'XYZ Ltd', date: '2025-09-15', dueDate: '2025-10-15', amount: 8750, status: 'sent' },
                { number: 'INV-003', customer: 'ABC Corp', date: '2025-09-20', dueDate: '2025-09-25', amount: 5200, status: 'overdue' }
            ];

            const tbody = document.querySelector('#invoicesTable tbody');
            tbody.innerHTML = '';

            invoices.forEach(invoice => {
                const statusClass = invoice.status === 'paid' ? 'success' : invoice.status === 'overdue' ? 'danger' : 'warning';
                const statusDotClass = invoice.status === 'paid' ? 'status-paid' : invoice.status === 'overdue' ? 'status-overdue' : invoice.status === 'sent' ? 'status-sent' : 'status-draft';
                const row = `
                    <tr>
                        <td>${invoice.number}</td>
                        <td>${invoice.customer}</td>
                        <td>${invoice.date}</td>
                        <td>${invoice.dueDate}</td>
                        <td>₱${invoice.amount.toLocaleString()}</td>
                        <td>
                            <div class="status-indicator ${statusDotClass}">
                                <span class="status-dot"></span>
                                <span class="badge bg-${statusClass}">${invoice.status}</span>
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="viewInvoice('${invoice.number}')" title="View Invoice">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success me-1" onclick="sendInvoice('${invoice.number}')" title="Send Invoice">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteInvoice('${invoice.number}')" title="Delete Invoice">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        function handleInvoiceSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const invoiceData = Object.fromEntries(formData);

            // Calculate total from invoice items
            const items = document.querySelectorAll('.invoice-item');
            let total = 0;
            items.forEach(item => {
                const lineTotal = parseFloat(item.querySelector('.line-total').value) || 0;
                total += lineTotal;
            });
            invoiceData.total = total;

            // Mock save - replace with actual API call
            console.log('Saving invoice:', invoiceData);

            // Close modal and reload data
            const modal = bootstrap.Modal.getInstance(document.getElementById('addInvoiceModal'));
            modal.hide();
            loadInvoices();

            // Reset form
            e.target.reset();
            document.getElementById('invoiceItems').innerHTML = `
                <div class="row mb-2 invoice-item">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Description" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control quantity" placeholder="Qty" step="1" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control unit-price" placeholder="Unit Price" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control line-total" placeholder="Total" readonly>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm remove-item">Remove</button>
                    </div>
                </div>
            `;
            updateInvoiceTotals();
        }

        function addInvoiceItem() {
            const itemHtml = `
                <div class="row mb-2 invoice-item">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Description" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control quantity" placeholder="Qty" step="1" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control unit-price" placeholder="Unit Price" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control line-total" placeholder="Total" readonly>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm remove-item">Remove</button>
                    </div>
                </div>
            `;
            document.getElementById('invoiceItems').insertAdjacentHTML('beforeend', itemHtml);
        }

        function handleInvoiceCalculations(e) {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('unit-price')) {
                const item = e.target.closest('.invoice-item');
                const quantity = parseFloat(item.querySelector('.quantity').value) || 0;
                const unitPrice = parseFloat(item.querySelector('.unit-price').value) || 0;
                const lineTotal = quantity * unitPrice;
                item.querySelector('.line-total').value = lineTotal.toFixed(2);
                updateInvoiceTotals();
            }

            // Handle remove item
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('.invoice-item').remove();
                updateInvoiceTotals();
            }
        }

        function updateInvoiceTotals() {
            const items = document.querySelectorAll('.invoice-item');
            let subtotal = 0;
            items.forEach(item => {
                const lineTotal = parseFloat(item.querySelector('.line-total').value) || 0;
                subtotal += lineTotal;
            });

            const tax = subtotal * 0.10;
            const total = subtotal + tax;

            document.getElementById('subtotal').textContent = `₱${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `₱${tax.toFixed(2)}`;
            document.getElementById('total').textContent = `₱${total.toFixed(2)}`;
        }

        function filterInvoices() {
            // Mock filter - replace with actual implementation
            loadInvoices();
        }

        // Payment Functions
        function loadPayments() {
            // Mock data - replace with actual API calls
            const payments = [
                { id: 'P001', customer: 'ABC Corp', invoice: 'INV-001', date: '2025-09-05', amount: 15000, method: 'bank_transfer', reference: 'CHK-12345' },
                { id: 'P002', customer: 'XYZ Ltd', invoice: 'INV-002', date: '2025-09-20', amount: 5000, method: 'check', reference: 'CHK-67890' }
            ];

            const tbody = document.querySelector('#collectionsTable tbody');
            tbody.innerHTML = '';

            payments.forEach(payment => {
                const row = `
                    <tr>
                        <td>${payment.id}</td>
                        <td>${payment.customer}</td>
                        <td>${payment.invoice}</td>
                        <td>${payment.date}</td>
                        <td>₱${payment.amount.toLocaleString()}</td>
                        <td>${payment.method.replace('_', ' ').toUpperCase()}</td>
                        <td>${payment.reference}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="viewPayment('${payment.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePayment('${payment.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        function handlePaymentSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const paymentData = Object.fromEntries(formData);

            // Mock save - replace with actual API call
            console.log('Saving payment:', paymentData);

            // Close modal and reload data
            const modal = bootstrap.Modal.getInstance(document.getElementById('addPaymentModal'));
            modal.hide();
            loadPayments();

            // Reset form
            e.target.reset();
        }

        // Adjustment Functions
        function loadAdjustments() {
            // Mock data - replace with actual API calls
            const adjustments = [
                { id: 'ADJ-001', type: 'credit_memo', customer: 'ABC Corp', invoice: 'INV-001', amount: 500, reason: 'Damaged goods', date: '2025-09-10' },
                { id: 'ADJ-002', type: 'discount', customer: 'XYZ Ltd', invoice: 'INV-002', amount: 250, reason: 'Early payment discount', date: '2025-09-18' }
            ];

            const tbody = document.querySelector('#adjustmentsTable tbody');
            tbody.innerHTML = '';

            adjustments.forEach(adjustment => {
                const typeLabel = adjustment.type.replace('_', ' ').toUpperCase();
                const row = `
                    <tr>
                        <td>${adjustment.id}</td>
                        <td>${typeLabel}</td>
                        <td>${adjustment.customer}</td>
                        <td>${adjustment.invoice}</td>
                        <td>₱${adjustment.amount.toLocaleString()}</td>
                        <td>${adjustment.reason}</td>
                        <td>${adjustment.date}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="viewAdjustment('${adjustment.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAdjustment('${adjustment.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        function handleAdjustmentSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const adjustmentData = Object.fromEntries(formData);

            // Mock save - replace with actual API call
            console.log('Saving adjustment:', adjustmentData);

            // Close modal and reload data
            const modal = bootstrap.Modal.getInstance(document.getElementById('addAdjustmentModal'));
            modal.hide();
            loadAdjustments();

            // Reset form
            e.target.reset();
        }

        // Aging Report Functions
        function generateAgingReport() {
            // Mock data - replace with actual API calls
            const agingData = [
                { customer: 'ABC Corp', current: 0, days30: 0, days60: 0, days90: 5200, total: 5200 },
                { customer: 'XYZ Ltd', current: 3750, days30: 0, days60: 0, days90: 0, total: 3750 }
            ];

            const tbody = document.querySelector('#agingTable tbody');
            tbody.innerHTML = '';

            agingData.forEach(data => {
                const row = `
                    <tr>
                        <td>${data.customer}</td>
                        <td>₱${data.current.toLocaleString()}</td>
                        <td>₱${data.days30.toLocaleString()}</td>
                        <td>₱${data.days60.toLocaleString()}</td>
                        <td>₱${data.days90.toLocaleString()}</td>
                        <td><strong>₱${data.total.toLocaleString()}</strong></td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Reports Functions
        function loadReportsData() {
            // Mock data - replace with actual calculations
            const totalReceivablesEl = document.getElementById('totalReceivables');
            const overdueAmountEl = document.getElementById('overdueAmount');

            if (totalReceivablesEl) {
                totalReceivablesEl.textContent = '₱24,950.00';
            }
            if (overdueAmountEl) {
                overdueAmountEl.textContent = '₱5,200.00';
            }
        }

        function exportReport(type) {
            // Mock export - replace with actual implementation
            alert(`Exporting ${type} report...`);
        }

        // Utility Functions
        function updateCustomerDropdowns(customers) {
            const dropdowns = ['invoiceCustomer', 'paymentCustomer', 'adjustmentCustomer'];
            dropdowns.forEach(id => {
                const select = document.getElementById(id);
                if (select) {
                    select.innerHTML = '<option value="">Select Customer</option>';
                    customers.forEach(customer => {
                        select.innerHTML += `<option value="${customer.id}">${customer.name}</option>`;
                    });
                }
            });
        }

        // Utility Functions
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

        // Placeholder functions for actions
        function viewInvoice(id) { alert(`View invoice ${id}`); }
        function sendInvoice(id) { alert(`Send invoice ${id}`); }
        function deleteInvoice(id) { if (confirm('Delete invoice?')) console.log('Delete', id); }
        function viewPayment(id) { alert(`View payment ${id}`); }
        function deletePayment(id) { if (confirm('Delete payment?')) console.log('Delete', id); }
        function viewAdjustment(id) { alert(`View adjustment ${id}`); }
        function deleteAdjustment(id) { if (confirm('Delete adjustment?')) console.log('Delete', id); }
    </script>
</body>
</html>
