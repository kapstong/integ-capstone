<?php
/**
 * Apply New Features Database Updates
 * This script applies all database schema changes for the new requirements
 */

require_once 'config.php';

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get database connection
try {
    $db = Database::getInstance()->getConnection();
    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Database Updates - New Features</title>\n";
    echo "<style>body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; } ";
    echo ".success { color: green; background: #e8f5e9; padding: 10px; margin: 5px 0; border-left: 4px solid green; } ";
    echo ".error { color: red; background: #ffebee; padding: 10px; margin: 5px 0; border-left: 4px solid red; } ";
    echo ".info { color: blue; background: #e3f2fd; padding: 10px; margin: 5px 0; border-left: 4px solid blue; } ";
    echo "h1 { color: #1b2f73; } h2 { color: #2342a6; margin-top: 30px; } </style>\n</head>\n<body>";

    echo "<h1>ATIERA Financial Management System - Database Updates</h1>";
    echo "<p>Applying new features database schema changes...</p>";

    // Read SQL file
    $sqlFile = __DIR__ . '/database_updates_new_features.sql';

    if (!file_exists($sqlFile)) {
        echo "<div class='error'>Error: SQL file not found at: $sqlFile</div>";
        exit;
    }

    $sql = file_get_contents($sqlFile);

    // Split SQL into individual statements
    // Remove comments and split by delimiter changes
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments

    // Split by DELIMITER commands
    $parts = preg_split('/DELIMITER\s+(.*?)\s*$/m', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);

    $delimiter = ';';
    $statements = [];
    $currentStatements = '';

    for ($i = 0; $i < count($parts); $i++) {
        if ($i % 2 == 0) {
            // This is a SQL block
            $currentStatements .= $parts[$i];
        } else {
            // This is a delimiter change
            $delimiter = $parts[$i];
        }
    }

    // Split by the final delimiter
    $rawStatements = explode(';', $currentStatements);

    // Clean and prepare statements
    foreach ($rawStatements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }
    }

    echo "<h2>Executing Database Updates</h2>";
    echo "<div class='info'>Total statements to execute: " . count($statements) . "</div>";

    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;

    foreach ($statements as $index => $statement) {
        try {
            // Skip empty statements
            if (empty(trim($statement))) {
                $skippedCount++;
                continue;
            }

            // Execute statement
            $db->exec($statement);

            // Show success for important statements
            if (preg_match('/CREATE TABLE|ALTER TABLE|CREATE VIEW|CREATE PROCEDURE|CREATE TRIGGER/i', $statement)) {
                $firstLine = substr($statement, 0, 100);
                echo "<div class='success'>✓ " . htmlspecialchars($firstLine) . "...</div>";
            }

            $successCount++;

        } catch (PDOException $e) {
            // Check if error is about duplicate/already exists
            if (strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), "can't DROP") !== false ||
                strpos($e->getMessage(), 'check that column') !== false) {
                $skippedCount++;
                // Silently skip
            } else {
                $firstLine = substr($statement, 0, 100);
                echo "<div class='error'>✗ Error in statement: " . htmlspecialchars($firstLine) . "...<br>";
                echo "Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                $errorCount++;
            }
        }
    }

    echo "<h2>Summary</h2>";
    echo "<div class='success'>Successful operations: $successCount</div>";
    echo "<div class='info'>Skipped (already exists): $skippedCount</div>";

    if ($errorCount > 0) {
        echo "<div class='error'>Errors encountered: $errorCount</div>";
    }

    // Verify tables were created
    echo "<h2>Verification</h2>";
    echo "<p>Checking if new tables were created successfully...</p>";

    $tablesToCheck = [
        'login_sessions',
        'notifications',
        'budget_liquidations',
        'liquidation_receipts',
        'budget_proposal_breakdown',
        'department_liquidation_requirements'
    ];

    foreach ($tablesToCheck as $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✓ Table '$table' exists</div>";

                // Show table structure
                $stmt = $db->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<div class='info'>Columns: " . count($columns) . " | ";
                $columnNames = array_map(function($col) { return $col['Field']; }, $columns);
                echo implode(', ', array_slice($columnNames, 0, 5));
                if (count($columnNames) > 5) echo ", ...";
                echo "</div>";
            } else {
                echo "<div class='error'>✗ Table '$table' NOT found</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error checking table '$table': " . $e->getMessage() . "</div>";
        }
    }

    // Check views
    echo "<h3>Views</h3>";
    $viewsToCheck = [
        'v_login_activity',
        'v_budget_liquidation_status',
        'v_user_activity_log'
    ];

    foreach ($viewsToCheck as $view) {
        try {
            $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . getenv('DB_NAME') . " = '$view'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✓ View '$view' exists</div>";
            } else {
                // Try alternative check
                try {
                    $db->query("SELECT 1 FROM $view LIMIT 1");
                    echo "<div class='success'>✓ View '$view' exists (verified by query)</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>✗ View '$view' NOT found</div>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error checking view '$view': " . $e->getMessage() . "</div>";
        }
    }

    // Check procedures
    echo "<h3>Stored Procedures</h3>";
    $proceduresToCheck = [
        'sp_log_login_session',
        'sp_log_logout_session',
        'sp_can_create_budget_proposal'
    ];

    foreach ($proceduresToCheck as $proc) {
        try {
            $stmt = $db->query("SHOW PROCEDURE STATUS WHERE Name = '$proc'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✓ Procedure '$proc' exists</div>";
            } else {
                echo "<div class='error'>✗ Procedure '$proc' NOT found</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error checking procedure '$proc': " . $e->getMessage() . "</div>";
        }
    }

    echo "<h2>Completion</h2>";
    if ($errorCount == 0) {
        echo "<div class='success'>";
        echo "<h3>✓ Database updates completed successfully!</h3>";
        echo "<p>All tables, views, procedures, and triggers have been created.</p>";
        echo "<p>You can now proceed to use the new features.</p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>⚠ Database updates completed with errors</h3>";
        echo "<p>Please review the errors above and fix them manually if needed.</p>";
        echo "</div>";
    }

    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #1b2f73; color: white; text-decoration: none; border-radius: 5px;'>Return to Login</a></p>";

    echo "</body>\n</html>";

} catch (Exception $e) {
    echo "<div class='error'><h2>Fatal Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
