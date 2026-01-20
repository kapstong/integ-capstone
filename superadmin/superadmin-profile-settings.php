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
    <link href="../includes/enhanced-ui.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #F1F7EE 0%, #E8F1E4 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #1e2936 0%, #2a3f54 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            padding-bottom: 2rem;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.15);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .navbar-brand span {
            display: none;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 15px;
        }

        .sidebar .navbar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .sidebar .navbar-brand img {
            height: 50px;
            width: auto;
            margin-right: 0.75rem;
        }

        .sidebar.collapsed .navbar-brand img {
            margin-right: 0;
            height: 60px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            font-size: 1.3em;
            min-width: 30px;
            text-align: center;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .content {
            margin-left: 300px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .content.expanded {
            margin-left: 80px;
        }

        .navbar {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 1.25rem 2rem;
            border-bottom: 2px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e2936;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-title i {
            color: #2c3e50;
        }

        .container-main {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideInDown 0.3s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .profile-header {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .profile-info h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }

        .profile-info p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }

        .nav-tabs {
            border: none;
            background: white;
            border-radius: 10px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 0.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            background: transparent;
            color: #6c757d;
            padding: 0.875rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-tabs .nav-link:hover {
            background-color: #f1f3f5;
            color: #1e2936;
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 41, 54, 0.2);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 2rem;
        }

        .card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-bottom: 2px solid #e9ecef;
            border-radius: 12px 12px 0 0;
            padding: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header h5 {
            color: #1e2936;
            font-weight: 700;
            margin: 0;
            font-size: 1.25rem;
        }

        .card-header i {
            color: #2c3e50;
            font-size: 1.25rem;
        }

        .card-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #1e2936;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #1e2936;
            box-shadow: 0 0 0 3px rgba(30, 41, 54, 0.1);
            background: white;
        }

        .form-control:disabled,
        .form-select:disabled {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
        }

        .form-control-plaintext {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .row {
            margin-right: -0.75rem;
            margin-left: -0.75rem;
        }

        .row > [class*='col-'] {
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        .hr-section {
            margin: 2rem 0;
            border: none;
            border-top: 2px solid #e9ecef;
        }

        .section-title {
            color: #1e2936;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 1.5rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #2c3e50;
        }

        .btn {
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 0.625rem 1.75rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
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
            width: 1.25em;
            height: 1.25em;
            border: 2px solid #1e2936;
            border-radius: 4px;
            cursor: pointer;
            accent-color: #1e2936;
        }

        .form-check-label {
            color: #1e2936;
            font-weight: 600;
            margin-bottom: 0.25rem;
            cursor: pointer;
        }

        .alert-info-custom {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .text-muted {
            color: #6c757d !important;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1010;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
            }

            .container-main {
                padding: 1rem;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .nav-tabs {
                flex-wrap: wrap;
                padding: 0.25rem;
            }

            .nav-tabs .nav-link {
                flex: 1;
                min-width: 150px;
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .navbar-title {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .row > [class*='col-'] {
                padding-right: 0.5rem;
                padding-left: 0.5rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem;
                font-size: 0.85rem;
            }

            .form-label {
                font-size: 0.95rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <?php include '../includes/superadmin_navigation.php'; ?>

        <!-- Main Content -->
        <div class="content">
            <!-- Navbar -->
            <div class="navbar">
                <div class="navbar-title">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile Settings</span>
                </div>
                <?php include '../includes/global_navbar.php'; ?>
            </div>

            <!-- Main Container -->
            <div class="container-main">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                        <strong><?= $messageType === 'success' ? 'Success!' : 'Error!' ?></strong>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <h2><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h2>
                        <p><i class="fas fa-shield-alt me-1"></i><?= htmlspecialchars($user['role'] ?? 'Super Admin') ?></p>
                        <p><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['email'] ?? '') ?></p>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                            <i class="fas fa-user"></i>Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                            <i class="fas fa-lock"></i>Security
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab" aria-controls="preferences" aria-selected="false">
                            <i class="fas fa-sliders-h"></i>Preferences
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="profileTabContent">
                    <!-- Profile Information Tab -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-id-card"></i>
                                <h5>Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="first_name" class="form-label">
                                                    <i class="fas fa-user"></i>First Name
                                                </label>
                                                <input type="text" class="form-control" id="first_name" name="first_name"
                                                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name" class="form-label">
                                                    <i class="fas fa-user"></i>Last Name
                                                </label>
                                                <input type="text" class="form-control" id="last_name" name="last_name"
                                                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email" class="form-label">
                                                    <i class="fas fa-envelope"></i>Email Address
                                                </label>
                                                <input type="email" class="form-control" id="email" name="email"
                                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone" class="form-label">
                                                    <i class="fas fa-phone"></i>Phone Number
                                                </label>
                                                <input type="tel" class="form-control" id="phone" name="phone"
                                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="department" class="form-label">
                                                    <i class="fas fa-building"></i>Department
                                                </label>
                                                <input type="text" class="form-control" id="department" name="department"
                                                       value="<?= htmlspecialchars($user['department'] ?? 'Finance') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="username" class="form-label">
                                                    <i class="fas fa-user-tag"></i>Username
                                                </label>
                                                <input type="text" class="form-control" id="username"
                                                       value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                                                <small class="form-text">Username cannot be changed</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="role" class="form-label">
                                                    <i class="fas fa-crown"></i>Role
                                                </label>
                                                <input type="text" class="form-control" id="role"
                                                       value="<?= htmlspecialchars($user['role'] ?? '') ?>" disabled>
                                                <small class="form-text">Role is managed by administrators</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save"></i>Save Changes
                                        </button>
                                        <button type="reset" class="btn btn-outline-primary">
                                            <i class="fas fa-redo"></i>Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-shield-alt"></i>
                                <h5>Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <!-- Change Password Section -->
                                <div class="section-title">
                                    <i class="fas fa-key"></i>Change Password
                                </div>

                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="current_password" class="form-label">
                                            <i class="fas fa-lock"></i>Current Password
                                        </label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new_password" class="form-label">
                                                    <i class="fas fa-lock-open"></i>New Password
                                                </label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <small class="form-text">Minimum 8 characters</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="confirm_password" class="form-label">
                                                    <i class="fas fa-check-circle"></i>Confirm Password
                                                </label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="fas fa-sync-alt"></i>Update Password
                                    </button>
                                </form>

                                <div class="hr-section"></div>

                                <!-- Two-Factor Authentication Section -->
                                <div class="section-title">
                                    <i class="fas fa-mobile-alt"></i>Two-Factor Authentication
                                </div>

                                <div class="alert-info-custom">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Two-factor authentication adds an extra layer of security to your account.
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="twofa" id="totp" value="totp">
                                    <label class="form-check-label" for="totp">
                                        <i class="fas fa-clock me-1"></i>TOTP (Time-based One-Time Password)
                                    </label>
                                    <small class="d-block text-muted ms-4 mt-1">Use an authenticator app like Google Authenticator or Authy</small>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="twofa" id="sms" value="sms">
                                    <label class="form-check-label" for="sms">
                                        <i class="fas fa-comment me-1"></i>SMS (Text Message)
                                    </label>
                                    <small class="d-block text-muted ms-4 mt-1">Receive verification codes via text message</small>
                                </div>

                                <button type="button" class="btn btn-outline-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-check"></i>Enable 2FA
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences Tab -->
                    <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-sliders-h"></i>
                                <h5>System Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="default_currency" class="form-label">
                                                    <i class="fas fa-dollar-sign"></i>Default Currency
                                                </label>
                                                <select class="form-select" id="default_currency" name="default_currency">
                                                    <option value="PHP" selected>PHP - Philippine Peso (₱)</option>
                                                    <option value="USD">USD - US Dollar ($)</option>
                                                    <option value="EUR">EUR - Euro (€)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="landing_page" class="form-label">
                                                    <i class="fas fa-home"></i>Default Landing Page
                                                </label>
                                                <select class="form-select" id="landing_page" name="landing_page">
                                                    <option value="dashboard" selected>Dashboard</option>
                                                    <option value="general_ledger">General Ledger</option>
                                                    <option value="accounts_receivable">Accounts Receivable</option>
                                                    <option value="accounts_payable">Accounts Payable</option>
                                                    <option value="reports">Reports</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="language" class="form-label">
                                                    <i class="fas fa-language"></i>Language
                                                </label>
                                                <select class="form-select" id="language" name="language">
                                                    <option value="en" selected>English</option>
                                                    <option value="fil">Filipino</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="theme" class="form-label">
                                                    <i class="fas fa-paint-brush"></i>Theme
                                                </label>
                                                <select class="form-select" id="theme" name="theme">
                                                    <option value="light" selected>Light</option>
                                                    <option value="dark">Dark</option>
                                                    <option value="auto">Auto (System)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="date_format" class="form-label">
                                                    <i class="fas fa-calendar"></i>Date Format
                                                </label>
                                                <select class="form-select" id="date_format" name="date_format">
                                                    <option value="MM/DD/YYYY" selected>MM/DD/YYYY (US)</option>
                                                    <option value="DD/MM/YYYY">DD/MM/YYYY (International)</option>
                                                    <option value="YYYY-MM-DD">YYYY-MM-DD (ISO)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="number_format" class="form-label">
                                                    <i class="fas fa-hashtag"></i>Number Format
                                                </label>
                                                <select class="form-select" id="number_format" name="number_format">
                                                    <option value="1,234.56" selected>1,234.56 (US)</option>
                                                    <option value="1.234,56">1.234,56 (EU)</option>
                                                    <option value="1 234.56">1 234.56 (International)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                                        <button type="submit" name="update_preferences" class="btn btn-primary">
                                            <i class="fas fa-save"></i>Save Preferences
                                        </button>
                                        <button type="reset" class="btn btn-outline-primary">
                                            <i class="fas fa-redo"></i>Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../includes/alert-modal.js"></script>
    <script src="../includes/privacy_mode.js?v=8"></script>
    <script>
        // Sidebar toggle functionality
        const toggleBtn = document.querySelector('.toggle-btn');
        const sidebar = document.querySelector('.sidebar');
        const content = document.querySelector('.content');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });
        }

        // Close sidebar on link click on mobile
        if (window.innerWidth < 768) {
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    sidebar.classList.remove('show');
                });
            });
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>
