<?php
/**
 * Confidential Mode API
 * Handles unlock/lock operations and settings management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize auth
$auth = new Auth();
$auth->requireLogin();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $db = Database::getInstance()->getConnection();
    $action = $_REQUEST['action'] ?? '';
    $userId = $_SESSION['user']['id'] ?? null;

    // Get settings helper
    function getSetting($db, $key, $default = null) {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }

    // Update setting helper
    function updateSetting($db, $key, $value) {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    }

    // Check if confidential mode is enabled
    if ($action === 'check_status') {
        $enabled = getSetting($db, 'confidential_mode_enabled', '0') === '1';
        $blurStyle = getSetting($db, 'confidential_mode_blur_style', 'blur');
        $isUnlocked = isset($_SESSION['confidential_unlocked']) && $_SESSION['confidential_unlocked'] === true;
        $unlockExpiry = $_SESSION['confidential_unlock_expiry'] ?? 0;

        // Check if unlock has expired
        if ($isUnlocked && time() > $unlockExpiry) {
            $_SESSION['confidential_unlocked'] = false;
            $isUnlocked = false;
        }

        echo json_encode([
            'success' => true,
            'enabled' => $enabled,
            'is_unlocked' => $isUnlocked,
            'blur_style' => $blurStyle,
            'time_remaining' => $isUnlocked ? max(0, $unlockExpiry - time()) : 0
        ]);
        exit;
    }

    // Unlock confidential data with password
    if ($action === 'unlock') {
        $password = $_POST['password'] ?? '';

        if (empty($password)) {
            echo json_encode([
                'success' => false,
                'error' => 'Password is required'
            ]);
            exit;
        }

        // Get current user's password hash
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode([
                'success' => false,
                'error' => 'Only administrators can unlock confidential data'
            ]);
            exit;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Log failed attempt
            Logger::getInstance()->warning('Failed confidential mode unlock attempt', [
                'user_id' => $userId
            ]);

            echo json_encode([
                'success' => false,
                'error' => 'Incorrect password'
            ]);
            exit;
        }

        // Unlock confidential data
        $unlockDuration = intval(getSetting($db, 'confidential_mode_unlock_duration', '1800'));
        $_SESSION['confidential_unlocked'] = true;
        $_SESSION['confidential_unlock_expiry'] = time() + $unlockDuration;

        // Log successful unlock
        Logger::getInstance()->info('Confidential mode unlocked', [
            'user_id' => $userId,
            'duration' => $unlockDuration
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Confidential data unlocked',
            'expires_in' => $unlockDuration
        ]);
        exit;
    }

    // Lock confidential data
    if ($action === 'lock') {
        $_SESSION['confidential_unlocked'] = false;
        unset($_SESSION['confidential_unlock_expiry']);

        Logger::getInstance()->info('Confidential mode locked', [
            'user_id' => $userId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Confidential data locked'
        ]);
        exit;
    }

    // Get settings (admin only)
    if ($action === 'get_settings') {
        // Verify admin
        if ($_SESSION['user']['role'] !== 'admin') {
            echo json_encode([
                'success' => false,
                'error' => 'Admin access required'
            ]);
            exit;
        }

        $settings = [
            'enabled' => getSetting($db, 'confidential_mode_enabled', '0') === '1',
            'blur_style' => getSetting($db, 'confidential_mode_blur_style', 'blur'),
            'auto_lock' => intval(getSetting($db, 'confidential_mode_auto_lock', '300')),
            'unlock_duration' => intval(getSetting($db, 'confidential_mode_unlock_duration', '1800'))
        ];

        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        exit;
    }

    // Update settings (admin only)
    if ($action === 'update_settings') {
        // Verify admin
        if ($_SESSION['user']['role'] !== 'admin') {
            echo json_encode([
                'success' => false,
                'error' => 'Admin access required'
            ]);
            exit;
        }

        $enabled = isset($_POST['enabled']) ? ($_POST['enabled'] === 'true' || $_POST['enabled'] === '1' ? '1' : '0') : null;
        $blurStyle = $_POST['blur_style'] ?? null;
        $autoLock = $_POST['auto_lock'] ?? null;
        $unlockDuration = $_POST['unlock_duration'] ?? null;

        if ($enabled !== null) {
            updateSetting($db, 'confidential_mode_enabled', $enabled);
        }
        if ($blurStyle !== null) {
            updateSetting($db, 'confidential_mode_blur_style', $blurStyle);
        }
        if ($autoLock !== null) {
            updateSetting($db, 'confidential_mode_auto_lock', $autoLock);
        }
        if ($unlockDuration !== null) {
            updateSetting($db, 'confidential_mode_unlock_duration', $unlockDuration);
        }

        Logger::getInstance()->logUserAction(
            'Updated confidential mode settings',
            'system_settings',
            null,
            null,
            ['enabled' => $enabled, 'blur_style' => $blurStyle]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
        exit;
    }

    // Invalid action
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
