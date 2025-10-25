<?php
/**
 * Access Diagnostic Tool
 * Check your login status and permissions
 */

require_once 'includes/database.php';
session_start();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Access Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e2936; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 15px 0; border-radius: 4px; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1e2936; color: white; }
        .btn { display: inline-block; padding: 12px 24px; background: #1e2936; color: white; text-decoration: none; border-radius: 4px; margin: 5px; text-align: center; }
        .btn:hover { background: #2c3e50; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Access Diagnostic Tool</h1>
        <p>This tool helps you diagnose login and permission issues.</p>

<?php
// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo '<div class="error">';
    echo '<h3>❌ You are NOT logged in</h3>';
    echo '<p>You need to log in to access the admin panel.</p>';
    echo '</div>';

    echo '<div class="info">';
    echo '<h4>Why did this happen?</h4>';
    echo '<ul>';
    echo '<li>Your session may have expired</li>';
    echo '<li>You haven\'t logged in yet</li>';
    echo '<li>You logged out</li>';
    echo '<li>Browser cleared cookies/session</li>';
    echo '</ul>';
    echo '</div>';

    echo '<div class="info">';
    echo '<h4>What to do:</h4>';
    echo '<p><a href="index.php" class="btn btn-success">🔐 Go to Login Page</a></p>';
    echo '</div>';

    // Show available users for reference
    try {
        $db = Database::getInstance()->getConnection();
        $usersQuery = $db->query("SELECT id, username, email, status FROM users ORDER BY id LIMIT 10");
        $users = $usersQuery->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($users)) {
            echo '<div class="warning">';
            echo '<h4>📋 Available User Accounts:</h4>';
            echo '<p><em>Use one of these usernames to log in (you need to know the password)</em></p>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Username</th><th>Email</th><th>Status</th></tr>';
            foreach ($users as $user) {
                echo '<tr>';
                echo '<td>' . $user['id'] . '</td>';
                echo '<td><strong>' . htmlspecialchars($user['username']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . ($user['status'] === 'active' ? '✅ Active' : '❌ ' . ucfirst($user['status'])) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }
    } catch (Exception $e) {
        // Ignore errors
    }

} else {
    // User is logged in
    $user = $_SESSION['user'];

    echo '<div class="success">';
    echo '<h3>✅ You ARE logged in</h3>';
    echo '</div>';

    echo '<div class="info">';
    echo '<h4>Your Session Information:</h4>';
    echo '<table>';
    echo '<tr><th>Property</th><th>Value</th></tr>';
    echo '<tr><td>User ID</td><td>' . htmlspecialchars($user['id']) . '</td></tr>';
    echo '<tr><td>Username</td><td><strong>' . htmlspecialchars($user['username']) . '</strong></td></tr>';
    echo '<tr><td>Name</td><td>' . htmlspecialchars($user['name'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>Email</td><td>' . htmlspecialchars($user['email'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>Role</td><td>' . htmlspecialchars($user['role_name'] ?? 'N/A') . '</td></tr>';
    echo '</table>';
    echo '</div>';

    // Show permissions
    if (isset($user['permissions']) && !empty($user['permissions'])) {
        echo '<div class="success">';
        echo '<h4>✅ Your Permissions (' . count($user['permissions']) . '):</h4>';
        echo '<ul>';
        foreach ($user['permissions'] as $perm) {
            // Handle if permission is an array or object
            $permString = is_array($perm) ? (isset($perm['name']) ? $perm['name'] : json_encode($perm)) : (string)$perm;
            echo '<li>' . htmlspecialchars($permString) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="warning">';
        echo '<h4>⚠️ You have NO permissions!</h4>';
        echo '<p>This might be why you\'re getting "Access Denied" errors.</p>';
        echo '<p><strong>Solution:</strong> Run the permission grant script:</p>';
        echo '<p><a href="grant_integrations_access.php" class="btn">Fix Permissions</a></p>';
        echo '</div>';
    }

    // Show roles
    if (isset($user['roles']) && !empty($user['roles'])) {
        echo '<div class="info">';
        echo '<h4>Your Roles:</h4>';
        echo '<ul>';
        foreach ($user['roles'] as $role) {
            // Handle if role is an array or object
            $roleString = is_array($role) ? (isset($role['name']) ? $role['name'] : json_encode($role)) : (string)$role;
            echo '<li>' . htmlspecialchars($roleString) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // Show quick links
    echo '<div class="success" style="margin-top: 30px;">';
    echo '<h4>🎯 Quick Actions:</h4>';
    echo '<p>';
    echo '<a href="admin/index.php" class="btn">🏠 Go to Dashboard</a>';
    echo '<a href="admin/integrations.php" class="btn">📦 Go to Integrations</a>';
    echo '<a href="admin/reports.php" class="btn">📊 Go to Reports</a>';
    echo '</p>';
    echo '<p>';
    echo '<a href="grant_integrations_access.php" class="btn btn-secondary">🔐 Fix Permissions</a>';
    echo '<a href="includes/logout.php" class="btn btn-secondary">🚪 Logout</a>';
    echo '</p>';
    echo '</div>';
}
?>

        <div class="info" style="margin-top: 30px;">
            <h4>💡 Common Solutions:</h4>
            <ul>
                <li><strong>If not logged in:</strong> Go to login page and enter credentials</li>
                <li><strong>If no permissions:</strong> Run the <a href="grant_integrations_access.php">permission grant script</a></li>
                <li><strong>If session expired:</strong> Log out and log back in</li>
                <li><strong>If still having issues:</strong> Clear browser cookies and try again</li>
            </ul>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
            <p><strong>⚠️ Security Note:</strong> Delete this file after diagnosing the issue:</p>
            <code style="display: block; padding: 10px; background: #f8f9fa; margin: 10px 0;">rm check_access.php</code>
        </div>
    </div>
</body>
</html>
