<?php
/**
 * Database Connection Test Script
 * Use this to test database connectivity in production
 */

// Test direct PDO connection
echo "Testing database connection...\n\n";

$host = 'localhost';
$dbname = 'fina_financialmngmnt';
$username = 'financia';
$password = 'Atiera@123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    echo "✅ Database connection successful!\n";

    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch();
    echo "✅ Query successful! Found {$result['user_count']} users in database.\n";

    // Test new tables
    $newTables = ['currencies', 'bank_accounts', 'tax_codes', 'fixed_assets', 'recurring_transactions'];
    echo "\nChecking for new tables:\n";

    foreach ($newTables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch()['count'];
            echo "✅ Table '$table' exists with $count records\n";
        } catch (PDOException $e) {
            echo "❌ Table '$table' does not exist or is not accessible\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";

    // Provide troubleshooting steps
    echo "\n🔧 Troubleshooting Steps:\n";
    echo "1. Check if MySQL server is running\n";
    echo "2. Verify database credentials are correct\n";
    echo "3. Ensure the database '$dbname' exists\n";
    echo "4. Check if user '$username' has access to database\n";
    echo "5. Verify PDO MySQL extension is installed: php -m | grep pdo_mysql\n";
}
?>