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
            z-index: 1030;
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

        .modal {
            z-index: 5000;
            pointer-events: auto;
        }

        .modal-backdrop {
            z-index: 4990;
            pointer-events: none;
        }

        .modal-backdrop.show {
            opacity: 0.15;
        }

        .modal-dialog,
        .modal-content,
        .modal * {
            pointer-events: auto;
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

        .forecast-card.black-forecast h3 {
            color: black !important;
        }

        .forecast-card .card-body {
            padding: 2.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_navigation.php'; ?>

    <div class="content">
        <!-- Top Navbar -->
        <?php include '../includes/global_navbar.php'; ?>

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
                                        <tbody id="trackingCyclesBody">
                                              <tr>
                                                  <td colspan="7" class="text-center text-muted">Loading budgets...</td>
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
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-1">Budget Allocation Hub</h6>
                        <small class="text-muted">Manage allocations by department with live utilization alerts.</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-sack-dollar fa-2x mb-3 text-primary"></i>
                            <h6>Total Allocated</h6>
                            <h3 id="allocationSummaryTotal">Loading...</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-arrow-up-right-dots fa-2x mb-3 text-warning"></i>
                            <h6>Utilized</h6>
                            <h3 id="allocationSummaryUtilized">Loading...</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-wallet fa-2x mb-3 text-success"></i>
                            <h6>Remaining</h6>
                            <h3 id="allocationSummaryRemaining">Loading...</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-gauge-high fa-2x mb-3 text-info"></i>
                            <h6>Utilization Rate</h6>
                            <h3 id="allocationSummaryRate">Loading...</h3>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search Department</label>
                                <input type="text" id="allocationSearch" class="form-control" placeholder="Type department name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select id="allocationStatusFilter" class="form-select">
                                    <option value="all">All Statuses</option>
                                    <option value="red">Red (100%)</option>
                                    <option value="orange">Orange (90%)</option>
                                    <option value="light_orange">Light Orange (80%)</option>
                                    <option value="yellow">Yellow (70%)</option>
                                    <option value="good">Good (&lt;70%)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6>Department Allocations</h6>
                            <button class="btn btn-outline-secondary btn-sm" onclick="loadAllocations()">
                                <i class="fas fa-sync me-2"></i>Refresh
                            </button>
                        </div>
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
                                        <th>Utilization %</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="allocationTableBody">
                                      <tr>
                                          <td colspan="8" class="text-center text-muted">Loading allocations...</td>
                                      </tr>
                                  </tbody>
                            </table>
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
                            <option value="last_30_days">Last 30 Days</option>
                              <option value="last_quarter">Last Quarter</option>
                              <option value="year_to_date">Year to Date</option>
                        </select>
                        <button class="btn btn-outline-secondary" id="trackingRefreshButton"><i class="fas fa-sync me-2"></i>Refresh</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-chart-pie fa-2x mb-3 text-primary"></i>
                            <h6>Total Budget</h6>
                            <h3>Loading...</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-coins fa-2x mb-3 text-success"></i>
                            <h6>Actual Spent</h6>
                            <h3>Loading...</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-percentage fa-2x mb-3 text-warning"></i>
                            <h6>Variance</h6>
                            <h3 class="variance-negative">Loading...</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card tracking-card">
                            <i class="fas fa-clock fa-2x mb-3 text-info"></i>
                            <h6>Remaining</h6>
                            <h3>Loading...</h3>
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
                                    <table class="table table-striped" id="trackingBudgetTable">
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
                                          <tbody id="trackingBudgetBody">
                                              <tr>
                                                  <td colspan="6" class="text-center text-muted">Loading tracking data...</td>
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
                            <option value="red">Red (100%)</option>
                            <option value="orange">Orange (90%)</option>
                            <option value="light_orange">Light Orange (80%)</option>
                            <option value="yellow">Yellow (70%)</option>
                        </select>
                        <button class="btn btn-outline-secondary" onclick="loadAlerts()"><i class="fas fa-sync me-2"></i>Refresh</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3 text-danger"></i>
                            <h6>Red (100%)</h6>
                            <h3 id="redCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-exclamation-circle fa-2x mb-3 text-warning"></i>
                            <h6>Orange (90%)</h6>
                            <h3 id="orangeCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-info-circle fa-2x mb-3 text-warning"></i>
                            <h6>Light Orange (80%)</h6>
                            <h3 id="lightOrangeCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-bell fa-2x mb-3 text-secondary"></i>
                            <h6>Yellow (70%)</h6>
                            <h3 id="yellowCount">0</h3>
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
                                        <th>Utilized Amount</th>
                                        <th>Utilization %</th>
                                        <th>Over Amount</th>
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

            <!-- Forecasting removed (moved to Dashboard) -->

            
            <div class="tab-pane fade" id="alerts" role="tabpanel" aria-labelledby="alerts-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Alerts & Notifications</h6>
                    <div>
                        <select class="form-select form-select-sm me-2" style="width: auto;" id="alertsFilter">
                            <option value="all">All Alerts</option>
                            <option value="red">Red (100%)</option>
                            <option value="orange">Orange (90%)</option>
                            <option value="light_orange">Light Orange (80%)</option>
                            <option value="yellow">Yellow (70%)</option>
                        </select>
                        <button class="btn btn-outline-secondary" onclick="loadAlerts()"><i class="fas fa-sync me-2"></i>Refresh</button>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3 text-danger"></i>
                            <h6>Red (100%)</h6>
                            <h3 id="redCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-exclamation-circle fa-2x mb-3 text-warning"></i>
                            <h6>Orange (90%)</h6>
                            <h3 id="orangeCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-info-circle fa-2x mb-3 text-warning"></i>
                            <h6>Light Orange (80%)</h6>
                            <h3 id="lightOrangeCount">0</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="reports-card alert-card">
                            <i class="fas fa-bell fa-2x mb-3 text-secondary"></i>
                            <h6>Yellow (70%)</h6>
                            <h3 id="yellowCount">0</h3>
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
                                        <th>Utilized Amount</th>
                                        <th>Utilization %</th>
                                        <th>Over Amount</th>
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

            <!-- Forecasting removed (moved to Dashboard) -->

            <!-- Reports & Analytics Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Budget Reports & Analytics</h6>
                    <button class="btn btn-outline-secondary" id="exportReportsBtn"><i class="fas fa-download me-2"></i>Export Reports</button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Budget vs Actual Report</h6>
                            </div>
                            <div class="card-body">
                                <p>Detailed variance breakdown by department and category with month-over-month trends.</p>
                                <button class="btn btn-primary" id="generateBudgetVsActualBtn">Generate Report</button>
                                <button class="btn btn-outline-secondary ms-2" id="downloadBudgetVsActualBtn">Download PDF</button>
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
                                <button class="btn btn-primary" id="generateDeptPerformanceBtn">Generate Report</button>
                                <button class="btn btn-outline-secondary ms-2" id="downloadDeptPerformanceBtn">Download PDF</button>
                            </div>
                        </div>
                    </div>
                      
                </div>
                
                <!-- Report Results Section -->
                <div class="row mt-4" id="reportResults" style="display: none;">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 id="reportTitle">Report Results</h6>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary me-2" id="printReportBtn"><i class="fas fa-print me-2"></i>Print</button>
                                    <button class="btn btn-sm btn-outline-secondary" id="closeReportBtn"><i class="fas fa-times me-2"></i>Close</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="reportContent">
                                    <!-- Report content will be loaded here -->
                                </div>
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
                                <tbody id="auditTrailBody">
                                      <tr>
                                          <td colspan="7" class="text-center text-muted">No audit records available.</td>
                                      </tr>
                                  </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../includes/alert-modal.js"></script>
<script>

function getStatusBadge(status) {
    var statusMap = {
        'draft': 'bg-info',
        'pending': 'bg-warning',
        'approved': 'bg-success',
        'active': 'bg-primary',
        'closed': 'bg-secondary',
        'completed': 'bg-secondary',
        'rejected': 'bg-danger'
    };
    var badgeClass = statusMap[status] || 'bg-secondary';
    return '<span class="badge ' + badgeClass + '">' + (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'N/A') + '</span>';
}

function getVarianceStatusBadge(variancePercent) {
    if (variancePercent < 0) return '<span class="badge bg-danger">Over</span>';
    if (variancePercent > 10) return '<span class="badge bg-success">Under</span>';
    return '<span class="badge bg-warning text-dark">On Track</span>';
}
</script>
<script>
    let currentAllocations = [];

    function getAllocationStatusKey(progressPercent) {
        if (progressPercent >= 100) return 'red';
        if (progressPercent >= 90) return 'orange';
        if (progressPercent >= 80) return 'light_orange';
        if (progressPercent >= 70) return 'yellow';
        return 'good';
    }

    function getAllocationStatusBadge(progressPercent) {
        if (progressPercent >= 100) return '<span class="badge bg-danger">Red (100%)</span>';
        if (progressPercent >= 90) return '<span class="badge bg-warning text-dark">Orange (90%)</span>';
        if (progressPercent >= 80) return '<span class="badge bg-warning text-dark">Light Orange (80%)</span>';
        if (progressPercent >= 70) return '<span class="badge bg-warning text-dark">Yellow (70%)</span>';
        return '<span class="badge bg-success">Good</span>';
    }

    function getFilteredAllocations() {
        const search = (document.getElementById('allocationSearch')?.value || '').toLowerCase();
        const statusFilter = document.getElementById('allocationStatusFilter')?.value || 'all';
        return currentAllocations.filter(allocation => {
            const nameMatch = (allocation.department || '').toLowerCase().includes(search);
            if (!nameMatch) return false;
            if (statusFilter === 'all') return true;
            const progressPercent = allocation.total_amount > 0 ? (allocation.utilized_amount / allocation.total_amount) * 100 : 0;
            return getAllocationStatusKey(progressPercent) === statusFilter;
        });
    }

    function renderAllocationsTable() {
        const tbody = document.getElementById('allocationTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';
        const filteredAllocations = getFilteredAllocations();
        if (filteredAllocations.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No allocations found.</td></tr>';
            return;
        }
        filteredAllocations.forEach(allocation => {
            const progressPercent = allocation.total_amount > 0 ? (allocation.utilized_amount / allocation.total_amount) * 100 : 0;
            const statusBadge = getAllocationStatusBadge(progressPercent);
            const row = '<tr>' +
                '<td>' + (allocation.department || 'Unassigned') + '</td>' +
                '<td>PHP ' + (parseFloat(allocation.total_amount || 0).toLocaleString()) + '</td>' +
                '<td>PHP ' + (parseFloat(allocation.reserved_amount || 0).toLocaleString()) + '</td>' +
                '<td>PHP ' + (parseFloat(allocation.utilized_amount || 0).toLocaleString()) + '</td>' +
                '<td>PHP ' + (parseFloat(allocation.remaining || 0).toLocaleString()) + '</td>' +
                '<td>' + (progressPercent.toFixed(1)) + '%</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td><button class="btn btn-sm btn-outline-primary">Adjust</button></td>' +
                '</tr>';
            tbody.innerHTML += row;
        });
    }

    function updateAllocationSummary() {
        const filteredAllocations = getFilteredAllocations();
        const totalAllocated = filteredAllocations.reduce((sum, item) => sum + (parseFloat(item.total_amount) || 0), 0);
        const totalUtilized = filteredAllocations.reduce((sum, item) => sum + (parseFloat(item.utilized_amount) || 0), 0);
        const totalRemaining = filteredAllocations.reduce((sum, item) => sum + (parseFloat(item.remaining) || 0), 0);
        const utilizationRate = totalAllocated > 0 ? (totalUtilized / totalAllocated) * 100 : 0;

        const totalEl = document.getElementById('allocationSummaryTotal');
        const remainingEl = document.getElementById('allocationSummaryRemaining');
        const rateEl = document.getElementById('allocationSummaryRate');
        if (totalEl) totalEl.textContent = 'PHP ' + totalAllocated.toLocaleString();
        if (remainingEl) remainingEl.textContent = 'PHP ' + totalRemaining.toLocaleString();
        if (rateEl) rateEl.textContent = utilizationRate.toFixed(1) + '%';
    }

    async function loadAllocations() {
        try {
            const response = await fetch('../api/budgets.php?action=allocations');
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            currentAllocations = data.allocations || [];
            renderAllocationsTable();
            updateAllocationSummary();
        } catch (error) {
            if (typeof showAlert === 'function') {
                showAlert('Error loading allocations: ' + error.message, 'danger');
            }
        }
    }

    async function loadTrackingData() {
        const tbody = document.getElementById('trackingBudgetBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tracking data unavailable.</td></tr>';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadAllocations();
        const allocationSearch = document.getElementById('allocationSearch');
        if (allocationSearch) allocationSearch.addEventListener('input', () => { renderAllocationsTable(); updateAllocationSummary(); });
        const allocationStatusFilter = document.getElementById('allocationStatusFilter');
        if (allocationStatusFilter) allocationStatusFilter.addEventListener('change', () => { renderAllocationsTable(); updateAllocationSummary(); });
        loadTrackingData();
    });
</script>
</body>
</html>
