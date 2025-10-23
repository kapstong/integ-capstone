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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Disbursements</title>
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
            <a class="nav-link active" href="disbursements.php">
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
                <span class="navbar-brand mb-0 h1 me-4">Disbursements</span>
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
        <ul class="nav nav-tabs mb-4" id="disbursementsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="records-tab" data-bs-toggle="tab" data-bs-target="#records" type="button" role="tab">
                    <i class="fas fa-list me-2"></i>Disbursement Records
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing" type="button" role="tab">
                    <i class="fas fa-credit-card me-2"></i>Payment Processing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vouchers-tab" data-bs-toggle="tab" data-bs-target="#vouchers" type="button" role="tab">
                    <i class="fas fa-file-invoice me-2"></i>Vouchers & Documentation
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="approval-tab" data-bs-toggle="tab" data-bs-target="#approval" type="button" role="tab">
                    <i class="fas fa-check-circle me-2"></i>Approval Workflow
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
        <div class="tab-content" id="disbursementsTabContent">
                            <!-- Disbursement Records Tab -->
                            <div class="tab-pane fade show active" id="records" role="tabpanel" aria-labelledby="records-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Master List of All Disbursements</h6>
                                    <div>
                                        <button class="btn btn-outline-secondary me-2" onclick="showFilters()"><i class="fas fa-filter me-2"></i>Filter</button>
                                        <button class="btn btn-primary" onclick="showAddDisbursementModal()"><i class="fas fa-plus me-2"></i>Add Disbursement</button>
                                    </div>
                                </div>

                                <!-- Filters Section (Hidden by default) -->
                                <div id="filtersSection" class="card mb-3" style="display: none;">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" id="filterStatus">
                                                    <option value="">All Status</option>
                                                    <option value="completed">Completed</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="cancelled">Cancelled</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Date From</label>
                                                <input type="date" class="form-control" id="filterDateFrom">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Date To</label>
                                                <input type="date" class="form-control" id="filterDateTo">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">&nbsp;</label>
                                                <div>
                                                    <button class="btn btn-primary me-2" onclick="applyFilters()">Apply</button>
                                                    <button class="btn btn-outline-secondary" onclick="clearFilters()">Clear</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped" id="disbursementsTable">
                                        <thead>
                                            <tr>
                                                <th>Reference #</th>
                                                <th>Payee</th>
                                                <th>Payment Method</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="disbursementsTableBody">
                                            <tr>
                                                <td colspan="7" class="text-center">
                                                    <div class="loading">Loading disbursements...</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Payment Processing Tab -->
                            <div class="tab-pane fade" id="processing" role="tabpanel" aria-labelledby="processing-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Process Payments and Settlements</h6>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#processPaymentModal"><i class="fas fa-plus me-2"></i>Process Payment</button>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Payment Methods</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-outline-primary"><i class="fas fa-money-bill-wave me-2"></i>Cash</button>
                                                    <button class="btn btn-outline-primary"><i class="fas fa-university me-2"></i>Bank Transfer</button>
                                                    <button class="btn btn-outline-primary"><i class="fas fa-credit-card me-2"></i>Check</button>
                                                    <button class="btn btn-outline-primary"><i class="fas fa-mobile-alt me-2"></i>E-wallet</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Linked Accounts Payable</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>Outstanding payables will be automatically settled upon payment processing.</p>
                                                <button class="btn btn-success"><i class="fas fa-link me-2"></i>Link to AP</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vouchers & Documentation Tab -->
                            <div class="tab-pane fade" id="vouchers" role="tabpanel" aria-labelledby="vouchers-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Payment Vouchers and Documentation</h6>
                                    <button class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Voucher</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Voucher #</th>
                                                <th>Type</th>
                                                <th>Disbursement Ref</th>
                                                <th>Date</th>
                                                <th>Attachments</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>PV-001</td>
                                                <td>Payment Voucher</td>
                                                <td>DIS-001</td>
                                                <td>2025-09-25</td>
                                                <td><i class="fas fa-paperclip"></i> Invoice, Receipt</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">View</button>
                                                    <button class="btn btn-sm btn-outline-secondary">Download</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>CV-001</td>
                                                <td>Check Voucher</td>
                                                <td>DIS-003</td>
                                                <td>2025-09-23</td>
                                                <td><i class="fas fa-paperclip"></i> Bill, Approval</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">View</button>
                                                    <button class="btn btn-sm btn-outline-secondary">Download</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Approval Workflow Tab -->
                            <div class="tab-pane fade" id="approval" role="tabpanel" aria-labelledby="approval-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Approval Workflow Management</h6>
                                    <button class="btn btn-primary"><i class="fas fa-cog me-2"></i>Configure Workflow</button>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Approval Chain</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="text-center">
                                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 50px; height: 50px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                        <small>Requester</small>
                                                    </div>
                                                    <i class="fas fa-arrow-right"></i>
                                                    <div class="text-center">
                                                        <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 50px; height: 50px;">
                                                            <i class="fas fa-user-tie"></i>
                                                        </div>
                                                        <small>Accountant</small>
                                                    </div>
                                                    <i class="fas fa-arrow-right"></i>
                                                    <div class="text-center">
                                                        <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 50px; height: 50px;">
                                                            <i class="fas fa-user-shield"></i>
                                                        </div>
                                                        <small>Manager</small>
                                                    </div>
                                                    <i class="fas fa-arrow-right"></i>
                                                    <div class="text-center">
                                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 50px; height: 50px;">
                                                            <i class="fas fa-user-check"></i>
                                                        </div>
                                                        <small>Admin</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Pending Approvals</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="list-group list-group-flush">
                                                    <a href="#" class="list-group-item list-group-item-action">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h6 class="mb-1">DIS-002</h6>
                                                            <small>2 days ago</small>
                                                        </div>
                                                        <p class="mb-1">Reimbursement - John Doe</p>
                                                        <small>₱2,500.00</small>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reports & Analytics Tab -->
                            <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Disbursement Reports and Analytics</h6>
                                    <button class="btn btn-outline-secondary"><i class="fas fa-download me-2"></i>Export Report</button>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Disbursement Report</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>Summary of all outgoing payments within a period.</p>
                                                <button class="btn btn-primary">Generate Report</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Vendor Payment History</h6>
                                            </div>
                                            <div class="card-body">
                                                <p>Shows all disbursements made to a supplier.</p>
                                                <button class="btn btn-primary">View History</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6>Cash Flow Report (Outflows)</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="cashFlowChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Audit Trail Tab -->
                            <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Audit Trail and Controls</h6>
                                    <button class="btn btn-outline-secondary"><i class="fas fa-filter me-2"></i>Filter Logs</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Disbursement Ref</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>2025-09-25 10:00 AM</td>
                                                <td>Admin</td>
                                                <td>Approved</td>
                                                <td>DIS-001</td>
                                                <td>Payment approved for ABC Suppliers</td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-24 2:30 PM</td>
                                                <td>Accountant</td>
                                                <td>Created</td>
                                                <td>DIS-002</td>
                                                <td>New reimbursement request submitted</td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-23 9:15 AM</td>
                                                <td>Manager</td>
                                                <td>Reviewed</td>
                                                <td>DIS-003</td>
                                                <td>Utility bill payment reviewed</td>
                                            </tr>
                                            <tr>
                                                <td>2025-09-22 4:45 PM</td>
                                                <td>Admin</td>
                                                <td>Rejected</td>
                                                <td>DIS-005</td>
                                                <td>Insufficient documentation</td>
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

        <!-- Disbursement Modal -->
        <div class="modal fade" id="disbursementModal" tabindex="-1" aria-labelledby="disbursementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Disbursement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="disbursementForm">
                            <input type="hidden" id="disbursementId" name="disbursement_id">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="vendorId" class="form-label">Vendor *</label>
                                    <select class="form-select" id="vendorId" name="vendor_id" required>
                                        <option value="">Select Vendor</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="disbursementDate" class="form-label">Disbursement Date *</label>
                                    <input type="date" class="form-control" id="disbursementDate" name="disbursement_date" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="amount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" placeholder="0.00" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="paymentMethod" class="form-label">Payment Method *</label>
                                    <select class="form-select" id="paymentMethod" name="payment_method" required>
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="ewallet">E-wallet</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="referenceNumber" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="referenceNumber" name="reference_number" placeholder="Check # or Transaction ID">
                                </div>
                                <div class="col-md-6">
                                    <label for="billId" class="form-label">Related Bill (Optional)</label>
                                    <select class="form-select" id="billId" name="bill_id">
                                        <option value="">Select Bill</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes or description"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveDisbursement()">Save Disbursement</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Process Payment Modal -->
        <div class="modal fade" id="processPaymentModal" tabindex="-1" aria-labelledby="processPaymentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="processPaymentModalLabel">Process Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="paymentDate" class="form-label">Payment Date</label>
                                    <input type="date" class="form-control" id="paymentDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="paymentReference" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="paymentReference" placeholder="DIS-001" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="payee" class="form-label">Payee</label>
                                <input type="text" class="form-control" id="payee" placeholder="Supplier/Vendor Name" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="paymentType" class="form-label">Payment Type</label>
                                    <select class="form-select" id="paymentType" required>
                                        <option value="">Select Type</option>
                                        <option value="supplier">Supplier Payment</option>
                                        <option value="reimbursement">Reimbursement</option>
                                        <option value="utility">Utility Bill</option>
                                        <option value="payroll">Payroll</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="paymentMethod" class="form-label">Payment Method</label>
                                    <select class="form-select" id="paymentMethod" required>
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="transfer">Bank Transfer</option>
                                        <option value="ewallet">E-wallet</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" rows="3" placeholder="Payment description" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Attachments</label>
                                <input type="file" class="form-control" id="attachments" multiple>
                                <small class="form-text text-muted">Upload invoice, receipt, or approval documents</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary">Process Payment</button>
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
        let currentFilters = {};

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

        // Test PHP connection first
            testPHPConnection().then(() => {
                loadDisbursements();
                loadVendors();
            });
        });

        // Load disbursements from API
        async function loadDisbursements() {
            try {
                const params = new URLSearchParams(currentFilters);
                const response = await fetch(`api/disbursements.php?${params}`, {
                    credentials: 'include'
                });

                if (!response.ok) {
                    // Try to get error message from response
                    try {
                        const errorData = await response.text();
                        throw new Error(`HTTP ${response.status}: ${errorData || response.statusText}`);
                    } catch (e) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, try to parse as text error
                    const errorText = await response.text();
                    throw new Error(errorText || 'Server returned an unexpected response format');
                }

                const data = await response.json();

                if (response.ok) {
                    renderDisbursementsTable(data);
                } else {
                    // Handle API error
                    if (data.error) {
                        const tbody = document.getElementById('disbursementsTableBody');
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading disbursements. Please try again.</td></tr>';
                        showAlert('Error loading disbursements: ' + data.error, 'danger');
                    } else {
                        throw new Error('API returned an error');
                    }
                }
            } catch (error) {
                console.error('Error loading disbursements:', error);
                const tbody = document.getElementById('disbursementsTableBody');
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading disbursements. Please try again.</td></tr>';
                showAlert('Error loading disbursements. Please try again.', 'warning');
            }
        }

        // Render disbursements table
        function renderDisbursementsTable(disbursements) {
            const tbody = document.getElementById('disbursementsTableBody');

            if (disbursements.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No disbursements found</td></tr>';
                return;
            }

            tbody.innerHTML = disbursements.map(d => `
                <tr>
                    <td>${d.disbursement_number || 'N/A'}</td>
                    <td>${d.vendor_name || 'N/A'}</td>
                    <td><span class="badge bg-secondary">${d.payment_method || 'N/A'}</span></td>
                    <td>${formatDate(d.disbursement_date)}</td>
                    <td>₱${parseFloat(d.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>${getStatusBadge(d.status)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewDisbursement(${d.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="editDisbursement(${d.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDisbursement(${d.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Load vendors for dropdown
        async function loadVendors() {
            try {
                const response = await fetch('api/vendors.php', {
                    credentials: 'include'
                });

                if (!response.ok) {
                    // Try to get error message from response
                    try {
                        const errorData = await response.text();
                        throw new Error(`HTTP ${response.status}: ${errorData || response.statusText}`);
                    } catch (e) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, try to parse as text error
                    const errorText = await response.text();
                    throw new Error(errorText || 'Server returned an unexpected response format');
                }

                const data = await response.json();

                if (response.ok) {
                    // Store vendors globally for use in forms
                    window.vendors = data;
                } else {
                    // Handle API error
                    if (data.error) {
                        console.error('Error loading vendors:', data.error);
                        showAlert('Error loading vendors: ' + data.error, 'danger');
                    } else {
                        throw new Error('API returned an error');
                    }
                }
            } catch (error) {
                console.error('Error loading vendors:', error);
                showAlert('Error loading vendors. Please try again.', 'warning');
            }
        }

        // Show/hide filters
        function showFilters() {
            const filtersSection = document.getElementById('filtersSection');
            filtersSection.style.display = filtersSection.style.display === 'none' ? 'block' : 'none';
        }

        // Apply filters
        function applyFilters() {
            currentFilters = {
                status: document.getElementById('filterStatus').value,
                date_from: document.getElementById('filterDateFrom').value,
                date_to: document.getElementById('filterDateTo').value
            };

            // Remove empty filters
            Object.keys(currentFilters).forEach(key => {
                if (!currentFilters[key]) {
                    delete currentFilters[key];
                }
            });

            loadDisbursements();
        }

        // Clear filters
        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            currentFilters = {};
            loadDisbursements();
        }

        // Show add disbursement modal
        function showAddDisbursementModal() {
            // Reset form
            document.getElementById('disbursementForm').reset();
            document.getElementById('disbursementId').value = '';
            document.getElementById('modalTitle').textContent = 'Add Disbursement';

            // Populate vendor dropdown
            populateVendorDropdown();

            // Show modal
            const modalEl = document.getElementById('disbursementModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        // Populate vendor dropdown
        function populateVendorDropdown() {
            const vendorSelect = document.getElementById('vendorId');
            vendorSelect.innerHTML = '<option value="">Select Vendor</option>';

            if (window.vendors) {
                window.vendors.forEach(vendor => {
                    vendorSelect.innerHTML += `<option value="${vendor.id}">${vendor.company_name}</option>`;
                });
            }
        }

        // View disbursement details
        async function viewDisbursement(id) {
            try {
                const response = await fetch(`api/disbursements.php?id=${id}`, {
                    credentials: 'include'
                });
                const data = await response.json();

                if (data.error) {
                    showAlert('Error loading disbursement: ' + data.error, 'danger');
                    return;
                }

                // Show view modal with details
                showDisbursementDetails(data);
            } catch (error) {
                console.error('Error viewing disbursement:', error);
                showAlert('Error loading disbursement details', 'danger');
            }
        }

        // Edit disbursement
        async function editDisbursement(id) {
            try {
                const response = await fetch(`api/disbursements.php?id=${id}`, {
                    credentials: 'include'
                });
                const data = await response.json();

                if (data.error) {
                    showAlert('Error loading disbursement: ' + data.error, 'danger');
                    return;
                }

                // Populate form with data
                populateDisbursementForm(data);
                document.getElementById('modalTitle').textContent = 'Edit Disbursement';

                // Show modal
                const modalEl = document.getElementById('disbursementModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            } catch (error) {
                console.error('Error editing disbursement:', error);
                showAlert('Error loading disbursement for editing', 'danger');
            }
        }

        // Populate form with disbursement data
        function populateDisbursementForm(data) {
            document.getElementById('disbursementId').value = data.id;
            document.getElementById('vendorId').value = data.vendor_id;
            document.getElementById('amount').value = data.amount;
            document.getElementById('paymentMethod').value = data.payment_method;
            document.getElementById('referenceNumber').value = data.reference_number || '';
            document.getElementById('disbursementDate').value = data.disbursement_date;
            document.getElementById('notes').value = data.notes || '';
        }

        // Delete disbursement
        async function deleteDisbursement(id) {
            if (!confirm('Are you sure you want to delete this disbursement?')) {
                return;
            }

            try {
                const response = await fetch(`api/disbursements.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                const data = await response.json();

                if (data.error) {
                    showAlert('Error deleting disbursement: ' + data.error, 'danger');
                    return;
                }

                showAlert('Disbursement deleted successfully', 'success');
                loadDisbursements();
            } catch (error) {
                console.error('Error deleting disbursement:', error);
                showAlert('Error deleting disbursement', 'danger');
            }
        }

        // Save disbursement (create or update)
        async function saveDisbursement() {
            const formData = new FormData(document.getElementById('disbursementForm'));
            const data = Object.fromEntries(formData);

            // Validate required fields
            if (!data.vendor_id || !data.amount || !data.payment_method || !data.disbursement_date) {
                showAlert('Please fill in all required fields', 'warning');
                return;
            }

            try {
                const method = data.disbursement_id ? 'PUT' : 'POST';
                const url = data.disbursement_id
                    ? `api/disbursements.php?id=${data.disbursement_id}`
                    : 'api/disbursements.php';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.error) {
                    showAlert('Error saving disbursement: ' + result.error, 'danger');
                    return;
                }

                showAlert(result.message || 'Disbursement saved successfully', 'success');

                // Close modal and reload data
                const modalEl = document.getElementById('disbursementModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }

                loadDisbursements();
            } catch (error) {
                console.error('Error saving disbursement:', error);
                showAlert('Error saving disbursement', 'danger');
            }
        }

        // Show disbursement details in a modal
        function showDisbursementDetails(data) {
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Reference:</strong> ${data.disbursement_number || 'N/A'}</p>
                        <p><strong>Vendor:</strong> ${data.vendor_name || 'N/A'}</p>
                        <p><strong>Amount:</strong> ₱${parseFloat(data.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        <p><strong>Payment Method:</strong> ${data.payment_method || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date:</strong> ${formatDate(data.disbursement_date)}</p>
                        <p><strong>Reference Number:</strong> ${data.reference_number || 'N/A'}</p>
                        <p><strong>Status:</strong> ${getStatusBadge(data.status)}</p>
                        <p><strong>Notes:</strong> ${data.notes || 'N/A'}</p>
                    </div>
                </div>
            `;

            // Create or update details modal
            let detailsModal = document.getElementById('detailsModal');
            if (!detailsModal) {
                detailsModal = document.createElement('div');
                detailsModal.className = 'modal fade';
                detailsModal.id = 'detailsModal';
                detailsModal.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Disbursement Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="detailsContent"></div>
                        </div>
                    </div>
                `;
                document.body.appendChild(detailsModal);
            }

            document.getElementById('detailsContent').innerHTML = detailsHtml;
            if (detailsModal) {
                const modal = new bootstrap.Modal(detailsModal);
                modal.show();
            }
        }

        // Utility functions
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function getStatusBadge(status) {
            const badges = {
                'completed': '<span class="badge bg-success">Completed</span>',
                'pending': '<span class="badge bg-warning">Pending</span>',
                'cancelled': '<span class="badge bg-danger">Cancelled</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
        }

        // Test PHP connection
        async function testPHPConnection() {
            try {
                const response = await fetch('api/disbursements.php?test=1', {
                    credentials: 'include'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('PHP is not processing files correctly');
                }

                const data = await response.json();
                console.log('PHP test successful:', data);

                if (data.status !== 'PHP is working in API directory') {
                    throw new Error('PHP test failed');
                }

                return true;
            } catch (error) {
                console.error('PHP connection test failed:', error);
                showAlert('PHP connection test failed. Please check your server configuration.', 'danger');
                throw error;
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
    </script>
</body>
</html>
