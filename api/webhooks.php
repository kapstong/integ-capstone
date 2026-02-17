<?php
/**
 * ATIERA Financial Management System - Webhooks Endpoint
 * Receives webhook payloads for configured integrations.
 */

require_once __DIR__ . '/../includes/api_integrations.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

$integrationName = $_GET['integration'] ?? $_POST['integration'] ?? '';
if ($integrationName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing integration parameter']);
    exit;
}

$manager = APIIntegrationManager::getInstance();
$integration = $manager->getIntegration($integrationName);
if (!$integration) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Integration not found']);
    exit;
}

$config = $manager->getIntegrationConfig($integrationName);
if (!$config) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Integration not configured']);
    exit;
}

// Verify webhook secret when configured.
$expectedSecret = $config['webhook_secret'] ?? null;
if ($expectedSecret) {
    $headerSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $bearer = '';
    if (preg_match('/Bearer\\s+(.*)/i', $authHeader, $matches)) {
        $bearer = trim($matches[1]);
    }

    if ($headerSecret !== $expectedSecret && $bearer !== $expectedSecret) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid webhook secret']);
        exit;
    }
}

$payload = file_get_contents('php://input');
$decoded = json_decode($payload, true);
$data = json_last_error() === JSON_ERROR_NONE ? $decoded : $payload;

try {
    $result = $manager->handleWebhook($integrationName, $data);
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    Logger::getInstance()->error('Webhook handling failed', [
        'integration' => $integrationName,
        'error' => $e->getMessage()
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
