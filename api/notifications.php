<?php
/**
 * ATIERA Financial Management System - Notifications API
 * Handles notification retrieval and management
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

try {
    $user = $auth->getCurrentUser();
    $userId = $user['id'];
    $db = Database::getInstance()->getConnection();

    // Handle different actions
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    switch ($action) {
        case 'list':
            // Get user notifications
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);

            $stmt = $db->prepare("
                SELECT
                    id,
                    type,
                    title,
                    message,
                    is_read,
                    read_at,
                    metadata,
                    created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get unread count
            $stmt = $db->prepare("
                SELECT COUNT(*) as unread_count
                FROM notifications
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => (int)$unreadCount
            ]);
            break;

        case 'mark_read':
            // Mark single notification as read
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? null;

            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'Notification ID required']);
                exit;
            }

            $stmt = $db->prepare("
                UPDATE notifications
                SET is_read = TRUE, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            break;

        case 'mark_all_read':
            // Mark all notifications as read
            $stmt = $db->prepare("
                UPDATE notifications
                SET is_read = TRUE, read_at = NOW()
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);

            $affectedRows = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'message' => "Marked {$affectedRows} notifications as read"
            ]);
            break;

        case 'delete':
            // Delete notification
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? null;

            if (!$notificationId) {
                echo json_encode(['success' => false, 'error' => 'Notification ID required']);
                exit;
            }

            $stmt = $db->prepare("
                DELETE FROM notifications
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted'
            ]);
            break;

        case 'delete_all':
            // Delete all read notifications
            $stmt = $db->prepare("
                DELETE FROM notifications
                WHERE user_id = ? AND is_read = TRUE
            ");
            $stmt->execute([$userId]);

            $affectedRows = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'message' => "Deleted {$affectedRows} notifications"
            ]);
            break;

        case 'create':
            // Create new notification (for testing or manual creation)
            $data = json_decode(file_get_contents('php://input'), true);

            $type = $data['type'] ?? 'info';
            $title = $data['title'] ?? 'Notification';
            $message = $data['message'] ?? '';
            $metadata = $data['metadata'] ?? null;

            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $metadata ? json_encode($metadata) : null
            ]);

            echo json_encode([
                'success' => true,
                'notification_id' => $db->lastInsertId(),
                'message' => 'Notification created'
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => 'Failed to process notification request'
    ]);
}
?>
