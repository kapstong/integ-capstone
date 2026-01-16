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

// Get user settings
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get user preferences (if exists)
    $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferencesResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $preferences = $preferencesResult ?: [
        'theme' => 'light',
        'language' => 'en',
        'timezone' => 'Asia/Manila',
        'dashboard_layout' => 'default',
        'items_per_page' => 10,
        'date_format' => 'M j, Y',
        'currency' => 'PHP'
    ];

} catch (Exception $e) {
    error_log("Error fetching user settings: " . $e->getMessage());
    $user = $_SESSION['user'];
    $preferences = [
        'theme' => 'light',
        'language' => 'en',
        'timezone' => 'Asia/Manila',
        'dashboard_layout' => 'default',
        'items_per_page' => 10,
        'date_format' => 'M j, Y',
        'currency' => 'PHP'
    ];
}

// Handle settings update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    try {
        $theme = $_POST['theme'] ?? 'light';
        $language = $_POST['language'] ?? 'en';
        $timezone = $_POST['timezone'] ?? 'Asia/Manila';
        $dashboard_layout = $_POST['dashboard_layout'] ?? 'default';
        $items_per_page = (int)($_POST['items_per_page'] ?? 10);
        $date_format = $_POST['date_format'] ?? 'M j, Y';
        $currency = $_POST['currency'] ?? 'PHP';

        // Update or insert user preferences
        $stmt = $db->prepare("
            INSERT INTO user_preferences
            (user_id, theme, language, timezone, dashboard_layout, items_per_page, date_format, currency, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            theme = VALUES(theme),
            language = VALUES(language),
            timezone = VALUES(timezone),
            dashboard_layout = VALUES(dashboard_layout),
            items_per_page = VALUES(items_per_page),
            date_format = VALUES(date_format),
            currency = VALUES(currency),
            updated_at = NOW()
        ");
        $stmt->execute([
            $user_id, $theme, $language, $timezone,
            $dashboard_layout, $items_per_page, $date_format, $currency
        ]);

        $message = 'Settings updated successfully';
        $messageType = 'success';

        // Update preferences array
        $preferences = [
            'theme' => $theme,
            'language' => $language,
            'timezone' => $timezone,
            'dashboard_layout' => $dashboard_layout,
            'items_per_page' => $items_per_page,
            'date_format' => $date_format,
            'currency' => $currency
        ];

    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle privacy settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_privacy'])) {
    try {
        $profile_visibility = $_POST['profile_visibility'] ?? 'private';
        $activity_visibility = $_POST['activity_visibility'] ?? 'private';
        $data_sharing = isset($_POST['data_sharing']) ? 1 : 0;

        // Update privacy settings
        $stmt = $db->prepare("
            UPDATE users SET
                profile_visibility = ?,
                activity_visibility = ?,
                data_sharing = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$profile_visibility, $activity_visibility, $data_sharing, $user_id]);

        $message = 'Privacy settings updated successfully';
        $messageType = 'success';

    } catch (Exception $e) {
        $message = 'Error updating privacy settings: ' . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/enhanced-ui.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e8ecf7 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .sidebar {
            height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
            background: linear-gradient(180deg, #0f1c49 0%, #1b2f73 50%, #15265e 100%);
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 300px;
            z-index: 1000;
            transition: transform 0.3s ease, width 0.3s ease;
            box-shadow: 4px 0 20px rgba(15, 28, 73, 0.15);
            border-right: 2px solid rgba(212, 175, 55, 0.2);
        }
        .sidebar.sidebar-collapsed {
            width: 120px;
        }
        .sidebar.sidebar-collapsed span {
            display: none;
        }
        .sidebar.sidebar-collapsed .nav-link {
            padding: 10px;
            text-align: center;
        }
        .sidebar.sidebar-collapsed .navbar-brand {
            text-align: center;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 14px 24px;
            border-radius: 12px;
            margin: 4px 16px;
            font-size: 1.05em;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .nav-link i {
            font-size: 1.3em;
            width: 24px;
            text-align: center;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.12);
            color: white;
            transform: translateX(4px);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
            color: #0f1c49;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }
        .content {
            margin-left: 120px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1;
        }
        .sidebar .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar .navbar-brand img {
            height: 50px;
            width: auto;
            max-width: 100%;
            transition: height 0.3s ease;
        }
        .sidebar.sidebar-collapsed .navbar-brand img {
            height: 80px;
        }
        .sidebar-toggle {
            position: fixed;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: white;
            font-size: 1.5em;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
            border: 2px solid #d4af37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: left 0.3s ease, transform 0.2s ease;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(15, 28, 73, 0.3);
        }
        .sidebar-toggle:hover {
            background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
            color: #0f1c49;
            transform: translateY(-50%) scale(1.1);
        }
        .toggle-btn {
            display: none;
        }
        .navbar {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e3e6ea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 10000;
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
        .navbar .dropdown-toggle span {
            font-weight: 600;
            font-size: 1.1rem;
            color: #495057;
        }
        .navbar .btn-link {
            font-size: 1.1rem;
            border-radius: 8px;
            padding: 0.5rem;
            transition: all 0.2s ease;
            color: #6c757d;
        }
        .navbar .btn-link:hover {
            background-color: rgba(0,0,0,0.05);
            color: #495057;
        }
        .navbar .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        .navbar .input-group:focus-within {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            border-color: #007bff;
        }
        .navbar .form-control {
            border: none;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background-color: #ffffff;
        }
        .navbar .form-control:focus {
            box-shadow: none;
            border-color: transparent;
            background-color: #ffffff;
        }
        .navbar .btn-outline-secondary {
            border: none;
            background-color: #f8f9fa;
            color: #6c757d;
            border-left: 1px solid #e9ecef;
            padding: 0.75rem 1rem;
        }
        .navbar .btn-outline-secondary:hover {
            background-color: #e9ecef;
            color: #495057;
        }
        .navbar .dropdown-menu {
            z-index: 9999;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            border: none;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        .navbar .dropdown-item {
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }
        .navbar .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #495057;
        }
        .hover-link:hover {
            color: #007bff !important;
            transition: color 0.2s ease;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
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
        .table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table thead th {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f1f1;
        }
        .table tbody tr:hover {
            background-color: rgba(30, 41, 54, 0.02);
            transform: scale(1.01);
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #495057;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
        }
        .btn-outline-primary {
            border: 2px solid #1e2936;
            color: #1e2936;
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
        }
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
            background: white;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1e2936;
            box-shadow: 0 0 0 0.2rem rgba(30, 41, 54, 0.1);
            transform: translateY(-1px);
        }
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-bottom: 1px solid #e9ecef;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem 2rem;
        }
        .modal-title {
            color: #1e2936;
            font-weight: 700;
            font-size: 1.25rem;
        }
        .modal-body {
            padding: 2rem;
        }
        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        .settings-section {
            margin-bottom: 2rem;
        }
        .settings-section h6 {
            color: #1e2936;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        .form-check-input:checked {
            background-color: #1e2936;
            border-color: #1e2936;
        }
        .form-check-label {
            font-weight: 500;
            color: #495057;
        }
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content {
                margin-left: 0;
                padding: 20px;
            }
            .toggle-btn {
                display: block;
            }
            .card-body {
                padding: 1rem;
            }
            .table-responsive {
                font-size: 0.875rem;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar sidebar-collapsed" id="sidebar">
        <div class="p-3">
            <h5 class="navbar-brand"><img src="atieralogo.png" alt="Atiera Logo" style="height: 100px;"></h5>
            <hr style="border-top: 2px solid white; margin: 10px 0;">
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i><span>Dashboard</span>
            </a>
            <a class="nav-link" href="tasks.php">
                <i class="fas fa-tasks me-2"></i><span>My Tasks</span>
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
            </a>
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user me-2"></i><span>Profile</span>
            </a>
            <a class="nav-link active" href="settings.php">
                <i class="fas fa-cog me-2"></i><span>Settings</span>
            </a>
        </nav>
    </div>
    <div class="sidebar-toggle" onclick="toggleSidebarDesktop()">
        <i class="fas fa-chevron-right" id="sidebarArrow"></i>
    </div>

    <div class="content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary toggle-btn" type="button" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="navbar-brand mb-0 h1 me-4">Settings</span>
                <div class="d-flex align-items-center me-4">

                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <span><strong><?php echo htmlspecialchars($_SESSION['user']['username']); ?></strong></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="input-group mx-auto" style="width: 500px;">
                        <input type="text" class="form-control" placeholder="Search settings..." aria-label="Search">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Settings Navigation -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                                    <i class="fas fa-sliders-h me-2"></i>Preferences
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>Privacy
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-lock me-2"></i>Security
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- Preferences Tab -->
            <div class="tab-pane fade show active" id="preferences" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6>Display & Interface Preferences</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="settings-section">
                                        <h6>Appearance</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Theme</label>
                                                    <select class="form-select" name="theme">
                                                        <option value="light" <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                                        <option value="dark" <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                                        <option value="auto" <?php echo $preferences['theme'] === 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Language</label>
                                                    <select class="form-select" name="language">
                                                        <option value="en" <?php echo $preferences['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                                        <option value="es" <?php echo $preferences['language'] === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                                        <option value="fr" <?php echo $preferences['language'] === 'fr' ? 'selected' : ''; ?>>French</option>
                                                        <option value="de" <?php echo $preferences['language'] === 'de' ? 'selected' : ''; ?>>German</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-section">
                                        <h6>Regional Settings</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Timezone</label>
                                                    <select class="form-select" name="timezone">
                                                        <option value="Asia/Manila" <?php echo $preferences['timezone'] === 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (GMT+8)</option>
                                                        <option value="America/New_York" <?php echo $preferences['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (GMT-5)</option>
                                                        <option value="Europe/London" <?php echo $preferences['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>London (GMT+0)</option>
                                                        <option value="Asia/Tokyo" <?php echo $preferences['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo (GMT+9)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Currency</label>
                                                    <select class="form-select" name="currency">
                                                        <option value="PHP" <?php echo $preferences['currency'] === 'PHP' ? 'selected' : ''; ?>>Philippine Peso (₱)</option>
                                                        <option value="USD" <?php echo $preferences['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                                        <option value="EUR" <?php echo $preferences['currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                                        <option value="GBP" <?php echo $preferences['currency'] === 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Date Format</label>
                                                    <select class="form-select" name="date_format">
                                                        <option value="M j, Y" <?php echo $preferences['date_format'] === 'M j, Y' ? 'selected' : ''; ?>>Jan 15, 2025</option>
                                                        <option value="d/m/Y" <?php echo $preferences['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>15/01/2025</option>
                                                        <option value="m/d/Y" <?php echo $preferences['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>01/15/2025</option>
                                                        <option value="Y-m-d" <?php echo $preferences['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>2025-01-15</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Items Per Page</label>
                                                    <select class="form-select" name="items_per_page">
                                                        <option value="5" <?php echo $preferences['items_per_page'] == 5 ? 'selected' : ''; ?>>5</option>
                                                        <option value="10" <?php echo $preferences['items_per_page'] == 10 ? 'selected' : ''; ?>>10</option>
                                                        <option value="25" <?php echo $preferences['items_per_page'] == 25 ? 'selected' : ''; ?>>25</option>
                                                        <option value="50" <?php echo $preferences['items_per_page'] == 50 ? 'selected' : ''; ?>>50</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-section">
                                        <h6>Dashboard Layout</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Layout Style</label>
                                            <select class="form-select" name="dashboard_layout">
                                                <option value="default" <?php echo $preferences['dashboard_layout'] === 'default' ? 'selected' : ''; ?>>Default</option>
                                                <option value="compact" <?php echo $preferences['dashboard_layout'] === 'compact' ? 'selected' : ''; ?>>Compact</option>
                                                <option value="detailed" <?php echo $preferences['dashboard_layout'] === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="update_preferences" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Preferences
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Preview Card -->
                        <div class="card">
                            <div class="card-header">
                                <h6>Preview</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Current Theme:</strong>
                                    <span class="badge bg-primary ms-2"><?php echo ucfirst($preferences['theme'] ?? 'light'); ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>Language:</strong>
                                    <span class="text-muted ms-2"><?php echo strtoupper($preferences['language'] ?? 'en'); ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>Currency:</strong>
                                    <span class="text-muted ms-2"><?php echo $preferences['currency']; ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>Items per page:</strong>
                                    <span class="text-muted ms-2"><?php echo $preferences['items_per_page']; ?></span>
                                </div>
                                <hr>
                                <small class="text-muted">Changes will take effect after saving and refreshing the page.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Privacy Tab -->
            <div class="tab-pane fade" id="privacy" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6>Privacy Settings</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="settings-section">
                                        <h6>Profile Visibility</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Who can see your profile</label>
                                            <select class="form-select" name="profile_visibility">
                                                <option value="public">Public</option>
                                                <option value="private" selected>Private</option>
                                                <option value="team">Team Only</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="settings-section">
                                        <h6>Activity Visibility</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Who can see your activity</label>
                                            <select class="form-select" name="activity_visibility">
                                                <option value="public">Public</option>
                                                <option value="private" selected>Private</option>
                                                <option value="team">Team Only</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="settings-section">
                                        <h6>Data Sharing</h6>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="data_sharing" id="data_sharing">
                                            <label class="form-check-label" for="data_sharing">
                                                Allow anonymous usage data collection for service improvement
                                            </label>
                                        </div>
                                        <small class="text-muted">This helps us improve the system but doesn't include personal information.</small>
                                    </div>

                                    <div class="settings-section">
                                        <h6>Data Export</h6>
                                        <p>You can export all your data at any time.</p>
                                        <button type="button" class="btn btn-outline-primary" onclick="exportData()">
                                            <i class="fas fa-download me-2"></i>Export My Data
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="update_privacy" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Privacy Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Privacy Tips -->
                        <div class="card">
                            <div class="card-header">
                                <h6>Privacy Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Keep your profile private</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Regularly review your settings</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Use strong passwords</li>
                                    <li class="mb-2"><i class="fas fa-info-circle text-info me-2"></i>Export your data regularly</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6>Security Settings</h6>
                            </div>
                            <div class="card-body">
                                <div class="settings-section">
                                    <h6>Password</h6>
                                    <p>Last changed: <?php echo date('M j, Y', strtotime($user['updated_at'] ?? 'now')); ?></p>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>

                                <div class="settings-section">
                                    <h6>Two-Factor Authentication</h6>
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="badge bg-warning me-3">Not Enabled</span>
                                        <button type="button" class="btn btn-outline-success">
                                            <i class="fas fa-shield-alt me-2"></i>Enable 2FA
                                        </button>
                                    </div>
                                    <small class="text-muted">Add an extra layer of security to your account.</small>
                                </div>

                                <div class="settings-section">
                                    <h6>Login Sessions</h6>
                                    <p>Current session started: <?php echo date('M j, Y H:i', strtotime($_SESSION['user']['last_login'] ?? 'now')); ?></p>
                                    <button type="button" class="btn btn-outline-danger">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout from All Devices
                                    </button>
                                </div>

                                <div class="settings-section">
                                    <h6>Account Deletion</h6>
                                    <p class="text-danger">Permanently delete your account and all associated data.</p>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                        <i class="fas fa-trash me-2"></i>Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Security Status -->
                        <div class="card">
                            <div class="card-header">
                                <h6>Security Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Password Strength:</strong>
                                    <span class="badge bg-success ms-2">Strong</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Two-Factor Auth:</strong>
                                    <span class="badge bg-warning ms-2">Disabled</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Last Login:</strong>
                                    <span class="text-muted ms-2"><?php echo date('M j, Y H:i', strtotime($_SESSION['user']['last_login'] ?? 'now')); ?></span>
                                </div>
                                <hr>
                                <small class="text-muted">Keep your account secure by enabling 2FA and using strong passwords.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="8">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="changePasswordForm" class="btn btn-primary">Change Password</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All your data, including invoices, bills, tasks, and reports will be permanently deleted.
                    </div>
                    <p>Please type <strong>"DELETE"</strong> to confirm:</p>
                    <input type="text" class="form-control" id="deleteConfirmation" placeholder="Type DELETE to confirm">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deleteAccount()">Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function toggleSidebarDesktop() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            sidebar.classList.toggle('sidebar-collapsed');
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            if (isCollapsed) {
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }
        }


        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('sidebar-collapsed');
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                sidebar.classList.remove('sidebar-collapsed');
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }
        });

        // Export user data
        function exportData() {
            // Show loading
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
            btn.disabled = true;

            // Simulate export process
            setTimeout(() => {
                // Create download link
                const link = document.createElement('a');
                link.href = '#'; // In real implementation, this would be the actual export URL
                link.download = `user_data_export_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Reset button
                btn.innerHTML = originalText;
                btn.disabled = false;

                // Show success message
                showAlert('Data export completed successfully', 'success');
            }, 2000);
        }

        // Delete account
        function deleteAccount() {
            const confirmation = document.getElementById('deleteConfirmation').value;
            if (confirmation !== 'DELETE') {
                showAlert('Please type "DELETE" to confirm account deletion', 'warning');
                return;
            }

            if (confirm('Are you absolutely sure you want to delete your account? This action cannot be undone.')) {
                // Show loading
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
                btn.disabled = true;

                // Simulate deletion process
                setTimeout(() => {
                    // In real implementation, this would make an AJAX call to delete the account
                    showAlert('Account deletion is not implemented in this demo', 'info');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            }
        }

        // Utility functions
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>

<script src="../includes/navbar_datetime.js"></script>
</body>
</html>
    </script>
            const sidebar = document.getElementById('sidebar');
            const sidebar = document.getElementById('sidebar');
            const sidebar = document.getElementById('sidebar');
            const sidebar = document.getElementById('sidebar');
            const sidebar = document.getElementById('sidebar');
            const sidebar = document.getElementById('sidebar');
            const sidebar = document.getElementById('sidebar');