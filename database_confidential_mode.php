<?php
/**
 * Database Migration: Confidential Mode Feature
 * Adds system settings for hiding/blurring sensitive financial data
 */

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Confidential Mode Database Setup</title>\n";
    echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #e8f5e9; padding: 10px; margin: 5px 0; border-left: 4px solid green; }
    .error { color: red; background: #ffebee; padding: 10px; margin: 5px 0; border-left: 4px solid red; }
    .info { color: blue; background: #e3f2fd; padding: 10px; margin: 5px 0; border-left: 4px solid blue; }
    h1 { color: #1b2f73; }
    h2 { color: #2342a6; margin-top: 30px; border-bottom: 2px solid #2342a6; padding-bottom: 10px; }
    </style>\n</head>\n<body>";

    echo "<h1>🔒 Confidential Mode Database Setup</h1>";

    // Check if system_settings table exists
    echo "<h2>Step 1: Checking system_settings Table</h2>";

    $tableExists = $db->query("SHOW TABLES LIKE 'system_settings'")->rowCount() > 0;

    if (!$tableExists) {
        echo "<div class='info'>Creating system_settings table...</div>";

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

        echo "<div class='success'>✓ Created system_settings table</div>";
    } else {
        echo "<div class='success'>✓ system_settings table already exists</div>";
    }

    // Insert confidential mode settings
    echo "<h2>Step 2: Adding Confidential Mode Settings</h2>";

    $settings = [
        [
            'key' => 'confidential_mode_enabled',
            'value' => '0',
            'type' => 'boolean',
            'description' => 'Enable/disable confidential mode to blur sensitive financial data'
        ],
        [
            'key' => 'confidential_mode_blur_style',
            'value' => 'blur',
            'type' => 'string',
            'description' => 'Display style for confidential data: blur, asterisk, or redacted'
        ],
        [
            'key' => 'confidential_mode_auto_lock',
            'value' => '300',
            'type' => 'number',
            'description' => 'Auto-lock confidential data after N seconds of inactivity (0 = never)'
        ],
        [
            'key' => 'confidential_mode_unlock_duration',
            'value' => '1800',
            'type' => 'number',
            'description' => 'How long (in seconds) confidential data stays unlocked (default 30 minutes)'
        ]
    ];

    foreach ($settings as $setting) {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_type = VALUES(setting_type),
                description = VALUES(description)
        ");

        $stmt->execute([
            $setting['key'],
            $setting['value'],
            $setting['type'],
            $setting['description']
        ]);

        echo "<div class='success'>✓ Added/Updated setting: {$setting['key']}</div>";
    }

    echo "<h2>Step 3: Verification</h2>";

    $stmt = $db->query("SELECT * FROM system_settings WHERE setting_key LIKE 'confidential_mode%'");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'><strong>Confidential Mode Settings:</strong><br>";
    foreach ($results as $row) {
        echo "- {$row['setting_key']}: {$row['setting_value']} ({$row['setting_type']})<br>";
    }
    echo "</div>";

    echo "<h2>✅ Setup Complete!</h2>";
    echo "<div class='success'>";
    echo "<p><strong>Confidential Mode feature has been set up successfully!</strong></p>";
    echo "<ul>";
    echo "<li>Database tables created/updated</li>";
    echo "<li>Default settings configured</li>";
    echo "<li>Ready to enable in Admin Settings</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='text-align: center; margin: 40px 0;'>";
    echo "<a href='admin/settings.php' style='display: inline-block; padding: 15px 30px; background: #1b2f73; color: white; text-decoration: none; border-radius: 8px;'>Go to Settings</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'><h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body>\n</html>";
?>
