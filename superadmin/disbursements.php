<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Department-based Access Control for Disbursements
$auth = new Auth();
$userDepartment = $_SESSION['user']['department'] ?? '';

// Define department permissions for disbursements module
$deptPermissions = [
    'finance' => ['view', 'create', 'edit', 'delete', 'process_claims'],
    'accounting' => ['view', 'create', 'edit', 'delete'],
    'hr' => ['view', 'process_claims', 'upload_vouchers'],
    'procurement' => ['view', 'create', 'upload_vouchers'],
    'admin' => ['view', 'create', 'edit', 'delete', 'process_claims', 'configure'],
];

// Department-based access control (permissive approach)
$userPerms = isset($deptPermissions[$userDepartment]) ? $deptPermissions[$userDepartment] : ['view']; // Default view access
$hasAdminRole = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

// Allow access by default - department restrictions should not block viewing
// Only restrict very specific operations, not the entire module access
if (!$hasAdminRole && empty($userPerms)) {
    // Very permissive: only block if no permissions and not admin (which should never happen now)
    header('Location: ../index.php');
    exit;
}

// Load user permissions
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
    <link rel="icon" type="image/png" href="../logo2.png">
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
        .sidebar.sidebar-collapsed .nav-item .dropdown-toggle {
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
        .sidebar .navbar-brand {
            color: white !important;
            font-weight: bold;
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
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1;
        }
        .sidebar-toggle {
            position: fixed;
            left: 290px;
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
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #495057;
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
        .modal-body {
            padding: 2rem;
        }
        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_navigation.php'; ?>

    <div class="content">
        <!-- Top Navbar -->
        <?php include '../includes/global_navbar.php'; ?>

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
                <button class="nav-link" id="claims-tab" data-bs-toggle="tab" data-bs-target="#claims" type="button" role="tab">
                    <i class="fas fa-receipt me-2"></i>Claims Processing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                    <i class="fas fa-money-check-alt me-2"></i>Payroll Processing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vouchers-tab" data-bs-toggle="tab" data-bs-target="#vouchers" type="button" role="tab">
                    <i class="fas fa-file-invoice me-2"></i>Vouchers & Documentation
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
                        <button class="btn btn-outline-danger me-2" id="bulkDeleteBtn" onclick="bulkDeleteDisbursements()" style="display: none;">
                            <i class="fas fa-trash me-1"></i>Bulk Delete (<span id="selectedCount">0</span>)
                        </button>
                        <button class="btn btn-outline-secondary me-2" onclick="showFilters()"><i class="fas fa-filter me-2"></i>Filter</button>
                        <button class="btn btn-primary" onclick="showAddDisbursementModal()"><i class="fas fa-plus me-2"></i>Add Disbursement</button>
                    </div>
                </div>

                <!-- Filters Section -->
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
                                <th width="30"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"></th>
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
                                <td colspan="8" class="text-center">
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
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#processPaymentModal">
                        <i class="fas fa-plus me-2"></i>Process Payment
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Payment Methods</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary payment-method-btn"><i class="fas fa-money-bill-wave me-2"></i>Cash</button>
                                    <button class="btn btn-outline-primary payment-method-btn"><i class="fas fa-university me-2"></i>Bank Transfer</button>
                                    <button class="btn btn-outline-primary payment-method-btn"><i class="fas fa-credit-card me-2"></i>Check</button>
                                    <button class="btn btn-outline-primary payment-method-btn"><i class="fas fa-mobile-alt me-2"></i>E-wallet</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Payment Processing</h6>
                            </div>
                            <div class="card-body">
                                <p>Select a payment method and fill in the details to process a payment.</p>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#processPaymentModal">
                                    <i class="fas fa-play me-2"></i>Start Processing
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Claims Processing Tab -->
            <div class="tab-pane fade" id="claims" role="tabpanel" aria-labelledby="claims-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">HR3 Claims Processing - From HR3 API</h6>
                    <div>
                        <button class="btn btn-success" onclick="loadClaims()">
                            <i class="fas fa-sync me-2"></i>Load Claims
                        </button>
                    </div>
                </div>



                <div class="table-responsive">
                    <table class="table table-striped" id="claimsTable">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="claimsTableBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="text-muted">Click "Load Claims" to fetch approved claims from HR3 system</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payroll Processing Tab -->
            <div class="tab-pane fade" id="payroll" role="tabpanel" aria-labelledby="payroll-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Payroll Processing</h6>
                    <div>
                        <button class="btn btn-success" onclick="loadPayroll(this)">
                            <i class="fas fa-sync me-2"></i>Load Payroll
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped" id="payrollTable">
                        <thead>
                            <tr>
                                <th>Payroll Period</th>
                                <th>Total Amount</th>
                                <th>Employees</th>
                                <th>Submitted By</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="payrollTableBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="text-muted">Click "Load Payroll" to fetch payroll data from HR4 system</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vouchers & Documentation Tab -->
            <div class="tab-pane fade" id="vouchers" role="tabpanel" aria-labelledby="vouchers-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Payment Vouchers and Documentation</h6>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadVoucherModal">
                        <i class="fas fa-plus me-2"></i>Upload Voucher
                    </button>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-select" id="disbursementFilter" onchange="filterVouchersByDisbursement()">
                            <option value="">All Recent Disbursements</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select">
                            <option value="">All Types</option>
                            <option value="receipt">Receipt</option>
                            <option value="invoice">Invoice</option>
                            <option value="contract">Contract</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-secondary" onclick="refreshVouchers()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped" id="vouchersTable">
                        <thead>
                            <tr>
                                <th>Voucher #</th>
                                <th>Type</th>
                                <th>Disbursement Ref</th>
                                <th>Date</th>
                                <th>File Info</th>
                                <th>Uploaded By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vouchersTableBody">
                            <!-- Vouchers will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports & Analytics Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Disbursement Reports and Analytics</h6>
                    <button class="btn btn-outline-secondary"><i class="fas fa-download me-2"></i>Export Report</button>
                </div>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 id="totalDisbursementsCount">-</h3>
                                <h6 class="text-muted">Total Disbursements</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 id="totalDisbursementsAmount">-</h3>
                                <h6 class="text-muted">Total Amount</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 id="pendingDisbursementsCount">-</h3>
                                <h6 class="text-muted">Pending</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
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
                    <table class="table table-striped" id="auditTable">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Disbursement Ref</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <!-- Audit logs will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Voucher Upload Modal -->
            <div class="modal fade" id="uploadVoucherModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Upload Voucher</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="voucherUploadForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="voucherDisbursementId" class="form-label">Disbursement Reference *</label>
                                    <select class="form-select" id="voucherDisbursementId" name="disbursement_id" required>
                                        <option value="">Select Disbursement</option>
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="voucherType" class="form-label">Voucher Type *</label>
                                    <select class="form-select" id="voucherType" name="voucher_type" required>
                                        <option value="receipt">Receipt</option>
                                        <option value="invoice">Invoice</option>
                                        <option value="contract">Contract</option>
                                        <option value="other">Other Document</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="voucherFile" class="form-label">File *</label>
                                    <input type="file" class="form-control" id="voucherFile" name="voucher_file"
                                           accept="image/*,.pdf" required>
                                    <small class="form-text text-muted">
                                        Supports images (JPG, PNG, GIF) and PDF files (max 5MB)
                                    </small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="uploadVoucher()">
                                <i class="fas fa-upload me-1"></i>Upload Voucher
                            </button>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>

    <!-- Disbursement Modal -->
    <div class="modal fade" id="disbursementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes"></textarea>
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
    <div class="modal fade" id="processPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="paymentType">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="processPayee" class="form-label">Payee *</label>
                            <input type="text" class="form-control" id="processPayee" placeholder="Supplier/Vendor Name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="paymentDate" class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" id="paymentDate" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="paymentMethodModal" class="form-label">Payment Method *</label>
                            <select class="form-select" id="paymentMethodModal" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="ewallet">E-wallet</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processAmount" class="form-label">Amount *</label>
                            <input type="number" class="form-control" id="processAmount" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="paymentReference" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="paymentReference" placeholder="Check # or Transaction ID">
                    </div>
                    <div class="mb-3">
                        <label for="processDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="processDescription" rows="3" placeholder="Payment description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="paymentAttachments" multiple>
                        <small class="form-text text-muted">Upload invoice, receipt, or approval documents</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processPayment()">Process Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../includes/alert-modal.js"></script>
    <script src="disbursements-js.php"></script>
    <script>
        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            // Default state is collapsed (consistent with other admin pages)
            const isCollapsed = localStorage.getItem('sidebarCollapsed') !== 'false';
            if (isCollapsed) {
                sidebar.classList.add('sidebar-collapsed');
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                // Default: sidebar remains expanded
                sidebar.classList.remove('sidebar-collapsed');
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }

            // Load initial data
            loadDisbursements();
            loadVendors();
        });

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
        }

    </script>

    <!-- HR3 Claims Processing Functions -->
    <script>
        // Wait for DOM to be fully loaded before defining functions
    window.addEventListener('DOMContentLoaded', function() {
        // Auto-load HR3 claims when Claims Processing tab is activated
        const claimsTab = document.getElementById('claims-tab');
        if (claimsTab) {
            claimsTab.addEventListener('shown.bs.tab', function() {
                // Check if claims table is empty (no claims loaded yet)
                const claimsTableBody = document.getElementById('claimsTableBody');
                if (claimsTableBody && claimsTableBody.children.length === 1) {
                    const firstChild = claimsTableBody.children[0];
                    if (firstChild && firstChild.tagName === 'TR' && firstChild.textContent.includes('Click "Load Claims"')) {
                        // Auto-load claims if not already loaded
                        window.loadClaims();
                    }
                }
            });
        }
        window.loadClaims = async function() {
            const btn = (typeof event !== 'undefined' && event.target)
                ? event.target.closest('button')
                : document.querySelector('button[onclick="loadClaims()"]');
            const originalText = btn ? btn.innerHTML : '';

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading Claims...';
            }

            try {
                const response = await fetch('../api/integrations.php?action=execute&integration_name=hr3&action_name=getApprovedClaims', {
                    method: 'GET',
                    credentials: 'include' // Include cookies for session
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                let responseText = await response.text();

                // Handle PHP warnings/errors that appear before JSON
                if (responseText.includes('{"success":') || responseText.includes('{"error":')) {
                    // Extract JSON from mixed HTML/JSON response
                    const jsonStart = responseText.indexOf('{"success":') !== -1 ?
                        responseText.indexOf('{"success":') :
                        responseText.indexOf('{"error":');
                    if (jsonStart !== -1) {
                        responseText = responseText.substring(jsonStart);
                    }
                }

                const result = JSON.parse(responseText);

                if (Array.isArray(result) && result.length > 0) {
                    window.displayHR3Claims(result);
                } else if (result.success || result.result) {
                    window.displayHR3Claims(result.result || result);
                } else {
                    window.showAlert('Error loading claims: ' + (result.error || 'No claims found'), 'danger');
                }
            } catch (error) {
                console.error('HR3 Claims loading error:', error);
                window.showAlert('Error loading claims: ' + error.message, 'danger');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        };

        window.loadHR3Claims = function() {
            // Backward compatibility - calls the new loadClaims function
            return window.loadClaims();
        };

        function normalizeClaimsPayload(claims) {
            if (Array.isArray(claims)) {
                return claims;
            }
            if (claims && Array.isArray(claims.result)) {
                return claims.result;
            }
            if (claims && Array.isArray(claims.data)) {
                return claims.data;
            }
            if (claims && Array.isArray(claims.claims)) {
                return claims.claims;
            }
            return [];
        }

        window.displayHR3Claims = function(claims) {
            const tbody = document.getElementById('claimsTableBody');
            const normalizedClaims = normalizeClaimsPayload(claims);

            if (!normalizedClaims || normalizedClaims.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No claims yet</td></tr>';
                return;
            }

            tbody.innerHTML = '';

            // Filter only approved claims and sort by created_at descending
            const approvedClaims = normalizedClaims.filter(claim => claim.status === 'Approved')
                                        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            if (approvedClaims.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No approved claims ready for payment processing</td></tr>';
                return;
            }

            approvedClaims.forEach(claim => {
                const amount = parseFloat(claim.total_amount || 0);
                const statusBadge = claim.status === 'Approved' ? '<span class="badge bg-success">Approved</span>' : '<span class="badge bg-secondary">' + claim.status + '</span>';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${claim.claim_id}</strong></td>
                    <td>${claim.employee_name}</td>
                    <td>${claim.status === 'Approved' ? 'Approved Claim' : claim.status}</td>
                    <td><strong>₱${amount.toFixed(2)}</strong> ${claim.currency_code ? '(' + claim.currency_code + ')' : ''}</td>
                    <td>${window.formatDate(claim.created_at)}</td>
                    <td>${claim.remarks || 'No remarks'}</td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="processHR3Claim('${claim.claim_id}', '${claim.employee_name}', ${amount}, '${claim.remarks || ''}', '${claim.currency_code || 'PHP'}')">
                            <i class="fas fa-money-bill-wave me-1"></i>Process Payment
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        };

        window.processHR3Claim = async function(claimId, employeeName, amount, description, currency) {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

            let hr3SyncResult = null;

            try {
                // Step 1: Update HR3 claim status first
                try {
                    hr3SyncResult = await window.markHR3ClaimAsPaid(claimId);
                } catch (hr3Error) {
                    hr3SyncResult = { success: false, error: hr3Error.message };
                }

                // Step 2: Create disbursement record
                const disbursementData = {
                    disbursement_date: new Date().toISOString().split('T')[0],
                    amount: amount,
                    payment_method: 'bank_transfer', // Default payment method
                    reference_number: `HR3-CLAIM-${claimId}`,
                    payee: employeeName,
                    purpose: `HR3 Claim Payment: ${description}`,
                    notes: `Processed from HR3 claim ${claimId} - Status changed to "Paid"`
                };

                // Log HR3 claim processing to audit trail
                try {
                    await fetch('../api/audit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        credentials: 'include',
                        body: new URLSearchParams({
                            action: 'log',
                            table_name: 'hr3_claims',
                            record_id: claimId,
                            action_type: 'processed_payment',
                            description: `Processed HR3 claim payment for ${employeeName} (₱${amount}) - Claim ID: ${claimId}`,
                            old_values: JSON.stringify({ status: 'Approved' }),
                            new_values: JSON.stringify({
                                status: 'Paid',
                                disbursement_created: true,
                                processed_by: 'Current User',
                                amount: amount,
                                employee: employeeName
                            })
                        })
                    });
                } catch (auditError) {
                    console.warn('HR3 claim audit logging failed:', auditError);
                    // Don't fail the main operation if audit logging fails
                }

                const response = await fetch('../api/disbursements.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(disbursementData)
                });

                const result = await response.json();

                if (result.success) {
                    if (hr3SyncResult && !hr3SyncResult.success) {
                        window.showAlert('HR3 system update failed (disbursement still created).', 'danger');
                    }

                    // Remove the processed claim row
                    btn.closest('tr').remove();

                    // Always refresh disbursements records (enhancement for visibility)
                    setTimeout(() => {
                        loadDisbursements();

                        // Show additional notification on Disbursements Records tab
                        const recordsTabLink = document.getElementById('records-tab');
                        if (recordsTabLink) {
                            // Add visual indicator that records were updated
                            recordsTabLink.innerHTML += ' <span class="badge bg-success">🔄 Updated</span>';
                            setTimeout(() => {
                                recordsTabLink.innerHTML = recordsTabLink.innerHTML.replace(' <span class="badge bg-success">🔄 Updated</span>', '');
                            }, 3000);
                        }

                        // If user is on records tab, also update the tab badge
                        if (document.getElementById('records-tab').classList.contains('active')) {
                            // No success notifications.
                        }
                    }, 500); // Small delay to ensure database commit
                } else {
                    window.showAlert('Error processing claim: ' + (result.error || 'Unknown error'), 'danger');
                }
            } catch (error) {
                window.showAlert('Error: ' + error.message, 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        };

        window.markHR3ClaimAsPaid = async function(claimId) {
            // This would call the HR3 API to update claim status to "Paid"
            // Implementation depends on HR3 API capabilities
            const response = await fetch('../api/integrations.php?action=execute&integration_name=hr3&action_name=updateClaimStatus', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    claim_id: claimId,
                    status: 'Paid'
                })
            });

            const result = await response.json();
            return result;
        };

        window.testHR3Connection = async function() {
            // Show loading
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            const originalClass = btn.className;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
            btn.className = 'btn btn-warning';

            try {
                // First, get an actual claim from the HR3 API to test with
                const claimsResponse = await fetch('../api/integrations.php?action=execute&integration_name=hr3&action_name=getApprovedClaims', {
                    method: 'GET'
                });
                const claimsData = await claimsResponse.json();

                if (!claimsData.success || !claimsData.data || claimsData.data.length === 0) {
                    window.showAlert('No approved claims available from HR3 API to test with', 'danger');
                    btn.innerHTML = '<i class="fas fa-sync me-2"></i>Test 2-Way Sync';
                    btn.className = 'btn btn-info';
                    return;
                }

                // Use the first available claim for testing
                const testClaimId = claimsData.data[0].claim_id;

                // Test claim status update with the actual claim ID
                const response = await fetch('../api/integrations.php?action=execute&integration_name=hr3&action_name=updateClaimStatus', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        claim_id: testClaimId,
                        status: 'Paid'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // No success notifications.
                } else {
                    // Build comprehensive error message with solutions
                    let errorMessage = '❌ HR3 Connection FAILED: ' + result.error + '\n\n';

                    // HTTP 405 specific solution
                    if (result.http_code === 405 && result.detailed_solution) {
                        errorMessage += '🔧 EXACT FIX REQUIRED (HTTP 405):\n';
                        errorMessage += 'Choose your HR3 web server and apply the configuration:\n\n';

                        if (result.detailed_solution.apache_htaccess) {
                            errorMessage += '📄 APACHE (.htaccess):\n';
                            errorMessage += result.detailed_solution.apache_htaccess + '\n\n';
                        }

                        if (result.detailed_solution.nginx_location) {
                            errorMessage += '🌐 NGINX:\n';
                            errorMessage += result.detailed_solution.nginx_location + '\n\n';
                        }

                        if (result.detailed_solution.apache_vhost) {
                            errorMessage += '🖥️ APACHE VHOST:\n';
                            errorMessage += result.detailed_solution.apache_vhost + '\n';
                        }

                        errorMessage += '\n✨ After applying, click "Test HR3 Connection" again.';
                    }

                    // Generic solution
                    errorMessage += '\n💡 If above doesn\'t work, also check:\n';
                    errorMessage += '• Enable PUT support in web server configuration\n';
                    errorMessage += '• Check PHP always_populate_raw_post_data setting\n';
                    errorMessage += '• Verify file permissions on HR3 server\n';

                    window.showAlert(errorMessage, 'danger');
                }

            } catch (error) {
                window.showAlert('❌ Error testing HR3 connection: ' + error.message, 'danger');
            } finally {
                // Restore button
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.className = originalClass;
            }
        };

        window.showAlert = function(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Insert at top of content area
            const content = document.querySelector('.content');
            content.insertBefore(alertDiv, content.firstChild);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        };

        window.formatDate = function(dateString) {
            if (!dateString) return 'N/A';
            try {
                return new Date(dateString).toLocaleDateString();
            } catch (e) {
                return dateString;
            }
        };
    });
    </script>

    <!-- HR4 Payroll Processing Functions -->
    <script>
        // Wait for DOM to be fully loaded before defining functions
    window.addEventListener('DOMContentLoaded', function() {

        window.loadPayroll = async function(buttonEl) {
            const btn = buttonEl && buttonEl.closest ? buttonEl.closest('button') : null;
            const originalText = btn ? btn.innerHTML : '';

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading Payroll...';
            }

            try {
                // Use the integration API to fetch payroll data
                const response = await fetch('../api/integrations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'execute',
                        integration_name: 'hr4',
                        action_name: 'getPayrollData'
                    }),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success && result.result) {
                    window.displayHR4Payroll(result.result);
                    // No success notifications.
                } else {
                    window.showAlert('Error loading payroll: ' + (result.error || 'No payroll data found'), 'danger');
                }
            } catch (error) {
                console.error('HR4 Payroll loading error:', error);
                window.showAlert('Error loading payroll: ' + error.message, 'danger');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        };

        window.displayHR4Payroll = function(payrollData) {
            const tbody = document.getElementById('payrollTableBody');

            if (!payrollData || payrollData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No payroll data found in HR4 system</td></tr>';
                return;
            }

            tbody.innerHTML = '';

            payrollData.forEach(payroll => {
                const totalAmount = parseFloat(payroll.total_amount || payroll.net_pay || 0);
                const submittedAt = payroll.submitted_at ? new Date(payroll.submitted_at).toLocaleString() : 'N/A';
                const rawStatus = payroll.status || '';
                let statusText = payroll.display_status || rawStatus || 'Unknown';
                if (['approved', 'rejected'].includes(String(rawStatus).toLowerCase())) {
                    statusText = rawStatus;
                }
                const statusKey = String(statusText).toLowerCase();
                const canApprove = Boolean(payroll.can_approve) || ['processed', 'success', 'pending', 'pending approval', 'for approval'].includes(statusKey);

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${payroll.period_display || payroll.payroll_period || 'N/A'}</strong></td>
                    <td><strong class="text-success">PHP ${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                    <td>${payroll.employee_count || 'N/A'}</td>
                    <td>${payroll.submitted_by || 'N/A'}</td>
                    <td>${submittedAt}</td>
                    <td><span class="badge ${canApprove ? 'bg-info' : 'bg-secondary'}">${statusText}</span></td>
                    <td>
                        ${canApprove ? `
                            <button class="btn btn-success btn-sm me-2" onclick="updatePayrollApproval(this, '${payroll.payroll_id}', 'approve')">
                                <i class="fas fa-check me-1"></i>Approve
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="updatePayrollApproval(this, '${payroll.payroll_id}', 'reject')">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        ` : '<span class="text-muted">N/A</span>'}
                    </td>
                `;
                tbody.appendChild(row);
            });
        };

        window.updatePayrollApproval = async function(buttonEl, payrollId, action) {
            const btn = buttonEl && buttonEl.closest ? buttonEl.closest('button') : null;
            const originalText = btn ? btn.innerHTML : '';

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
            }

            try {
                let rejectionReason = '';
                if (action === 'reject') {
                    rejectionReason = prompt('Provide rejection reason (required for reject):', '');
                    if (rejectionReason === null || rejectionReason.trim() === '') {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                        return;
                    }
                }

                const response = await fetch('../api/integrations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'execute',
                        integration_name: 'hr4',
                        action_name: 'updatePayrollStatus',
                        id: payrollId,
                        approval_action: action,
                        rejection_reason: rejectionReason,
                        params: JSON.stringify({
                            id: payrollId,
                            action: action,
                            rejection_reason: rejectionReason
                        })
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // No success notifications.
                    window.loadPayroll();
                } else {
                    window.showAlert('Error updating payroll: ' + (result.error || 'Unknown error'), 'danger');
                }
            } catch (error) {
                window.showAlert('Error: ' + error.message, 'danger');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        };

        // Auto-load payroll data on page load
        window.loadPayroll();
    });
    </script>

    <!-- Privacy Mode - Hide amounts with asterisks + Eye button -->
    <script src="../includes/privacy_mode.js?v=8"></script>

    <!-- Inactivity Timeout - Blur screen + Auto logout -->
    <script src="../includes/inactivity_timeout.js?v=3"></script>
<script src="../includes/navbar_datetime.js"></script>

</body>
</html>
    <script src="../includes/inactivity_timeout.js?v=3"></script>
<script src="../includes/navbar_datetime.js"></script>



