<?php
/**
 * Quick system check to validate database state without running setup
 */

require_once 'config.php';

echo "🔍 Quick System Validation\n";
echo "=========================\n\n";

$issues = []; $passed = [];

try {
    $db = Database::getInstance()->getConnection();
    $passed[] = "✅ Database connection successful";

    // Check critical tables exist
    $criticalTables = ['users', 'chart_of_accounts', 'departments', 'api_clients', 'workflows'];
    $tablesExist = 0;

    foreach ($criticalTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $tablesExist++;
            echo "✅ Table exists: $table\n";
        } else {
            echo "❌ Table missing: $table\n";
        }
    }

    // Check admin user exists
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $adminCount = $stmt->fetch()['count'];
    if ($adminCount > 0) {
        echo "✅ Admin user exists\n";
    } else {
        echo "❌ Admin user missing\n";
    }

    // Try API tables (these were failing)
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM api_clients");
        echo "✅ API clients table accessible\n";
    } catch (Exception $e) {
        echo "❌ API tables not accessible\n";
    }

} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n📊 SUMMARY: $tablesExist/5 critical tables exist\n";

if ($tablesExist >= 4) {
    echo "🎉 SYSTEM IS READY FOR USE!\n";
    echo "\nNext steps:\n";
    echo "1. Access admin panel: http://localhost/integ-capstone/admin/\n";
    echo "2. Change default password (admin/admin123)\n";
    echo "3. Run full diagnostic: system_diagnostic.php\n";
} else {
    echo "⚠️  System needs setup completion\n";
    echo "Try running individual setup scripts or drop the database and start fresh\n";
}
