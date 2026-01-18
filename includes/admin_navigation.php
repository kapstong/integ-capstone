<?php
/**
 * Unified Admin Navigation Component
 * Global sidebar navigation with permission-based menu items
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

<!-- Global Sidebar Navigation -->
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
        <?php endif; ?>
        <?php if ($canCashier): ?>
        <a class="nav-link <?php echo ($currentPage === 'cashier.php') ? 'active' : ''; ?>" href="financials/cashier.php">
            <i class="fas fa-cash-register me-2"></i><span>Cashier Shifts</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Admin & Security -->
        <hr class="my-3">
        <a class="nav-link <?php echo ($currentPage === 'roles.php') ? 'active' : ''; ?>" href="roles.php">
            <i class="fas fa-user-shield me-2"></i><span>Roles & Permissions</span>
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
            if (content) content.style.marginLeft = '120px';
            arrow.classList.remove('fa-chevron-left');
            arrow.classList.add('fa-chevron-right');
            toggle.style.left = '110px';
        } else {
            logoImg.src = 'atieralogo.png';
            if (content) content.style.marginLeft = '300px';
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
            if (content) content.style.marginLeft = '120px';
            arrow.classList.remove('fa-chevron-left');
            arrow.classList.add('fa-chevron-right');
            toggle.style.left = '110px';
        } else {
            sidebar.classList.remove('sidebar-collapsed');
            logoImg.src = 'atieralogo.png';
            if (content) content.style.marginLeft = '300px';
            arrow.classList.remove('fa-chevron-right');
            arrow.classList.add('fa-chevron-left');
            toggle.style.left = '290px';
        }
    });
    </script>
