<?php
/**
 * ATIERA Financial Management System - Admin Header Template
 * Common HTML head and navigation for all admin pages
 */
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$adminPos = strpos($scriptName, '/admin/');
$basePath = $adminPos !== false ? substr($scriptName, 0, $adminPos) : '';
$assetBase = rtrim($basePath, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>ATIERA Financial Management System</title>
    <link rel="icon" type="image/png" href="<?php echo $assetBase; ?>/logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $assetBase; ?>/responsive.css" rel="stylesheet">
    <link href="<?php echo $assetBase; ?>/includes/enhanced-ui.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e8ecf7 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            min-height: 100vh;
        }
        .navbar-brand {
            font-weight: 700;
            color: #1b2f73 !important;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .navbar-brand img {
            height: 40px;
            width: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        .sidebar {
            height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
            background: linear-gradient(180deg, #0f1c49 0%, #1b2f73 50%, #15265e 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(15, 28, 73, 0.15);
            border-right: 2px solid rgba(212, 175, 55, 0.2);
        }
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 2px solid #d4af37;
            background: rgba(0, 0, 0, 0.2);
        }
        .sidebar-brand {
            color: white;
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .sidebar-brand img {
            height: 36px;
            width: auto;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 14px 24px;
            margin: 4px 16px;
            border-radius: 12px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #d4af37;
            transform: scaleY(0);
            transition: transform 0.2s ease;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.12);
            transform: translateX(4px);
        }
        .sidebar .nav-link:hover::before {
            transform: scaleY(1);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
            color: #0f1c49;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }
        .sidebar .nav-link.active::before {
            display: none;
        }
        .sidebar .nav-link i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        .content {
            margin-left: 280px;
            padding: 24px;
            min-height: 100vh;
        }
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #1b2f73;
        }
        .page-header h1, .page-header h2 {
            margin: 0;
            color: #1b2f73;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header .breadcrumb {
            margin: 8px 0 0 0;
            background: none;
            padding: 0;
        }
        .page-header .breadcrumb-item {
            color: #64748b;
        }
        .page-header .breadcrumb-item.active {
            color: #1b2f73;
            font-weight: 600;
        }
        .page-header .breadcrumb-item + .breadcrumb-item::before {
            color: #d4af37;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 28, 73, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .card:hover {
            box-shadow: 0 8px 30px rgba(15, 28, 73, 0.12);
            transform: translateY(-2px);
        }
        .card-header {
            background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
            color: white;
            font-weight: 700;
            border-bottom: 3px solid #d4af37;
            padding: 18px 24px;
        }
        .card-header h5, .card-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header i {
            color: #d4af37;
        }
        .card-body {
            padding: 24px;
        }
        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 0;
        }
        .table thead th {
            background: linear-gradient(135deg, #1b2f73 0%, #15265e 100%);
            color: white;
            font-weight: 700;
            border: none;
            padding: 16px 20px;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }
        .table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #d4af37;
        }
        .table tbody tr {
            transition: all 0.15s ease;
        }
        .table tbody tr:nth-child(even) {
            background-color: rgba(27, 47, 115, 0.02);
        }
        .table tbody tr:hover {
            background-color: rgba(27, 47, 115, 0.05);
            transform: scale(1.001);
        }
        .table tbody td {
            padding: 14px 20px;
            vertical-align: middle;
        }
        .btn {
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
    <?php include_once __DIR__ . '/../includes/loading_screen.php'; ?>

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
                <?php if ($auth->hasPermission('departments.view')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'outlets.php') ? 'active' : ''; ?>" href="financials/outlets.php">
                    <i class="fas fa-store me-2"></i>Outlets
                </a>
                <?php endif; ?>
                <?php if ($auth->hasPermission('departments.view')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'daily_revenue.php') ? 'active' : ''; ?>" href="financials/daily_revenue.php">
                    <i class="fas fa-receipt me-2"></i>Daily Revenue
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
                <?php if ($auth->hasPermission('settings.edit')): ?>
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'financial_setup.php') ? 'active' : ''; ?>" href="financials/financial_setup.php">
                    <i class="fas fa-cogs me-2"></i>Financial Setup
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
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
