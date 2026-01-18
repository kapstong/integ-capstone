<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');

$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Check if user has system admin access (superadmin)
$user = $auth->getCurrentUser();
$isSuperAdmin = ($user['role'] === 'super_admin');

$method = $_SERVER['REQUEST_METHOD'];
$user = $auth->getCurrentUser();

switch ($method) {
    case 'GET':
        handleGet($db, $auth);
        break;
    case 'POST':
        handlePost($db, $auth, $user);
        break;
    case 'PUT':
        handlePut($db, $auth, $user);
        break;
    case 'DELETE':
        handleDelete($db, $auth, $user);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGet($db, $auth) {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list_users':
            global $isSuperAdmin;
            if (!$isSuperAdmin && !$auth->hasPermission('users.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                return;
            }

            try {
                $stmt = $db->prepare("
                    SELECT id, username, full_name, email, role, status, created_at, updated_at, last_login
                    FROM users
                    WHERE deleted_at IS NULL
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
                $users = $stmt->fetchAll();

                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('List users', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        case 'get_user':
            global $isSuperAdmin;
            if (!$isSuperAdmin && !$auth->hasPermission('users.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                return;
            }

            $userId = $_GET['user_id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID required']);
                return;
            }

            try {
                $stmt = $db->prepare("
                    SELECT id, username, full_name, email, role, status, created_at, updated_at, last_login
                    FROM users
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                }
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Get user', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePost($db, $auth, $currentUser) {
    global $isSuperAdmin;
    if (!$isSuperAdmin && !$auth->hasPermission('users.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'create_user':
            $username = trim($data['username'] ?? '');
            $fullName = trim($data['full_name'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'staff';

            if (empty($username) || empty($fullName) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username, full name, and password are required']);
                return;
            }

            // Validate role
            if (!in_array($role, ['super_admin', 'admin', 'staff'])) {
                $role = 'staff';
            }

            try {
                // Check if username already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND deleted_at IS NULL");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Username already exists']);
                    return;
                }

                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (username, full_name, email, password, role, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
                ");
                $stmt->execute([$username, $fullName, $email, $hashedPassword, $role]);
                $userId = $db->lastInsertId();

                Logger::getInstance()->logUserAction(
                    'Created user',
                    'users',
                    $userId,
                    null,
                    ['username' => $username, 'role' => $role]
                );

                echo json_encode(['success' => true, 'user_id' => $userId, 'message' => 'User created successfully']);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Create user', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        case 'update_user':
            $userId = $data['user_id'] ?? null;
            $username = trim($data['username'] ?? '');
            $fullName = trim($data['full_name'] ?? '');
            $email = trim($data['email'] ?? '');
            $role = $data['role'] ?? null;
            $status = $data['status'] ?? null;
            $permissions = $data['permissions'] ?? [];

            if (!$userId || empty($username) || empty($fullName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID, username, and full name are required']);
                return;
            }

            // Validate role
            if ($role && !in_array($role, ['super_admin', 'admin', 'staff'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid role']);
                return;
            }

            // Validate status
            if ($status && !in_array($status, ['active', 'inactive'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid status']);
                return;
            }

            try {
                $db->beginTransaction();

                // Update user basic info
                $updateFields = [];
                $params = [];

                if (!empty($username)) {
                    $updateFields[] = "username = ?";
                    $params[] = $username;
                }
                if (!empty($fullName)) {
                    $updateFields[] = "full_name = ?";
                    $params[] = $fullName;
                }
                if (!empty($email)) {
                    $updateFields[] = "email = ?";
                    $params[] = $email;
                }
                if ($role) {
                    $updateFields[] = "role = ?";
                    $params[] = $role;
                }
                if ($status) {
                    $updateFields[] = "status = ?";
                    $params[] = $status;
                }

                if (!empty($updateFields)) {
                    $updateFields[] = "updated_at = NOW()";
                    $params[] = $userId;

                    $stmt = $db->prepare("
                        UPDATE users
                        SET " . implode(', ', $updateFields) . "
                        WHERE id = ? AND deleted_at IS NULL
                    ");
                    $stmt->execute($params);
                }

                // Update user permissions if provided
                if (isset($permissions)) {
                    // Convert permission names to IDs
                    $permissionIds = [];
                    if (!empty($permissions)) {
                        foreach ($permissions as $permName) {
                            $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
                            $stmt->execute([$permName]);
                            $perm = $stmt->fetch();
                            if ($perm) {
                                $permissionIds[] = $perm['id'];
                            }
                        }
                    }

                    $permManager = PermissionManager::getInstance();
                    $result = $permManager->setUserPermissions($userId, $permissionIds);
                    if (!$result['success']) {
                        throw new Exception('Failed to update permissions: ' . $result['error']);
                    }
                }

                $db->commit();

                Logger::getInstance()->logUserAction(
                    'Updated user',
                    'users',
                    $userId,
                    null,
                    ['username' => $username, 'role' => $role, 'status' => $status]
                );

                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } catch (Exception $e) {
                $db->rollback();
                Logger::getInstance()->logDatabaseError('Update user', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePut($db, $auth, $currentUser) {
    // PUT requests can be handled the same as POST for updates
    handlePost($db, $auth, $currentUser);
}

function handleDelete($db, $auth, $currentUser) {
    global $isSuperAdmin;
    if (!$isSuperAdmin && !$auth->hasPermission('users.manage')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'delete_user':
            $userId = $data['user_id'] ?? null;

            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID required']);
                return;
            }

            try {
                // Get user data before deletion for logging
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();

                if (!$userData) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    return;
                }

                // Soft delete the user
                $stmt = $db->prepare("
                    UPDATE users
                    SET deleted_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);

                // Insert into deleted_items table
                $stmt = $db->prepare("
                    INSERT INTO deleted_items (table_name, record_id, data, deleted_by, deleted_at, auto_delete_at)
                    VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
                ");
                $stmt->execute([
                    'users',
                    $userId,
                    json_encode($userData),
                    $currentUser['id']
                ]);

                Logger::getInstance()->logUserAction(
                    'Soft deleted user',
                    'users',
                    $userId,
                    null,
                    ['username' => $userData['username']]
                );

                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Delete user', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
?>
