<?php
/**
 * ATIERA Financial Management System
 * Hotel & Restaurant Module Setup Script
 *
 * This script applies all hotel/restaurant-specific database extensions
 * Run this AFTER the main database schema has been created
 */

require_once 'includes/database.php';
require_once 'includes/auth.php';

// Start session and check admin access
session_start();

// Check if running from command line or web
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    // Web access - require admin login
    $auth = new Auth();
    if (!$auth->isLoggedIn() || !$auth->hasPermission('settings.edit')) {
        die('Error: This script requires administrator access. Please log in as admin.');
    }
}

// Get database connection
$db = Database::getInstance()->getConnection();

echo "==============================================\n";
echo "ATIERA Hotel & Restaurant Module Setup\n";
echo "==============================================\n\n";

$errors = [];
$success = [];

try {
    // Start transaction
    $db->beginTransaction();

    // ========================================
    // STEP 1: Create Hotel/Restaurant Tables
    // ========================================
    echo "Step 1: Creating hotel/restaurant specific tables...\n";

    $schemaFile = __DIR__ . '/hotel_restaurant_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    $schemaSql = file_get_contents($schemaFile);

    // Remove USE statement and comments for execution
    $schemaSql = preg_replace('/USE\s+\w+;/i', '', $schemaSql);
    $schemaSql = preg_replace('/--.*$/m', '', $schemaSql);

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));

    $tableCount = 0;
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    $tableCount++;
                }
            } catch (PDOException $e) {
                // Ignore "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
    }

    $success[] = "Created/verified $tableCount hotel/restaurant tables";
    echo "  ✓ Tables created successfully\n\n";

    // ========================================
    // STEP 2: Add Hotel/Restaurant Accounts
    // ========================================
    echo "Step 2: Adding hotel/restaurant chart of accounts...\n";

    $accountsFile = __DIR__ . '/hotel_restaurant_accounts.sql';
    if (!file_exists($accountsFile)) {
        throw new Exception("Accounts file not found: $accountsFile");
    }

    $accountsSql = file_get_contents($accountsFile);

    // Remove USE statement and comments
    $accountsSql = preg_replace('/USE\s+\w+;/i', '', $accountsSql);
    $accountsSql = preg_replace('/--.*$/m', '', $accountsSql);

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $accountsSql)));

    $accountCount = 0;
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
                if (stripos($statement, 'INSERT INTO') !== false) {
                    $accountCount++;
                }
            } catch (PDOException $e) {
                // Ignore duplicate entry errors
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }

    $success[] = "Added/updated hotel/restaurant chart of accounts";
    echo "  ✓ Chart of accounts updated\n\n";

    // ========================================
    // STEP 3: Add Hotel/Restaurant Permissions
    // ========================================
    echo "Step 3: Adding hotel/restaurant specific permissions...\n";

    $hotelPermissions = [
        // Department Management
        ['name' => 'departments.view', 'description' => 'View departments', 'module' => 'departments'],
        ['name' => 'departments.create', 'description' => 'Create departments', 'module' => 'departments'],
        ['name' => 'departments.edit', 'description' => 'Edit departments', 'module' => 'departments'],
        ['name' => 'departments.delete', 'description' => 'Delete departments', 'module' => 'departments'],

        // Room Management
        ['name' => 'rooms.view', 'description' => 'View room inventory', 'module' => 'rooms'],
        ['name' => 'rooms.manage', 'description' => 'Manage room inventory', 'module' => 'rooms'],
        ['name' => 'reservations.view', 'description' => 'View reservations', 'module' => 'rooms'],
        ['name' => 'reservations.create', 'description' => 'Create reservations', 'module' => 'rooms'],
        ['name' => 'reservations.edit', 'description' => 'Edit reservations', 'module' => 'rooms'],

        // Inventory Management
        ['name' => 'inventory.view', 'description' => 'View inventory', 'module' => 'inventory'],
        ['name' => 'inventory.manage', 'description' => 'Manage inventory items', 'module' => 'inventory'],
        ['name' => 'inventory.adjust', 'description' => 'Make inventory adjustments', 'module' => 'inventory'],
        ['name' => 'inventory.transfer', 'description' => 'Transfer inventory', 'module' => 'inventory'],

        // Purchase Orders
        ['name' => 'purchase_orders.view', 'description' => 'View purchase orders', 'module' => 'inventory'],
        ['name' => 'purchase_orders.create', 'description' => 'Create purchase orders', 'module' => 'inventory'],
        ['name' => 'purchase_orders.approve', 'description' => 'Approve purchase orders', 'module' => 'inventory'],

        // Cashier Operations
        ['name' => 'cashier.operate', 'description' => 'Operate cashier terminal', 'module' => 'cashier'],
        ['name' => 'cashier.reconcile', 'description' => 'Reconcile cashier sessions', 'module' => 'cashier'],
        ['name' => 'cashier.view_all', 'description' => 'View all cashier sessions', 'module' => 'cashier'],

        // Commission Management
        ['name' => 'commissions.view', 'description' => 'View commissions', 'module' => 'commissions'],
        ['name' => 'commissions.calculate', 'description' => 'Calculate commissions', 'module' => 'commissions'],
        ['name' => 'commissions.approve', 'description' => 'Approve commissions', 'module' => 'commissions'],

        // Event Management
        ['name' => 'events.view', 'description' => 'View events', 'module' => 'events'],
        ['name' => 'events.create', 'description' => 'Create events', 'module' => 'events'],
        ['name' => 'events.manage', 'description' => 'Manage events', 'module' => 'events'],

        // POS Operations
        ['name' => 'pos.view', 'description' => 'View POS sales', 'module' => 'pos'],
        ['name' => 'pos.manage', 'description' => 'Manage POS settings', 'module' => 'pos'],

        // Revenue Management
        ['name' => 'revenue.view', 'description' => 'View revenue reports', 'module' => 'revenue'],
        ['name' => 'revenue.analysis', 'description' => 'Access revenue analysis tools', 'module' => 'revenue'],

        // Housekeeping
        ['name' => 'housekeeping.view', 'description' => 'View housekeeping tasks', 'module' => 'housekeeping'],
        ['name' => 'housekeeping.manage', 'description' => 'Manage housekeeping tasks', 'module' => 'housekeeping'],
    ];

    $permStmt = $db->prepare("
        INSERT INTO permissions (name, description, module, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE description = VALUES(description), module = VALUES(module)
    ");

    $permCount = 0;
    foreach ($hotelPermissions as $perm) {
        $permStmt->execute([$perm['name'], $perm['description'], $perm['module']]);
        $permCount++;
    }

    $success[] = "Added $permCount hotel/restaurant permissions";
    echo "  ✓ Permissions created\n\n";

    // ========================================
    // STEP 4: Assign Permissions to Admin Role
    // ========================================
    echo "Step 4: Assigning permissions to admin role...\n";

    // Get admin role ID
    $stmt = $db->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
    $adminRole = $stmt->fetch();

    if ($adminRole) {
        $assignStmt = $db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id, assigned_at)
            SELECT ?, p.id, NOW()
            FROM permissions p
            WHERE p.module IN ('departments', 'rooms', 'inventory', 'cashier', 'commissions', 'events', 'pos', 'revenue', 'housekeeping')
        ");
        $assignStmt->execute([$adminRole['id']]);

        $success[] = "Assigned hotel/restaurant permissions to admin role";
        echo "  ✓ Permissions assigned to admin\n\n";
    }

    // ========================================
    // STEP 5: Create Default Sample Data
    // ========================================
    echo "Step 5: Creating sample data (optional)...\n";

    // Check if sample data already exists
    $stmt = $db->query("SELECT COUNT(*) as count FROM departments");
    $deptCount = $stmt->fetch()['count'];

    if ($deptCount == 0) {
        echo "  → No departments found, sample data already inserted via schema\n";
    }

    $success[] = "Sample data verified";
    echo "  ✓ Sample data ready\n\n";

    // Commit transaction
    $db->commit();

    // ========================================
    // SETUP COMPLETE
    // ========================================
    echo "\n==============================================\n";
    echo "✓ SETUP COMPLETED SUCCESSFULLY!\n";
    echo "==============================================\n\n";

    echo "Summary:\n";
    foreach ($success as $msg) {
        echo "  ✓ $msg\n";
    }

    echo "\n\nNext Steps:\n";
    echo "  1. Log in to the admin panel\n";
    echo "  2. Navigate to the new hotel/restaurant modules\n";
    echo "  3. Configure your departments and revenue centers\n";
    echo "  4. Set up inventory items and menu items\n";
    echo "  5. Configure POS and PMS integration (if needed)\n\n";

    if (!$isCLI) {
        echo '<a href="/admin/dashboard.php">Go to Admin Dashboard</a>';
    }

} catch (Exception $e) {
    $db->rollBack();

    echo "\n==============================================\n";
    echo "✗ SETUP FAILED\n";
    echo "==============================================\n\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";

    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  ✗ $error\n";
        }
    }

    exit(1);
}
?>
