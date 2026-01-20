<?php
$pageTitle = 'Profile Settings';
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user']['id'];

// Fetch current user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = $_SESSION['user'];
}

// Handle form submission for profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            // Update user profile
            $stmt = $db->prepare("
                UPDATE users SET
                    first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    department = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $department, $user_id]);

            // Update session data
            $_SESSION['user']['first_name'] = $first_name;
            $_SESSION['user']['last_name'] = $last_name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['department'] = $department;

            $message = 'Profile updated successfully';
            $messageType = 'success';

            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $message = 'Error updating profile: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if (isset($_POST['change_password'])) {
        try {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match');
            }

            if (strlen($new_password) < 8) {
                throw new Exception('New password must be at least 8 characters long');
            }

            // Verify current password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $passwordHash = $stmt->fetchColumn();

            if (!password_verify($current_password, $passwordHash)) {
                throw new Exception('Current password is incorrect');
            }

            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);

            $message = 'Password changed successfully';
            $messageType = 'success';

        } catch (Exception $e) {
            $message = 'Error changing password: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ATIERA Financial Management</title>
    <link rel="icon" type="image/png" href="../logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #F1F7EE;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
            background: linear-gradient(180deg, #1e2936 0%, #2a3f54 100%);
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
            padding-top: 0;
            padding-left: 0;
            padding-right: 0;
        }

        .sidebar.sidebar-collapsed {
            width: 100px;
        }

        .sidebar.sidebar-collapsed span {
            display: none;
        }

        .sidebar.sidebar-collapsed .nav-link {
            padding: 0.875rem;
            justify-content: center;
        }

        .sidebar.sidebar-collapsed .navbar-brand {
            padding: 1rem 0.5rem;
        }

        .sidebar.sidebar-collapsed .nav-item i[data-bs-toggle="collapse"] {
            display: none;
        }

        .sidebar.sidebar-collapsed .submenu {
            display: none;
        }
        .sidebar .navbar-brand {
            color: white !important;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
            padding-bottom: 1rem;
        }

        .sidebar .navbar-brand img {
            height: 60px;
            width: auto;
            max-width: 100%;
            transition: height 0.3s ease;
        }

        .sidebar.sidebar-collapsed .navbar-brand img {
            height: 50px;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 0.75rem;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.875rem 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            white-space: nowrap;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            font-size: 1.1em;
            min-width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .sidebar .nav-link.active {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
            color: white;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
            border-left: 3px solid #20c997;
            padding-left: calc(1rem - 3px);
        }

        .sidebar .nav-item {
            position: relative;
        }

        .sidebar .nav-item i[data-bs-toggle="collapse"] {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s ease;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9em;
        }

        .sidebar .nav-item i[aria-expanded="true"] {
            transform: translateY(-50%) rotate(90deg);
        }

        .sidebar .nav-item i[aria-expanded="false"] {
            transform: translateY(-50%) rotate(0deg);
        }

        .sidebar .submenu {
            padding: 0.5rem 0.5rem 0.5rem 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .sidebar .submenu .nav-link {
            padding: 0.625rem 0.75rem;
            font-size: 0.85rem;
        }

        .sidebar .submenu .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .sidebar .submenu .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .content {
            margin-left: 280px;
            padding: 0;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .content.sidebar-collapsed {
            margin-left: 100px;
        }
        .sidebar-toggle {
            position: fixed;
            left: 270px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: white;
            font-size: 1.2em;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #1e2936 0%, #2a3f54 100%);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar-toggle:hover {
            background: linear-gradient(135deg, #2a3f54 0%, #1e2936 100%);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }
        .toggle-btn {
            display: none;
        }
        .navbar {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 1.25rem 2rem;
            border-bottom: 2px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex-shrink: 0;
        }
        .navbar-brand {
            font-weight: 700;
            color: #2c3e50 !important;
            font-size: 1.4rem;
            letter-spacing: -0.02em;
        }
        .navbar .dropdown-toggle {
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            transition: all 0.2s ease;
        }
        .navbar .dropdown-toggle:hover {
            background-color: rgba(0,0,0,0.05);
        }
        .navbar .btn-link {
            text-decoration: none !important;
        }
        .navbar .btn-link:focus {
            box-shadow: none;
        }
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 8px 8px 0 0;
            padding: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            margin-right: 0.25rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            background-color: rgba(30, 41, 54, 0.05);
            color: #1e2936;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
            box-shadow: 0 4px 8px rgba(30, 41, 54, 0.2);
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            margin-bottom: 2rem;
        }
        .card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-bottom: 1px solid #e9ecef;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.5rem;
        }
        .card-header h5 {
            color: #1e2936;
            font-weight: 700;
            margin: 0;
            font-size: 1.25rem;
        }
        .card-body {
            padding: 2rem;
        }
        .form-label {
            font-weight: 600;
            color: #1e2936;
            margin-bottom: 0.5rem;
        }
        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            background: white;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: #1e2936;
            box-shadow: 0 0 0 0.2rem rgba(30, 41, 54, 0.1);
        }
        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
            margin-right: 0.5rem;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1a2330 0%, #243844 100%);
            color: white;
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #ffb300 0%, #fc6800 100%);
            color: #212529;
        }
        .btn-outline-primary {
            border: 2px solid #1e2936;
            color: #1e2936;
            background: white;
        }
        .btn-outline-primary:hover {
            background: #1e2936;
            color: white;
        }
        .form-check {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-check:hover {
            background: #f1f3f5;
            border-color: #1e2936;
        }
        .form-check-input {
            accent-color: #1e2936;
            width: 1.25em;
            height: 1.25em;
            border: 2px solid #1e2936;
        }
        .form-check-label {
            color: #1e2936;
            font-weight: 600;
            margin-bottom: 0.25rem;
            cursor: pointer;
        }
        .alert {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .text-muted {
            color: #6c757d !important;
            font-size: 0.875rem;
        }
        .container-fluid {
            padding: 2rem;
            flex: 1;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 280px;
                transform: translateX(-100%);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
            }

            .toggle-btn {
                display: block;
            }

            .nav-tabs {
                flex-direction: column;
                padding: 0.25rem;
            }

            .nav-tabs .nav-link {
                margin-right: 0;
                margin-bottom: 0.25rem;
                text-align: center;
            }

            .card-body {
                padding: 1.5rem;
            }

            .container-fluid {
                padding: 1rem;
            }

            .navbar {
                padding: 1rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 250px;
            }

            .sidebar .nav-link {
                padding: 0.75rem 0.75rem;
                font-size: 0.85rem;
            }

            .navbar {
                padding: 0.75rem 1rem;
            }

            .container-fluid {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/superadmin_navigation.php'; ?>

    <div class="content">
        <?php include '../includes/global_navbar.php'; ?>

        <div class="container-fluid">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                        <i class="fas fa-user me-2"></i>Profile Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab" aria-controls="preferences" aria-selected="false">
                        <i class="fas fa-cog me-2"></i>Preferences
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabContent">
                <!-- Profile Information Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name"
                                               value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                               value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" name="department"
                                               value="<?= htmlspecialchars($user['department'] ?? 'Finance') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username"
                                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                                        <small class="text-muted">Username cannot be changed</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <input type="text" class="form-control" id="role"
                                               value="<?= htmlspecialchars($user['role'] ?? '') ?>" disabled>
                                        <small class="text-muted">Role is managed by administrators</small>
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <h6 class="text-primary fw-bold mb-3" style="color: #1b2f73 !important;">Change Password</h6>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>

                            <hr class="my-4">

                            <h6 class="text-primary fw-bold mb-3" style="color: #1b2f73 !important;">Two-Factor Authentication</h6>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Two-factor authentication adds an extra layer of security to your account.
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="twofa" id="totp" value="totp">
                                <label class="form-check-label fw-bold" for="totp">
                                    TOTP (Time-based One-Time Password)
                                </label>
                                <small class="d-block text-muted ms-4">Use an authenticator app like Google Authenticator</small>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="twofa" id="sms" value="sms">
                                <label class="form-check-label fw-bold" for="sms">
                                    SMS (Text Message)
                                </label>
                                <small class="d-block text-muted ms-4">Receive codes via text message</small>
                            </div>
                            <button type="button" class="btn btn-outline-primary">
                                <i class="fas fa-mobile-alt me-2"></i>Enable 2FA
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>System Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="default_currency" class="form-label">Default Currency</label>
                                        <select class="form-select" id="default_currency" name="default_currency">
                                            <option value="PHP" selected>PHP - Philippine Peso (₱)</option>
                                            <option value="USD">USD - US Dollar ($)</option>
                                            <option value="EUR">EUR - Euro (€)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="landing_page" class="form-label">Default Landing Page</label>
                                        <select class="form-select" id="landing_page" name="landing_page">
                                            <option value="dashboard" selected>Dashboard</option>
                                            <option value="general_ledger">General Ledger</option>
                                            <option value="accounts_receivable">Accounts Receivable</option>
                                            <option value="accounts_payable">Accounts Payable</option>
                                            <option value="reports">Reports</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="language" class="form-label">Language</label>
                                        <select class="form-select" id="language" name="language">
                                            <option value="en" selected>English</option>
                                            <option value="fil">Filipino</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="theme" class="form-label">Theme</label>
                                        <select class="form-select" id="theme" name="theme">
                                            <option value="light" selected>Light</option>
                                            <option value="dark">Dark</option>
                                            <option value="auto">Auto (System)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="date_format" class="form-label">Date Format</label>
                                        <select class="form-select" id="date_format" name="date_format">
                                            <option value="MM/DD/YYYY" selected>MM/DD/YYYY (US)</option>
                                            <option value="DD/MM/YYYY">DD/MM/YYYY (International)</option>
                                            <option value="YYYY-MM-DD">YYYY-MM-DD (ISO)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="number_format" class="form-label">Number Format</label>
                                        <select class="form-select" id="number_format" name="number_format">
                                            <option value="1,234.56" selected>1,234.56 (US)</option>
                                            <option value="1.234,56">1.234,56 (EU)</option>
                                            <option value="1 234.56">1 234.56 (International)</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" name="update_preferences" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../includes/alert-modal.js"></script>
    <script src="../includes/privacy_mode.js?v=8"></script>
</body>
</html>
