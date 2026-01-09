<?php
/**
 * ATIERA Financial Management System Configuration
 * Loads environment variables and provides configuration management
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found. Please create one based on .env.example');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env file
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    error_log('Configuration error: ' . $e->getMessage());
    // Continue with default values if .env is missing
}

// Set session configuration BEFORE any session is started
$sessionLifetime = getenv('SESSION_LIFETIME') ?: 7200;
ini_set('session.gc_maxlifetime', $sessionLifetime);
ini_set('session.cookie_lifetime', $sessionLifetime);

// Set session cookie parameters for proper cookie handling
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');

// Configuration class
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

    public static function set($key, $value) {
        if (empty(self::$config)) {
            self::loadConfig();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    private static function loadConfig() {
        self::$config = [
            'app' => [
                'env' => getenv('APP_ENV') ?: 'development',
                'name' => getenv('APP_NAME') ?: 'ATIERA Finance',
                'url' => getenv('APP_URL') ?: 'http://localhost',
                'key' => getenv('APP_KEY') ?: 'default-key-change-in-production',
                'debug' => getenv('APP_ENV') === 'development',
            ],

            'database' => [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'name' => getenv('DB_NAME') ?: 'fina_financialmngmnt',
                'user' => getenv('DB_USER') ?: 'financia',
                'pass' => getenv('DB_PASS') ?: 'Atiera@123',
                'charset' => 'utf8mb4',
            ],

            'mail' => [
                'mailer' => getenv('MAIL_MAILER') ?: 'smtp',
                'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
                'port' => getenv('MAIL_PORT') ?: 587,
                'username' => getenv('MAIL_USERNAME') ?: '',
                'password' => getenv('MAIL_PASSWORD') ?: '',
                'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
                'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@atiera.com',
                'from_name' => getenv('MAIL_FROM_NAME') ?: 'ATIERA Finance',
            ],

            'upload' => [
                'path' => getenv('UPLOAD_PATH') ?: 'uploads/',
                'max_size' => getenv('MAX_FILE_SIZE') ?: 10485760, // 10MB
                'allowed_extensions' => explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'pdf,doc,docx,jpg,jpeg,png'),
            ],

            'security' => [
                'session_lifetime' => getenv('SESSION_LIFETIME') ?: 7200, // 2 hours
                'csrf_lifetime' => getenv('CSRF_TOKEN_LIFETIME') ?: 3600, // 1 hour
                'login_attempts_max' => getenv('LOGIN_ATTEMPTS_MAX') ?: 5,
                'lockout_duration' => getenv('LOCKOUT_DURATION') ?: 300, // 5 minutes
            ],

            'api' => [
                'rate_limit' => getenv('API_RATE_LIMIT') ?: 100,
                'key' => getenv('API_KEY') ?: '',
            ],

            'logging' => [
                'level' => getenv('LOG_LEVEL') ?: 'error',
                'file' => getenv('LOG_FILE') ?: 'logs/app.log',
            ],

            'backup' => [
                'path' => getenv('BACKUP_PATH') ?: 'backups/',
                'retention_days' => getenv('BACKUP_RETENTION_DAYS') ?: 30,
            ],

            'currency' => [
                'default' => getenv('DEFAULT_CURRENCY') ?: 'PHP',
                'symbol' => getenv('CURRENCY_SYMBOL') ?: '₱',
            ],

            'company' => [
                'name' => getenv('COMPANY_NAME') ?: 'ATIERA Hotel & Restaurant',
                'address' => getenv('COMPANY_ADDRESS') ?: '',
                'phone' => getenv('COMPANY_PHONE') ?: '',
                'email' => getenv('COMPANY_EMAIL') ?: 'info@atiera.com',
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

// Include required files
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

// Initialize configuration
Config::get('app.name'); // Trigger config loading

// Set PHP configuration based on environment
if (Config::isDevelopment()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ERROR | E_PARSE);
}



// Create necessary directories
$dirs = [
    Config::get('upload.path'),
    Config::get('logging.file') ? dirname(Config::get('logging.file')) : 'logs',
    Config::get('backup.path'),
];

foreach ($dirs as $dir) {
    if ($dir && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
