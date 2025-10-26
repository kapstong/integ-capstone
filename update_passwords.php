<?php
/**
 * Update Default Passwords Script
 * This script updates the default admin and staff passwords for security
 */

require_once 'config.php';
require_once 'includes/database.php';

header('Content-Type: text/html; charset=utf-8');

// Security: Only allow running this script from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Access denied. This script can only be run from localhost.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // Check if user exists
            $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "User '$username' not found";
            } else {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update the password
                $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
                $updateStmt->execute([$hashedPassword, $username]);

                $message = "Password for user '$username' has been updated successfully!";

                // Log the action
                $logStmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, ip_address, created_at) VALUES (?, 'password_update', ?, ?, NOW())");
                $logStmt->execute([
                    $user['id'],
                    "Password updated via update_passwords.php script",
                    $_SERVER['REMOTE_ADDR']
                ]);
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get all users for the dropdown
$users = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT username, name FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Cannot connect to database: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update User Passwords - ATIERA Finance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 500px; width: 100%; }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 14px; }
        input, select { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        .password-strength { height: 4px; background: #e0e0e0; border-radius: 2px; margin-top: 8px; overflow: hidden; }
        .password-strength-bar { height: 100%; width: 0%; transition: all 0.3s; background: #dc3545; }
        .password-hint { font-size: 12px; color: #666; margin-top: 5px; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        button:active { transform: translateY(0); }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .default-credentials { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid #17a2b8; }
        .default-credentials h3 { color: #333; font-size: 16px; margin-bottom: 15px; }
        .credentials-list { list-style: none; }
        .credentials-list li { padding: 8px 0; color: #666; font-size: 14px; font-family: 'Courier New', monospace; }
        .credentials-list strong { color: #dc3545; }
        .link { text-align: center; margin-top: 20px; }
        .link a { color: #667eea; text-decoration: none; font-size: 14px; }
        .link a:hover { text-decoration: underline; }
        .info-icon { display: inline-block; width: 18px; height: 18px; background: #17a2b8; color: white; border-radius: 50%; text-align: center; line-height: 18px; font-size: 12px; margin-right: 8px; }
    </style>
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.querySelector('.password-strength-bar');
            const hint = document.querySelector('.password-hint');

            let strength = 0;
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 12.5;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 12.5;

            strengthBar.style.width = strength + '%';

            if (strength < 25) {
                strengthBar.style.background = '#dc3545';
                hint.textContent = 'Weak password';
                hint.style.color = '#dc3545';
            } else if (strength < 50) {
                strengthBar.style.background = '#ffc107';
                hint.textContent = 'Fair password';
                hint.style.color = '#ffc107';
            } else if (strength < 75) {
                strengthBar.style.background = '#17a2b8';
                hint.textContent = 'Good password';
                hint.style.color = '#17a2b8';
            } else {
                strengthBar.style.background = '#28a745';
                hint.textContent = 'Strong password';
                hint.style.color = '#28a745';
            }
        }

        function confirmPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const confirmInput = document.getElementById('confirm_password');

            if (confirm.length > 0) {
                if (password === confirm) {
                    confirmInput.style.borderColor = '#28a745';
                } else {
                    confirmInput.style.borderColor = '#dc3545';
                }
            } else {
                confirmInput.style.borderColor = '#e0e0e0';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>🔒 Update User Password</h1>
        <p class="subtitle">Change default passwords for security</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="default-credentials">
            <h3><span class="info-icon">!</span>Default Credentials (CHANGE THESE!)</h3>
            <ul class="credentials-list">
                <li><strong>admin</strong> / admin123</li>
                <li><strong>staff</strong> / staff123</li>
            </ul>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Select User</label>
                <select name="username" id="username" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                            (<?php echo htmlspecialchars($user['name'] ?? 'No name'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    placeholder="Enter new password (min. 8 characters)"
                    required
                    oninput="checkPasswordStrength(); confirmPasswordMatch();"
                >
                <div class="password-strength">
                    <div class="password-strength-bar"></div>
                </div>
                <div class="password-hint">Password must be at least 8 characters</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter new password"
                    required
                    oninput="confirmPasswordMatch();"
                >
            </div>

            <button type="submit">🔐 Update Password</button>
        </form>

        <div class="link">
            <a href="verify_system.php">← Back to System Verification</a> |
            <a href="index.php">Go to Login →</a>
        </div>

        <div class="alert alert-warning" style="margin-top: 30px;">
            <strong>⚠️ Security Notice:</strong> Delete this file (update_passwords.php) after updating all passwords!
        </div>
    </div>
</body>
</html>
