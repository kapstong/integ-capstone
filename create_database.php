<?php
// Simple script to create the database
require_once 'config.php';

try {
    // Connect without specifying database
    $pdo = new PDO(
        "mysql:host=" . Config::get('database.host'),
        Config::get('database.user'),
        Config::get('database.pass'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    // Create database if it doesn't exist
    $dbName = Config::get('database.name');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "Database '$dbName' created or already exists.\n";

} catch (Exception $e) {
    echo "Error creating database: " . $e->getMessage() . "\n";
}
?>
