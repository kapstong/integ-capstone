<?php
/**
 * ATIERA Financial Management System - Keep Alive API
 * Updates user activity timestamp to prevent timeout
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

    // Update user's last activity timestamp
    $db = Database::getInstance();
    $db->query(
        "UPDATE users SET last_activity = NOW() WHERE id = ?",
        [$userId]
    );

    // Update session last activity time
    $_SESSION['last_activity'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Activity updated',
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Keep-alive error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => 'Failed to update activity'
    ]);
}
?>
