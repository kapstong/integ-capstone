<?php
/**
 * Grant Integrations Access Script
 * Run this once to give yourself access to /admin/integrations.php
 * Access via: http://yoursite.com/grant_integrations_access.php
 */

require_once 'includes/database.php';

// FOR SECURITY: Comment this line out after running once
// die('Script disabled. Uncomment line 9 to run.');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Grant Integrations Access</title>
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
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1e2936; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #1e2936; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #2c3e50; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Grant Integrations Access</h1>

<?php
try {
    $db = Database::getInstance()->getConnection();

    echo '<div class="info"><strong>Step 1:</strong> Checking if <code>settings.edit</code> permission exists...</div>';

    // Step 1: Ensure permission exists
    $checkPerm = $db->query("SELECT id FROM permissions WHERE name = 'settings.edit'");
    $permExists = $checkPerm->fetch(PDO::FETCH_ASSOC);

    if (!$permExists) {
        $db->exec("
            INSERT INTO permissions (name, description, module)
            VALUES ('settings.edit', 'Edit system settings and manage integrations', 'settings')
        ");
        echo '<div class="success">✅ Created <code>settings.edit</code> permission</div>';
    } else {
        echo '<div class="success">✅ Permission <code>settings.edit</code> already exists (ID: ' . $permExists['id'] . ')</div>';
    }

    // Get permission ID
    $permResult = $db->query("SELECT id FROM permissions WHERE name = 'settings.edit'");
    $permission = $permResult->fetch(PDO::FETCH_ASSOC);
    $permissionId = $permission['id'];

    echo '<div class="info"><strong>Step 2:</strong> Finding all users and their roles...</div>';

    // Step 2: Get all users and their roles
    $usersQuery = $db->query("
        SELECT
            u.id as user_id,
            u.username,
            u.email,
            r.id as role_id,
            r.name as role_name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        ORDER BY u.id
    ");
    $users = $usersQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo '<div class="error">❌ No users found in the system!</div>';
        exit;
    }

    echo '<div class="success">✅ Found ' . count($users) . ' user(s)</div>';
    echo '<table>';
    echo '<tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>';

    $grantedCount = 0;

    foreach ($users as $user) {
        $userId = $user['user_id'];
        $roleId = $user['role_id'];

        echo '<tr>';
        echo '<td>' . $userId . '</td>';
        echo '<td>' . htmlspecialchars($user['username']) . '</td>';
        echo '<td>' . htmlspecialchars($user['email']) . '</td>';
        echo '<td>' . htmlspecialchars($user['role_name'] ?? 'No role') . '</td>';

        if (!$roleId) {
            echo '<td><span style="color: orange;">⚠️ No role assigned</span></td>';
        } else {
            // Check if permission already granted
            $checkQuery = $db->prepare("
                SELECT id FROM role_permissions
                WHERE role_id = ? AND permission_id = ?
            ");
            $checkQuery->execute([$roleId, $permissionId]);
            $hasPermission = $checkQuery->fetch(PDO::FETCH_ASSOC);

            if ($hasPermission) {
                echo '<td><span style="color: green;">✅ Already has access</span></td>';
            } else {
                // Grant permission
                $grantQuery = $db->prepare("
                    INSERT INTO role_permissions (role_id, permission_id, assigned_by)
                    VALUES (?, ?, ?)
                ");
                $grantQuery->execute([$roleId, $permissionId, $userId]);
                echo '<td><span style="color: blue;">✅ Access granted!</span></td>';
                $grantedCount++;
            }
        }

        echo '</tr>';
    }

    echo '</table>';

    if ($grantedCount > 0) {
        echo '<div class="success"><strong>✅ Success!</strong> Granted integrations access to <strong>' . $grantedCount . '</strong> user(s)!</div>';
    } else {
        echo '<div class="info">ℹ️ All users already have access to integrations.</div>';
    }

    // Step 3: Verify permissions
    echo '<div class="info"><strong>Step 3:</strong> Verifying permissions...</div>';

    $verifyQuery = $db->query("
        SELECT
            u.id as user_id,
            u.username,
            r.name as role_name,
            p.name as permission_name,
            p.description
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        JOIN role_permissions rp ON r.id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE p.name = 'settings.edit'
        ORDER BY u.username
    ");
    $verified = $verifyQuery->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($verified)) {
        echo '<div class="success"><strong>✅ Verified!</strong> The following users can now access integrations:</div>';
        echo '<table>';
        echo '<tr><th>User ID</th><th>Username</th><th>Role</th><th>Permission</th></tr>';
        foreach ($verified as $v) {
            echo '<tr>';
            echo '<td>' . $v['user_id'] . '</td>';
            echo '<td>' . htmlspecialchars($v['username']) . '</td>';
            echo '<td>' . htmlspecialchars($v['role_name']) . '</td>';
            echo '<td>' . htmlspecialchars($v['permission_name']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo '<div class="success">';
    echo '<h3>🎉 All Done!</h3>';
    echo '<p>You can now access the integrations page:</p>';
    echo '<p><a href="admin/integrations.php" class="btn">Go to Integrations →</a></p>';
    echo '<p style="margin-top: 20px;"><strong>Security Note:</strong> For security, you should delete this file after use:</p>';
    echo '<code>rm grant_integrations_access.php</code>';
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="error">';
    echo '<strong>❌ Error:</strong> ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>

    </div>
</body>
</html>
