<?php
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
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="content" style="margin-left: 300px; padding: 20px;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0" style="color: #1b2f73; font-weight: 700;">
                    <i class="fas fa-user-cog me-2"></i>Profile Settings
                </h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
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
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="false">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabContent">
                <!-- Profile Information Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); color: white; border-bottom: 3px solid #d4af37;">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label fw-bold">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name"
                                               value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label fw-bold">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                               value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label fw-bold">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label fw-bold">Department</label>
                                        <input type="text" class="form-control" id="department" name="department"
                                               value="<?= htmlspecialchars($user['department'] ?? 'Finance') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label fw-bold">Username</label>
                                        <input type="text" class="form-control" id="username"
                                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                                        <small class="text-muted">Username cannot be changed</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label fw-bold">Role</label>
                                        <input type="text" class="form-control" id="role"
                                               value="<?= htmlspecialchars($user['role'] ?? '') ?>" disabled>
                                        <small class="text-muted">Role is managed by administrators</small>
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); border: none;">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); color: white; border-bottom: 3px solid #d4af37;">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <h6 class="text-primary fw-bold mb-3" style="color: #1b2f73 !important;">Change Password</h6>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label fw-bold">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_password" class="form-label fw-bold">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label fw-bold">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-warning" style="background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%); border: none; color: #0f1c49; font-weight: 600;">
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
                            <button type="button" class="btn btn-outline-primary" style="border-color: #1b2f73; color: #1b2f73;">
                                <i class="fas fa-mobile-alt me-2"></i>Enable 2FA
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); color: white; border-bottom: 3px solid #d4af37;">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>System Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="default_currency" class="form-label fw-bold">Default Currency</label>
                                        <select class="form-select" id="default_currency" name="default_currency">
                                            <option value="PHP" selected>PHP - Philippine Peso (₱)</option>
                                            <option value="USD">USD - US Dollar ($)</option>
                                            <option value="EUR">EUR - Euro (€)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="landing_page" class="form-label fw-bold">Default Landing Page</label>
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
                                        <label for="language" class="form-label fw-bold">Language</label>
                                        <select class="form-select" id="language" name="language">
                                            <option value="en" selected>English</option>
                                            <option value="fil">Filipino</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="theme" class="form-label fw-bold">Theme</label>
                                        <select class="form-select" id="theme" name="theme">
                                            <option value="light" selected>Light</option>
                                            <option value="dark">Dark</option>
                                            <option value="auto">Auto (System)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="date_format" class="form-label fw-bold">Date Format</label>
                                        <select class="form-select" id="date_format" name="date_format">
                                            <option value="MM/DD/YYYY" selected>MM/DD/YYYY (US)</option>
                                            <option value="DD/MM/YYYY">DD/MM/YYYY (International)</option>
                                            <option value="YYYY-MM-DD">YYYY-MM-DD (ISO)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="number_format" class="form-label fw-bold">Number Format</label>
                                        <select class="form-select" id="number_format" name="number_format">
                                            <option value="1,234.56" selected>1,234.56 (US)</option>
                                            <option value="1.234,56">1.234,56 (EU)</option>
                                            <option value="1 234.56">1 234.56 (International)</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" name="update_preferences" class="btn btn-primary" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); border: none;">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); color: white; border-bottom: 3px solid #d4af37;">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <h6 class="text-primary fw-bold mb-3" style="color: #1b2f73 !important;">Notification Channels</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_notif" name="email_notif" checked>
                                    <label class="form-check-label fw-bold" for="email_notif">
                                        Email Notifications
                                    </label>
                                </div>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="in_app_notif" name="in_app_notif" checked>
                                    <label class="form-check-label fw-bold" for="in_app_notif">
                                        In-App Notifications
                                    </label>
                                </div>

                                <h6 class="text-primary fw-bold mb-3" style="color: #1b2f73 !important;">Financial Notifications</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="invoice_overdue" name="invoice_overdue" checked>
                                    <label class="form-check-label fw-bold" for="invoice_overdue">
                                        Overdue Invoices
                                    </label>
                                    <small class="d-block text-muted ms-4">Notify when customer invoices become overdue</small>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="payment_received" name="payment_received" checked>
                                    <label class="form-check-label fw-bold" for="payment_received">
                                        Payment Received
                                    </label>
                                    <small class="d-block text-muted ms-4">Notify when payments are received</small>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="bill_due" name="bill_due" checked>
                                    <label class="form-check-label fw-bold" for="bill_due">
                                        Bills Due Soon (7 days)
                                    </label>
                                    <small class="d-block text-muted ms-4">Notify when vendor bills are due within 7 days</small>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="check_deposit" name="check_deposit" checked>
                                    <label class="form-check-label fw-bold" for="check_deposit">
                                        Post-Dated Checks Due Today
                                    </label>
                                    <small class="d-block text-muted ms-4">Notify when PDCs are due for deposit</small>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="budget_alert" name="budget_alert" checked>
                                    <label class="form-check-label fw-bold" for="budget_alert">
                                        Budget Threshold Alerts
                                    </label>
                                    <small class="d-block text-muted ms-4">Notify when budget utilization exceeds 80%</small>
                                </div>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="reconciliation" name="reconciliation" checked>
                                    <label class="form-check-label fw-bold" for="reconciliation">
                                        Reconciliation Reminders
                                    </label>
                                    <small class="d-block text-muted ms-4">Monthly reminders for account reconciliation</small>
                                </div>

                                <button type="submit" name="update_notifications" class="btn btn-primary" style="background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%); border: none;">
                                    <i class="fas fa-save me-2"></i>Save Notification Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
