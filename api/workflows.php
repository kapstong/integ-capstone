<?php
/**
 * ATIERA Financial Management System - Workflow API
 * Handles workflow management and execution operations
 */

require_once '../includes/auth.php';
require_once '../includes/workflow.php';

header('Content-Type: application/json');
session_start();
$auth = new Auth();
ensure_api_auth($method, [
    'GET' => 'settings.edit',
    'PUT' => 'settings.edit',
    'DELETE' => 'settings.edit',
    'POST' => 'settings.edit',
    'PATCH' => 'settings.edit',
]);


// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user']['id'];
$method = $_SERVER['REQUEST_METHOD'];

$auth = new Auth();
$workflowEngine = WorkflowEngine::getInstance();

// Require settings edit permission for workflow management
if (!$auth->hasPermission('settings.edit')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'get_workflow':
                    // Get workflow details
                    $workflowId = (int)$_GET['id'] ?? 0;

                    $stmt = $auth->getDatabase()->prepare("SELECT * FROM workflows WHERE id = ?");
                    $stmt->execute([$workflowId]);
                    $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($workflow) {
                        echo json_encode(['success' => true, 'workflow' => $workflow]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Workflow not found']);
                    }
                    break;

                case 'get_instance':
                    // Get workflow instance details
                    $instanceId = (int)$_GET['id'] ?? 0;

                    $stmt = $auth->getDatabase()->prepare("
                        SELECT wi.*, w.name as workflow_name, w.definition
                        FROM workflow_instances wi
                        INNER JOIN workflows w ON wi.workflow_id = w.id
                        WHERE wi.id = ?
                    ");
                    $stmt->execute([$instanceId]);
                    $instance = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($instance) {
                        // Get steps for this instance
                        $stmt = $auth->getDatabase()->prepare("
                            SELECT * FROM workflow_steps
                            WHERE instance_id = ?
                            ORDER BY step_index ASC
                        ");
                        $stmt->execute([$instanceId]);
                        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $instance['steps'] = $steps;
                        echo json_encode(['success' => true, 'instance' => $instance]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Workflow instance not found']);
                    }
                    break;

                case 'list_workflows':
                    // List all workflows
                    $stmt = $auth->getDatabase()->prepare("
                        SELECT w.*, u.username as created_by_name
                        FROM workflows w
                        LEFT JOIN users u ON w.created_by = u.id
                        ORDER BY w.created_at DESC
                    ");
                    $stmt->execute();
                    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'workflows' => $workflows]);
                    break;

                case 'get_stats':
                    // Get workflow statistics
                    $stats = $workflowEngine->getWorkflowStats();
                    echo json_encode(['success' => true, 'stats' => $stats]);
                    break;

                case 'get_instances':
                    // Get workflow instances
                    $limit = (int)($_GET['limit'] ?? 50);
                    $offset = (int)($_GET['offset'] ?? 0);

                    $instances = $workflowEngine->getWorkflowInstances($limit, $offset);
                    echo json_encode(['success' => true, 'instances' => $instances]);
                    break;

                case 'get_available_workflows':
                    // Get available workflow templates
                    $workflows = $workflowEngine->getAvailableWorkflows();
                    echo json_encode(['success' => true, 'workflows' => $workflows]);
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
                case 'create_workflow':
                    // Create new workflow
                    $name = trim($_POST['name'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $definition = $_POST['definition'] ?? '';

                    if (empty($name) || empty($definition)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Name and definition are required']);
                        exit;
                    }

                    $definitionJson = json_decode($definition, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid workflow definition JSON']);
                        exit;
                    }

                    $stmt = $auth->getDatabase()->prepare("
                        INSERT INTO workflows (name, description, definition, created_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $result = $stmt->execute([$name, $description, json_encode($definitionJson), $userId]);

                    if ($result) {
                        $workflowId = $auth->getDatabase()->lastInsertId();

                        Logger::getInstance()->logUserAction(
                            'Created workflow',
                            'workflows',
                            $workflowId,
                            null,
                            ['name' => $name]
                        );

                        echo json_encode(['success' => true, 'workflow_id' => $workflowId]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to create workflow']);
                    }
                    break;

                case 'test_workflow':
                    // Test workflow execution
                    $workflowId = (int)$_POST['workflow_id'] ?? 0;
                    $testData = $_POST['test_data'] ?? '';

                    if ($workflowId <= 0) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Valid workflow ID required']);
                        exit;
                    }

                    $testDataArray = json_decode($testData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid test data JSON']);
                        exit;
                    }

                    // Get workflow definition
                    $stmt = $auth->getDatabase()->prepare("SELECT definition FROM workflows WHERE id = ?");
                    $stmt->execute([$workflowId]);
                    $workflow = $stmt->fetch();

                    if (!$workflow) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Workflow not found']);
                        exit;
                    }

                    $definition = json_decode($workflow['definition'], true);
                    $workflowEngine->triggerWorkflow($definition['trigger'], $testDataArray);

                    Logger::getInstance()->logUserAction(
                        'Tested workflow',
                        'workflows',
                        $workflowId,
                        null,
                        ['test_data' => $testDataArray]
                    );

                    echo json_encode(['success' => true, 'message' => 'Workflow test executed']);
                    break;

                case 'trigger_workflow':
                    // Manually trigger workflow
                    $eventType = $_POST['event_type'] ?? '';
                    $eventData = isset($_POST['event_data']) ? json_decode($_POST['event_data'], true) : [];

                    if (empty($eventType)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Event type is required']);
                        exit;
                    }

                    $workflowEngine->triggerWorkflow($eventType, $eventData);

                    Logger::getInstance()->logUserAction(
                        'Manually triggered workflow',
                        'workflow_instances',
                        null,
                        null,
                        ['event_type' => $eventType, 'event_data' => $eventData]
                    );

                    echo json_encode(['success' => true, 'message' => 'Workflow triggered']);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'PUT':
            // Update workflow
            parse_str(file_get_contents('php://input'), $putData);
            $action = $putData['action'] ?? '';

            switch ($action) {
                case 'update_workflow':
                    $workflowId = (int)$putData['workflow_id'] ?? 0;
                    $updates = $putData['updates'] ?? [];

                    if ($workflowId <= 0 || empty($updates)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Valid workflow ID and updates required']);
                        exit;
                    }

                    $setParts = [];
                    $params = [];

                    foreach ($updates as $field => $value) {
                        if (in_array($field, ['name', 'description', 'is_active'])) {
                            $setParts[] = "$field = ?";
                            $params[] = $value;
                        }
                    }

                    if (empty($setParts)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'No valid fields to update']);
                        exit;
                    }

                    $params[] = $workflowId;
                    $sql = "UPDATE workflows SET " . implode(', ', $setParts) . " WHERE id = ?";

                    $stmt = $auth->getDatabase()->prepare($sql);
                    $result = $stmt->execute($params);

                    if ($result) {
                        Logger::getInstance()->logUserAction(
                            'Updated workflow',
                            'workflows',
                            $workflowId,
                            null,
                            $updates
                        );

                        echo json_encode(['success' => true, 'message' => 'Workflow updated']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to update workflow']);
                    }
                    break;

                case 'toggle_workflow':
                    $workflowId = (int)$putData['workflow_id'] ?? 0;
                    $active = (bool)($putData['is_active'] ?? false);

                    $stmt = $auth->getDatabase()->prepare("UPDATE workflows SET is_active = ? WHERE id = ?");
                    $result = $stmt->execute([$active ? 1 : 0, $workflowId]);

                    if ($result) {
                        Logger::getInstance()->logUserAction(
                            ($active ? 'Enabled' : 'Disabled') . ' workflow',
                            'workflows',
                            $workflowId,
                            null,
                            ['is_active' => $active]
                        );

                        echo json_encode(['success' => true, 'message' => 'Workflow status updated']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to update workflow status']);
                    }
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
                case 'delete_workflow':
                    // Delete workflow
                    $workflowId = (int)$_GET['workflow_id'] ?? 0;

                    if ($workflowId <= 0) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Valid workflow ID required']);
                        exit;
                    }

                    // Check if workflow has running instances
                    $stmt = $auth->getDatabase()->prepare("
                        SELECT COUNT(*) as running_count
                        FROM workflow_instances
                        WHERE workflow_id = ? AND status = 'running'
                    ");
                    $stmt->execute([$workflowId]);
                    $result = $stmt->fetch();

                    if ($result['running_count'] > 0) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Cannot delete workflow with running instances']);
                        exit;
                    }

                    $stmt = $auth->getDatabase()->prepare("DELETE FROM workflows WHERE id = ?");
                    $result = $stmt->execute([$workflowId]);

                    if ($result) {
                        Logger::getInstance()->logUserAction(
                            'Deleted workflow',
                            'workflows',
                            $workflowId,
                            null,
                            null
                        );

                        echo json_encode(['success' => true, 'message' => 'Workflow deleted']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to delete workflow']);
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
    Logger::getInstance()->logDatabaseError('Workflow API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>


