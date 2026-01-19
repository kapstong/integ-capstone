<?php
/**
 * ATIERA Financial Management System - Roles and Permissions API
 * Manages user roles and permissions
 */

require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/logger.php';

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user']['id'];
$method = $_SERVER['REQUEST_METHOD'];

$permManager = PermissionManager::getInstance();

// Check if user has permission to manage roles
if (!$permManager->hasPermission('roles.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'roles':
                    $roles = $permManager->getAllRoles();
                    echo json_encode(['success' => true, 'roles' => $roles]);
                    break;

                case 'permissions':
                    $permissions = $permManager->getAllPermissions();
                    echo json_encode(['success' => true, 'permissions' => $permissions]);
                    break;

                case 'user_roles':
                    if (!isset($_GET['user_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'User ID required']);
                        exit;
                    }
                    $permManager->loadUserPermissions($_GET['user_id']);
                    $roles = $permManager->getUserRoles();
                    $permissions = $permManager->getUserPermissions();
                    echo json_encode([
                        'success' => true,
                        'roles' => $roles,
                        'permissions' => $permissions
                    ]);
                    break;

                case 'role_permissions':
                    if (!isset($_GET['role_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Role ID required']);
                        exit;
                    }
                    $permissions = $permManager->getRolePermissions($_GET['role_id']);
                    echo json_encode(['success' => true, 'permissions' => $permissions]);
                    break;

                case 'role_users':
                    if (!isset($_GET['role_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Role ID required']);
                        exit;
                    }
                    $users = $permManager->getUsersByRole($_GET['role_id']);
                    echo json_encode(['success' => true, 'users' => $users]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $action = $data['action'] ?? '';

            // Check if user has manage permission for write operations
            if (!$permManager->hasPermission('roles.manage')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied - manage permission required']);
                exit;
            }

            switch ($action) {
                case 'create_role':
                    if (empty($data['name'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Role name is required']);
                        exit;
                    }

                    $result = $permManager->createRole($data['name'], $data['description'] ?? '');
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Created role',
                            'roles',
                            $result['role_id'],
                            null,
                            ['name' => $data['name'], 'description' => $data['description']]
                        );
                    }
                    echo json_encode($result);
                    break;

                case 'create_permission':
                    if (empty($data['name'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Permission name is required']);
                        exit;
                    }

                    $result = $permManager->createPermission($data['name'], $data['description'] ?? '');
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Created permission',
                            'permissions',
                            $result['permission_id'],
                            null,
                            ['name' => $data['name'], 'description' => $data['description']]
                        );
                    }
                    echo json_encode($result);
                    break;

                case 'assign_role':
                    if (empty($data['user_id']) || empty($data['role_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'User ID and Role ID are required']);
                        exit;
                    }

                    $result = $permManager->assignRole($data['user_id'], $data['role_id']);
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Assigned role to user',
                            'user_roles',
                            null,
                            null,
                            ['user_id' => $data['user_id'], 'role_id' => $data['role_id']]
                        );
                    }
                    echo json_encode($result);
                    break;

                case 'assign_permission':
                    if (empty($data['role_id']) || empty($data['permission_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Role ID and Permission ID are required']);
                        exit;
                    }

                    $result = $permManager->assignPermissionToRole($data['role_id'], $data['permission_id']);
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Assigned permission to role',
                            'role_permissions',
                            null,
                            null,
                            ['role_id' => $data['role_id'], 'permission_id' => $data['permission_id']]
                        );
                    }
                    echo json_encode($result);
                    break;

                case 'initialize_defaults':
                    $result = $permManager->initializeDefaults();
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Initialized default roles and permissions',
                            'roles',
                            null,
                            null,
                            null
                        );
                    }
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'DELETE':
            // Check if user has manage permission
            if (!$permManager->hasPermission('roles.manage')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied - manage permission required']);
                exit;
            }

            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'remove_role':
                    if (!isset($_GET['user_id']) || !isset($_GET['role_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'User ID and Role ID are required']);
                        exit;
                    }

                    $result = $permManager->removeRole($_GET['user_id'], $_GET['role_id']);
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Removed role from user',
                            'user_roles',
                            null,
                            null,
                            ['user_id' => $_GET['user_id'], 'role_id' => $_GET['role_id']]
                        );
                    }
                    echo json_encode($result);
                    break;

                case 'remove_permission':
                    if (!isset($_GET['role_id']) || !isset($_GET['permission_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Role ID and Permission ID are required']);
                        exit;
                    }

                    $result = $permManager->removePermissionFromRole($_GET['role_id'], $_GET['permission_id']);
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Removed permission from role',
                            'role_permissions',
                            null,
                            null,
                            ['role_id' => $_GET['role_id'], 'permission_id' => $_GET['permission_id']]
                        );
                    }
                    echo json_encode($result);
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
    Logger::getInstance()->logDatabaseError('Roles and Permissions API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>

