<?php
require_once 'database.php';
require_once 'permissions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $db;
    private $permManager;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->permManager = PermissionManager::getInstance();
    }

    public function login($username, $password) {
        // Check if account is locked
        if ($this->checkLockout()['locked']) {
            return ['success' => false, 'lockout' => $this->checkLockout()];
        }

        try {
            // Get user from database
            $stmt = $this->db->query(
                "SELECT id, username, password_hash, email, full_name, role, status, last_login
                 FROM users
                 WHERE username = ? AND status = 'active'",
                [$username]
            );

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful login
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role_name' => $user['role'],
                    'name' => $user['full_name'],
                    'email' => $user['email'],
                    'department' => $user['department'] ?? '',
                    'phone' => $user['phone'] ?? ''
                ];

                // Load user permissions
                $this->permManager->loadUserPermissions($user['id']);
                $_SESSION['user']['permissions'] = $this->permManager->getUserPermissions();
                $_SESSION['user']['roles'] = $this->permManager->getUserRoles();

                // Update last login
                $this->db->query(
                    "UPDATE users SET last_login = NOW() WHERE id = ?",
                    [$user['id']]
                );

                $_SESSION['login_attempts'] = 0;
                return ['success' => true, 'user' => $_SESSION['user']];
            } else {
                // Failed login
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                return ['success' => false, 'attempts' => $_SESSION['login_attempts']];
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user']);
    }

    public function logout() {
        // Clear all session variables
        $_SESSION = array();

        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        // Destroy the session
        session_destroy();
    }

    public function checkLockout() {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $locked = $attempts >= 5;
        $remaining = 0;

        if ($locked) {
            $lockoutTime = $_SESSION['lockout_time'] ?? time();
            $elapsed = time() - $lockoutTime;
            $remaining = max(0, 300 - $elapsed); // 5 minutes lockout

            if ($remaining <= 0) {
                // Lockout expired
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['lockout_time']);
                $locked = false;
            }
        } else if ($attempts >= 5) {
            $_SESSION['lockout_time'] = time();
            $remaining = 300;
        }

        return ['locked' => $locked, 'remaining' => $remaining];
    }

    public function getCurrentUser() {
        return $_SESSION['user'] ?? null;
    }

    // Additional methods for user management
    public function getUserById($id) {
        try {
            $stmt = $this->db->query(
                "SELECT id, username, email, full_name, role, status, last_login, created_at
                 FROM users WHERE id = ?",
                [$id]
            );
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }

    public function getAllUsers() {
        try {
            return $this->db->select(
                "SELECT id, username, email, full_name, role, status, last_login, created_at
                 FROM users ORDER BY created_at DESC"
            );
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }

    public function createUser($data) {
        try {
            $this->db->query(
                "INSERT INTO users (username, password_hash, email, full_name, role, status)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $data['username'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['email'],
                    $data['full_name'],
                    $data['role'] ?? 'staff',
                    $data['status'] ?? 'active'
                ]
            );
            return $this->db->getConnection()->lastInsertId();
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }

    public function updateUser($id, $data) {
        try {
            $fields = [];
            $params = [];

            if (isset($data['email'])) {
                $fields[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['full_name'])) {
                $fields[] = "full_name = ?";
                $params[] = $data['full_name'];
            }
            if (isset($data['role'])) {
                $fields[] = "role = ?";
                $params[] = $data['role'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $fields[] = "password_hash = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (empty($fields)) {
                return false;
            }

            $params[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

            return $this->db->execute($sql, $params) > 0;
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteUser($id) {
        try {
            return $this->db->execute("DELETE FROM users WHERE id = ?", [$id]) > 0;
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }

    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verify current password
            $stmt = $this->db->query("SELECT password_hash FROM users WHERE id = ?", [$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            // Update password
            $this->db->query(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($newPassword, PASSWORD_DEFAULT), $userId]
            );

            return ['success' => true];
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }

    // Permission checking methods
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        if ($this->isAdminUser()) {
            return true;
        }
        return $this->permManager->hasPermission($permission);
    }

    public function hasAnyPermission($permissions) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        if ($this->isAdminUser()) {
            return true;
        }
        return $this->permManager->hasAnyPermission($permissions);
    }

    public function hasAllPermissions($permissions) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        if ($this->isAdminUser()) {
            return true;
        }
        return $this->permManager->hasAllPermissions($permissions);
    }

    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        return $this->permManager->hasRole($role);
    }

    public function getUserPermissions() {
        if (!$this->isLoggedIn()) {
            return [];
        }
        return $this->permManager->getUserPermissions();
    }

    public function getUserRoles() {
        if (!$this->isLoggedIn()) {
            return [];
        }
        return $this->permManager->getUserRoles();
    }

    public function checkAccess($permission, $redirect = true) {
        if (!$this->hasPermission($permission)) {
            if ($redirect) {
                $redirectUrl = $this->getRoleBasedRedirectUrl('?error=access_denied');
                header('Location: ' . $redirectUrl);
                exit;
            }
            return false;
        }
        return true;
    }

    public function requireLogin($redirect = true) {
        if (!$this->isLoggedIn()) {
            if ($redirect) {
                header('Location: /index.php?error=login_required');
                exit;
            }
            return false;
        }
        return true;
    }

    public function requireRole($role, $redirect = true) {
        if (!$this->hasRole($role)) {
            if ($redirect) {
                $redirectUrl = $this->getRoleBasedRedirectUrl('?error=access_denied');
                header('Location: ' . $redirectUrl);
                exit;
            }
            return false;
        }
        return true;
    }

    public function requirePermission($permission, $redirect = true) {
        return $this->checkAccess($permission, $redirect);
    }

    private function isAdminUser() {
        $roleName = $_SESSION['user']['role_name'] ?? '';
        if (in_array($roleName, ['admin', 'super_admin'], true)) {
            return true;
        }
        return $this->permManager->hasRole('admin') || $this->permManager->hasRole('super_admin');
    }

    private function getRoleBasedRedirectUrl($queryString = '') {
        $roleName = $_SESSION['user']['role_name'] ?? '';
        switch ($roleName) {
            case 'super_admin':
                return '/superadmin/index.php' . $queryString;
            case 'admin':
                return '/admin/index.php' . $queryString;
            case 'staff':
                return '/staff/index.php' . $queryString;
            default:
                return '/staff/index.php' . $queryString;
        }
    }
}
?>
