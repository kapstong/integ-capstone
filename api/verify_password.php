<?php
/**
 * Verify Admin Password API
 * Used by privacy mode to verify password before showing amounts
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';
require_once '../includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

// Get password from POST
$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode([
        'success' => false,
        'error' => 'Password is required'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Get current user's password hash
    $userId = $_SESSION['user']['id'];
    $stmt = $db->prepare("SELECT password, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }

    // Verify password
    if (password_verify($password, $user['password'])) {
        // Password correct
        echo json_encode([
            'success' => true,
            'message' => 'Password verified'
        ]);
    } else {
        // Password incorrect
        echo json_encode([
            'success' => false,
            'error' => 'Incorrect password'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
