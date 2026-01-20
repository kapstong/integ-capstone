<?php
/**
 * Super Admin Navigation Component
 * Full sidebar navigation with permission-based menu items
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

<style>
    /* Sidebar Container */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 300px;
        height: 100vh;
        background-color: #1f2936;
        z-index: 20000;
        overflow-y: auto;
        transition: all 0.3s ease;
        padding-top: 0;
    }

    .sidebar.sidebar-collapsed {
        width: 80px;
    }

    /* Sidebar Logo Section */
    .sidebar-nav-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        min-height: 80px;
    }

    .sidebar-nav-logo img {
        height: 60px;
        width: auto;
        max-width: 100%;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-logo img {
        height: 40px;
    }

    /* Menu Container */
    .sidebar-nav-menu {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 1rem 0;
    }

    /* Menu Item */
    .sidebar-nav-item {
        position: relative;
        margin: 0;
    }

    /* Main Links */
    .sidebar-nav-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 1rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s ease;
        white-space: nowrap;
        border-left: 3px solid transparent;
    }

    .sidebar-nav-link i {
        font-size: 1.1em;
        min-width: 20px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .sidebar-nav-link.active {
        background-color: rgba(32, 201, 151, 0.15);
        color: white;
        border-left-color: #20c997;
    }

    /* Chevron for expandable items */
    .collapse-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.8em;
        transition: transform 0.3s ease;
    }

    .collapse-toggle[aria-expanded="true"] {
        transform: translateY(-50%) rotate(90deg);
    }

    /* Submenu */
    .sidebar-nav-submenu {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding-left: 3rem;
        background-color: rgba(0, 0, 0, 0.2);
    }

    .sidebar-nav-submenu .sidebar-nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .sidebar-nav-submenu .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.08);
    }

    .sidebar-nav-submenu .sidebar-nav-link.active {
        background-color: rgba(32, 201, 151, 0.2);
        color: white;
    }

    /* Logout Button */
    .sidebar-nav-logout {
        margin-top: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 1rem;
    }

    .sidebar-nav-logout .sidebar-nav-link {
        color: #dc3545;
    }

    .sidebar-nav-logout .sidebar-nav-link:hover {
        background-color: rgba(220, 53, 69, 0.1);
    }

    /* Collapsed State */
    .sidebar.sidebar-collapsed .sidebar-nav-link span {
        display: none;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-link {
        justify-content: center;
        padding: 0.75rem 0.5rem;
    }

    .sidebar.sidebar-collapsed .collapse-toggle {
        display: none;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-submenu {
        display: none;
    }

    /* Collapse Button */
    .sidebar-collapse-btn {
        position: fixed;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 36px;
        height: 50px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: rgba(255, 255, 255, 0.7);
        cursor: pointer;
        font-size: 1.1em;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0 8px 8px 0;
        z-index: 20001;
        transition: all 0.2s ease;
    }

    .sidebar-collapse-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white;
    }

    .sidebar-collapse-btn i {
        transition: transform 0.3s ease;
    }

    .sidebar.sidebar-collapsed ~ .sidebar-collapse-btn i {
        transform: rotate(180deg);
    }
</style>

<!-- Sidebar Collapse Button -->
<button class="sidebar-collapse-btn" onclick="toggleSidebarDesktop()" title="Collapse sidebar">
    <i class="fas fa-chevron-left"></i>
</button>

<!-- Global Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-nav-logo">
        <img src="atieralogo.png" alt="Atiera Logo">
    </div>
    <nav class="sidebar-nav-menu">
        <!-- Dashboard -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
        </div>

        <!-- General Ledger Section -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'general_ledger.php') ? 'active' : ''; ?>" href="general_ledger.php">
                <i class="fas fa-book"></i><span>General Ledger</span>
                <i class="fas fa-chevron-right collapse-toggle" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu"
                   aria-expanded="<?php echo $glExpanded ? 'true' : 'false'; ?>" style="position: absolute; right: 1rem;"></i>
            </a>
            <div class="collapse <?php echo $glExpanded ? 'show' : ''; ?>" id="generalLedgerMenu">
                <div class="sidebar-nav-submenu">
                    <a class="sidebar-nav-link <?php echo ($currentPage === 'accounts_payable.php') ? 'active' : ''; ?>" href="accounts_payable.php">
                        <i class="fas fa-credit-card"></i><span>Accounts Payable</span>
                    </a>
                    <a class="sidebar-nav-link <?php echo ($currentPage === 'accounts_receivable.php') ? 'active' : ''; ?>" href="accounts_receivable.php">
                        <i class="fas fa-money-bill-wave"></i><span>Accounts Receivable</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Disbursements -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'disbursements.php') ? 'active' : ''; ?>" href="disbursements.php">
                <i class="fas fa-money-check"></i><span>Disbursements</span>
            </a>
        </div>

        <!-- Budget Management -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'budget_management.php') ? 'active' : ''; ?>" href="budget_management.php">
                <i class="fas fa-chart-line"></i><span>Budget Management</span>
            </a>
        </div>

        <!-- Reports -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar"></i><span>Reports</span>
            </a>
        </div>

        <!-- Logout -->
        <div class="sidebar-nav-item sidebar-nav-logout">
            <a class="sidebar-nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </nav>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }

    function toggleSidebarDesktop() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('sidebar-collapsed');
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    // Initialize sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
        }
    });
</script>

<style>
    .sidebar-nav-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 1rem;
    }

    .sidebar-nav-logo img {
        height: 60px;
        width: auto;
        max-width: 100%;
        transition: height 0.3s ease;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-logo img {
        height: 45px;
    }

    .sidebar-nav-menu {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 0 0.75rem;
    }

    .sidebar-nav-item {
        position: relative;
    }

    .sidebar-nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .sidebar-nav-link i {
        font-size: 1.1em;
        min-width: 24px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.12);
        color: white;
        transform: translateX(4px);
    }

    .sidebar-nav-link.active {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
        color: white;
        border-left: 3px solid #20c997;
        padding-left: calc(1rem - 3px);
        box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .sidebar-nav-item .collapse-toggle {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.85em;
        transition: transform 0.3s ease;
        padding: 0.5rem 0.75rem;
    }

    .sidebar-nav-item .collapse-toggle[aria-expanded="true"] {
        transform: translateY(-50%) rotate(90deg);
    }

    .sidebar-nav-submenu {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        margin-left: 1.75rem;
        border-left: 2px solid rgba(255, 255, 255, 0.1);
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .sidebar-nav-submenu .sidebar-nav-link {
        padding: 0.625rem 0.75rem;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.75);
    }

    .sidebar-nav-submenu .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.08);
    }

    .sidebar-nav-submenu .sidebar-nav-link.active {
        color: white;
        background-color: rgba(255, 255, 255, 0.1);
    }

    .sidebar-nav-logout {
        color: #dc3545 !important;
        margin-top: auto;
        padding-top: 1rem;
        border-top: 2px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar.sidebar-collapsed .sidebar-nav-logout {
        padding-top: 0.5rem;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-link span {
        display: none;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-link {
        justify-content: center;
        padding: 0.875rem;
    }

    .sidebar.sidebar-collapsed .collapse-toggle {
        display: none;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-submenu {
        display: none;
    }

    .sidebar-collapse-btn {
        position: fixed;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: rgba(255, 255, 255, 0.7);
        cursor: pointer;
        font-size: 1.2em;
        padding: 0.75rem 0.5rem;
        transition: all 0.2s ease;
        width: 36px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0 8px 8px 0;
        z-index: 20001;
    }

    .sidebar-collapse-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .sidebar-collapse-btn i {
        transition: transform 0.3s ease;
    }

    .sidebar.sidebar-collapsed ~ .sidebar-collapse-btn {
        left: auto;
    }

    .sidebar.sidebar-collapsed .sidebar-collapse-btn i {
        transform: rotate(180deg);
    }
</style>

<!-- Sidebar Collapse Button -->
<button class="sidebar-collapse-btn" id="sidebarCollapseBtn" onclick="toggleSidebarDesktop()" title="Collapse sidebar">
    <i class="fas fa-chevron-left"></i>
</button>

<!-- Global Sidebar Navigation -->
<div class="sidebar" id="sidebar" style="display:block; left:0; width:300px; z-index:20000; background-color:#1f2936;">
    <div class="sidebar-nav-logo">
        <img src="atieralogo.png" alt="Atiera Logo">
    </div>
    <nav class="sidebar-nav-menu">
        <!-- Dashboard -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
        </div>

        <!-- General Ledger Section -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'general_ledger.php') ? 'active' : ''; ?>" href="general_ledger.php">
                <i class="fas fa-book"></i><span>General Ledger</span>
            </a>
            <i class="fas fa-chevron-right collapse-toggle" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu"
               aria-expanded="<?php echo $glExpanded ? 'true' : 'false'; ?>"></i>
            <div class="collapse <?php echo $glExpanded ? 'show' : ''; ?>" id="generalLedgerMenu">
                <div class="sidebar-nav-submenu">
                    <a class="sidebar-nav-link <?php echo ($currentPage === 'accounts_payable.php') ? 'active' : ''; ?>" href="accounts_payable.php">
                        <i class="fas fa-credit-card"></i><span>Accounts Payable</span>
                    </a>
                    <a class="sidebar-nav-link <?php echo ($currentPage === 'accounts_receivable.php') ? 'active' : ''; ?>" href="accounts_receivable.php">
                        <i class="fas fa-money-bill-wave"></i><span>Accounts Receivable</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Other Main Navigation -->
        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'disbursements.php') ? 'active' : ''; ?>" href="disbursements.php">
                <i class="fas fa-money-check"></i><span>Disbursements</span>
            </a>
        </div>

        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'budget_management.php') ? 'active' : ''; ?>" href="budget_management.php">
                <i class="fas fa-chart-line"></i><span>Budget Management</span>
            </a>
        </div>

        <div class="sidebar-nav-item">
            <a class="sidebar-nav-link <?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar"></i><span>Reports</span>
            </a>
        </div>

        <!-- Logout -->
        <div class="sidebar-nav-item sidebar-nav-logout">
            <a class="sidebar-nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </nav>
</div>

<!-- Sidebar Toggle -->
<div class="sidebar-toggle" onclick="toggleSidebarDesktop()" style="display: none;">
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
        sidebar.classList.toggle('sidebar-collapsed');
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    // Initialize sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
        }
    });
</script>
