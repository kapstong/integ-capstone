<?php
/**
 * ATIERA FINANCIALS System - Setup Script
 * Hotel & Restaurant Financial Management Extensions
 *
 * SCOPE: Financial tracking and reporting ONLY
 * Integrates with: Hotel Core 1, Restaurant Core 2, Logistics 1, HR Systems
 */

require_once 'includes/database.php';
require_once 'includes/auth.php';

session_start();

$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    $auth = new Auth();
    if (!$auth->isLoggedIn() || !$auth->hasPermission('settings.edit')) {
        die('Error: Administrator access required.');
    }
}

$db = Database::getInstance()->getConnection();

echo "==============================================\n";
echo "ATIERA FINANCIALS Extension Setup\n";
echo "Hotel & Restaurant Financial Management\n";
echo "==============================================\n\n";

try {
    $db->beginTransaction();

    // Step 1: Create Financial Tables
    echo "Step 1: Creating financial management tables...\n";

    $schemaFile = __DIR__ . '/financials_extension_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    $schemaSql = file_get_contents($schemaFile);
    $schemaSql = preg_replace('/USE\s+\w+;/i', '', $schemaSql);
    $schemaSql = preg_replace('/--.*$/m', '', $schemaSql);
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
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
    }

    echo "  ✓ Created/verified $tableCount financial tables\n\n";

    // Step 2: Add USALI Chart of Accounts
    echo "Step 2: Adding USALI chart of accounts...\n";

    $accountsFile = __DIR__ . '/hotel_restaurant_accounts.sql';
    if (!file_exists($accountsFile)) {
        throw new Exception("Accounts file not found: $accountsFile");
    }

    $accountsSql = file_get_contents($accountsFile);
    $accountsSql = preg_replace('/USE\s+\w+;/i', '', $accountsSql);
    $accountsSql = preg_replace('/--.*$/m', '', $accountsSql);
    $statements = array_filter(array_map('trim', explode(';', $accountsSql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }

    echo "  ✓ USALI chart of accounts ready\n\n";

    // Step 3: Create Financial Permissions
    echo "Step 3: Creating financial permissions...\n";

    $permissions = [
        // Department/Cost Center Management
        ['name' => 'departments.view', 'description' => 'View financial departments', 'module' => 'departments'],
        ['name' => 'departments.manage', 'description' => 'Manage financial departments', 'module' => 'departments'],

        // Collection/Cashier
        ['name' => 'cashier.operate', 'description' => 'Operate cashier terminal', 'module' => 'collection'],
        ['name' => 'cashier.reconcile', 'description' => 'Reconcile cashier sessions', 'module' => 'collection'],
        ['name' => 'cashier.view_all', 'description' => 'View all cashier sessions', 'module' => 'collection'],

        // Integration Management
        ['name' => 'integrations.view', 'description' => 'View system integrations', 'module' => 'integrations'],
        ['name' => 'integrations.manage', 'description' => 'Manage system integrations', 'module' => 'integrations'],
        ['name' => 'integrations.import', 'description' => 'Import transactions from other systems', 'module' => 'integrations'],
        ['name' => 'integrations.post', 'description' => 'Post imported transactions to GL', 'module' => 'integrations'],

        // Budget Management
        ['name' => 'budgets.view', 'description' => 'View budgets', 'module' => 'budgets'],
        ['name' => 'budgets.create', 'description' => 'Create budgets', 'module' => 'budgets'],
        ['name' => 'budgets.approve', 'description' => 'Approve budgets', 'module' => 'budgets'],

        // Financial Reporting
        ['name' => 'reports.usali', 'description' => 'Generate USALI reports', 'module' => 'reports'],
        ['name' => 'reports.department', 'description' => 'Generate department P&L reports', 'module' => 'reports'],
        ['name' => 'reports.budget_variance', 'description' => 'Generate budget variance reports', 'module' => 'reports'],
    ];

    $permStmt = $db->prepare("
        INSERT INTO permissions (name, description, module, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE description = VALUES(description), module = VALUES(module)
    ");

    foreach ($permissions as $perm) {
        $permStmt->execute([$perm['name'], $perm['description'], $perm['module']]);
    }

    echo "  ✓ Created " . count($permissions) . " financial permissions\n\n";

    // Step 4: Assign to Admin Role
    echo "Step 4: Assigning permissions to admin...\n";

    $stmt = $db->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
    $adminRole = $stmt->fetch();

    if ($adminRole) {
        $assignStmt = $db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id, assigned_at)
            SELECT ?, p.id, NOW()
            FROM permissions p
            WHERE p.module IN ('departments', 'collection', 'integrations', 'budgets', 'reports')
        ");
        $assignStmt->execute([$adminRole['id']]);
        echo "  ✓ Permissions assigned to admin role\n\n";
    }

    $db->commit();

    echo "\n==============================================\n";
    echo "✓ SETUP COMPLETED SUCCESSFULLY!\n";
    echo "==============================================\n\n";

    echo "Financial System Ready:\n";
    echo "  ✓ Department/cost center tracking\n";
    echo "  ✓ Collection/cashier module\n";
    echo "  ✓ System integration framework\n";
    echo "  ✓ USALI chart of accounts\n";
    echo "  ✓ Budget management\n";
    echo "  ✓ Financial reporting structure\n\n";

    echo "Integration Points Configured:\n";
    echo "  → Hotel Core 1 (PMS)\n";
    echo "  → Restaurant Core 2 (POS)\n";
    echo "  → Logistics System\n";
    echo "  → HR System\n\n";

    echo "Next Steps:\n";
    echo "  1. Configure integration mappings\n";
    echo "  2. Set up department budgets\n";
    echo "  3. Import historical transactions\n";
    echo "  4. Generate financial reports\n\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\n✗ SETUP FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>
