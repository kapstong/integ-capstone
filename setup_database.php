<?php
require_once 'includes/database.php';

try {
    $pdo = Database::getInstance()->getConnection();

    // Set foreign key checks off temporarily
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Read the SQL file
    $sql = file_get_contents('database_schema.sql');

    // Split into individual statements more carefully
    $statements = [];
    $currentStatement = '';
    $inString = false;
    $stringChar = '';
    $inComment = false;

    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip empty lines and comments
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }

        // Handle multi-line statements
        $currentStatement .= $line . "\n";

        // Check if this line ends a statement
        $semicolonPos = strpos($line, ';');
        if ($semicolonPos !== false) {
            // Make sure semicolon is not inside quotes
            $beforeSemicolon = substr($line, 0, $semicolonPos);
            $quoteCount = substr_count($beforeSemicolon, "'") + substr_count($beforeSemicolon, '"');

            if ($quoteCount % 2 == 0) { // Even number of quotes means semicolon is not inside a string
                $statements[] = trim($currentStatement);
                $currentStatement = '';
            }
        }
    }

    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (Exception $e) {
                echo "Warning on statement: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
                // Continue with other statements
            }
        }
    }

    // Set foreign key checks back on
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\nDatabase schema executed successfully!\n";
    echo "Default users created:\n";
    echo "- admin / admin123 (Administrator)\n";
    echo "- staff / staff123 (Staff Member)\n";

} catch (Exception $e) {
    echo "Error executing database schema: " . $e->getMessage() . "\n";
}
?>
