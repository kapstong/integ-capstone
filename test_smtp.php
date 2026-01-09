<?php
/**
 * Test SMTP connection directly
 */

require_once 'config.php';

echo "Testing SMTP connection...\n";

$host = 'smtp.gmail.com';
$port = 587;

echo "Connecting to $host:$port...\n";

$socket = @fsockopen($host, $port, $errno, $errstr, 10);

if (!$socket) {
    echo "Connection failed: $errstr ($errno)\n";
    exit;
}

echo "Connected successfully!\n";

// Set timeout
stream_set_timeout($socket, 10);

// Read greeting
$greeting = fgets($socket, 515);
echo "Greeting: " . trim($greeting) . "\n";

// Send EHLO
$serverName = 'localhost';
fputs($socket, "EHLO $serverName\r\n");
echo "Sent: EHLO $serverName\n";

// Read EHLO response
$response = '';
while (($line = fgets($socket, 515)) !== false) {
    $response .= $line;
    echo "Response: " . trim($line) . "\n";
    if (substr($line, 3, 1) === ' ') {
        break;
    }
}

echo "Full EHLO response: " . trim($response) . "\n";

// Send STARTTLS
fputs($socket, "STARTTLS\r\n");
echo "Sent: STARTTLS\n";

$tlsResponse = fgets($socket, 515);
echo "STARTTLS Response: " . trim($tlsResponse) . "\n";

// Try to enable TLS
if (trim($tlsResponse) === '220 2.0.0 Ready to start TLS') {
    echo "Attempting TLS upgrade...\n";
    $tlsResult = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    echo "TLS upgrade result: " . ($tlsResult ? 'SUCCESS' : 'FAILED') . "\n";
} else {
    echo "STARTTLS not accepted\n";
}

// Close connection
fputs($socket, "QUIT\r\n");
fclose($socket);

echo "Test completed.\n";
?>