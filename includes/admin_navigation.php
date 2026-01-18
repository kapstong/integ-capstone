<?php
/**
 * Unified Admin Navigation Component
 * Combines sidebar navigation, top navbar, and permission-based menu items
 */

// Get current page for active state
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$currentPage = basename($scriptName);

// Permission checks
require_once __DIR__ . '/auth.php';
$auth = $auth ?? new Auth();
$canFinancialSetup = $auth->hasPermission('settings.edit');
$canDepartmentView = $auth->hasPermission('departments.view');
$canCashier = $auth->hasAnyPermission(['cashier.operate', 'cashier.view_all']);
$canFinancialReports = $auth->hasPermission('reports.view');

// Check if Financials menu should be shown
$showFinancialsNav = $canFinancialSetup || $canDepartmentView || $canCashier || $canFinancialReports;

// Determine if GL menu should be expanded
$glExpanded = in_array($currentPage, ['general_ledger.php', 'accounts_payable.php', 'accounts_receivable.php'], true);
?>

<!-- Top Navbar -->
<?php include_once __DIR__ . '/global_navbar.php'; ?>

<!-- Sidebar Navigation -->
<div class="sidebar sidebar-collapsed" id="sidebar">
    <div class="p-3">
        <h5 class="navbar-brand"><img src="atieralogo.png" alt="Atiera Logo" style="height: 100px;"></h5>
        <hr style="border-top: 2px solid white; margin: 10px 0;">
    </div>
    <nav class="nav flex-column">
        <!-- Dashboard -->
        <a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">
            <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
        </a>

        <!-- General Ledger Section -->
        <div class="nav-item">
            <a class="nav-link <?php echo ($currentPage === 'general_ledger.php') ? 'active' : ''; ?>" href="general_ledger.php">
                <i class="fas fa-book me-2"></i><span>General Ledger</span>
            </a>
            <i class="fas fa-chevron-right" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu"
               aria-expanded="<?php echo $glExpanded ? 'true' : 'false'; ?>" style="cursor: pointer; color: white; padding: 5px 10px;"></i>
            <div class="collapse <?php echo $glExpanded ? 'show' : ''; ?>" id="generalLedgerMenu">
                <div class="submenu">
                    <a class="nav-link <?php echo ($currentPage === 'accounts_payable.php') ? 'active' : ''; ?>" href="accounts_payable.php">
                        <i class="fas fa-credit-card me-2"></i><span>Accounts Payable</span>
                    </a>
                    <a class="nav-link <?php echo ($currentPage === 'accounts_receivable.php') ? 'active' : ''; ?>" href="accounts_receivable.php">
                        <i class="fas fa-money-bill-wave me-2"></i><span>Accounts Receivable</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Other Main Navigation -->
        <a class="nav-link <?php echo ($currentPage === 'disbursements.php') ? 'active' : ''; ?>" href="disbursements.php">
            <i class="fas fa-money-check me-2"></i><span>Disbursements</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'budget_management.php') ? 'active' : ''; ?>" href="budget_management.php">
            <i class="fas fa-chart-line me-2"></i><span>Budget Management</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>" href="reports.php">
            <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
        </a>

        <!-- Financials Section (if user has permissions) -->
        <?php if ($showFinancialsNav): ?>
        <hr class="my-3">
        <div class="px-3 mb-2">
            <small class="text-white-50 text-uppercase fw-bold">Financials</small>
        </div>
        <?php if ($canFinancialSetup): ?>
        <a class="nav-link <?php echo ($currentPage === 'financial_setup.php') ? 'active' : ''; ?>" href="financials/financial_setup.php">
            <i class="fas fa-cogs me-2"></i><span>Financial Setup</span>
        </a>
        <?php endif; ?>
        <?php if ($canDepartmentView): ?>
        <a class="nav-link <?php echo ($currentPage === 'outlets.php') ? 'active' : ''; ?>" href="financials/outlets.php">
            <i class="fas fa-store me-2"></i><span>Outlets</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'daily_revenue.php') ? 'active' : ''; ?>" href="financials/daily_revenue.php">
            <i class="fas fa-receipt me-2"></i><span>Daily Revenue</span>
        </a>
        <?php endif; ?>
        <?php if ($canCashier): ?>
        <a class="nav-link <?php echo ($currentPage === 'cashier.php') ? 'active' : ''; ?>" href="financials/cashier.php">
            <i class="fas fa-cash-register me-2"></i><span>Cashier Shifts</span>
        </a>
        <?php endif; ?>
        <?php if ($canFinancialReports): ?>
        <a class="nav-link <?php echo ($currentPage === 'financial_reports.php') ? 'active' : ''; ?>" href="financials/financial_reports.php">
            <i class="fas fa-chart-line me-2"></i><span>Financial Reports</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Other Admin Sections -->
        <hr class="my-3">
        <a class="nav-link <?php echo ($currentPage === 'customer_handler.php') ? 'active' : ''; ?>" href="customer_handler.php">
            <i class="fas fa-users me-2"></i><span>Customer Management</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'audit.php') ? 'active' : ''; ?>" href="audit.php">
            <i class="fas fa-history me-2"></i><span>Audit Trail</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'search.php') ? 'active' : ''; ?>" href="search.php">
            <i class="fas fa-search me-2"></i><span>Search</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'two_factor_auth.php') ? 'active' : ''; ?>" href="two_factor_auth.php">
            <i class="fas fa-shield-alt me-2"></i><span>2FA Management</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'backups.php') ? 'active' : ''; ?>" href="backups.php">
            <i class="fas fa-save me-2"></i><span>Backup & Recovery</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'workflows.php') ? 'active' : ''; ?>" href="workflows.php">
            <i class="fas fa-cogs me-2"></i><span>Workflows</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'roles.php') ? 'active' : ''; ?>" href="roles.php">
            <i class="fas fa-user-shield me-2"></i><span>Roles & Permissions</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'settings.php') ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cog me-2"></i><span>Settings</span>
        </a>
        <a class="nav-link <?php echo ($currentPage === 'performance.php') ? 'active' : ''; ?>" href="performance.php">
            <i class="fas fa-tachometer-alt me-2"></i><span>Performance</span>
        </a>

        <!-- Logout -->
        <hr class="my-3">
        <a class="nav-link text-danger" href="../logout.php">
            <i class="fas fa-sign-out-alt me-2"></i><span>Logout</span>
        </a>
    </nav>
</div>

<!-- Sidebar Toggle -->
<div class="sidebar-toggle" onclick="toggleSidebarDesktop()">
    <i class="fas fa-chevron-right" id="sidebarArrow"></i>
</div>

<!-- Main Content Wrapper -->
<div class="content">
    <script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
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
    });
    </script>

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
}
</style>
