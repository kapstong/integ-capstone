<?php
/**
 * Verify Admin Password API
 * Used by privacy mode to verify password before showing amounts
 */

// Start output buffering to catch any errors
ob_start();

// Start session FIRST
session_start();

// Don't display errors, return JSON instead
ini_set('display_errors', 0);
error_reporting(0);

// Now set headers
header('Content-Type: application/json');

try {
    require_once '../config.php';
    require_once '../includes/database.php';

    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated'
        ]);
        exit;
    }

    // Get password from POST
    $password = $_POST['password'] ?? '';

    if (empty($password)) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Password is required'
        ]);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    // Get current user's password hash
    $userId = $_SESSION['user']['id'];
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }

    // Verify password
    if (password_verify($password, $user['password'])) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Password verified'
        ]);
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Incorrect password'
        ]);
    }

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
exit;
?>
