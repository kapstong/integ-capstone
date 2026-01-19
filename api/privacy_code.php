<?php
/**
 * Privacy Mode Code API
 * Handles sending, verifying, and checking privacy mode verification codes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start output buffering
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set up error handler to catch and output errors as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $errstr]);
    ob_end_flush();
    exit(1);
}, E_ALL);

// Set up exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception: ' . $exception->getMessage()]);
    ob_end_flush();
    exit(1);
});

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Load required files
    if (file_exists(__DIR__ . '/../includes/auth.php')) {
        require_once __DIR__ . '/../includes/auth.php';
    }
    if (file_exists(__DIR__ . '/../includes/database.php')) {
        require_once __DIR__ . '/../includes/database.php';
    }
    if (file_exists(__DIR__ . '/../includes/mailer.php')) {
        require_once __DIR__ . '/../includes/mailer.php';
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        ob_end_flush();
        exit;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $user = $_SESSION['user'];
    
    switch ($action) {
        case 'send_code':
            handleSendCode($user);
            break;
            
        case 'verify_code':
            handleVerifyCode($user);
            break;
            
        case 'check_status':
            handleCheckStatus($user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Handle sending verification code
 */
function handleSendCode($user) {
    try {
        $code = generateVerificationCode();
        
        // Store code in session with timestamp
        $_SESSION['privacy_code'] = $code;
        $_SESSION['privacy_code_time'] = time();
        
        // Send code via email
        $email = $user['email'] ?? '';
        if ($email && function_exists('sendEmail')) {
            $subject = 'Privacy Mode Verification Code';
            $body = "Your verification code is: <strong>{$code}</strong>\n\nThis code will expire in 5 minutes.";
            
            sendEmail($email, $subject, $body);
        }
        
        // Return masked email
        $masked_email = maskEmail($email);
        
        echo json_encode([
            'success' => true,
            'email' => $email,
            'masked_email' => $masked_email,
            'message' => 'Code sent to email'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send code: ' . $e->getMessage()]);
    }
}

/**
 * Handle verifying the code
 */
function handleVerifyCode($user) {
    try {
        $code = $_POST['code'] ?? '';
        
        // Check if code exists and is not expired
        if (!isset($_SESSION['privacy_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No code sent yet']);
            exit;
        }
        
        $code_time = $_SESSION['privacy_code_time'] ?? 0;
        $current_time = time();
        
        // Check if code expired (5 minutes = 300 seconds)
        if ($current_time - $code_time > 300) {
            unset($_SESSION['privacy_code']);
            unset($_SESSION['privacy_code_time']);
            http_response_code(400);
            echo json_encode(['error' => 'Code expired']);
            exit;
        }
        
        // Verify code
        if ($code === $_SESSION['privacy_code']) {
            // Mark as unlocked in session
            $_SESSION['privacy_unlocked'] = true;
            $_SESSION['privacy_unlocked_time'] = time();
            
            // Clear the code
            unset($_SESSION['privacy_code']);
            unset($_SESSION['privacy_code_time']);
            
            echo json_encode([
                'success' => true,
                'unlocked' => true,
                'message' => 'Code verified successfully'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid code']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Verification failed: ' . $e->getMessage()]);
    }
}

/**
 * Handle checking unlock status
 */
function handleCheckStatus($user) {
    try {
        $unlocked = isset($_SESSION['privacy_unlocked']) && $_SESSION['privacy_unlocked'] === true;
        
        echo json_encode([
            'success' => true,
            'unlocked' => $unlocked
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Status check failed: ' . $e->getMessage()]);
    }
}

/**
 * Generate a random 6-digit verification code
 */
function generateVerificationCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Mask email address for display
 */
function maskEmail($email) {
    if (!$email || !strpos($email, '@')) {
        return '***@***.***';
    }
    
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    
    $name_length = strlen($name);
    $masked_name = substr($name, 0, 1) . str_repeat('*', max(1, $name_length - 2)) . substr($name, -1);
    
    return $masked_name . '@' . $domain;
}

ob_end_flush();
?>

