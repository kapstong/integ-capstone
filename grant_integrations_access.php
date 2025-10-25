<?php
/**
 * Grant Integrations Access Script
 * Run this once to give yourself access to /admin/integrations.php
 * Access via: http://yoursite.com/grant_integrations_access.php
 */

require_once 'includes/database.php';

// FOR SECURITY: Uncomment this line after running once to disable the script
// die('Script disabled for security. Delete this file or comment out line 11 to run again.');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Grant Integrations Access</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e2936; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 15px 0; border-radius: 4px; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1e2936; color: white; }
        .btn { display: inline-block; padding: 12px 24px; background: #1e2936; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #2c3e50; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Grant Integrations Access</h1>
        <p>This script will automatically grant the <code>settings.edit</code> permission required to access the integrations page.</p>

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

    echo '<div class="info"><strong>Step 2:</strong> Checking available roles...</div>';

    // Check if roles exist
    $rolesQuery = $db->query("SELECT id, name, description FROM roles ORDER BY id");
    $roles = $rolesQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($roles)) {
        echo '<div class="warning">⚠️ No roles found in system! Creating default "Administrator" role...</div>';

        $db->exec("
            INSERT INTO roles (name, description, is_system)
            VALUES ('Administrator', 'System administrator with full access', 0)
        ");

        $rolesQuery = $db->query("SELECT id, name, description FROM roles ORDER BY id");
        $roles = $rolesQuery->fetchAll(PDO::FETCH_ASSOC);

        echo '<div class="success">✅ Created "Administrator" role (ID: ' . $roles[0]['id'] . ')</div>';
    } else {
        echo '<div class="success">✅ Found ' . count($roles) . ' role(s) in the system:</div>';
        echo '<ul>';
        foreach ($roles as $role) {
            echo '<li><strong>' . htmlspecialchars($role['name']) . '</strong> (ID: ' . $role['id'] . ')';
            if (!empty($role['description'])) {
                echo ' - ' . htmlspecialchars($role['description']);
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    // Get default role (usually first role is Administrator)
    $defaultRoleId = $roles[0]['id'];
    $defaultRoleName = $roles[0]['name'];

    echo '<div class="info"><strong>Step 3:</strong> Finding all users and checking role assignments...</div>';

    // Get all users
    $usersQuery = $db->query("
        SELECT
            u.id as user_id,
            u.username,
            u.email,
            ur.role_id,
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

    // Assign roles to users who don't have one
    $assignedRoles = 0;
    foreach ($users as &$user) {
        if (empty($user['role_id'])) {
            echo '<div class="warning">⚠️ User "' . htmlspecialchars($user['username']) . '" has no role. Assigning "' . htmlspecialchars($defaultRoleName) . '"...</div>';

            $assignStmt = $db->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_by)
                VALUES (?, ?, ?)
            ");
            $assignStmt->execute([$user['user_id'], $defaultRoleId, $user['user_id']]);

            $user['role_id'] = $defaultRoleId;
            $user['role_name'] = $defaultRoleName;
            $assignedRoles++;

            echo '<div class="success">✅ Assigned role to user "' . htmlspecialchars($user['username']) . '"</div>';
        }
    }

    if ($assignedRoles > 0) {
        echo '<div class="success"><strong>✅ Summary:</strong> Assigned "' . htmlspecialchars($defaultRoleName) . '" role to ' . $assignedRoles . ' user(s)</div>';
    }

    echo '<div class="info"><strong>Step 4:</strong> Granting permissions to roles...</div>';
    echo '<table>';
    echo '<tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>';

    $grantedCount = 0;
    $alreadyHadCount = 0;

    foreach ($users as $user) {
        $userId = $user['user_id'];
        $roleId = $user['role_id'];

        echo '<tr>';
        echo '<td>' . $userId . '</td>';
        echo '<td>' . htmlspecialchars($user['username']) . '</td>';
        echo '<td>' . htmlspecialchars($user['email'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($user['role_name']) . '</td>';

        // Check if permission already granted to this role
        $checkQuery = $db->prepare("
            SELECT id FROM role_permissions
            WHERE role_id = ? AND permission_id = ?
        ");
        $checkQuery->execute([$roleId, $permissionId]);
        $hasPermission = $checkQuery->fetch(PDO::FETCH_ASSOC);

        if ($hasPermission) {
            echo '<td style="color: green;">✅ Already has access</td>';
            $alreadyHadCount++;
        } else {
            // Grant permission to role
            try {
                $grantQuery = $db->prepare("
                    INSERT INTO role_permissions (role_id, permission_id, assigned_by)
                    VALUES (?, ?, ?)
                ");
                $grantQuery->execute([$roleId, $permissionId, $userId]);
                echo '<td style="color: blue; font-weight: bold;">✅ Access GRANTED!</td>';
                $grantedCount++;
            } catch (Exception $e) {
                echo '<td style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</td>';
            }
        }

        echo '</tr>';
    }

    echo '</table>';

    // Summary
    if ($grantedCount > 0) {
        echo '<div class="success">';
        echo '<strong>🎉 Success!</strong> Granted integrations access to <strong>' . $grantedCount . '</strong> user(s)!';
        echo '</div>';
    }

    if ($alreadyHadCount > 0) {
        echo '<div class="info">';
        echo '<strong>ℹ️ Note:</strong> ' . $alreadyHadCount . ' user(s) already had access.';
        echo '</div>';
    }

    // Step 5: Verify permissions
    echo '<div class="info"><strong>Step 5:</strong> Verifying permissions were granted correctly...</div>';

    $verifyQuery = $db->query("
        SELECT
            u.id as user_id,
            u.username,
            u.email,
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
        echo '<tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th>Permission</th></tr>';
        foreach ($verified as $v) {
            echo '<tr>';
            echo '<td>' . $v['user_id'] . '</td>';
            echo '<td>' . htmlspecialchars($v['username']) . '</td>';
            echo '<td>' . htmlspecialchars($v['email'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($v['role_name']) . '</td>';
            echo '<td>' . htmlspecialchars($v['permission_name']) . ' ✅</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ Verification failed! No users have the settings.edit permission.</div>';
    }

    echo '<div class="success" style="margin-top: 30px;">';
    echo '<h3>🎉 All Done!</h3>';
    echo '<p><strong>What was done:</strong></p>';
    echo '<ul>';
    echo '<li>✅ Ensured "settings.edit" permission exists</li>';
    if ($assignedRoles > 0) {
        echo '<li>✅ Assigned roles to ' . $assignedRoles . ' user(s) who had no role</li>';
    }
    if ($grantedCount > 0) {
        echo '<li>✅ Granted integrations access to ' . $grantedCount . ' user(s)</li>';
    }
    echo '<li>✅ Verified permissions are working</li>';
    echo '</ul>';
    echo '<p style="margin-top: 20px;"><strong>Next Steps:</strong></p>';
    echo '<p>1. <a href="admin/integrations.php" class="btn">📦 Go to Integrations Page →</a></p>';
    echo '<p>2. Import data from Logistics 1 and Logistics 2</p>';
    echo '<p>3. View imported data in Reports → Income Statement</p>';
    echo '<p style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;"><strong>⚠️ Security Note:</strong></p>';
    echo '<p>For security, you should <strong>delete this file</strong> after use:</p>';
    echo '<code style="display: block; padding: 10px; background: #f8f9fa; margin: 10px 0;">rm grant_integrations_access.php</code>';
    echo '<p>Or uncomment line 11 in the file to disable it.</p>';
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="error">';
    echo '<h3>❌ Error Occurred</h3>';
    echo '<p><strong>Error Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '<details><summary>Stack Trace</summary><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></details>';
    echo '</div>';
}
?>

    </div>
</body>
</html>
