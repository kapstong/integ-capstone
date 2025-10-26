<?php
/**
 * ATIERA Financial Management System - Admin Header Template
 * Common HTML head and navigation for all admin pages
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>ATIERA Financial Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../responsive.css" rel="stylesheet">
    <link href="../includes/confidential_mode.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            color: #2c3e50 !important;
        }
        .sidebar {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 24px;
            margin: 4px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
        }
        .content {
            margin-left: 280px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
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
        }
        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1e2936;
            box-shadow: 0 0 0 0.2rem rgba(30, 41, 54, 0.1);
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
            }
        }
    </style>

    <?php include_once __DIR__ . '/../includes/datepicker.php'; ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="navbar-brand text-white mb-4">
                <i class="fas fa-building me-2"></i>ATIERA Admin
            </h4>
            <nav class="nav flex-column">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'accounts_receivable.php') ? 'active' : ''; ?>" href="accounts_receivable.php">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Accounts Receivable
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'accounts_payable.php') ? 'active' : ''; ?>" href="accounts_payable.php">
                    <i class="fas fa-credit-card me-2"></i>Accounts Payable
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'general_ledger.php') ? 'active' : ''; ?>" href="general_ledger.php">
                    <i class="fas fa-book me-2"></i>General Ledger
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'budget_management.php') ? 'active' : ''; ?>" href="budget_management.php">
                    <i class="fas fa-chart-pie me-2"></i>Budget Management
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'customer_handler.php') ? 'active' : ''; ?>" href="customer_handler.php">
                    <i class="fas fa-users me-2"></i>Customer Management
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'disbursements.php') ? 'active' : ''; ?>" href="disbursements.php">
                    <i class="fas fa-money-bill-wave me-2"></i>Disbursements
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'audit.php') ? 'active' : ''; ?>" href="audit.php">
                    <i class="fas fa-history me-2"></i>Audit Trail
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'search.php') ? 'active' : ''; ?>" href="search.php">
                    <i class="fas fa-search me-2"></i>Search
                </a>

                <!-- FINANCIALS Modules -->
                <hr class="my-3">
                <div class="px-3 mb-2">
                    <small class="text-white-50 text-uppercase fw-bold">Financials</small>
                </div>
                <?php if ($auth->hasPermission('departments.view')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'departments.php') ? 'active' : ''; ?>" href="financials/departments.php">
                    <i class="fas fa-building me-2"></i>Departments
                </a>
                <?php endif; ?>
                <?php if ($auth->hasPermission('cashier.operate') || $auth->hasPermission('cashier.view_all')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'cashier.php') ? 'active' : ''; ?>" href="financials/cashier.php">
                    <i class="fas fa-cash-register me-2"></i>Cashier/Collection
                </a>
                <?php endif; ?>
                <?php if ($auth->hasPermission('integrations.view')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'integration_management.php') ? 'active' : ''; ?>" href="financials/integration_management.php">
                    <i class="fas fa-exchange-alt me-2"></i>Integrations
                </a>
                <?php endif; ?>
                <?php if ($auth->hasPermission('reports.usali')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'financial_reports.php') ? 'active' : ''; ?>" href="financials/financial_reports.php">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Financial Reports
                </a>
                <?php endif; ?>

                <hr class="my-3">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'integrations.php') ? 'active' : ''; ?>" href="integrations.php">
                    <i class="fas fa-plug me-2"></i>API Integrations
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'two_factor_auth.php') ? 'active' : ''; ?>" href="two_factor_auth.php">
                    <i class="fas fa-shield-alt me-2"></i>2FA Management
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'backups.php') ? 'active' : ''; ?>" href="backups.php">
                    <i class="fas fa-save me-2"></i>Backup & Recovery
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'workflows.php') ? 'active' : ''; ?>" href="workflows.php">
                    <i class="fas fa-cogs me-2"></i>Workflows
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'roles.php') ? 'active' : ''; ?>" href="roles.php">
                    <i class="fas fa-user-shield me-2"></i>Roles & Permissions
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'performance.php') ? 'active' : ''; ?>" href="performance.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Performance
                </a>
                <hr class="my-3">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </nav>
        </div>
    </div>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="margin-left: 280px;">
        <div class="container-fluid">
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <span class="navbar-brand mb-0 h1"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Panel'; ?></span>
            <div class="d-flex align-items-center ms-auto">
                <div class="dropdown">
                    <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <span><strong><?php echo htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username']); ?></strong></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="admin-profile-settings.php"><i class="fas fa-user me-2"></i>Profile Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="content" style="margin-top: 80px;">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Current Page'; ?></li>
            </ol>
        </nav>
