<?php
/**
 * ATIERA Financial Management System - Integrations API
 * Handles external API integration operations
 */

// Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $errstr,
        'debug' => ['file' => $errfile, 'line' => $errline]
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message'],
            'debug' => ['file' => $error['file'], 'line' => $error['line']]
        ]);
    }
});

require_once '../includes/auth.php';
require_once '../includes/api_integrations.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user']['id'];
$method = $_SERVER['REQUEST_METHOD'];

$auth = new Auth();
$integrationManager = APIIntegrationManager::getInstance();

// Get the action to determine permission requirements
$action = ($method === 'POST') ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

// Allow superadmin to execute integration actions without settings.edit permission
$isSuperadmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$canExecuteIntegrations = $auth->hasPermission('settings.edit') || ($isSuperadmin && $action === 'execute');

if (!$canExecuteIntegrations && !$auth->hasPermission('settings.edit')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'get_config_form':
                    // Get configuration form for an integration
                    $integrationName = $_GET['integration'] ?? '';
                    $integration = $integrationManager->getIntegration($integrationName);

                    if (!$integration) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Integration not found']);
                        exit;
                    }

                    $metadata = $integration->getMetadata();
                    $currentConfig = $integrationManager->getIntegrationConfig($integrationName);

                    $formHtml = generateConfigForm($metadata, $currentConfig);
                    echo json_encode(['success' => true, 'form_html' => $formHtml]);
                    break;

                case 'get_stats':
                    // Get integration statistics
                    $stats = $integrationManager->getIntegrationStats();
                    echo json_encode(['success' => true, 'stats' => $stats]);
                    break;

                case 'get_logs':
                    // Get integration activity logs
                    $limit = (int)($_GET['limit'] ?? 50);
                    $logs = getIntegrationLogs($limit);
                    echo json_encode(['success' => true, 'logs' => $logs]);
                    break;

                case 'list_integrations':
                    // List all available integrations
                    $integrations = $integrationManager->getAllIntegrations();
                    $integrationList = [];

                    foreach ($integrations as $name => $integration) {
                        $metadata = $integration->getMetadata();
                        $status = $integrationManager->getIntegrationStatus($name);
                        $config = $integrationManager->getIntegrationConfig($name);

                        $integrationList[] = [
                            'name' => $name,
                            'display_name' => $metadata['display_name'],
                            'description' => $metadata['description'],
                            'is_active' => $status ? $status['is_active'] : false,
                            'is_configured' => $config !== null,
                            'webhook_support' => $metadata['webhook_support'],
                            'required_config' => $metadata['required_config']
                        ];
                    }

                    echo json_encode(['success' => true, 'integrations' => $integrationList]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'POST':
            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'configure':
                    // Configure an integration
                    $integrationName = $_POST['integration_name'] ?? '';
                    $config = $_POST['config'] ?? [];

                    $result = $integrationManager->configureIntegration($integrationName, $config);
                    echo json_encode($result);
                    break;

                case 'test':
                    // Test integration connection
                    $integrationName = $_POST['integration_name'] ?? '';
                    $result = $integrationManager->testIntegration($integrationName);
                    echo json_encode($result);
                    break;

                case 'execute':
                    // Execute integration action
                    $integrationName = $_POST['integration_name'] ?? '';
                    $actionName = $_POST['action_name'] ?? '';
                    $params = [];
                    $rawParams = $_POST;
                    unset($rawParams['action'], $rawParams['integration_name'], $rawParams['action_name'], $rawParams['params']);
                    if (!empty($rawParams)) {
                        $params = $rawParams;
                    }
                    if (isset($_POST['params'])) {
                        $decodedParams = json_decode($_POST['params'], true);
                        if (is_array($decodedParams)) {
                            $params = array_merge($params, $decodedParams);
                        }
                    }

                    try {
                        $result = $integrationManager->executeIntegrationAction($integrationName, $actionName, $params);
                        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
                            http_response_code(400);
                            echo json_encode([
                                'success' => false,
                                'error' => $result['error'] ?? $result['message'] ?? 'Integration action failed',
                                'result' => $result
                            ]);
                            exit;
                        }

                        echo json_encode(['success' => true, 'result' => $result]);
                    } catch (Exception $e) {
                        http_response_code(400);
                        error_log("Integration Error: {$integrationName}->{$actionName}: " . $e->getMessage());
                        echo json_encode([
                            'success' => false,
                            'error' => $e->getMessage(),
                            'debug' => ['integration' => $integrationName, 'action' => $actionName]
                        ]);
                        exit;
                    }
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'PUT':
            // Update integration status
            parse_str(file_get_contents('php://input'), $putData);
            $action = $putData['action'] ?? '';

            switch ($action) {
                case 'toggle_status':
                    $integrationName = $putData['integration_name'] ?? '';
                    $active = (bool)($putData['active'] ?? false);

                    $integrationManager->updateIntegrationStatus($integrationName, $active);
                    echo json_encode(['success' => true, 'message' => 'Integration status updated']);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'DELETE':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'remove_config':
                    // Remove integration configuration
                    $integrationName = $_GET['integration'] ?? '';

                    $configFile = '../../config/integrations/' . $integrationName . '.json';
                    if (file_exists($configFile)) {
                        unlink($configFile);
                        $integrationManager->updateIntegrationStatus($integrationName, false);

                        Logger::getInstance()->logUserAction(
                            'Removed integration configuration',
                            'api_integrations',
                            null,
                            null,
                            ['integration' => $integrationName]
                        );

                        echo json_encode(['success' => true, 'message' => 'Configuration removed']);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Configuration not found']);
                    }
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Integration API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Generate configuration form HTML
 */
function generateConfigForm($metadata, $currentConfig = null) {
    $html = '<div class="row g-3">';

    foreach ($metadata['required_config'] as $field) {
        $fieldType = getFieldType($field);
        $fieldLabel = ucfirst(str_replace(['_', '-'], ' ', $field));
        $fieldValue = $currentConfig[$field] ?? '';
        $fieldPlaceholder = getFieldPlaceholder($field);

        $html .= '<div class="col-md-6">';
        $html .= '<label for="' . $field . '" class="form-label">' . $fieldLabel . ' *</label>';

        if ($fieldType === 'textarea') {
            $html .= '<textarea class="form-control" id="' . $field . '" name="config[' . $field . ']" rows="3" placeholder="' . $fieldPlaceholder . '" required>' . htmlspecialchars($fieldValue) . '</textarea>';
        } elseif ($fieldType === 'select') {
            $html .= '<select class="form-control" id="' . $field . '" name="config[' . $field . ']" required>';
            $html .= '<option value="">Select ' . $fieldLabel . '</option>';
            $options = getFieldOptions($field);
            foreach ($options as $value => $label) {
                $selected = ($fieldValue === $value) ? 'selected' : '';
                $html .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="' . $fieldType . '" class="form-control" id="' . $field . '" name="config[' . $field . ']" value="' . htmlspecialchars($fieldValue) . '" placeholder="' . $fieldPlaceholder . '" required>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Get field type based on field name
 */
function getFieldType($field) {
    $fieldTypes = [
        'webhook_secret' => 'password',
        'api_key' => 'password',
        'auth_token' => 'password',
        'access_token' => 'password',
        'client_secret' => 'password',
        'refresh_token' => 'password',
        'phone_number' => 'tel',
        'email' => 'email',
        'url' => 'url',
        'webhook_url' => 'url',
        'server_prefix' => 'text',
        'tenant_id' => 'text',
        'company_id' => 'text'
    ];

    return $fieldTypes[$field] ?? 'text';
}

/**
 * Get field placeholder
 */
function getFieldPlaceholder($field) {
    $placeholders = [
        'api_key' => 'sk_test_... or sg_...',
        'auth_token' => 'Your authentication token',
        'access_token' => 'Your access token',
        'client_id' => 'Your client ID',
        'client_secret' => 'Your client secret',
        'webhook_secret' => 'whsec_...',
        'webhook_url' => 'https://your-domain.com/webhook',
        'phone_number' => '+1234567890',
        'email' => 'your-email@example.com',
        'server_prefix' => 'us1 or eu1',
        'tenant_id' => 'Your tenant ID',
        'company_id' => 'Your company ID'
    ];

    return $placeholders[$field] ?? 'Enter ' . ucfirst(str_replace(['_', '-'], ' ', $field));
}

/**
 * Get field options for select fields
 */
function getFieldOptions($field) {
    if ($field === 'server_prefix') {
        return [
            'us1' => 'US Server 1',
            'us2' => 'US Server 2',
            'us3' => 'US Server 3',
            'eu1' => 'EU Server 1',
            'au1' => 'AU Server 1'
        ];
    }

    return [];
}

/**
 * Get integration logs
 */
function getIntegrationLogs($limit = 50) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM integration_logs
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        Logger::getInstance()->error("Failed to get integration logs: " . $e->getMessage());
        return [];
    }
}
?>

