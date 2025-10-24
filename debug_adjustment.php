<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Database connection successful\n";

    // Check if tables exist
    $tables = ['adjustments', 'chart_of_accounts', 'adjustments'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            echo "Table '$table': " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
        } catch (Exception $e) {
            echo "Table '$table': ERROR - {$e->getMessage()}\n";
        }
    }

    // Create a test adjustment
    $testData = [
        'adjustment_type' => 'credit_memo',
        'amount' => 100.00,
        'reason' => 'Test adjustment',
        'adjustment_date' => '2024-01-01'
    ];

    echo "\nTesting adjustment creation...\n";
    echo "Data: " . json_encode($testData) . "\n";

    // Try the insert query manually
    $stmt = $db->prepare("
        INSERT INTO adjustments (
            adjustment_number, adjustment_type, vendor_id, customer_id,
            bill_id, invoice_id, amount, reason, adjustment_date, recorded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        'ADJ-P-0001', // adjustment_number
        $testData['adjustment_type'],
        null, // vendor_id
        null, // customer_id
        null, // bill_id
        null, // invoice_id
        $testData['amount'],
        $testData['reason'],
        $testData['adjustment_date'],
        1 // recorded_by
    ]);

    if ($result) {
        echo "Insert successful! ID: " . $db->lastInsertId() . "\n";
        // Clean up the test record
        $db->exec("DELETE FROM adjustments WHERE adjustment_number = 'ADJ-P-0001'");
        echo "Cleanup successful\n";
    } else {
        echo "Insert failed!\n";
    }

} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}\n";
    echo "Line: {$e->getLine()}\n";
}
?>
