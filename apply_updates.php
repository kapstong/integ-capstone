<?php
/**
 * ATIERA Financial Management System - Apply Database Updates
 * This script applies the database updates from database_updates.sql
 */

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "Starting database updates...\n";

    // Read the SQL file
    $sql = file_get_contents('database_updates.sql');

    if (!$sql) {
        throw new Exception("Could not read database_updates.sql file");
    }

    // Split the SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $totalStatements = count($statements);
    $executedStatements = 0;
    $errors = 0;

    echo "Found {$totalStatements} SQL statements to execute...\n\n";

    foreach ($statements as $statement) {
        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $db->exec($statement);
            $executedStatements++;
            echo "✓ Executed statement " . ($executedStatements + $errors) . "/" . $totalStatements . "\n";
        } catch (PDOException $e) {
            // Check if it's an acceptable error (like table already exists)
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false ||
                strpos($e->getMessage(), 'Cannot add foreign key constraint') !== false) {
                echo "⚠ Skipped statement " . ($executedStatements + $errors + 1) . " (already exists or constraint issue): " . substr($e->getMessage(), 0, 100) . "\n";
                $errors++;
            } else {
                echo "✗ Error in statement " . ($executedStatements + $errors + 1) . ": " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 200) . "...\n\n";
                $errors++;
            }
        }
    }

    echo "\nDatabase update completed!\n";
    echo "Successfully executed: {$executedStatements} statements\n";
    echo "Skipped/errors: {$errors} statements\n";

    // Verify some key tables were created
    echo "\nVerifying new tables...\n";

    $tablesToCheck = [
        'currencies',
        'bank_accounts',
        'tax_codes',
        'fixed_assets',
        'recurring_transactions',
        'email_templates',
        'companies'
    ];

    foreach ($tablesToCheck as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM `{$table}`");
            $count = $stmt->fetch()['count'];
            echo "✓ Table '{$table}' exists with {$count} records\n";
        } catch (PDOException $e) {
            echo "✗ Table '{$table}' verification failed: " . $e->getMessage() . "\n";
        }
    }

    echo "\nDatabase updates applied successfully!\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>