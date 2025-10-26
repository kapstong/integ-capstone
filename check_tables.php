<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SHOW TABLES');

    echo "Database Tables:\n";
    echo str_repeat('=', 50) . "\n";

    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }

    echo "\n\nChecking for user_2fa table structure:\n";
    echo str_repeat('=', 50) . "\n";

    $stmt = $db->query("SHOW TABLES LIKE 'user_2fa'");
    if ($stmt->rowCount() > 0) {
        echo "user_2fa table EXISTS\n";
        $stmt = $db->query("DESCRIBE user_2fa");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
        }
    } else {
        echo "user_2fa table DOES NOT EXIST\n";
    }

    echo "\n\nChecking for audit_log table structure:\n";
    echo str_repeat('=', 50) . "\n";

    $stmt = $db->query("SHOW TABLES LIKE 'audit_log'");
    if ($stmt->rowCount() > 0) {
        echo "audit_log table EXISTS\n";
        $stmt = $db->query("DESCRIBE audit_log");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
        }
    } else {
        echo "audit_log table DOES NOT EXIST\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
