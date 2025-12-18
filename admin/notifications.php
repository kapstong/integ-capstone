<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user']['id'];

// Handle actions
$action = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $notificationAction = $data['action'] ?? $_POST['action'] ?? '';

    switch ($notificationAction) {
        case 'mark_read':
            $notificationId = $data['notification_id'] ?? 0;
            $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            $message = 'Notification marked as read';
            break;

        case 'mark_all_read':
            $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE");
            $stmt->execute([$userId]);
            $affected = $stmt->rowCount();
            $message = "Marked {$affected} notifications as read";
            break;

        case 'delete':
            $notificationId = $data['notification_id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            $message = 'Notification deleted';
            break;

        case 'delete_all':
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
            $stmt->execute([$userId]);
            $affected = $stmt->rowCount();
            $message = "Deleted {$affected} read notifications";
            break;
    }
}

// Get notifications
$limit = 50;
$offset = 0;
$filter = $_GET['filter'] ?? 'all'; // 'all', 'unread', 'read'

$whereClause = "WHERE user_id = ?";
$params = [$userId];

if ($filter === 'unread') {
    $whereClause .= " AND is_read = FALSE";
} elseif ($filter === 'read') {
    $whereClause .= " AND is_read = TRUE";
}

$stmt = $db->prepare("
    SELECT SQL_CALC_FOUND_ROWS * FROM notifications
    {$whereClause}
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$stmt = $db->query("SELECT FOUND_ROWS() as total");
$totalNotifications = $stmt->fetch()['total'];

// Get counts
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
$stmt->execute([$userId]);
$totalCount = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ATIERA Finance</title>
    <link rel="icon" type="image/png" href="../logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #F1F7EE; }
        .notification-item { transition: all 0.2s ease; border-left: 4px solid transparent; }
        .notification-item.unread { border-left-color: #007bff; background-color: rgba(0,123,255,0.05); }
        .notification-item:hover { background-color: rgba(0,0,0,0.05); }
        .notification-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .login { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .logout { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .warning { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .error { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .info { background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .success { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
                        <p class="text-muted mb-0">Stay updated with your financial activities</p>
                    </div>
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h3 text-primary"><?php echo $totalCount; ?></div>
                                <div class="text-muted">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h3 text-danger"><?php echo $unreadCount; ?></div>
                                <div class="text-muted">Unread</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="h3 text-success"><?php echo $totalCount - $unreadCount; ?></div>
                                <div class="text-muted">Read</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <button class="btn btn-primary w-100" onclick="markAllAsRead()">
                                    <i class="fas fa-check-double me-2"></i>Mark All Read
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">
                                    All (<?php echo $totalCount; ?>)
                                </a>
                                <a href="?filter=unread" class="btn btn-sm <?php echo $filter === 'unread' ? 'btn-danger' : 'btn-outline-danger'; ?> me-2">
                                    Unread (<?php echo $unreadCount; ?>)
                                </a>
                                <a href="?filter=read" class="btn btn-sm <?php echo $filter === 'read' ? 'btn-success' : 'btn-outline-success'; ?>">
                                    Read (<?php echo $totalCount - $unreadCount; ?>)
                                </a>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteReadNotifications()">
                                    <i class="fas fa-trash me-2"></i>Delete Read
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="card">
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No notifications found</h5>
                                <p class="text-muted">You're all caught up!</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" data-id="<?php echo $notification['id']; ?>">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="notification-icon <?php echo $notification['type']; ?>">
                                                    <i class="fas <?php
                                                        $icons = [
                                                            'login' => 'fa-sign-in-alt',
                                                            'logout' => 'fa-sign-out-alt',
                                                            'info' => 'fa-info-circle',
                                                            'warning' => 'fa-exclamation-triangle',
                                                            'error' => 'fa-times-circle',
                                                            'success' => 'fa-check-circle'
                                                        ];
                                                        echo $icons[$notification['type']] ?? 'fa-bell';
                                                    ?> fa-lg"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                            <?php echo htmlspecialchars($notification['title']); ?>
                                                        </h6>
                                                        <p class="mb-2 text-muted">
                                                            <?php echo htmlspecialchars($notification['message']); ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                            <?php if ($notification['is_read'] && $notification['read_at']): ?>
                                                                • Read <?php echo date('M j, Y g:i A', strtotime($notification['read_at'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if (!$notification['is_read']): ?>
                                                                <li><a class="dropdown-item" href="#" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                                    <i class="fas fa-check me-2"></i>Mark as Read
                                                                </a></li>
                                                            <?php endif; ?>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function markAllAsRead() {
            if (!confirm('Mark all notifications as read?')) return;

            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_read' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function deleteNotification(notificationId) {
            if (!confirm('Delete this notification?')) return;

            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function deleteReadNotifications() {
            if (!confirm('Delete all read notifications?')) return;

            fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_all' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>
