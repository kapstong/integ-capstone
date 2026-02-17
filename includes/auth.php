<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure CSRF token exists for the session.
csrf_token();

if (!isset($_SESSION['privacy_unlocked'])) {
    $_SESSION['privacy_unlocked'] = false;
}
if (!isset($_SESSION['privacy_visible'])) {
    $_SESSION['privacy_visible'] = false;
}

// Ensure RBAC defaults are present (idempotent).
if (!isset($_SESSION['rbac_defaults_initialized'])) {
    try {
        PermissionManager::getInstance()->initializeDefaults();
    } catch (Exception $e) {
        // If initialization fails, continue without blocking requests.
    }
    $_SESSION['rbac_defaults_initialized'] = true;
}

// Enforce CSRF protection for state-changing web requests (non-API).
$scriptPath = $_SERVER['PHP_SELF'] ?? '';
$isApiRequest = strpos($scriptPath, '/api/') !== false || strpos($scriptPath, '\\api\\') !== false;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$isStateChanging = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isApiRequest && $isStateChanging && !empty($_SESSION['user']['id'])) {
    if (!csrf_verify_request()) {
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        header('Location: ' . $_SERVER['REQUEST_URI'] . '?error=csrf_invalid');
        exit;
    }
}

// Enforce API permissions for internal API endpoints.
if ($isApiRequest && !empty($_SESSION['user']['id'])) {
    $scriptPathLower = strtolower($scriptPath);
    $isExternalApi = strpos($scriptPathLower, '/api/v1/') !== false || strpos($scriptPathLower, '\\api\\v1\\') !== false
        || strpos($scriptPathLower, '/api/oauth/') !== false || strpos($scriptPathLower, '\\api\\oauth\\') !== false;

    if (!$isExternalApi) {
        $fileBase = strtolower(pathinfo($scriptPath, PATHINFO_FILENAME));

        $methodToAction = [
            'GET' => 'view',
            'POST' => 'create',
            'PUT' => 'edit',
            'PATCH' => 'edit',
            'DELETE' => 'delete'
        ];

        $specialPermissions = [
            'financial_records' => [
                'GET' => 'view_financial_records',
                'POST' => 'create_journal_entries',
                'PUT' => 'edit_journal_entries',
                'PATCH' => 'edit_journal_entries',
                'DELETE' => 'delete_journal_entries'
            ],
            'dashboard' => [
                'GET' => 'dashboard.view',
                'POST' => 'settings.edit',
                'PUT' => 'settings.edit',
                'PATCH' => 'settings.edit',
                'DELETE' => 'settings.edit'
            ]
        ];

        $resourceMap = [
            'customers' => 'customers',
            'vendors' => 'vendors',
            'invoices' => 'invoices',
            'bills' => 'bills',
            'payments' => 'payments',
            'journal_entries' => 'journal',
            'reports' => 'reports',
            'users' => 'users',
            'roles' => 'roles',
            'upload' => 'files',
            'backups' => 'settings',
            'workflows' => 'settings',
            'currencies' => 'currencies',
            'tax_codes' => 'tax_codes',
            'bank_accounts' => 'bank_accounts',
            'budgets' => 'budgets',
            'fixed_assets' => 'fixed_assets',
            'disbursements' => 'disbursements',
            'chart_of_accounts' => 'chart_of_accounts',
            'integrations' => 'integrations',
            'audit' => 'audit'
        ];

        $requiredPermission = null;
        if (isset($specialPermissions[$fileBase])) {
            $requiredPermission = $specialPermissions[$fileBase][$method] ?? null;
        } elseif (isset($resourceMap[$fileBase]) && isset($methodToAction[$method])) {
            $requiredPermission = $resourceMap[$fileBase] . '.' . $methodToAction[$method];
        }

        if ($requiredPermission) {
            $permManager = PermissionManager::getInstance();
            if (empty($_SESSION['user']['permissions'])) {
                $permManager->loadUserPermissions($_SESSION['user']['id']);
                $_SESSION['user']['permissions'] = $permManager->getUserPermissions();
                $_SESSION['user']['roles'] = $permManager->getUserRoles();
            }
            $roleName = $_SESSION['user']['role_name'] ?? ($_SESSION['user']['role'] ?? '');
            $isAdmin = in_array($roleName, ['admin', 'super_admin'], true) || $permManager->hasRole('admin') || $permManager->hasRole('super_admin');

            if (!$isAdmin && !$permManager->hasPermission($requiredPermission)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Forbidden - insufficient permissions']);
                exit;
            }
        }
    }
}

// Log page views for authenticated users (non-API, non-AJAX requests)
if (!empty($_SESSION['user']['id'])) {
    $isApi = $isApiRequest;

    if (!$isApi && !$isAjax && stripos($scriptPath, '.php') !== false) {
        require_once __DIR__ . '/privacy_output_mask.php';
        startPrivacyOutputMasking();
    }

    if (!$isApi && !$isAjax && stripos($scriptPath, '.php') !== false) {
        $scriptName = pathinfo($scriptPath, PATHINFO_FILENAME);
        $moduleMap = [
            'accounts_receivable' => 'invoices',
            'accounts_payable' => 'bills',
            'disbursements' => 'disbursements',
            'budget_management' => 'budgets',
            'general_ledger' => 'journal_entries'
        ];
        $tableName = $moduleMap[$scriptName] ?? $scriptName;

        Logger::getInstance()->logUserAction('viewed', $tableName, null, null, [
            'path' => $scriptPath
        ]);
    }
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
                "SELECT id, username, password_hash, email, first_name, last_name, full_name, role, status, last_login, department, phone
                 FROM users
                 WHERE username = ? AND status = 'active'",
                [$username]
            );

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $firstName = trim($user['first_name'] ?? '');
                $lastName = trim($user['last_name'] ?? '');
                $computedFullName = trim($firstName . ' ' . $lastName);
                $fullName = $computedFullName ?: ($user['full_name'] ?? '');
                $fullName = trim($fullName);

                // Successful login
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role_name' => $user['role'],
                    'role' => $user['role'],
                    'name' => $fullName ?: $user['username'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'full_name' => $fullName,
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
                 FROM users WHERE id = ? AND deleted_at IS NULL",
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
                 FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC"
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
        $roleName = $_SESSION['user']['role_name'] ?? ($_SESSION['user']['role'] ?? '');
        if (in_array($roleName, ['admin', 'super_admin'], true)) {
            return true;
        }
        return $this->permManager->hasRole('admin') || $this->permManager->hasRole('super_admin');
    }

    private function getRoleBasedRedirectUrl($queryString = '') {
        $roleName = $_SESSION['user']['role_name'] ?? ($_SESSION['user']['role'] ?? '');
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

function ensure_api_auth($method, array $permissionMap, Auth $auth = null) {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $auth = $auth ?? new Auth();
    $permission = $permissionMap[$method] ?? null;
    if (!$permission) {
        return;
    }

    if ($auth->hasRole('admin') || $auth->hasRole('super_admin')) {
        return;
    }

    if (is_array($permission)) {
        if ($auth->hasAnyPermission($permission)) {
            return;
        }
    } elseif ($auth->hasPermission($permission)) {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden - Insufficient privileges']);
    exit;
}
?>

