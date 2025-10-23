<?php
/**
 * ATIERA Database Migration - External API Tables
 * Creates tables needed for external API functionality
 */

require_once 'includes/database.php';
require_once 'includes/logger.php';

$db = Database::getInstance();

echo "Starting API tables migration...\n";

try {
    // Create api_clients table
    $db->execute("
        CREATE TABLE IF NOT EXISTS api_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            api_key VARCHAR(255) NOT NULL UNIQUE,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_api_key (api_key),
            INDEX idx_active (is_active),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created api_clients table\n";

    // Create api_requests table for logging and rate limiting
    $db->execute("
        CREATE TABLE IF NOT EXISTS api_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            method VARCHAR(10) NOT NULL,
            endpoint VARCHAR(500) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            response_code INT DEFAULT 200,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client_id (client_id),
            INDEX idx_created_at (created_at),
            INDEX idx_client_time (client_id, created_at),
            FOREIGN KEY (client_id) REFERENCES api_clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created api_requests table\n";

    // Add API-related columns to existing tables
    $tables = [
        'invoices' => [
            'created_via_api' => 'TINYINT(1) DEFAULT 0',
            'api_client_id' => 'INT NULL'
        ],
        'bills' => [
            'created_via_api' => 'TINYINT(1) DEFAULT 0',
            'api_client_id' => 'INT NULL'
        ],
        'payments_received' => [
            'created_via_api' => 'TINYINT(1) DEFAULT 0',
            'api_client_id' => 'INT NULL'
        ],
        'payments_made' => [
            'created_via_api' => 'TINYINT(1) DEFAULT 0',
            'api_client_id' => 'INT NULL'
        ]
    ];

    foreach ($tables as $table => $columns) {
        foreach ($columns as $column => $definition) {
            try {
                $db->execute("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
                echo "✓ Added {$column} to {$table}\n";
            } catch (Exception $e) {
                echo "⚠ Column {$column} may already exist in {$table}: " . $e->getMessage() . "\n";
            }
        }
    }

    // Add foreign key constraints
    $foreignKeys = [
        'invoices' => 'api_client_id',
        'bills' => 'api_client_id',
        'payments_received' => 'api_client_id',
        'payments_made' => 'api_client_id'
    ];

    foreach ($foreignKeys as $table => $column) {
        try {
            $db->execute("ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_{$column} FOREIGN KEY ({$column}) REFERENCES api_clients(id) ON DELETE SET NULL");
            echo "✓ Added foreign key constraint for {$table}.{$column}\n";
        } catch (Exception $e) {
            echo "⚠ Foreign key constraint may already exist for {$table}.{$column}: " . $e->getMessage() . "\n";
        }
    }

    // Create webhooks table for real-time notifications
    $db->execute("
        CREATE TABLE IF NOT EXISTS webhooks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            url VARCHAR(500) NOT NULL,
            secret VARCHAR(255),
            events JSON NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created webhooks table\n";

    // Create webhook_deliveries table for tracking webhook attempts
    $db->execute("
        CREATE TABLE IF NOT EXISTS webhook_deliveries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            webhook_id INT NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            payload JSON NOT NULL,
            response_code INT,
            response_body TEXT,
            success TINYINT(1) DEFAULT 0,
            attempt_count INT DEFAULT 1,
            last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_webhook_id (webhook_id),
            INDEX idx_event_type (event_type),
            INDEX idx_success (success),
            FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created webhook_deliveries table\n";

    // Insert default permissions for API client management
    $permissions = [
        ['name' => 'admin.api_clients', 'description' => 'Manage external API clients and keys'],
        ['name' => 'admin.webhooks', 'description' => 'Manage webhook configurations']
    ];

    foreach ($permissions as $perm) {
        try {
            $db->execute("
                INSERT IGNORE INTO permissions (name, description, created_at)
                VALUES (?, ?, NOW())
            ", [$perm['name'], $perm['description']]);
            echo "✓ Added permission: {$perm['name']}\n";
        } catch (Exception $e) {
            echo "⚠ Permission may already exist: {$perm['name']}\n";
        }
    }

    // Add admin.api_clients permission to admin role
    try {
        $db->execute("
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'admin' AND p.name = 'admin.api_clients'
        ");
        echo "✓ Granted admin.api_clients permission to admin role\n";
    } catch (Exception $e) {
        echo "⚠ Permission assignment may already exist\n";
    }

    echo "\n✅ API tables migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Create your first API client in Admin > API Clients\n";
    echo "2. Test the API endpoints at /api/v1/\n";
    echo "3. Set up webhooks for real-time notifications\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    Logger::getInstance()->error("API tables migration failed", ['error' => $e->getMessage()]);
    exit(1);
}
?>
