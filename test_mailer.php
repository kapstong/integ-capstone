<?php
/**
 * Test script for Mailer functionality
 */

require_once 'config.php';
require_once 'includes/mailer.php';

echo "Testing Mailer...\n";

try {
    $mailer = Mailer::getInstance();

    echo "Testing testConnection method...\n";
    $result = $mailer->testConnection();

    if ($result) {
        echo "✓ Mailer test passed! Email sent successfully.\n";
    } else {
        echo "✗ Mailer test failed! Check logs for details.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>