<?php
/**
 * System Verification Script
 * Checks database connection, PHP extensions, and API connectivity
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Verification - ATIERA Finance</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #6c757d; padding-bottom: 8px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 10px 0; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #dee2e6; }
        .status-icon { font-size: 20px; margin-right: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ATIERA Finance - System Verification</h1>
        <p>This page verifies that all system components are properly configured and connected.</p>

        <h2>1. PHP Configuration</h2>
        <?php
        $phpVersion = phpversion();
        $requiredVersion = '7.4.0';
        if (version_compare($phpVersion, $requiredVersion, '>=')) {
            echo "<div class='success'><span class='status-icon'>✅</span> <strong>PHP Version:</strong> $phpVersion (Required: $requiredVersion+)</div>";
        } else {
            echo "<div class='error'><span class='status-icon'>❌</span> <strong>PHP Version:</strong> $phpVersion (Required: $requiredVersion+ - UPGRADE NEEDED)</div>";
        }

        // Check required PHP extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'mbstring', 'json', 'curl', 'openssl'];
        $missingExtensions = [];

        echo "<table>";
        echo "<tr><th>Extension</th><th>Status</th></tr>";
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            if ($loaded) {
                echo "<tr><td>$ext</td><td><span style='color: #28a745;'>✅ Loaded</span></td></tr>";
            } else {
                echo "<tr><td>$ext</td><td><span style='color: #dc3545;'>❌ Missing</span></td></tr>";
                $missingExtensions[] = $ext;
            }
        }
        echo "</table>";

        if (!empty($missingExtensions)) {
            echo "<div class='error'><span class='status-icon'>❌</span> <strong>Missing Extensions:</strong> " . implode(', ', $missingExtensions) . "<br>";
            echo "<strong>Action Required:</strong> Enable these extensions in your php.ini file (usually at c:\\wamp64\\bin\\apache\\apache2.x.x\\bin\\php.ini or c:\\wamp64\\bin\\php\\phpx.x.x\\php.ini)</div>";

            // Provide specific instructions
            echo "<div class='info'><strong>How to Enable Missing Extensions in WAMP64:</strong><br>";
            echo "1. Click on WAMP icon in system tray<br>";
            echo "2. Go to PHP → php.ini<br>";
            echo "3. Find and uncomment (remove ;) these lines:<br>";
            echo "<pre>";
            foreach ($missingExtensions as $ext) {
                echo "extension=$ext\n";
            }
            echo "</pre>";
            echo "4. Save the file and restart WAMP<br>";
            echo "5. Refresh this page to verify</div>";
        }
        ?>

        <h2>2. Database Connection</h2>
        <?php
        require_once 'config.php';

        try {
            $dbHost = Config::get('database.host');
            $dbName = Config::get('database.name');
            $dbUser = Config::get('database.user');
            $dbPass = Config::get('database.password');

            echo "<div class='info'><span class='status-icon'>ℹ️</span> <strong>Database Configuration:</strong><br>";
            echo "Host: $dbHost<br>";
            echo "Database: $dbName<br>";
            echo "User: $dbUser<br>";
            echo "Password: " . (empty($dbPass) ? '(empty)' : '***') . "</div>";

            if (!extension_loaded('pdo_mysql')) {
                throw new Exception('pdo_mysql extension is not loaded. Cannot connect to database.');
            }

            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo "<div class='success'><span class='status-icon'>✅</span> <strong>Database Connection:</strong> SUCCESSFUL</div>";

            // Count tables
            $stmt = $pdo->query("SHOW TABLES");
            $tableCount = $stmt->rowCount();
            echo "<div class='success'><span class='status-icon'>✅</span> <strong>Database Tables:</strong> $tableCount tables found</div>";

            // Check critical tables
            $criticalTables = ['users', 'invoices', 'bills', 'journal_entries', 'chart_of_accounts', 'customers', 'vendors'];
            $missingTables = [];

            foreach ($criticalTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() == 0) {
                    $missingTables[] = $table;
                }
            }

            if (empty($missingTables)) {
                echo "<div class='success'><span class='status-icon'>✅</span> <strong>Critical Tables:</strong> All present</div>";
            } else {
                echo "<div class='error'><span class='status-icon'>❌</span> <strong>Missing Tables:</strong> " . implode(', ', $missingTables) . "<br>";
                echo "<strong>Action Required:</strong> Run the database setup script: <a href='create_database.php' class='btn'>Setup Database</a></div>";
            }

            // Check for data
            $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo "<div class='info'><span class='status-icon'>ℹ️</span> <strong>User Accounts:</strong> $userCount user(s)</div>";

        } catch (PDOException $e) {
            echo "<div class='error'><span class='status-icon'>❌</span> <strong>Database Connection FAILED:</strong><br>";
            echo "Error: " . htmlspecialchars($e->getMessage()) . "</div>";

            if (strpos($e->getMessage(), 'could not find driver') !== false) {
                echo "<div class='warning'><strong>Solution:</strong> The PDO MySQL driver is not enabled. Follow the PHP Extension instructions above.</div>";
            } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
                echo "<div class='warning'><strong>Solution:</strong> The database '$dbName' does not exist. <a href='create_database.php' class='btn'>Create Database</a></div>";
            } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
                echo "<div class='warning'><strong>Solution:</strong> Check your database credentials in the .env file.</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'><span class='status-icon'>❌</span> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>

        <h2>3. API Integrations</h2>
        <?php
        $apis = [
            'HR3' => 'https://hr3.atierahotelandrestaurant.com/api/claimsApi.php',
            'HR4' => 'https://hr4.atierahotelandrestaurant.com/payroll_api.php',
            'Logistics1' => 'https://logistics1.atierahotelandrestaurant.com/api/procurement/purchase-order.php',
            'Logistics2' => 'https://logistic2.atierahotelandrestaurant.com/integration/trip-costs-api.php'
        ];

        echo "<table>";
        echo "<tr><th>API Name</th><th>Endpoint</th><th>Status</th></tr>";

        foreach ($apis as $name => $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            echo "<tr><td><strong>$name</strong></td><td style='font-size: 11px;'>$url</td>";

            if ($httpCode == 200 || $httpCode == 400 || $httpCode == 405) {
                // API is reachable (even if it returns error, the endpoint exists)
                echo "<td><span style='color: #28a745;'>✅ Reachable (HTTP $httpCode)</span></td>";
            } elseif ($httpCode == 0) {
                echo "<td><span style='color: #dc3545;'>❌ Unreachable (" . htmlspecialchars($error) . ")</span></td>";
            } else {
                echo "<td><span style='color: #ffc107;'>⚠️ HTTP $httpCode</span></td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        ?>

        <h2>4. File Permissions</h2>
        <?php
        $directories = ['uploads/', 'logs/', 'backups/'];

        echo "<table>";
        echo "<tr><th>Directory</th><th>Status</th><th>Writable</th></tr>";

        foreach ($directories as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            $exists = is_dir($fullPath);
            $writable = $exists && is_writable($fullPath);

            echo "<tr><td>$dir</td>";
            echo "<td>" . ($exists ? "<span style='color: #28a745;'>✅ Exists</span>" : "<span style='color: #dc3545;'>❌ Missing</span>") . "</td>";
            echo "<td>" . ($writable ? "<span style='color: #28a745;'>✅ Writable</span>" : "<span style='color: #dc3545;'>❌ Not Writable</span>") . "</td>";
            echo "</tr>";

            if (!$exists) {
                @mkdir($fullPath, 0755, true);
            }
        }
        echo "</table>";
        ?>

        <h2>5. Summary & Next Steps</h2>
        <?php
        $allGood = extension_loaded('pdo_mysql') && isset($pdo) && $pdo !== null && empty($missingExtensions);

        if ($allGood) {
            echo "<div class='success'><span class='status-icon'>🎉</span> <strong>All Systems GO!</strong><br>";
            echo "Your ATIERA Finance system is properly configured and ready to use.<br><br>";
            echo "<a href='index.php' class='btn'>Go to Login Page</a>";
            echo "<a href='admin/integrations.php' class='btn' style='background: #28a745;'>Test API Integrations</a>";
            echo "</div>";
        } else {
            echo "<div class='warning'><span class='status-icon'>⚠️</span> <strong>Action Required</strong><br>";
            echo "Please fix the issues listed above before using the system.<br>";
            echo "After making changes, <a href='verify_system.php'>refresh this page</a> to verify.</div>";
        }
        ?>

        <h2>6. System Information</h2>
        <table>
            <tr><th>Item</th><th>Value</th></tr>
            <tr><td>PHP Version</td><td><?php echo phpversion(); ?></td></tr>
            <tr><td>Server Software</td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td></tr>
            <tr><td>Document Root</td><td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></td></tr>
            <tr><td>PHP SAPI</td><td><?php echo php_sapi_name(); ?></td></tr>
            <tr><td>Max Execution Time</td><td><?php echo ini_get('max_execution_time'); ?>s</td></tr>
            <tr><td>Memory Limit</td><td><?php echo ini_get('memory_limit'); ?></td></tr>
            <tr><td>Upload Max Filesize</td><td><?php echo ini_get('upload_max_filesize'); ?></td></tr>
            <tr><td>Post Max Size</td><td><?php echo ini_get('post_max_size'); ?></td></tr>
        </table>

        <p style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #007bff;">
            <strong>Note:</strong> For security reasons, delete this file (<code>verify_system.php</code>) after verification is complete.
        </p>
    </div>
</body>
</html>
