<?php
/**
 * ATIERA Financial Management System - Role-Based Access Control (RBAC)
 * Manages user permissions and access control
 */

class PermissionManager {
    private static $instance = null;
    private $db;
    private $userPermissions = [];
    private $userRoles = [];

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load user permissions and roles
     */
    public function loadUserPermissions($userId) {
        // Get user roles
        $stmt = $this->db->prepare("
            SELECT r.name as role_name, r.id as role_id
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        $this->userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all permissions for user's roles
        $roleIds = array_column($this->userRoles, 'role_id');
        if (empty($roleIds)) {
            $this->userPermissions = [];
            return;
        }

        $placeholders = str_repeat('?,', count($roleIds) - 1) . '?';
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.name as permission_name
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id IN ($placeholders)
        ");
        $stmt->execute($roleIds);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->userPermissions = array_column($permissions, 'permission_name');
    }

    /**
     * Check if user has permission
     */
    public function hasPermission($permission) {
        return in_array($permission, $this->userPermissions);
    }

    /**
     * Check if user has any of the permissions
     */
    public function hasAnyPermission($permissions) {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }
        return !empty(array_intersect($permissions, $this->userPermissions));
    }

    /**
     * Check if user has all permissions
     */
    public function hasAllPermissions($permissions) {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }
        return empty(array_diff($permissions, $this->userPermissions));
    }

    /**
     * Check if user has role
     */
    public function hasRole($role) {
        $roleNames = array_column($this->userRoles, 'role_name');
        return in_array($role, $roleNames);
    }

    /**
     * Get user roles
     */
    public function getUserRoles() {
        return $this->userRoles;
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions() {
        return $this->userPermissions;
    }

    /**
     * Assign role to user
     */
    public function assignRole($userId, $roleId) {
        try {
            // Check if assignment already exists
            $stmt = $this->db->prepare("
                SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?
            ");
            $stmt->execute([$userId, $roleId]);

            if ($stmt->fetch()) {
                return ['success' => true, 'message' => 'Role already assigned'];
            }

            // Assign role
            $stmt = $this->db->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())
            ");
            $stmt->execute([$userId, $roleId]);

            return ['success' => true, 'message' => 'Role assigned successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole($userId, $roleId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_roles WHERE user_id = ? AND role_id = ?
            ");
            $stmt->execute([$userId, $roleId]);

            return ['success' => true, 'message' => 'Role removed successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create new role
     */
    public function createRole($name, $description = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO roles (name, description, created_at) VALUES (?, ?, NOW())
            ");
            $stmt->execute([$name, $description]);

            $roleId = $this->db->lastInsertId();
            return ['success' => true, 'role_id' => $roleId, 'message' => 'Role created successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create new permission
     */
    public function createPermission($name, $description = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO permissions (name, description, created_at) VALUES (?, ?, NOW())
            ");
            $stmt->execute([$name, $description]);

            $permissionId = $this->db->lastInsertId();
            return ['success' => true, 'permission_id' => $permissionId, 'message' => 'Permission created successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole($roleId, $permissionId) {
        try {
            // Check if assignment already exists
            $stmt = $this->db->prepare("
                SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?
            ");
            $stmt->execute([$roleId, $permissionId]);

            if ($stmt->fetch()) {
                return ['success' => true, 'message' => 'Permission already assigned to role'];
            }

            // Assign permission
            $stmt = $this->db->prepare("
                INSERT INTO role_permissions (role_id, permission_id, assigned_at) VALUES (?, ?, NOW())
            ");
            $stmt->execute([$roleId, $permissionId]);

            return ['success' => true, 'message' => 'Permission assigned to role successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole($roleId, $permissionId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?
            ");
            $stmt->execute([$roleId, $permissionId]);

            return ['success' => true, 'message' => 'Permission removed from role successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all roles
     */
    public function getAllRoles() {
        $stmt = $this->db->query("
            SELECT r.*, COUNT(ur.user_id) as user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id
            ORDER BY r.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions() {
        $stmt = $this->db->query("
            SELECT p.*, COUNT(rp.role_id) as role_count
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            GROUP BY p.id
            ORDER BY p.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get role permissions
     */
    public function getRolePermissions($roleId) {
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.name ASC
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($roleId) {
        $stmt = $this->db->prepare("
            SELECT u.*, ur.assigned_at
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
            ORDER BY u.username ASC
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Initialize default roles and permissions
     */
    public function initializeDefaults() {
        try {
            $this->db->beginTransaction();

            // Create default roles
            $roles = [
                ['name' => 'admin', 'description' => 'System Administrator - Full access'],
                ['name' => 'manager', 'description' => 'Manager - Can approve and manage'],
                ['name' => 'accountant', 'description' => 'Accountant - Financial operations'],
                ['name' => 'staff', 'description' => 'Staff - Basic operations'],
                ['name' => 'user', 'description' => 'User - Limited access']
            ];

            $roleIds = [];
            foreach ($roles as $role) {
                $result = $this->createRole($role['name'], $role['description']);
                if ($result['success']) {
                    $roleIds[$role['name']] = $result['role_id'];
                }
            }

            // Create default permissions
            $permissions = [
                // User management
                ['name' => 'users.view', 'description' => 'View users'],
                ['name' => 'users.create', 'description' => 'Create users'],
                ['name' => 'users.edit', 'description' => 'Edit users'],
                ['name' => 'users.delete', 'description' => 'Delete users'],

                // Customer management
                ['name' => 'customers.view', 'description' => 'View customers'],
                ['name' => 'customers.create', 'description' => 'Create customers'],
                ['name' => 'customers.edit', 'description' => 'Edit customers'],
                ['name' => 'customers.delete', 'description' => 'Delete customers'],

                // Vendor management
                ['name' => 'vendors.view', 'description' => 'View vendors'],
                ['name' => 'vendors.create', 'description' => 'Create vendors'],
                ['name' => 'vendors.edit', 'description' => 'Edit vendors'],
                ['name' => 'vendors.delete', 'description' => 'Delete vendors'],

                // Invoice management
                ['name' => 'invoices.view', 'description' => 'View invoices'],
                ['name' => 'invoices.create', 'description' => 'Create invoices'],
                ['name' => 'invoices.edit', 'description' => 'Edit invoices'],
                ['name' => 'invoices.delete', 'description' => 'Delete invoices'],
                ['name' => 'invoices.approve', 'description' => 'Approve invoices'],

                // Bill management
                ['name' => 'bills.view', 'description' => 'View bills'],
                ['name' => 'bills.create', 'description' => 'Create bills'],
                ['name' => 'bills.edit', 'description' => 'Edit bills'],
                ['name' => 'bills.delete', 'description' => 'Delete bills'],
                ['name' => 'bills.approve', 'description' => 'Approve bills'],

                // Payment management
                ['name' => 'payments.view', 'description' => 'View payments'],
                ['name' => 'payments.create', 'description' => 'Create payments'],
                ['name' => 'payments.edit', 'description' => 'Edit payments'],
                ['name' => 'payments.delete', 'description' => 'Delete payments'],
                ['name' => 'payments.approve', 'description' => 'Approve payments'],

                // Journal entries
                ['name' => 'journal.view', 'description' => 'View journal entries'],
                ['name' => 'journal.create', 'description' => 'Create journal entries'],
                ['name' => 'journal.edit', 'description' => 'Edit journal entries'],
                ['name' => 'journal.post', 'description' => 'Post journal entries'],

                // Reports
                ['name' => 'reports.view', 'description' => 'View reports'],
                ['name' => 'reports.generate', 'description' => 'Generate reports'],
                ['name' => 'reports.export', 'description' => 'Export reports'],

                // File management
                ['name' => 'files.view', 'description' => 'View files'],
                ['name' => 'files.upload', 'description' => 'Upload files'],
                ['name' => 'files.download', 'description' => 'Download files'],
                ['name' => 'files.delete', 'description' => 'Delete files'],

                // System settings
                ['name' => 'settings.view', 'description' => 'View system settings'],
                ['name' => 'settings.edit', 'description' => 'Edit system settings'],

                // Audit logs
                ['name' => 'audit.view', 'description' => 'View audit logs'],

                // Role management
                ['name' => 'roles.view', 'description' => 'View roles and permissions'],
                ['name' => 'roles.manage', 'description' => 'Manage roles and permissions']
            ];

            $permissionIds = [];
            foreach ($permissions as $permission) {
                $result = $this->createPermission($permission['name'], $permission['description']);
                if ($result['success']) {
                    $permissionIds[$permission['name']] = $result['permission_id'];
                }
            }

            // Assign permissions to roles
            $rolePermissions = [
                'admin' => array_keys($permissionIds), // Admin gets all permissions

                'manager' => [
                    'users.view', 'customers.view', 'customers.create', 'customers.edit',
                    'vendors.view', 'vendors.create', 'vendors.edit',
                    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.approve',
                    'bills.view', 'bills.create', 'bills.edit', 'bills.approve',
                    'payments.view', 'payments.create', 'payments.edit', 'payments.approve',
                    'journal.view', 'journal.create', 'journal.edit',
                    'reports.view', 'reports.generate', 'reports.export',
                    'files.view', 'files.upload', 'files.download'
                ],

                'accountant' => [
                    'customers.view', 'customers.create', 'customers.edit',
                    'vendors.view', 'vendors.create', 'vendors.edit',
                    'invoices.view', 'invoices.create', 'invoices.edit',
                    'bills.view', 'bills.create', 'bills.edit',
                    'payments.view', 'payments.create', 'payments.edit',
                    'journal.view', 'journal.create', 'journal.edit', 'journal.post',
                    'reports.view', 'reports.generate', 'reports.export',
                    'files.view', 'files.upload', 'files.download'
                ],

                'staff' => [
                    'customers.view', 'customers.create', 'customers.edit',
                    'vendors.view', 'vendors.create', 'vendors.edit',
                    'invoices.view', 'invoices.create', 'invoices.edit',
                    'bills.view', 'bills.create', 'bills.edit',
                    'payments.view', 'payments.create', 'payments.edit',
                    'reports.view', 'reports.generate',
                    'files.view', 'files.upload', 'files.download'
                ],

                'user' => [
                    'customers.view',
                    'invoices.view',
                    'bills.view',
                    'payments.view',
                    'reports.view',
                    'files.view', 'files.download'
                ]
            ];

            foreach ($rolePermissions as $roleName => $perms) {
                if (isset($roleIds[$roleName])) {
                    $roleId = $roleIds[$roleName];
                    foreach ($perms as $permName) {
                        if (isset($permissionIds[$permName])) {
                            $this->assignPermissionToRole($roleId, $permissionIds[$permName]);
                        }
                    }
                }
            }

            // Assign admin role to default admin user
            if (isset($roleIds['admin'])) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = 'admin'");
                $stmt->execute();
                $adminUser = $stmt->fetch();

                if ($adminUser) {
                    $this->assignRole($adminUser['id'], $roleIds['admin']);
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Default roles and permissions initialized'];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
