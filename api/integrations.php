<?php
/**
 * ATIERA Financial Management System - Integrations API Endpoint
 * Handles external API integrations and actions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/api_integrations.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $manager = APIIntegrationManager::getInstance();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'execute':
            $integrationName = $_GET['integration_name'] ?? '';
            $actionName = $_GET['action_name'] ?? '';

            if (!$integrationName || !$actionName) {
                throw new Exception('integration_name and action_name are required');
            }

            // Get request data
            $params = [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

                if (strpos($contentType, 'application/json') !== false) {
                    $params = json_decode(file_get_contents('php://input'), true);
                } else {
                    $params = $_POST;
                }
            }

            $result = $manager->executeIntegrationAction($integrationName, $actionName, $params);
            echo json_encode(['success' => true, 'result' => $result]);
            break;

        case 'test':
            $integrationName = $_GET['integration_name'] ?? '';
            if (!$integrationName) {
                throw new Exception('integration_name is required');
            }

            $result = $manager->testIntegration($integrationName);
            echo json_encode($result);
            break;

        case 'configure':
            $integrationName = $_GET['integration_name'] ?? '';
            if (!$integrationName) {
                throw new Exception('integration_name is required');
            }

            $config = json_decode(file_get_contents('php://input'), true);
            if (!$config) {
                throw new Exception('Configuration data required');
            }

            $result = $manager->configureIntegration($integrationName, $config);
            echo json_encode($result);
            break;

        case 'list':
            $integrations = $manager->getAllIntegrations();
            $integrationList = [];

            foreach ($integrations as $name => $integration) {
                $status = $manager->getIntegrationStatus($name);
                $config = $manager->getIntegrationConfig($name);

                $integrationList[$name] = [
                    'name' => $name,
                    'metadata' => $integration->getMetadata(),
                    'is_configured' => $config !== null,
                    'is_active' => $status ? $status['is_active'] : false,
                    'last_updated' => $status ? $status['last_updated'] : null
                ];
            }

            echo json_encode(['success' => true, 'integrations' => $integrationList]);
            break;

        case 'stats':
            $stats = $manager->getIntegrationStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

    // Log the error
    if (class_exists('Logger')) {
        Logger::getInstance()->error('API Integration Error: ' . $e->getMessage(), [
            'action' => $_GET['action'] ?? 'unknown',
            'user_id' => $_SESSION['user']['id'] ?? null
        ]);
    }
}
