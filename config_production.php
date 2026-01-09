<?php
/**
 * ATIERA Financial Management System - Production Configuration
 * This file contains production-ready database configuration
 */

// Production Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'fina_financialmngmnt'); // Update this to your actual production database name
define('DB_USER', 'fina_financialg10'); // Update this to your actual database username
define('DB_PASS', 'jekjek123'); // Update this to your actual database password
define('DB_CHARSET', 'utf8mb4');

// Other production settings
define('APP_ENV', 'production');
define('APP_NAME', 'ATIERA Finance');
define('APP_URL', 'https://financial.atierahotelandrestaurant.com');
define('APP_KEY', 'prod-key-' . md5('atiera-production-key')); // Generate a proper key

// Session configuration
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // HTTPS only
ini_set('session.cookie_samesite', 'Lax');

// Configuration class for production
if (!class_exists('Config')) {
class Config {
    private static $config = [];

    public static function get($key, $default = null) {
        if (empty(self::$config)) {
            self::loadConfig();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    private static function loadConfig() {
        self::$config = [
            'app' => [
                'env' => APP_ENV,
                'name' => APP_NAME,
                'url' => APP_URL,
                'key' => APP_KEY,
                'debug' => false,
            ],

            'database' => [
                'host' => DB_HOST,
                'name' => DB_NAME,
                'user' => DB_USER,
                'pass' => DB_PASS,
                'charset' => DB_CHARSET,
            ],

            'mail' => [
                'mailer' => 'smtp',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'username' => '', // Add your email
                'password' => '', // Add your email password
                'encryption' => 'tls',
                'from_address' => 'noreply@atierahotelandrestaurant.com',
                'from_name' => 'ATIERA Finance',
            ],

            'upload' => [
                'path' => 'uploads/',
                'max_size' => 10485760, // 10MB
                'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
            ],

            'security' => [
                'session_lifetime' => 7200,
                'csrf_lifetime' => 3600,
                'login_attempts_max' => 5,
                'lockout_duration' => 300,
            ],

            'api' => [
                'rate_limit' => 1000, // Higher limit for production
                'key' => md5(APP_KEY . 'api'),
            ],

            'logging' => [
                'level' => 'error',
                'file' => 'logs/app.log',
            ],

            'backup' => [
                'path' => 'backups/',
                'retention_days' => 30,
            ],

            'currency' => [
                'default' => 'PHP',
                'symbol' => '₱',
            ],

            'company' => [
                'name' => 'ATIERA Hotel & Restaurant',
                'address' => '',
                'phone' => '',
                'email' => 'info@atierahotelandrestaurant.com',
            ],
        ];
    }

    public static function isProduction() {
        return self::get('app.env') === 'production';
    }

    public static function isDevelopment() {
        return self::get('app.env') === 'development';
    }
}
}

// Production error handling - temporarily enable for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

// Initialize configuration
Config::get('app.name');

// Create necessary directories
$dirs = [
    Config::get('upload.path'),
    dirname(Config::get('logging.file')),
    Config::get('backup.path'),
];

foreach ($dirs as $dir) {
    if ($dir && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>