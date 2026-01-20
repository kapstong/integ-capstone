<?php
/**
 * Super Admin Navigation Component
 * Modern sidebar navigation with permission-based menu items
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
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar.sidebar-collapsed {
        width: 80px;
    }

    /* Sidebar Header/Logo Section */
    .sidebar-nav-logo {
        padding: 24px 20px;
        border-bottom: 2px solid #d4af37;
        background: rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80px;
    }

    .sidebar-nav-logo img {
        height: 36px;
        width: auto;
        max-width: 100%;
        transition: height 0.3s ease;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }

    .sidebar.sidebar-collapsed .sidebar-nav-logo img {
        height: 28px;
    }

    /* Menu Container */
    .sidebar-nav-menu {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 1.5rem 0.75rem;
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
        gap: 12px;
        padding: 14px 24px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
        border-radius: 12px;
        margin: 4px 16px;
        position: relative;
        overflow: hidden;
    }

    .sidebar-nav-link::before {
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

    .sidebar-nav-link i {
        font-size: 18px;
        width: 20px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-nav-link:hover {
        color: white;
        background: rgba(255, 255, 255, 0.12);
        transform: translateX(4px);
    }

    .sidebar-nav-link:hover::before {
        transform: scaleY(1);
    }

    .sidebar-nav-link.active {
        background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
        color: #0f1c49;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .sidebar-nav-link.active::before {
        display: none;
    }

    /* Chevron for expandable items */
    .collapse-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.85em;
        transition: transform 0.3s ease;
        padding: 0.5rem 0.75rem;
    }

    .collapse-toggle[aria-expanded="true"] {
        transform: translateY(-50%) rotate(90deg);
    }

    /* Submenu */
    .sidebar-nav-submenu {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        margin-left: 1.75rem;
        border-left: 2px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-nav-submenu .sidebar-nav-link {
        padding: 10px 16px;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.75);
        margin: 2px 0;
        border-radius: 8px;
    }

    .sidebar-nav-submenu .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.08);
    }

    .sidebar-nav-submenu .sidebar-nav-link.active {
        background-color: rgba(212, 175, 55, 0.2);
        color: white;
        font-weight: 600;
    }

    /* Logout Button */
    .sidebar-nav-logout {
        margin-top: auto;
        border-top: 2px solid rgba(255, 255, 255, 0.1);
        padding-top: 1rem;
    }

    .sidebar-nav-logout .sidebar-nav-link {
        color: #dc3545;
        margin-bottom: 1rem;
    }

    .sidebar-nav-logout .sidebar-nav-link:hover {
        background-color: rgba(220, 53, 69, 0.15);
        color: #ff6b6b;
    }

    .sidebar-nav-logout .sidebar-nav-link::before {
        background: #dc3545;
    }

    /* Collapsed State */
    .sidebar.sidebar-collapsed .sidebar-nav-logo img {
        height: 28px;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-link span {
        display: none;
    }

    .sidebar.sidebar-collapsed .sidebar-nav-link {
        justify-content: center;
        padding: 14px 0;
        margin: 4px 8px;
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
        left: 280px;
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
        z-index: 1001;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar-collapse-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white;
    }

    .sidebar-collapse-btn i {
        transition: transform 0.3s ease;
    }

    .sidebar.sidebar-collapsed ~ .sidebar-collapse-btn {
        left: 80px;
    }

    .sidebar.sidebar-collapsed ~ .sidebar-collapse-btn i {
        transform: rotate(180deg);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-collapse-btn {
            display: none;
        }
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
