<?php
/**
 * ATIERA Financial Management System - Roles and Permissions Management
 * Admin interface for managing user roles and permissions
 */

require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/logger.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('roles.view');

$permManager = PermissionManager::getInstance();
$user = $auth->getCurrentUser();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$auth->hasPermission('roles.manage')) {
        $error = 'You do not have permission to manage roles and permissions.';
    } else {
        switch ($action) {
            case 'create_role':
                if (empty($_POST['role_name'])) {
                    $error = 'Role name is required.';
                } else {
                    $result = $permManager->createRole($_POST['role_name'], $_POST['description'] ?? '');
                    if ($result['success']) {
                        $message = 'Role created successfully.';
                        Logger::getInstance()->logUserAction(
                            'Created role',
                            'roles',
                            $result['role_id'],
                            null,
                            ['name' => $_POST['role_name'], 'description' => $_POST['description']]
                        );
                    } else {
                        $error = $result['error'];
                    }
                }
                break;

            case 'assign_role':
                if (empty($_POST['user_id']) || empty($_POST['role_id'])) {
                    $error = 'User and role are required.';
                } else {
                    $result = $permManager->assignRole($_POST['user_id'], $_POST['role_id']);
                    if ($result['success']) {
                        $message = 'Role assigned successfully.';
                        Logger::getInstance()->logUserAction(
                            'Assigned role to user',
                            'user_roles',
                            null,
                            null,
                            ['user_id' => $_POST['user_id'], 'role_id' => $_POST['role_id']]
                        );
                    } else {
                        $error = $result['error'];
                    }
                }
                break;

            case 'assign_permission':
                if (empty($_POST['role_id']) || empty($_POST['permission_id'])) {
                    $error = 'Role and permission are required.';
                } else {
                    $result = $permManager->assignPermissionToRole($_POST['role_id'], $_POST['permission_id']);
                    if ($result['success']) {
                        $message = 'Permission assigned successfully.';
                        Logger::getInstance()->logUserAction(
                            'Assigned permission to role',
                            'role_permissions',
                            null,
                            null,
                            ['role_id' => $_POST['role_id'], 'permission_id' => $_POST['permission_id']]
                        );
                    } else {
                        $error = $result['error'];
                    }
                }
                break;

            case 'initialize_defaults':
                $result = $permManager->initializeDefaults();
                if ($result['success']) {
                    $message = 'Default roles and permissions initialized successfully.';
                    Logger::getInstance()->logUserAction(
                        'Initialized default roles and permissions',
                        'roles',
                        null,
                        null,
                        null
                    );
                } else {
                    $error = $result['error'];
                }
                break;
        }
    }
}

// Handle AJAX requests for removing roles/permissions
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (!$auth->hasPermission('roles.manage')) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $action = $_GET['action'];

    switch ($action) {
        case 'remove_role':
            if (!isset($_GET['user_id']) || !isset($_GET['role_id'])) {
                echo json_encode(['success' => false, 'error' => 'User ID and Role ID required']);
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
                echo json_encode(['success' => false, 'error' => 'Role ID and Permission ID required']);
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
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Get data for display
$roles = $permManager->getAllRoles();
$permissions = $permManager->getAllPermissions();
$users = $auth->getAllUsers();

$pageTitle = 'Roles & Permissions Management';
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-user-shield"></i> Roles & Permissions Management</h2>
                <?php if ($auth->hasPermission('roles.manage')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#initializeModal">
                    <i class="fas fa-magic"></i> Initialize Defaults
                </button>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Roles Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users-cog"></i> Roles</h5>
                </div>
                <div class="card-body">
                    <?php if ($auth->hasPermission('roles.manage')): ?>
                    <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                        <i class="fas fa-plus"></i> Create New Role
                    </button>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Users Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($role['name']); ?></td>
                                    <td><?php echo htmlspecialchars($role['description'] ?? ''); ?></td>
                                    <td><?php echo $role['user_count']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="viewRolePermissions(<?php echo $role['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Permissions
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="viewRoleUsers(<?php echo $role['id']; ?>)">
                                            <i class="fas fa-users"></i> View Users
                                        </button>
                                        <?php if ($auth->hasPermission('roles.manage')): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="assignPermissionToRole(<?php echo $role['id']; ?>)">
                                            <i class="fas fa-plus"></i> Add Permission
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Permissions Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Permissions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Permission</th>
                                    <th>Description</th>
                                    <th>Module</th>
                                    <th>Roles Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissions as $permission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($permission['name']); ?></td>
                                    <td><?php echo htmlspecialchars($permission['description'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($permission['module'] ?? ''); ?></td>
                                    <td><?php echo $permission['role_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Roles Assignment Section -->
            <?php if ($auth->hasPermission('roles.manage')): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-tag"></i> User Role Assignments</h5>
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#assignRoleModal">
                        <i class="fas fa-plus"></i> Assign Role to User
                    </button>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Current Roles</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $userData): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($userData['username']); ?></td>
                                    <td><?php echo htmlspecialchars($userData['full_name']); ?></td>
                                    <td>
                                        <?php
                                        $permManager->loadUserPermissions($userData['id']);
                                        $userRoles = $permManager->getUserRoles();
                                        $roleNames = array_column($userRoles, 'role_name');
                                        echo htmlspecialchars(implode(', ', $roleNames));
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="viewUserPermissions(<?php echo $userData['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Permissions
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="assignRoleToUser(<?php echo $userData['id']; ?>)">
                                            <i class="fas fa-plus"></i> Assign Role
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_role">
                    <div class="mb-3">
                        <label for="role_name" class="form-label">Role Name *</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Role Modal -->
<div class="modal fade" id="assignRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Role to User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_role">
                    <div class="mb-3">
                        <label for="assign_user_id" class="form-label">User *</label>
                        <select class="form-control" id="assign_user_id" name="user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $userData): ?>
                            <option value="<?php echo $userData['id']; ?>">
                                <?php echo htmlspecialchars($userData['username'] . ' - ' . $userData['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="assign_role_id" class="form-label">Role *</label>
                        <select class="form-control" id="assign_role_id" name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Initialize Defaults Modal -->
<div class="modal fade" id="initializeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Initialize Default Roles & Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="initialize_defaults">
                    <p>This will create default roles (super_admin, admin, staff) and assign appropriate permissions to each role.</p>
                    <p class="text-warning"><strong>Warning:</strong> This action cannot be undone. Existing roles and permissions may be affected.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Initialize Defaults</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View role permissions
function viewRolePermissions(roleId) {
    fetch(`api/roles.php?action=role_permissions&role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let permissions = data.permissions.map(p => p.name).join(', ');
                alert(`Permissions for this role:\n${permissions}`);
            } else {
                alert('Error loading permissions: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// View role users
function viewRoleUsers(roleId) {
    fetch(`api/roles.php?action=role_users&role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let users = data.users.map(u => u.username).join(', ');
                alert(`Users with this role:\n${users}`);
            } else {
                alert('Error loading users: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// View user permissions
function viewUserPermissions(userId) {
    fetch(`api/roles.php?action=user_roles&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let permissions = data.permissions.join(', ');
                alert(`Permissions for this user:\n${permissions}`);
            } else {
                alert('Error loading permissions: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Assign permission to role
function assignPermissionToRole(roleId) {
    // This would open a modal to select permission
    alert('Permission assignment modal would open here');
}

// Assign role to user
function assignRoleToUser(userId) {
    // This would open a modal to select role
    alert('Role assignment modal would open here');
}
</script>

<?php include 'footer.php'; ?>
