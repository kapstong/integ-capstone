<?php
/**
 * ATIERA Financial Management System - Backup and Recovery Management
 * Admin interface for managing system backups and recovery
 */

require_once '../includes/auth.php';
require_once '../includes/backup.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.edit'); // Use settings.edit as backup permission

$backupManager = BackupManager::getInstance();
$user = $auth->getCurrentUser();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_database_backup':
            $result = $backupManager->createDatabaseBackup($_POST['backup_name'] ?? null);
            if ($result['success']) {
                $message = 'Database backup created successfully: ' . $result['name'];
                Logger::getInstance()->logUserAction(
                    'Created database backup',
                    'backups',
                    null,
                    null,
                    ['backup_name' => $result['name'], 'size' => $result['size']]
                );
            } else {
                $error = 'Failed to create database backup: ' . $result['error'];
            }
            break;

        case 'create_filesystem_backup':
            $result = $backupManager->createFilesystemBackup($_POST['backup_name'] ?? null);
            if ($result['success']) {
                $message = 'Filesystem backup created successfully: ' . $result['name'];
                Logger::getInstance()->logUserAction(
                    'Created filesystem backup',
                    'backups',
                    null,
                    null,
                    ['backup_name' => $result['name'], 'size' => $result['size']]
                );
            } else {
                $error = 'Failed to create filesystem backup: ' . $result['error'];
            }
            break;

        case 'create_full_backup':
            $result = $backupManager->createFullBackup($_POST['backup_name'] ?? null);
            if ($result['success']) {
                $message = 'Full system backup created successfully: ' . $result['name'];
                Logger::getInstance()->logUserAction(
                    'Created full system backup',
                    'backups',
                    null,
                    null,
                    ['backup_name' => $result['name'], 'size' => $result['size']]
                );
            } else {
                $error = 'Failed to create full backup: ' . $result['error'];
            }
            break;

        case 'schedule_backup':
            $frequency = (int)$_POST['frequency'];
            $frequencyUnit = $_POST['frequency_unit'];

            // Convert to minutes
            switch ($frequencyUnit) {
                case 'hours':
                    $frequency *= 60;
                    break;
                case 'days':
                    $frequency *= 1440; // 24 * 60
                    break;
                case 'weeks':
                    $frequency *= 10080; // 7 * 24 * 60
                    break;
            }

            $result = $backupManager->scheduleBackup(
                $_POST['schedule_type'],
                $frequency,
                $_POST['scheduled_time'] ?? null
            );

            if ($result['success']) {
                $message = 'Backup schedule created successfully.';
                Logger::getInstance()->logUserAction(
                    'Scheduled automated backup',
                    'backup_schedules',
                    $result['schedule_id'],
                    null,
                    ['type' => $_POST['schedule_type'], 'frequency' => $frequency]
                );
            } else {
                $error = 'Failed to schedule backup: ' . $result['error'];
            }
            break;

        case 'run_scheduled':
            $result = $backupManager->runScheduledBackups();
            if ($result['success']) {
                $completed = count(array_filter($result['results'], fn($r) => $r['success']));
                $failed = count($result['results']) - $completed;
                $message = "Scheduled backups completed. Success: {$completed}, Failed: {$failed}";
                Logger::getInstance()->logUserAction(
                    'Ran scheduled backups',
                    'backup_schedules',
                    null,
                    null,
                    ['results' => $result['results']]
                );
            } else {
                $error = 'Failed to run scheduled backups: ' . $result['error'];
            }
            break;
    }
}

// Handle AJAX requests for delete/restore/verify
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];

    switch ($action) {
        case 'delete_backup':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'Backup ID required']);
                exit;
            }

            $result = $backupManager->deleteBackup($_GET['id']);
            if ($result['success']) {
                Logger::getInstance()->logUserAction(
                    'Deleted backup',
                    'backups',
                    $_GET['id'],
                    null,
                    null
                );
            }
            echo json_encode($result);
            break;

        case 'verify_backup':
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'Backup ID required']);
                exit;
            }

            $result = $backupManager->verifyBackup($_GET['id']);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Get data for display
$backups = $backupManager->getBackups();
$stats = $backupManager->getBackupStats();

// Get backup schedules
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM backup_schedules ORDER BY created_at DESC");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $schedules = [];
}

$pageTitle = 'Backup & Recovery';
include 'legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shield-alt"></i> Backup & Recovery Management</h2>
                <div>
                    <button type="button" class="btn btn-success me-2" onclick="createFullBackup()">
                        <i class="fas fa-plus"></i> Create Full Backup
                    </button>
                    <button type="button" class="btn btn-info" onclick="runScheduledBackups()">
                        <i class="fas fa-play"></i> Run Scheduled
                    </button>
                </div>
            </div>

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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-database"></i> Database Backups</h5>
                            <h3><?php echo number_format($stats['db_backups'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-folder"></i> Filesystem Backups</h5>
                            <h3><?php echo number_format($stats['fs_backups'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-archive"></i> Full Backups</h5>
                            <h3><?php echo number_format($stats['full_backups'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-hdd"></i> Total Size</h5>
                            <h4><?php echo $stats['total_size'] ? formatBytes($stats['total_size']) : '0 B'; ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Backup Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Backup Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100" onclick="createDatabaseBackup()">
                                <i class="fas fa-database"></i><br>
                                <strong>Database Backup</strong><br>
                                <small>Backup all database tables and data</small>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-success w-100" onclick="createFilesystemBackup()">
                                <i class="fas fa-folder"></i><br>
                                <strong>Filesystem Backup</strong><br>
                                <small>Backup application files and uploads</small>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-warning w-100" onclick="createFullBackup()">
                                <i class="fas fa-archive"></i><br>
                                <strong>Full System Backup</strong><br>
                                <small>Complete backup of database and files</small>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Schedules -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Backup Schedules</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="fas fa-plus"></i> Add Schedule
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($schedules)): ?>
                    <p class="text-muted">No backup schedules configured.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Type</th>
                                    <th>Frequency</th>
                                    <th>Scheduled Time</th>
                                    <th>Status</th>
                                    <th>Last Run</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo ucfirst(htmlspecialchars($schedule['type'])); ?></td>
                                    <td><?php echo formatFrequency($schedule['frequency']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['scheduled_time'] ?? 'Anytime'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $schedule['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $schedule['last_run'] ? date('M j, Y H:i', strtotime($schedule['last_run'])) : 'Never'; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Backups List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Available Backups</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($backups)): ?>
                    <p class="text-muted">No backups available.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $backup['type'] === 'database' ? 'primary' :
                                                 ($backup['type'] === 'filesystem' ? 'success' : 'warning');
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($backup['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatBytes($backup['size_bytes']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($backup['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $backup['status'] === 'completed' ? 'success' :
                                                 ($backup['status'] === 'failed' ? 'danger' : 'warning');
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($backup['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-info" onclick="verifyBackup(<?php echo $backup['id']; ?>)">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="downloadBackup(<?php echo $backup['id']; ?>)">
                                                <i class="fas fa-download"></i> Download
                                            </button>
                                            <?php if ($backup['type'] === 'database'): ?>
                                            <button type="button" class="btn btn-sm btn-warning" onclick="restoreBackup(<?php echo $backup['id']; ?>)">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteBackup(<?php echo $backup['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
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
        </div>
    </div>
</div>

<!-- Schedule Backup Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Automated Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="schedule_backup">
                    <div class="mb-3">
                        <label for="schedule_type" class="form-label">Backup Type *</label>
                        <select class="form-control" id="schedule_type" name="schedule_type" required>
                            <option value="database">Database Backup</option>
                            <option value="filesystem">Filesystem Backup</option>
                            <option value="full">Full System Backup</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="frequency" class="form-label">Frequency *</label>
                            <input type="number" class="form-control" id="frequency" name="frequency" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="frequency_unit" class="form-label">Unit *</label>
                            <select class="form-control" id="frequency_unit" name="frequency_unit" required>
                                <option value="minutes">Minutes</option>
                                <option value="hours" selected>Hours</option>
                                <option value="days">Days</option>
                                <option value="weeks">Weeks</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="scheduled_time" class="form-label">Preferred Time (Optional)</label>
                        <input type="time" class="form-control" id="scheduled_time" name="scheduled_time">
                        <small class="form-text text-muted">Leave empty to run anytime during the interval</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Backup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Quick backup functions
function createDatabaseBackup() {
    showConfirmDialog(
        'Create Database Backup',
        'Create a database backup? This will backup all tables and data.',
        () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="create_database_backup">';
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function createFilesystemBackup() {
    showConfirmDialog(
        'Create Filesystem Backup',
        'Create a filesystem backup? This will backup application files and uploads.',
        () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="create_filesystem_backup">';
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function createFullBackup() {
    showConfirmDialog(
        'Create Full Backup',
        'Create a full system backup? This includes both database and filesystem.',
        () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="create_full_backup">';
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function runScheduledBackups() {
    showConfirmDialog(
        'Run Scheduled Backups',
        'Run all scheduled backups now?',
        () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="run_scheduled">';
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Backup management functions
function verifyBackup(backupId) {
    fetch(`api/backups.php?action=verify&id=${backupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const status = data.valid ? 'valid' : 'invalid';
                const details = data.details;
                let message = `Backup verification: ${status.toUpperCase()}\n\n`;

                if (details.file_exists) {
                    message += `File exists: Yes\n`;
                    message += `File size: ${formatBytes(details.file_size)}\n`;
                } else {
                    message += `File exists: No\n`;
                }

                alert(message);
            } else {
                alert('Verification failed: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

function downloadBackup(backupId) {
    window.open(`api/backups.php?action=download&id=${backupId}`, '_blank');
}

function restoreBackup(backupId) {
    showConfirmDialog(
        'Restore Backup',
        'WARNING: This will restore the database from backup and may overwrite existing data. Are you sure?',
        () => {
            showConfirmDialog(
                'Confirm Restore',
                'This action cannot be undone. Confirm restore?',
                async () => {
                    try {
                        const response = await fetch(`api/backups.php?action=restore&id=${backupId}`, { method: 'POST' });
                        const data = await response.json();
                        if (data.success) {
                            showAlert('Database restored successfully. You may need to refresh the page.', 'success');
                            location.reload();
                        } else {
                            showAlert('Restore failed: ' + data.error, 'danger');
                        }
                    } catch (error) {
                        showAlert('Error: ' + error.message, 'danger');
                    }
                }
            );
        }
    );
}

function deleteBackup(backupId) {
    showConfirmDialog(
        'Delete Backup',
        'Delete this backup? This action cannot be undone.',
        async () => {
            try {
                const response = await fetch(`api/backups.php?action=delete&id=${backupId}`, { method: 'DELETE' });
                const data = await response.json();
                if (data.success) {
                    showAlert('Backup deleted successfully.', 'success');
                    location.reload();
                } else {
                    showAlert('Delete failed: ' + data.error, 'danger');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'danger');
            }
        }
    );
}

function deleteSchedule(scheduleId) {
    showConfirmDialog(
        'Delete Schedule',
        'Delete this backup schedule?',
        async () => {
            try {
                const response = await fetch(`api/backups.php?action=delete_schedule&id=${scheduleId}`, { method: 'DELETE' });
                const data = await response.json();
                if (data.success) {
                    showAlert('Schedule deleted successfully.', 'success');
                    location.reload();
                } else {
                    showAlert('Delete failed: ' + data.error, 'danger');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'danger');
            }
        }
    );
}

// Utility functions
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatFrequency(minutes) {
    if (minutes < 60) return `${minutes} minutes`;
    if (minutes < 1440) return `${Math.round(minutes / 60)} hours`;
    if (minutes < 10080) return `${Math.round(minutes / 1440)} days`;
    return `${Math.round(minutes / 10080)} weeks`;
}
</script>

<?php include 'legacy_footer.php'; ?>
