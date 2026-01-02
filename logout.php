<?php
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/logger.php';

session_start();

// Get logout reason (manual, timeout, or system)
$logoutType = $_GET['reason'] ?? 'manual';
$userId = $_SESSION['user']['id'] ?? null;

// Log the logout event if user was logged in
if ($userId) {
    try {
        $db = Database::getInstance()->getConnection();

        // Create logout notification
        require_once 'includes/notifications.php';
        $notificationManager = NotificationManager::getInstance();

        // Get client IP address
        $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        // Create logout notification
        $notificationManager->createInAppNotification(
            $userId,
            'logout',
            'Logged Out',
            'You successfully logged out from IP address: ' . $clientIP,
            [
                'logout_time' => date('Y-m-d H:i:s'),
                'ip_address' => $clientIP,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'logout_type' => $logoutType
            ]
        );

        // Try to use stored procedure if it exists
        try {
            $stmt = $db->prepare("CALL sp_log_logout_session(?, ?)");
            $stmt->execute([$userId, $logoutType]);
        } catch (Exception $e) {
            // Fallback if stored procedure doesn't exist
            // Update the most recent active login session
            $stmt = $db->prepare("
                UPDATE login_sessions
                SET
                    logout_time = NOW(),
                    logout_type = ?,
                    session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW())
                WHERE user_id = ? AND logout_time IS NULL
                ORDER BY login_time DESC
                LIMIT 1
            ");
            $stmt->execute([$logoutType, $userId]);

            // Log to audit trail
            Logger::getInstance()->logUserAction(
                'User Logout',
                'login_sessions',
                null,
                null,
                ['logout_type' => $logoutType]
            );
        }
    } catch (Exception $e) {
        error_log("Error logging logout: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Start a new session for flash message
session_start();

// Redirect to login page with appropriate message
if ($logoutType === 'timeout') {
    header('Location: index.php?info=session_timeout');
} else {
    $_SESSION['logout_success'] = true;
    header('Location: index.php');
}
exit;
?>
