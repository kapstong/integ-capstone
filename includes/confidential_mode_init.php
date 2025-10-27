<?php
/**
 * Auto-initialize Confidential Mode
 * This runs automatically and ensures confidential mode is set up and enabled
 */

if (!defined('CONFIDENTIAL_MODE_INITIALIZED')) {
    define('CONFIDENTIAL_MODE_INITIALIZED', true);

    try {
        $db = Database::getInstance()->getConnection();

        // Check if system_settings table exists
        $tableExists = $db->query("SHOW TABLES LIKE 'system_settings'")->rowCount() > 0;

        if (!$tableExists) {
            // Create system_settings table
            $db->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    setting_type ENUM('boolean', 'string', 'number', 'json') DEFAULT 'string',
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_setting_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Check if confidential mode settings exist
        $stmt = $db->query("SELECT COUNT(*) as count FROM system_settings WHERE setting_key LIKE 'confidential_mode%'");
        $settingsExist = $stmt->fetch()['count'] > 0;

        if (!$settingsExist) {
            // Insert default settings with ENABLED by default
            $settings = [
                ['confidential_mode_enabled', '1', 'boolean', 'Enable/disable confidential mode to blur sensitive financial data'],
                ['confidential_mode_blur_style', 'blur', 'string', 'Display style for confidential data: blur, asterisk, or redacted'],
                ['confidential_mode_auto_lock', '300', 'number', 'Auto-lock after N seconds of inactivity (0 = never)'],
                ['confidential_mode_unlock_duration', '1800', 'number', 'How long data stays unlocked (default 30 minutes)']
            ];

            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, description)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_type = VALUES(setting_type),
                    description = VALUES(description)
            ");

            foreach ($settings as $setting) {
                $stmt->execute($setting);
            }
        }

    } catch (Exception $e) {
        // Silently fail - don't break the system if confidential mode setup fails
        error_log("Confidential Mode Init Error: " . $e->getMessage());
    }
}
?>
