<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();

    // Check if adjustments table exists
    $result = $db->query('SHOW TABLES LIKE "adjustments"');
    echo 'Adjustments table exists: ' . ($result->rowCount() > 0 ? 'YES' : 'NO') . PHP_EOL;

    // Check if vendors table has data
    $result = $db->query('SELECT COUNT(*) FROM vendors');
    $count = $result->fetchColumn();
    echo 'Vendors count: ' . $count . PHP_EOL;

    // Check if chart_of_accounts has our needed accounts
    $result = $db->query('SELECT account_code FROM chart_of_accounts WHERE account_code IN ("1002", "2001", "4001", "5002")');
    $accounts = $result->fetchAll(PDO::FETCH_COLUMN);
    echo 'Required accounts: ' . implode(', ', $accounts) . PHP_EOL;

    echo 'Database connection successful!' . PHP_EOL;

} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
