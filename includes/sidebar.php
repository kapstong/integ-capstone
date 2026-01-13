<?php
/**
 * Global Sidebar Include
 * Displays different menus based on user role and current directory
 */

// Determine if admin or user section
$isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$isUser = strpos($_SERVER['REQUEST_URI'], '/user/') !== false;

// If not clearly admin or user, check session or default
if (!$isAdmin && !$isUser) {
    $isAdmin = isset($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
    $isUser = !$isAdmin;
}

$sidebarCollapsedClass = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'sidebar-collapsed' : '';
?>

<div class="sidebar <?php echo $sidebarCollapsedClass; ?>" id="sidebar">
    <div class="p-3">
        <h5 class="navbar-brand"><img src="<?php echo $isAdmin ? 'atieralogo.png' : '../atieralogo.png'; ?>" alt="Atiera Logo" style="height: 100px;"></h5>
        <hr style="border-top: 2px solid white; margin: 10px 0;">
    </div>
    <nav class="nav flex-column">
        <?php if ($isAdmin): ?>
            <!-- Admin Sidebar Menu -->
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
            </a>
            <div class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'general_ledger.php') ? 'active' : ''; ?>" href="general_ledger.php">
                    <i class="fas fa-book me-2"></i><span>General Ledger</span>
                </a>
                <i class="fas fa-chevron-right" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu" aria-expanded="true" style="cursor: pointer; color: white; padding: 5px 10px;"></i>
                <div class="collapse show" id="generalLedgerMenu">
                    <div class="submenu">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'accounts_payable.php') ? 'active' : ''; ?>" href="accounts_payable.php">
                            <i class="fas fa-credit-card me-2"></i><span>Accounts Payable</span>
                        </a>
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'accounts_receivable.php') ? 'active' : ''; ?>" href="accounts_receivable.php">
                            <i class="fas fa-money-bill-wave me-2"></i><span>Accounts Receivable</span>
                        </a>
                    </div>
                </div>
            </div>
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'disbursements.php') ? 'active' : ''; ?>" href="disbursements.php">
                <i class="fas fa-money-check me-2"></i><span>Disbursements</span>
            </a>
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'budget_management.php') ? 'active' : ''; ?>" href="budget_management.php">
                <i class="fas fa-chart-line me-2"></i><span>Budget Management</span>
            </a>
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
            </a>
            <!-- Add other admin menu items as needed -->
        <?php else: ?>
            <!-- User Sidebar Menu -->
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
            </a>
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'tasks.php') ? 'active' : ''; ?>" href="tasks.php">
                <i class="fas fa-tasks me-2"></i><span>My Tasks</span>
            </a>
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
            </a>
        <?php endif; ?>
    </nav>
</div>

<div class="sidebar-toggle" onclick="toggleSidebarDesktop()">
    <i class="fas fa-chevron-right" id="sidebarArrow"></i>
</div>

<script>
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
        logoImg.src = '<?php echo $isAdmin ? 'atieralogo2.png' : '../atieralogo2.png'; ?>';
        content.style.marginLeft = '120px';
        arrow.classList.remove('fa-chevron-left');
        arrow.classList.add('fa-chevron-right');
        toggle.style.left = '110px';
    } else {
        logoImg.src = '<?php echo $isAdmin ? 'atieralogo.png' : '../atieralogo.png'; ?>';
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
    if (footer) {
        const marginLeft = content.style.marginLeft || '120px';
        footer.style.left = marginLeft;
        footer.style.width = `calc(100% - ${marginLeft})`;
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
        logoImg.src = '<?php echo $isAdmin ? 'atieralogo2.png' : '../atieralogo2.png'; ?>';
        content.style.marginLeft = '120px';
        arrow.classList.remove('fa-chevron-left');
        arrow.classList.add('fa-chevron-right');
        toggle.style.left = '110px';
    } else {
        sidebar.classList.remove('sidebar-collapsed');
        logoImg.src = '<?php echo $isAdmin ? 'atieralogo.png' : '../atieralogo.png'; ?>';
        content.style.marginLeft = '300px';
        arrow.classList.remove('fa-chevron-right');
        arrow.classList.add('fa-chevron-left');
        toggle.style.left = '290px';
    }
    updateFooterPosition();
});
</script>
