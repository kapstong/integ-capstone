<?php
/**
 * ATIERA Financial Management System - Security Hardening Checklist
 * Security assessment and hardening recommendations
 */

require_once 'config.php';

class SecurityCheck {
    private $issues = [];
    private $recommendations = [];
    private $score = 100;

    public function runChecks() {
        echo "🔒 ATIERA Security Assessment\n";
        echo "============================\n\n";

        $this->checkEnvironmentSecurity();
        $this->checkFilePermissions();
        $this->checkConfigurationSecurity();
        $this->checkDatabaseSecurity();
        $this->checkSessionSecurity();
        $this->checkPasswordPolicy();

        $this->generateReport();
    }

    private function checkEnvironmentSecurity() {
        echo "📋 Environment Security...\n";

        // Check if running in development mode
        if (Config::get('app.env') === 'development') {
            $this->issues[] = "Running in development mode - consider production settings";
            $this->score -= 10;
        }

        // Check debug mode
        if (Config::get('app.debug')) {
            $this->issues[] = "Debug mode enabled - disable in production";
            $this->score -= 15;
        }

        // Check PHP version
        $phpVersion = PHP_VERSION;
        if (!version_compare($phpVersion, '8.0.0', '>=')) {
            $this->recommendations[] = "Consider upgrading PHP to 8.0+ for better security";
        }

        echo "✅ Environment checks completed\n\n";
    }

    private function checkFilePermissions() {
        echo "🔐 File Permissions...\n";

        $criticalFiles = [
            '.env',
            'config.php',
            'includes/auth.php',
            'includes/database.php'
        ];

        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $perms = fileperms($file);
                // Check if world-readable or world-writable
                if (($perms & 0x0002)) { // world-writable
                    $this->issues[] = "File is world-writable: $file";
                    $this->score -= 20;
                }

                // Critical files should not be world-readable in production
                if (DIRECTORY_SEPARATOR === '/' && ($perms & 0x0004)) { // world-readable on Unix
                    $this->recommendations[] = "Consider restricting permissions on: $file";
                }
            }
        }

        // Check for sensitive files in web root
        $sensitiveFiles = ['.env', 'config.php'];
        foreach ($sensitiveFiles as $file) {
            $this->recommendations[] = "Ensure $file is not accessible via web (protect with .htaccess)";
        }

        echo "✅ File permission checks completed\n\n";
    }

    private function checkConfigurationSecurity() {
        echo "⚙️ Configuration Security...\n";

        // Check database password
        $dbPass = Config::get('database.pass');
        if (empty($dbPass)) {
            $this->issues[] = "Database password is empty - set a strong password";
            $this->score -= 25;
        }

        // Check app key
        $appKey = Config::get('app.key');
        if (empty($appKey) || $appKey === 'default-key-change-in-production') {
            $this->issues[] = "Application key not set or using default - generate a secure key";
            $this->score -= 20;
        }

        // Check email configuration
        $mailUser = Config::get('mail.username');
        if (strpos($mailUser, 'your-email') !== false) {
            $this->issues[] = "Email configuration not set - configure proper SMTP settings";
            $this->score -= 10;
        }

        echo "✅ Configuration checks completed\n\n";
    }

    private function checkDatabaseSecurity() {
        echo "💾 Database Security...\n";

        try {
            $db = Database::getInstance()->getConnection();
            $this->recommendations[] = "Ensure database user has minimal required privileges";

            // Check if we're using root user (not recommended)
            $dbUser = Config::get('database.user');
            if (strtolower($dbUser) === 'root') {
                $this->issues[] = "Using root database user - create dedicated user";
                $this->score -= 15;
            }

            echo "✅ Database security checks completed\n";

        } catch (Exception $e) {
            $this->issues[] = "Cannot check database security: " . $e->getMessage();
            $this->score -= 20;
        }

        echo "\n";
    }

    private function checkSessionSecurity() {
        echo "🍪 Session Security...\n";

        // Check session settings
        $sessionLifetime = ini_get('session.gc_maxlifetime');
        if ($sessionLifetime < 3600) { // 1 hour
            $this->recommendations[] = "Consider longer session lifetime for better UX";
        }

        if (!ini_get('session.cookie_httponly')) {
            $this->issues[] = "Session cookies not marked as HttpOnly";
            $this->score -= 10;
        }

        if (ini_get('session.cookie_secure') && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
            $this->issues[] = "Cookie secure flag enabled but not using HTTPS";
            $this->score -= 15;
        }

        $this->recommendations[] = "Use HTTPS in production to protect session cookies";

        echo "✅ Session security checks completed\n\n";
    }

    private function checkPasswordPolicy() {
        echo "🔑 Password Policy...\n";

        // Check login attempt limits
        $maxAttempts = Config::get('security.login_attempts_max', 5);
        if ($maxAttempts > 10) {
            $this->recommendations[] = "Consider reducing max login attempts";
        }

        $this->recommendations[] = "Implement strong password requirements (length, complexity)";
        $this->recommendations[] = "Enable two-factor authentication for admin accounts";
        $this->recommendations[] = "Implement password history to prevent reuse";
        $this->recommendations[] = "Set up account lockout after failed attempts";

        echo "✅ Password policy checks completed\n\n";
    }

    private function generateReport() {
        echo "📊 SECURITY ASSESSMENT REPORT\n";
        echo "=============================\n\n";

        $grade = $this->getGrade($this->score);
        echo "Overall Security Score: {$this->score}/100 ($grade)\n\n";

        if (!empty($this->issues)) {
            echo "🚨 CRITICAL ISSUES:\n";
            foreach ($this->issues as $issue) {
                echo "   • $issue\n";
            }
            echo "\n";
        }

        if (!empty($this->recommendations)) {
            echo "💡 RECOMMENDATIONS:\n";
            foreach ($this->recommendations as $rec) {
                echo "   • $rec\n";
            }
            echo "\n";
        }

        echo "🔧 PRODUCTION HARDENING CHECKLIST:\n";
        echo "   □ Change default admin password\n";
        echo "   □ Set strong database password\n";
        echo "   □ Generate secure application key\n";
        echo "   □ Enable HTTPS/SSL\n";
        echo "   □ Configure SMTP for email notifications\n";
        echo "   □ Set appropriate file permissions\n";
        echo "   □ Enable error logging (disable display_errors)\n";
        echo "   □ Set up regular backups\n";
        echo "   □ Configure firewall rules\n";
        echo "   □ Enable fail2ban or similar for brute force protection\n";
        echo "   □ Keep software updated\n\n";

        // Score guide
        echo "📈 SCORE GUIDANCE:\n";
        echo "   90-100: Excellent security posture\n";
        echo "   70-89:  Good security with minor improvements needed\n";
        echo "   50-69:  Fair security - address critical issues\n";
        echo "   0-49:   Poor security - immediate action required\n\n";

        echo str_repeat("=", 50) . "\n";
        echo "Assessment completed at " . date('Y-m-d H:i:s') . "\n";
    }

    private function getGrade($score) {
        if ($score >= 90) return "A (Excellent)";
        if ($score >= 80) return "B (Good)";
        if ($score >= 70) return "C (Fair)";
        if ($score >= 60) return "D (Poor)";
        return "F (Critical)";
    }
}

// Run security check
$checker = new SecurityCheck();
$checker->runChecks();
?>
