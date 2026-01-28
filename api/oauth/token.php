<?php
/**
 * OAuth Token Endpoint (Client Credentials)
 * POST: grant_type=client_credentials, client_id, client_secret
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../includes/database.php';
require_once '../../includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS oauth_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            client_id VARCHAR(80) NOT NULL UNIQUE,
            client_secret VARCHAR(120) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT NOW()
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS oauth_access_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            access_token VARCHAR(120) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT NOW()
        )
    ");

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $grantType = $input['grant_type'] ?? '';
    $clientId = $input['client_id'] ?? '';
    $clientSecret = $input['client_secret'] ?? '';

    if ($grantType !== 'client_credentials') {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported grant_type']);
        exit;
    }

    if (!$clientId || !$clientSecret) {
        http_response_code(400);
        echo json_encode(['error' => 'client_id and client_secret are required']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT department_id
        FROM oauth_clients
        WHERE client_id = ? AND client_secret = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$clientId, $clientSecret]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid client credentials']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $insert = $db->prepare("
        INSERT INTO oauth_access_tokens (department_id, access_token, expires_at)
        VALUES (?, ?, ?)
    ");
    $insert->execute([$client['department_id'], $token, $expiresAt]);

    echo json_encode([
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 3600
    ]);

} catch (Exception $e) {
    $logger->log("OAuth token error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
