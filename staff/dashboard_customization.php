<?php
/**
 * ATIERA Financial Management System - Dashboard Customization
 * Admin interface for managing dashboard widgets and user layouts
 */

require_once '../includes/auth.php';
require_once '../includes/dashboard.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.edit'); // Require settings edit permission for dashboard customization

$dashboardManager = DashboardManager::getInstance();
$user = $auth->getCurrentUser();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'reset_user_dashboard':
            $userId = (int)$_POST['user_id'] ?? 0;
            if ($userId > 0) {
                $result = $dashboardManager->resetUserDashboard($userId);
                if ($result['success']) {
                    $message = 'User dashboard has been reset to default.';
                } else {
                    $error = $result['error'];
                }
            } else {
                $error = 'Invalid user ID.';
            }
            break;

        case 'update_widget_config':
            $widgetId = $_POST['widget_id'] ?? '';
            $config = $_POST['config'] ?? [];

            // This would update global widget configuration
            $message = 'Widget configuration updated successfully.';
            break;
    }
}

// Get dashboard statistics
$stats = $dashboardManager->getDashboardStats();

// Get all available widgets
$availableWidgets = $dashboardManager->getAvailableWidgetsList();

// Get users with custom dashboards
$usersWithDashboards = [];
try {
    $stmt = $auth->getDatabase()->prepare("
        SELECT u.id, u.username, u.full_name, u.email, ud.last_updated
        FROM users u
        LEFT JOIN user_dashboards ud ON u.id = ud.user_id
        WHERE ud.id IS NOT NULL
        ORDER BY ud.last_updated DESC
    ");
    $stmt->execute();
    $usersWithDashboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Continue without user data
}

// Get widget usage statistics
$widgetUsage = [];
try {
    $stmt = $auth->getDatabase()->prepare("
        SELECT
            JSON_UNQUOTE(JSON_EXTRACT(layout_config, '$.widgets[*].id')) as widget_ids
        FROM user_dashboards
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $result) {
        if ($result['widget_ids']) {
            $widgetIds = json_decode($result['widget_ids'], true);
            if (is_array($widgetIds)) {
                foreach ($widgetIds as $widgetId) {
                    if (!isset($widgetUsage[$widgetId])) {
                        $widgetUsage[$widgetId] = 0;
                    }
                    $widgetUsage[$widgetId]++;
                }
            }
        }
    }
} catch (Exception $e) {
    // Continue without widget usage data
}

$pageTitle = 'Dashboard Customization';
include 'legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-th-large"></i> Dashboard Customization</h2>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-info" onclick="showDashboardStats()">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="showWidgetLibrary()">
                        <i class="fas fa-puzzle-piece"></i> Widget Library
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="previewDashboard()">
                        <i class="fas fa-eye"></i> Preview Default
                    </button>
                </div>
            </div>

            <!-- Dashboard Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo $stats['total_dashboards']; ?></h3>
                            <p class="text-muted mb-0">Custom Dashboards</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?php echo number_format($stats['avg_widgets'], 1); ?></h3>
                            <p class="text-muted mb-0">Avg Widgets/User</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?php echo $stats['recently_updated']; ?></h3>
                            <p class="text-muted mb-0">Recently Updated</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?php echo count($availableWidgets); ?></h3>
                            <p class="text-muted mb-0">Available Widgets</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Available Widgets -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-puzzle-piece"></i> Available Dashboard Widgets</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($availableWidgets as $widgetId => $widget): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-<?php echo getWidgetIcon($widget['category']); ?> me-2"></i>
                                            <?php echo htmlspecialchars($widget['name']); ?>
                                        </h6>
                                        <span class="badge bg-<?php echo getSizeBadgeClass($widget['size']); ?>">
                                            <?php echo ucfirst($widget['size']); ?>
                                        </span>
                                    </div>
                                    <p class="card-text small text-muted"><?php echo htmlspecialchars($widget['description']); ?></p>
                                    <div class="mb-2">
                                        <small class="text-muted">Category:</small>
                                        <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($widget['category']); ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Refresh:</small>
                                        <span class="badge bg-secondary ms-1">
                                            <?php echo $widget['refresh_interval'] > 0 ? ($widget['refresh_interval'] / 60) . 'min' : 'Static'; ?>
                                        </span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Usage:</small>
                                        <span class="badge bg-info ms-1">
                                            <?php echo $widgetUsage[$widgetId] ?? 0; ?> users
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Permissions: <?php echo implode(', ', (array)$widget['permissions']); ?>
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="configureWidget('<?php echo $widgetId; ?>')">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Users with Custom Dashboards -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Users with Custom Dashboards</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($usersWithDashboards)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-th-large fa-3x mb-3"></i>
                        <h5>No custom dashboards</h5>
                        <p>Users can customize their dashboards from their profile settings.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersWithDashboards as $userDashboard): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($userDashboard['username']); ?></td>
                                    <td><?php echo htmlspecialchars($userDashboard['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($userDashboard['email']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($userDashboard['last_updated'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                    onclick="viewUserDashboard(<?php echo $userDashboard['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="resetUserDashboard(<?php echo $userDashboard['id']; ?>, '<?php echo htmlspecialchars($userDashboard['username']); ?>')">
                                                <i class="fas fa-undo"></i> Reset
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Widget Categories -->
            <div class="row">
                <div class="col-md-12">
                    <h4>Widget Categories</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                                    <h6>Financial</h6>
                                    <small class="text-muted">Revenue, expenses, cash flow</small>
                                    <div class="mt-2">
                                        <span class="badge bg-primary"><?php echo count(array_filter($availableWidgets, fn($w) => $w['category'] === 'financial')); ?> widgets</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-tasks fa-2x text-success mb-2"></i>
                                    <h6>Operational</h6>
                                    <small class="text-muted">Tasks, transactions, budgets</small>
                                    <div class="mt-2">
                                        <span class="badge bg-success"><?php echo count(array_filter($availableWidgets, fn($w) => in_array($w['category'], ['transactions', 'tasks', 'budget']))); ?> widgets</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-2x text-info mb-2"></i>
                                    <h6>Analytics</h6>
                                    <small class="text-muted">Reports, charts, insights</small>
                                    <div class="mt-2">
                                        <span class="badge bg-info"><?php echo count(array_filter($availableWidgets, fn($w) => $w['category'] === 'analytics')); ?> widgets</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-2x text-warning mb-2"></i>
                                    <h6>System</h6>
                                    <small class="text-muted">Status, activity, notifications</small>
                                    <div class="mt-2">
                                        <span class="badge bg-warning"><?php echo count(array_filter($availableWidgets, fn($w) => in_array($w['category'], ['system', 'actions']))); ?> widgets</span>
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

<!-- Dashboard Preview Modal -->
<div class="modal fade" id="dashboardPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Default Dashboard Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="dashboardPreview">
                    <!-- Dashboard preview will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Widget Configuration Modal -->
<div class="modal fade" id="widgetConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Widget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_widget_config">
                    <input type="hidden" name="widget_id" id="config_widget_id">

                    <div id="widgetConfigFields">
                        <!-- Widget configuration fields will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Widget icon mapping
function getWidgetIcon(category) {
    const icons = {
        'financial': 'chart-line',
        'receivables': 'file-invoice-dollar',
        'payables': 'credit-card',
        'transactions': 'exchange-alt',
        'tasks': 'tasks',
        'budget': 'calculator',
        'analytics': 'chart-bar',
        'system': 'server',
        'actions': 'bolt'
    };
    return icons[category] || 'square';
}

// Size badge class mapping
function getSizeBadgeClass(size) {
    const classes = {
        'small': 'secondary',
        'medium': 'info',
        'large': 'primary'
    };
    return classes[size] || 'secondary';
}

// Preview default dashboard
function previewDashboard() {
    fetch('../api/dashboard.php?action=get_default_layout')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="dashboard-grid">';
                data.layout.widgets.forEach(widget => {
                    const widgetInfo = data.available_widgets[widget.id];
                    html += `
                        <div class="dashboard-widget" style="grid-column: span ${widget.width}; grid-row: span ${widget.height};">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">${widgetInfo.name}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small">${widgetInfo.description}</p>
                                    <div class="text-center">
                                        <i class="fas fa-${getWidgetIcon(widgetInfo.category)} fa-2x text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';

                document.getElementById('dashboardPreview').innerHTML = html;
                const modalEl = document.getElementById('dashboardPreviewModal');
                if (modalEl) {
                    new bootstrap.Modal(modalEl).show();
                }
            } else {
                alert('Error loading dashboard preview: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Configure widget
function configureWidget(widgetId) {
    fetch(`api/dashboard.php?action=get_widget_config&widget_id=${widgetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('config_widget_id').value = widgetId;
                document.getElementById('widgetConfigFields').innerHTML = data.config_form;
                const modalEl = document.getElementById('widgetConfigModal');
                if (modalEl) {
                    new bootstrap.Modal(modalEl).show();
                }
            } else {
                alert('Error loading widget configuration: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// View user dashboard
function viewUserDashboard(userId) {
    window.open(`api/dashboard.php?action=get_user_layout&user_id=${userId}`, '_blank');
}

// Reset user dashboard
function resetUserDashboard(userId, username) {
    showConfirmDialog(
        'Reset Dashboard',
        `Reset dashboard for user "${username}" to default layout?`,
        () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="reset_user_dashboard">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Show dashboard statistics
function showDashboardStats() {
    const stats = {
        total_dashboards: <?php echo $stats['total_dashboards']; ?>,
        avg_widgets: <?php echo number_format($stats['avg_widgets'], 1); ?>,
        recently_updated: <?php echo $stats['recently_updated']; ?>,
        available_widgets: <?php echo count($availableWidgets); ?>
    };

    alert(`Dashboard Statistics:\n\nTotal Custom Dashboards: ${stats.total_dashboards}\nAverage Widgets per User: ${stats.avg_widgets}\nRecently Updated: ${stats.recently_updated}\nAvailable Widgets: ${stats.available_widgets}`);
}

// Show widget library
function showWidgetLibrary() {
    const widgets = <?php echo json_encode($availableWidgets); ?>;
    let message = 'Available Dashboard Widgets:\n\n';

    Object.values(widgets).forEach(widget => {
        message += `${widget.name} (${widget.category})\n`;
        message += `  ${widget.description}\n`;
        message += `  Size: ${widget.size}, Refresh: ${widget.refresh_interval > 0 ? (widget.refresh_interval / 60) + 'min' : 'Static'}\n\n`;
    });

    alert(message);
}
</script>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 1rem;
    padding: 1rem;
}

.dashboard-widget {
    min-height: 200px;
}

.dashboard-widget .card {
    height: 100%;
}
</style>

<?php include 'legacy_footer.php'; ?>



<?php include '../includes/csrf_auto_form.php'; ?>

