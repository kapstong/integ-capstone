<?php
/**
 * ATIERA Financial Management System - Audit Trail Viewer
 * Admin interface for viewing audit logs and system activity
 */

require_once '../includes/auth.php';
require_once '../includes/logger.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('audit.view');

$logger = Logger::getInstance();
$user = $auth->getCurrentUser();

// Get filters from request
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'table_name' => $_GET['table_name'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'ip_address' => $_GET['ip_address'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

// Get audit logs
$auditLogs = $logger->getAuditLogs($filters, $limit, $offset);

// Get statistics
$stats = $logger->getAuditStats();

// Get unique values for filter dropdowns
try {
    $db = Database::getInstance()->getConnection();

    // Get unique actions
    $stmt = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
    $uniqueActions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get unique tables
    $stmt = $db->query("SELECT DISTINCT table_name FROM audit_log WHERE table_name IS NOT NULL ORDER BY table_name");
    $uniqueTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get unique users
    $stmt = $db->query("SELECT DISTINCT u.id, u.username, u.full_name FROM audit_log al JOIN users u ON al.user_id = u.id ORDER BY u.username");
    $uniqueUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $uniqueActions = [];
    $uniqueTables = [];
    $uniqueUsers = [];
}

$pageTitle = 'Audit Trail';
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-history"></i> Audit Trail</h2>
                <div>
                    <button type="button" class="btn btn-outline-primary me-2" onclick="exportAuditLogs()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="cleanupOldLogs()">
                        <i class="fas fa-broom"></i> Cleanup Old Logs
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-list"></i> Total Logs</h5>
                            <h3><?php echo number_format($stats['total_logs'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users"></i> Active Users</h5>
                            <h3><?php echo number_format($stats['unique_users'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clock"></i> Recent Activity</h5>
                            <h3><?php echo number_format($stats['recent_logs'] ?? 0); ?></h3>
                            <small>Last 30 days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-calendar"></i> Last Activity</h5>
                            <h6><?php echo $stats['last_activity'] ? date('M j, Y H:i', strtotime($stats['last_activity'])) : 'Never'; ?></h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-control" id="user_id" name="user_id">
                                <option value="">All Users</option>
                                <?php foreach ($uniqueUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($filters['user_id'] == $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['username'] . ' - ' . $u['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="action" class="form-label">Action</label>
                            <select class="form-control" id="action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($uniqueActions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo ($filters['action'] == $action) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($action); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="table_name" class="form-label">Table</label>
                            <select class="form-control" id="table_name" name="table_name">
                                <option value="">All Tables</option>
                                <?php foreach ($uniqueTables as $table): ?>
                                <option value="<?php echo htmlspecialchars($table); ?>" <?php echo ($filters['table_name'] == $table) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($table); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="ip_address" class="form-label">IP Address</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($filters['ip_address']); ?>" placeholder="192.168.1.1">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="audit.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Audit Logs</h5>
                    <div class="btn-group" role="group">
                        <input type="number" id="limit" class="form-control form-control-sm" value="<?php echo $limit; ?>" style="width: 80px;" min="10" max="500">
                        <span class="input-group-text">per page</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date/Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Record ID</th>
                                    <th>IP Address</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditLogs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No audit logs found matching the current filters.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php if ($log['username']): ?>
                                            <span title="<?php echo htmlspecialchars($log['full_name']); ?>">
                                                <?php echo htmlspecialchars($log['username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['table_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($log['record_id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="showLogDetails(<?php echo $log['id']; ?>)">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if (!empty($auditLogs)): ?>
                    <nav aria-label="Audit log pagination" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php
                            // Simple pagination - in a real app you'd calculate total pages
                            $prevPage = max(1, $page - 1);
                            $nextPage = $page + 1;
                            ?>
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $prevPage; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Previous</a>
                            </li>
                            <li class="page-item active">
                                <span class="page-link">Page <?php echo $page; ?></span>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $nextPage; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="logDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show log details
function showLogDetails(logId) {
    fetch(`api/audit.php?action=details&id=${logId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const log = data.log;
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>ID:</strong></td><td>${log.id}</td></tr>
                                <tr><td><strong>User:</strong></td><td>${log.username || 'System'}</td></tr>
                                <tr><td><strong>Action:</strong></td><td>${log.action}</td></tr>
                                <tr><td><strong>Table:</strong></td><td>${log.table_name || 'N/A'}</td></tr>
                                <tr><td><strong>Record ID:</strong></td><td>${log.record_id || 'N/A'}</td></tr>
                                <tr><td><strong>IP Address:</strong></td><td>${log.ip_address}</td></tr>
                                <tr><td><strong>Timestamp:</strong></td><td>${new Date(log.created_at).toLocaleString()}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Changes</h6>
                            ${log.old_values ? `<h7>Old Values:</h7><pre class="bg-light p-2">${JSON.stringify(JSON.parse(log.old_values), null, 2)}</pre>` : ''}
                            ${log.new_values ? `<h7>New Values:</h7><pre class="bg-light p-2">${JSON.stringify(JSON.parse(log.new_values), null, 2)}</pre>` : ''}
                            ${!log.old_values && !log.new_values ? '<p class="text-muted">No value changes recorded</p>' : ''}
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>User Agent</h6>
                            <code class="d-block bg-light p-2">${log.user_agent}</code>
                        </div>
                    </div>
                `;
                document.getElementById('logDetailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('logDetailsModal')).show();
            } else {
                alert('Error loading log details: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Export audit logs
function exportAuditLogs() {
    const filters = new URLSearchParams(window.location.search);
    window.open(`api/audit.php?action=export&${filters.toString()}`, '_blank');
}

// Cleanup old logs
function cleanupOldLogs() {
    if (confirm('Are you sure you want to cleanup audit logs older than 1 year? This action cannot be undone.')) {
        fetch('api/audit.php?action=cleanup', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully cleaned up ${data.deleted_count} old audit log entries.`);
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    }
}

// Update limit when changed
document.getElementById('limit').addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('limit', this.value);
    url.searchParams.set('page', '1'); // Reset to first page
    window.location.href = url.toString();
});
</script>

<?php include 'footer.php'; ?>
