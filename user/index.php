<?php
require_once '../includes/auth.php';
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}
$pageTitle = 'Staff Dashboard';
include 'header.php';
?>

<!-- Page Content -->

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
</body>
</html>
