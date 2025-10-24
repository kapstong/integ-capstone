<?php
/**
 * ATIERA Financial Management System - System Diagnostic
 * Comprehensive diagnostic script to check for errors and issues
 */

require_once 'config.php';

class SystemDiagnostic {
    private $results = [];
    private $errors = [];
    private $warnings = [];

    public function runAllChecks() {
        echo "🚀 ATIERA System Diagnostic Starting...\n";
        echo "======================================\n\n";

        $this->checkEnvironment();
        $this->checkDatabaseConnection();
        $this->checkRequiredDirectories();
        $this->checkFilePermissions();
        $this->checkConfiguration();
        $this->checkCriticalFiles();
        $this->checkDatabaseSchema();
        $this->checkAPIAccess();
        $this->performanceChecks();

        $this->generateReport();
    }

    private function checkEnvironment() {
        echo "📋 Checking Environment...\n";

        // PHP Version
        $phpVersion = PHP_VERSION;
        $minVersion = '7.4.0';
        if (version_compare($phpVersion, $minVersion) >= 0) {
            $this->results[] = "✅ PHP Version: $phpVersion (Compatible)";
        } else {
            $this->errors[] = "❌ PHP Version: $phpVersion (Minimum $minVersion required)";
        }

        // Required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring', 'gd'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $this->results[] = "✅ Extension loaded: $ext";
            } else {
                $this->errors[] = "❌ Missing extension: $ext";
            }
        }

        // Web server
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $this->results[] = "✅ Web Server: " . $_SERVER['SERVER_SOFTWARE'];
        }

        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        $this->results[] = "✅ Memory Limit: $memoryLimit";

        echo "\n";
    }

    private function checkDatabaseConnection() {
        echo "💾 Checking Database Connection...\n";

        try {
            $db = Database::getInstance()->getConnection();
            $this->results[] = "✅ Database connection successful";

            // Test query
            $stmt = $db->query("SELECT VERSION() as version");
            $version = $stmt->fetch()['version'];
            $this->results[] = "✅ Database version: $version";

        } catch (Exception $e) {
            $this->errors[] = "❌ Database connection failed: " . $e->getMessage();
        }

        echo "\n";
    }

    private function checkRequiredDirectories() {
        echo "📁 Checking Required Directories...\n";

        $requiredDirs = ['uploads', 'logs', 'backups', 'templates', 'languages', 'cache'];
        foreach ($requiredDirs as $dir) {
            if (is_dir($dir)) {
                $this->results[] = "✅ Directory exists: $dir";
                if (is_writable($dir)) {
                    $this->results[] = "✅ Directory writable: $dir";
                } else {
                    $this->errors[] = "❌ Directory not writable: $dir";
                }
            } else {
                $this->warnings[] = "⚠️  Directory missing: $dir";
            }
        }

        echo "\n";
    }

    private function checkFilePermissions() {
        echo "🔐 Checking File Permissions...\n";

        $criticalFiles = [
            'config.php',
            'includes/database.php',
            'includes/auth.php',
            'admin/index.php',
            'user/index.php',
            'index.php'
        ];

        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                if (is_readable($file)) {
                    $this->results[] = "✅ File readable: $file";
                } else {
                    $this->errors[] = "❌ File not readable: $file";
                }

                // Check if file is world-readable (basic security check)
                $perms = fileperms($file);
                if (($perms & 0x0004)) { // world-readable
                    $this->warnings[] = "⚠️  File world-readable: $file";
                }
            } else {
                $this->errors[] = "❌ File missing: $file";
            }
        }

        echo "\n";
    }

    private function checkConfiguration() {
        echo "⚙️ Checking Configuration...\n";

        // Check .env file
        if (file_exists('.env')) {
            $this->results[] = "✅ Configuration file exists: .env";
        } else {
            $this->errors[] = "❌ Configuration file missing: .env";
        }

        // Check critical config values
        $appUrl = Config::get('app.url');
        $dbName = Config::get('database.name');

        if (strpos($appUrl, 'capstone-new') !== false) {
            $this->warnings[] = "⚠️  APP_URL may be incorrect (contains 'capstone-new')";
        }

        if (empty($dbName)) {
            $this->errors[] = "❌ Database name not configured";
        } else {
            $this->results[] = "✅ Database configured: $dbName";
        }

        // Check logging
        $logFile = Config::get('logging.file');
        if (!empty($logFile) && is_writable(dirname($logFile))) {
            $this->results[] = "✅ Log directory writable: " . dirname($logFile);
        } else {
            $this->warnings[] = "⚠️  Log directory not writable";
        }

        echo "\n";
    }

    private function checkCriticalFiles() {
        echo "🔍 Checking Critical Files...\n";

        $criticalFiles = [
            'includes/auth.php',
            'includes/database.php',
            'includes/csrf.php',
            'includes/validation.php',
            'admin/header.php',
            'admin/footer.php',
            'responsive.css'
        ];

        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $this->results[] = "✅ Critical file exists: $file";
            } else {
                $this->errors[] = "❌ Critical file missing: $file";
            }
        }

        echo "\n";
    }

    private function checkDatabaseSchema() {
        echo "🗃️ Checking Database Schema...\n";

        try {
            $db = Database::getInstance()->getConnection();

            // Check critical tables
            $criticalTables = [
                'users',
                'chart_of_accounts',
                'customers',
                'vendors',
                'invoices',
                'bills',
                'departments'
            ];

            foreach ($criticalTables as $table) {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $this->results[] = "✅ Table exists: $table";
                } else {
                    $this->warnings[] = "⚠️  Table missing: $table";
                }
            }

            // Check admin user
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
            $adminCount = $stmt->fetch()['count'];
            if ($adminCount > 0) {
                $this->results[] = "✅ Admin user exists ($adminCount found)";
            } else {
                $this->errors[] = "❌ No admin user found";
            }

        } catch (Exception $e) {
            $this->errors[] = "❌ Database schema check failed: " . $e->getMessage();
        }

        echo "\n";
    }

    private function checkAPIAccess() {
        echo "🌐 Checking API Access...\n";

        $apiFiles = [
            'api/v1/invoices.php',
            'api/v1/test.php'
        ];

        foreach ($apiFiles as $api) {
            if (file_exists($api)) {
                $this->results[] = "✅ API endpoint exists: $api";
            } else {
                $this->warnings[] = "⚠️  API endpoint missing: $api";
            }
        }

        // Test basic API response by making a simple request
        $apiUrl = Config::get('app.url') . '/api/v1/test.php';
        if (Config::get('app.debug')) {
            $this->results[] = "✅ Debug mode enabled";
        } else {
            $this->results[] = "ℹ️  Debug mode disabled";
        }

        echo "\n";
    }

    private function performanceChecks() {
        echo "⚡ Running Performance Checks...\n";

        // Check for large files that might slow down loading
        $largeFiles = $this->findLargeFiles('.', 5 * 1024 * 1024); // 5MB threshold
        if (!empty($largeFiles)) {
            foreach ($largeFiles as $file) {
                $this->warnings[] = "⚠️  Large file detected: $file";
            }
        } else {
            $this->results[] = "✅ No large files detected";
        }

        echo "\n";
    }

    private function findLargeFiles($dir, $threshold) {
        $largeFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getSize() > $threshold) {
                $largeFiles[] = $file->getPathname() . ' (' . round($file->getSize() / 1024 / 1024, 2) . ' MB)';
            }
        }

        return $largeFiles;
    }

    private function generateReport() {
        echo "📊 DIAGNOSTIC REPORT\n";
        echo "==================\n\n";

        // Summary
        $totalResults = count($this->results);
        $totalErrors = count($this->errors);
        $totalWarnings = count($this->warnings);

        echo "SUMMARY:\n";
        echo "--------\n";
        echo "✅ Passed: $totalResults\n";
        echo "❌ Errors: $totalErrors\n";
        echo "⚠️  Warnings: $totalWarnings\n\n";

        if ($totalErrors === 0 && $totalWarnings === 0) {
            echo "🎉 SYSTEM STATUS: HEALTHY\n\n";
        } elseif ($totalErrors > 0) {
            echo "🔴 SYSTEM STATUS: ISSUES FOUND\n\n";
        } else {
            echo "🟡 SYSTEM STATUS: WARNINGS PRESENT\n\n";
        }

        // Detailed results
        if (!empty($this->results)) {
            echo "✅ RESULTS:\n";
            foreach ($this->results as $result) {
                echo "   $result\n";
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS:\n";
            foreach ($this->warnings as $warning) {
                echo "   $warning\n";
            }
            echo "\n";
        }

        if (!empty($this->errors)) {
            echo "❌ ERRORS:\n";
            foreach ($this->errors as $error) {
                echo "   $error\n";
            }
            echo "\n";
        }

        // Recommendations
        echo "💡 RECOMMENDATIONS:\n";
        if (!empty($this->errors)) {
            echo "   1. Address all errors before proceeding\n";
            echo "   2. Check database connectivity\n";
            echo "   3. Verify file permissions\n";
            echo "   4. Run database setup scripts if needed\n";
        }

        if (!empty($this->warnings)) {
            echo "   - Review warnings for potential improvements\n";
            echo "   - Update APP_URL in .env if using different directory\n";
            echo "   - Set appropriate file permissions\n";
        }

        if (empty($this->errors) && empty($this->warnings)) {
            echo "   - System appears to be properly configured\n";
            echo "   - Run setup scripts if database tables are missing\n";
            echo "   - Consider enabling error logging for production\n";
        }

        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Diagnostic completed at " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run diagnostic
$diagnostic = new SystemDiagnostic();
$diagnostic->runAllChecks();
?>
