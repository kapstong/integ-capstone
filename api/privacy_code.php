<?php
/**
 * Privacy Mode Email Verification Code API
 * Generates and verifies 6-digit codes sent to admin email
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
    require_once '../includes/mailer.php';

    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated'
        ]);
        exit;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Handle GET request - check if privacy mode is already unlocked
    if ($action === 'check_status') {
        $unlocked = isset($_SESSION['privacy_mode_unlocked']) && $_SESSION['privacy_mode_unlocked'] === true;
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'unlocked' => $unlocked
        ]);
        exit;
    }

    // Handle send code request
    if ($action === 'send_code') {
        $db = Database::getInstance()->getConnection();

        // Get current user's email
        $userId = $_SESSION['user']['id'];
        $stmt = $db->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['email'])) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'User email not found'
            ]);
            exit;
        }

        // Generate 6-digit code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code in session with expiration (5 minutes)
        $_SESSION['privacy_verification_code'] = $code;
        $_SESSION['privacy_code_expires'] = time() + 300; // 5 minutes

        // Send email using Mailer class
        $mailer = Mailer::getInstance();

        error_log("About to send verification code to {$user['email']} for user {$user['first_name']}");
        $mailSent = $mailer->sendVerificationCode($user['email'], $code, $user['first_name']);

        if ($mailSent) {
            // Log the code for security monitoring
            error_log("Privacy Mode Verification Code sent to {$user['email']}: {$code}");

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Verification code sent to your email',
                'email' => $user['email']
            ]);
        } else {
            error_log("Failed to send verification code to {$user['email']}");

            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send email. Please contact system administrator.',
                'debug_info' => 'Email sending failed - check server mail configuration'
            ]);
        }
        exit;
    }

    // Handle verify code request
    if ($action === 'verify_code') {
        $code = trim($_POST['code'] ?? '');

        if (empty($code)) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Verification code is required'
            ]);
            exit;
        }

        // Check if code exists in session
        if (!isset($_SESSION['privacy_verification_code'])) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'No verification code sent. Please request a new code.'
            ]);
            exit;
        }

        // Check if code has expired
        if (time() > $_SESSION['privacy_code_expires']) {
            unset($_SESSION['privacy_verification_code']);
            unset($_SESSION['privacy_code_expires']);

            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Verification code has expired. Please request a new one.'
            ]);
            exit;
        }

        // Verify code
        if ($code === $_SESSION['privacy_verification_code']) {
            // Set session variable to remember verification
            $_SESSION['privacy_mode_unlocked'] = true;

            // Clear verification code
            unset($_SESSION['privacy_verification_code']);
            unset($_SESSION['privacy_code_expires']);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Code verified successfully'
            ]);
        } else {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Incorrect verification code'
            ]);
        }
        exit;
    }

    // Invalid action
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);

} catch (Exception $e) {
    // Log the actual error for debugging
    error_log('Privacy Mode Code Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
exit;
?>
