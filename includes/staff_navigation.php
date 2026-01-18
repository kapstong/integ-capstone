<?php
/**
 * Staff Navigation Component
 * Limited sidebar navigation for staff users
 */

// Get current page for active state
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$currentPage = basename($scriptName);
?>

<!-- Staff Sidebar Navigation -->
<div class="sidebar sidebar-collapsed" id="sidebar">
    <div class="p-3">
        <h5 class="navbar-brand"><img src="atieralogo.png" alt="Atiera Logo" style="height: 100px;"></h5>
    </div>
    <nav class="nav flex-column">
        <!-- Dashboard -->
        <a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">
            <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
        </a>

        <!-- Tasks -->
        <a class="nav-link <?php echo ($currentPage === 'tasks.php') ? 'active' : ''; ?>" href="tasks.php">
            <i class="fas fa-tasks me-2"></i><span>My Tasks</span>
        </a>

        <!-- Reports -->
        <a class="nav-link <?php echo ($currentPage === 'reports.php') ? 'active' : ''; ?>" href="reports.php">
            <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
        </a>

        <!-- Profile -->
        <a class="nav-link <?php echo ($currentPage === 'profile.php') ? 'active' : ''; ?>" href="profile.php">
            <i class="fas fa-user me-2"></i><span>Profile</span>
        </a>

        <!-- Logout -->
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
