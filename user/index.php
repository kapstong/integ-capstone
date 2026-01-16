<?php
require_once '../includes/auth.php';
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Financial Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/enhanced-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    background: linear-gradient(135deg, #f8fafc 0%, #e8ecf7 100%);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
.sidebar {
    height: 100vh;
    max-height: 100vh;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 2rem;
    background: linear-gradient(180deg, #0f1c49 0%, #1b2f73 50%, #15265e 100%);
    color: white;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    width: 300px;
    z-index: 1000;
    transition: transform 0.3s ease, width 0.3s ease;
    box-shadow: 4px 0 20px rgba(15, 28, 73, 0.15);
    border-right: 2px solid rgba(212, 175, 55, 0.2);
}
.sidebar.sidebar-collapsed {
    width: 120px;
}
.sidebar.sidebar-collapsed span {
    display: none;
}
.sidebar.sidebar-collapsed .nav-link {
    padding: 12px;
    text-align: center;
    justify-content: center;
}
.sidebar.sidebar-collapsed .navbar-brand {
    text-align: center;
}
.sidebar .nav-link {
    color: rgba(255,255,255,0.85);
    padding: 14px 24px;
    border-radius: 12px;
    margin: 4px 16px;
    font-size: 1.05em;
    font-weight: 500;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 12px;
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
.sidebar .nav-link i {
    font-size: 1.3em;
    width: 24px;
    text-align: center;
}
.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.12);
    color: white;
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
.sidebar .navbar-brand {
    color: white !important;
    font-weight: 800;
    padding: 24px 20px;
    border-bottom: 2px solid #d4af37;
    background: rgba(0, 0, 0, 0.2);
}
.sidebar .navbar-brand img {
    height: 50px;
    width: auto;
    max-width: 100%;
    transition: height 0.3s ease;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3));
}
.sidebar.sidebar-collapsed .navbar-brand img {
    height: 70px;
}
.content {
    margin-left: 120px;
    padding: 20px;
    transition: margin-left 0.3s ease;
    position: relative;
    z-index: 1;
}
.sidebar-toggle {
    position: fixed;
    left: 110px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: white;
    font-size: 1.5em;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    border: 2px solid #d4af37;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: left 0.3s ease, background-color 0.3s ease, transform 0.2s ease;
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(15, 28, 73, 0.3);
}
.sidebar-toggle:hover {
    background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
    color: #0f1c49;
    transform: translateY(-50%) scale(1.1);
}
.toggle-btn {
    display: none;
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
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    color: white;
    border-bottom: 3px solid #d4af37;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(15, 28, 73, 0.1);
}
.card-body {
    padding: 2rem;
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
.btn-success {
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    color: white;
    border: 1px solid rgba(212, 175, 55, 0.3);
}
.btn-success:hover {
    background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
    color: #0f1c49;
    border-color: #d4af37;
}
.stats-card {
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(15, 28, 73, 0.2);
    border: 2px solid rgba(212, 175, 55, 0.2);
    transition: all 0.3s ease;
}
.stats-card:hover {
    border-color: #d4af37;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.3);
    transform: translateY(-2px);
}
.stats-card h3 {
    font-size: 2em;
    margin-bottom: 5px;
}
.stats-card p {
    margin: 0;
    opacity: 0.9;
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
    .stats-card h3 {
        font-size: 1.5em;
    }
}
</style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/loading_screen.php'; ?>

    <div class="sidebar sidebar-collapsed" id="sidebar">
        <div class="p-3">
            <h5 class="navbar-brand"><img src="atieralogo.png" alt="Atiera Logo" style="height: 100px;"></h5>
            <hr style="border-top: 2px solid white; margin: 10px 0;">
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
            </a>
            <a class="nav-link" href="tasks.php">
                <i class="fas fa-tasks me-2"></i><span>My Tasks</span>
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-chevron-left" id="sidebarArrow"></i>
    </div>

    <div class="content">
        <?php include '../includes/global_navbar.php'; ?>

        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card" style="background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%); border: none; box-shadow: 0 8px 25px rgba(30, 41, 54, 0.3); color: white;">
                    <div class="card-body py-5">
                        <div class="row align-items-center">
                            <div class="col-lg-8 text-center text-lg-start">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-user-check fa-2x"></i>
                                    </div>
                                    <div>
                                        <h2 class="mb-1" style="color: white; font-weight: 700;">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user']['username']); ?></strong></h2>
                                        <p class="mb-0" style="color: rgba(255,255,255,0.8);">Ready to handle your daily tasks efficiently</p>
                                    </div>
                                </div>
                                <p class="mb-4" style="color: rgba(255,255,255,0.9); font-size: 1.1rem; line-height: 1.6;">
                                    Your staff dashboard for managing daily operations and tracking progress.
                                    Stay organized and productive with our streamlined interface.
                                </p>
                                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                                    <button class="btn btn-primary btn-lg px-4">
                                        <i class="fas fa-plus me-2"></i>Log Activity
                                    </button>
                                    <button class="btn btn-outline-primary btn-lg px-4">
                                        <i class="fas fa-tasks me-2"></i>View Tasks
                                    </button>
                                    <button class="btn btn-outline-info btn-lg px-4">
                                        <i class="fas fa-clock me-2"></i>Time Tracking
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-4 text-center mt-4 mt-lg-0">
                                <div class="position-relative">
                                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                        <i class="fas fa-user-tie fa-4x text-primary"></i>
                                    </div>
                                    <div class="position-absolute" style="top: -10px; right: -10px;">
                                        <span class="badge bg-primary rounded-pill px-3 py-2">
                                            <i class="fas fa-check me-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h5 class="text-white mb-1">Today's Status</h5>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="p-2">
                                                <i class="fas fa-tasks text-primary fa-lg mb-1"></i>
                                                <div class="small text-white-50">Tasks</div>
                                                <div class="fw-bold text-primary">0</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2">
                                                <i class="fas fa-clock text-info fa-lg mb-1"></i>
                                                <div class="small text-white-50">Hours</div>
                                                <div class="fw-bold text-info">0</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true"><i class="fas fa-tachometer-alt me-1"></i>Overview</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tasks-tab" data-bs-toggle="tab" href="#tasks" role="tab" aria-controls="tasks" aria-selected="false"><i class="fas fa-tasks me-1"></i>My Tasks</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="activity-tab" data-bs-toggle="tab" href="#activity" role="tab" aria-controls="activity" aria-selected="false"><i class="fas fa-history me-1"></i>Activity</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="dashboardTabsContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                                <!-- Key Metrics Row -->
                                <div class="row mb-4">
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-tasks fa-2x text-primary me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">0</h5>
                                                        <small class="text-muted">Active Tasks</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-clock fa-2x text-primary me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">0h</h5>
                                                        <small class="text-muted">Hours Today</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="d-flex align-items-center justify-content-center mb-2">
                                                    <i class="fas fa-check-circle fa-2x text-info me-2"></i>
                                                    <div>
                                                        <h5 class="mb-0">0</h5>
                                                        <small class="text-muted">Completed This Week</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Activity and Quick Actions -->
                                <div class="row">
                                    <div class="col-lg-8 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-history text-primary me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Recent Activity</h6>
                                                    <small class="text-muted">Your latest actions and updates</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center py-5">
                                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                    <h6 class="text-muted">No Recent Activity</h6>
                                                    <p class="text-muted small">Your recent activities will appear here</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center">
                                                <i class="fas fa-bolt text-primary me-3 fa-lg"></i>
                                                <div>
                                                    <h6 class="mb-0">Quick Actions</h6>
                                                    <small class="text-muted">Common tasks</small>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-outline-primary">
                                                        <i class="fas fa-plus me-2"></i>New Task
                                                    </button>
                                                    <button class="btn btn-outline-info">
                                                        <i class="fas fa-clock me-2"></i>Start Timer
                                                    </button>
                                                    <button class="btn btn-outline-warning">
                                                        <i class="fas fa-calendar me-2"></i>Schedule
                                                    </button>
                                                    <button class="btn btn-outline-secondary">
                                                        <i class="fas fa-file-alt me-2"></i>Report Issue
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Tasks Tab -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
                                <div class="text-center py-5">
                                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">Task Management</h6>
                                    <p class="text-muted small">Your assigned tasks will be displayed here</p>
                                </div>
                            </div>
                            <!-- Activity Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                                <div class="text-center py-5">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">Activity Log</h6>
                                    <p class="text-muted small">Your activity history will appear here</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            sidebar.classList.toggle('sidebar-collapsed');
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            if (isCollapsed) {
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }
        }

    </script>

    <!-- Inactivity Timeout -->
    <script src="../includes/inactivity_timeout.js"></script>
<script src="../includes/navbar_datetime.js"></script>
</body>
</html>
</html>