<?php
// Basic PHP structure - authentication check can be added later
session_start();
// if (!isset($_SESSION['user'])) {
//     header('Location: login.php');
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Admin Profile Settings</title>
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
            background-color: #1e2936;
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 300px;
            z-index: 1000;
            transition: transform 0.3s ease, width 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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
        .sidebar.sidebar-collapsed .nav-item i[data-bs-toggle="collapse"] {
            display: none;
        }
        .sidebar.sidebar-collapsed .submenu {
            display: none;
        }
        .sidebar .nav-link {
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .sidebar .nav-link i {
            font-size: 1.4em;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .submenu {
            padding-left: 20px;
        }
        .sidebar .submenu .nav-link {
            padding: 5px 20px;
            font-size: 0.9em;
        }
        .sidebar .nav-item {
            position: relative;
        }
        .sidebar .nav-item i[data-bs-toggle="collapse"] {
            position: absolute;
            right: 20px;
            top: 10px;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-item i[aria-expanded="true"] {
            transform: rotate(90deg);
        }
        .sidebar .nav-item i[aria-expanded="false"] {
            transform: rotate(0deg);
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
            background-color: #1e2936;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: left 0.3s ease, background-color 0.3s ease;
            z-index: 1001;
        }
        .sidebar-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .toggle-btn {
            display: none;
        }
        .navbar .dropdown-toggle {
            text-decoration: none !important;
        }
        .navbar .dropdown-toggle:focus {
            box-shadow: none;
        }
        .navbar .btn-link {
            text-decoration: none !important;
        }
        .navbar .btn-link:focus {
            box-shadow: none;
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
        /* Enhanced UI Styles */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 8px 8px 0 0;
            padding: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            margin-right: 0.25rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
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

        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
            font-size: 0.9em;
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

        .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
        }

        .btn-outline-danger:hover {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
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

        /* Reports Cards Enhancement */
        .reports-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .reports-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #1e2936, #2c3e50);
        }

        .reports-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .reports-card h3 {
            color: #1e2936;
            font-weight: 800;
            font-size: 2rem;
            margin: 0.5rem 0;
        }

        .reports-card h6 {
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(30, 41, 54, 0.3);
            border-radius: 50%;
            border-top-color: #1e2936;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Enhanced Responsive Design */
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
                padding: 1rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .reports-card {
                margin-bottom: 1rem;
            }
        }

        /* Invoice Items Styling */
        .invoice-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .invoice-item:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-paid .status-dot { background-color: #28a745; }
        .status-overdue .status-dot { background-color: #dc3545; }
        .status-sent .status-dot { background-color: #ffc107; }
        .status-draft .status-dot { background-color: #6c757d; }

        /* Enhanced Footer */
        .footer-enhanced {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 3px solid #1e2936;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
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
            <div class="nav-item">
                <a class="nav-link" href="general_ledger.php">
                    <i class="fas fa-book me-2"></i><span>General Ledger</span>
                </a>
                <i class="fas fa-chevron-right" data-bs-toggle="collapse" data-bs-target="#generalLedgerMenu" aria-expanded="false" style="cursor: pointer; color: white; padding: 5px 10px;"></i>
                <div class="collapse" id="generalLedgerMenu">
                    <div class="submenu">
                        <a class="nav-link" href="accounts_payable.php">
                            <i class="fas fa-credit-card me-2"></i><span>Accounts Payable</span>
                        </a>
                        <a class="nav-link" href="accounts_receivable.php">
                            <i class="fas fa-money-bill-wave me-2"></i><span>Accounts Receivable</span>
                        </a>
                    </div>
                </div>
            </div>
            <a class="nav-link" href="disbursements.php">
                <i class="fas fa-money-check me-2"></i><span>Disbursements</span>
            </a>
            <a class="nav-link" href="budget_management.php">
                <i class="fas fa-chart-line me-2"></i><span>Budget Management</span>
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i><span>Reports</span>
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
                <span class="navbar-brand mb-0 h1 me-4">Admin Profile Settings</span>
                <div class="d-flex align-items-center me-4">
                    <button class="btn btn-link text-dark me-3 position-relative" type="button">
                        <i class="fas fa-bell fa-lg"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7em;">
                            3
                        </span>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <span><strong><?php
                                // Display user name: first_name + last_name, or full_name, or username
                                $firstName = $_SESSION['user']['first_name'] ?? '';
                                $lastName = $_SESSION['user']['last_name'] ?? '';
                                $fullName = $_SESSION['user']['full_name'] ?? '';
                                $userName = $_SESSION['user']['username'] ?? '';

                                if (!empty($firstName) || !empty($lastName)) {
                                    echo htmlspecialchars(trim($firstName . ' ' . $lastName));
                                } elseif (!empty($fullName)) {
                                    echo htmlspecialchars($fullName);
                                } else {
                                    echo htmlspecialchars($userName);
                                }
                            ?></strong></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin-profile-settings.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="input-group mx-auto" style="width: 500px;">
                        <input type="text" class="form-control" placeholder="Search..." aria-label="Search">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="identity-tab" data-bs-toggle="tab" data-bs-target="#identity" type="button" role="tab" aria-controls="identity" aria-selected="true"><i class="fas fa-user me-2"></i>Identity & Signature</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false"><i class="fas fa-shield-alt me-2"></i>Security</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab" aria-controls="preferences" aria-selected="false"><i class="fas fa-cogs me-2"></i>Preferences</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="false"><i class="fas fa-bell me-2"></i>Notifications</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pins-tab" data-bs-toggle="tab" data-bs-target="#pins" type="button" role="tab" aria-controls="pins" aria-selected="false"><i class="fas fa-thumbtack me-2"></i>Pins & Saved Views</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="delegation-tab" data-bs-toggle="tab" data-bs-target="#delegation" type="button" role="tab" aria-controls="delegation" aria-selected="false"><i class="fas fa-user-friends me-2"></i>Delegation / Out-of-Office</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab" aria-controls="privacy" aria-selected="false"><i class="fas fa-lock me-2"></i>Privacy</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabContent">
            <!-- Identity & Signature Tab -->
            <div class="tab-pane fade show active" id="identity" role="tabpanel" aria-labelledby="identity-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Identity & Signature</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="fullName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="fullName" placeholder="Enter full name">
                                </div>
                                <div class="col-md-6">
                                    <label for="photo" class="form-label">Photo</label>
                                    <input type="file" class="form-control" id="photo">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="jobTitle" class="form-label">Job Title</label>
                                    <input type="text" class="form-control" id="jobTitle" placeholder="Enter job title">
                                </div>
                                <div class="col-md-6">
                                    <label for="contactInfo" class="form-label">Contact Info</label>
                                    <textarea class="form-control" id="contactInfo" rows="3" placeholder="Enter contact details"></textarea>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="eSignature" class="form-label">E-Signature Image</label>
                                    <input type="file" class="form-control" id="eSignature">
                                </div>
                                <div class="col-md-6">
                                    <label for="signatureDisclaimer" class="form-label">Signature Disclaimer</label>
                                    <textarea class="form-control" id="signatureDisclaimer" rows="3" placeholder="Enter disclaimer text"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Security</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password">
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Setup 2FA (TOTP/SMS)</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="twofa" id="totp" value="totp">
                                    <label class="form-check-label" for="totp">TOTP</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="twofa" id="sms" value="sms">
                                    <label class="form-check-label" for="sms">SMS</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="recoveryEmail" class="form-label">Recovery Email</label>
                                <input type="email" class="form-control" id="recoveryEmail" placeholder="Enter recovery email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Active Sessions/Devices</label>
                                <textarea class="form-control" id="activeSessions" rows="3" placeholder="List active sessions" readonly></textarea>
                                <button type="button" class="btn btn-secondary mt-2">Sign Out Everywhere</button>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Preferences</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="defaultProperty" class="form-label">Default Property/Branch</label>
                                    <select class="form-select" id="defaultProperty">
                                        <option selected>Select property</option>
                                        <option>Main Branch</option>
                                        <option>Branch 1</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="defaultPeriod" class="form-label">Default Period</label>
                                    <select class="form-select" id="defaultPeriod">
                                        <option selected>Today</option>
                                        <option>MTD</option>
                                        <option>YTD</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="defaultCurrency" class="form-label">Default Currency</label>
                                    <select class="form-select" id="defaultCurrency">
                                        <option selected>PHP</option>
                                        <option>USD</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="landingPage" class="form-label">Landing Page</label>
                                    <select class="form-select" id="landingPage">
                                        <option selected>Dashboard</option>
                                        <option>AR</option>
                                        <option>AP</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="language" class="form-label">Language</label>
                                    <select class="form-select" id="language">
                                        <option selected>EN</option>
                                        <option>PH</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme">
                                        <option selected>Light</option>
                                        <option>Dark</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="numberFormat" class="form-label">Number/Date Format</label>
                                    <select class="form-select" id="numberFormat">
                                        <option selected>Default</option>
                                        <option>US</option>
                                        <option>EU</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Accessibility</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fontSize">
                                        <label class="form-check-label" for="fontSize">Adjust Font Size</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="reducedMotion">
                                        <label class="form-check-label" for="reducedMotion">Reduced Motion</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="highContrast">
                                        <label class="form-check-label" for="highContrast">High Contrast</label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Notifications</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Notification Types</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="emailNotif">
                                    <label class="form-check-label" for="emailNotif">Email</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="inAppNotif">
                                    <label class="form-check-label" for="inAppNotif">In-App</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Specific Notifications</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="promisesDue">
                                    <label class="form-check-label" for="promisesDue">Promises-to-Pay Due</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="apDue">
                                    <label class="form-check-label" for="apDue">AP Due 7 Days</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pdcToday">
                                    <label class="form-check-label" for="pdcToday">PDC Today</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="quietHours" class="form-label">Quiet Hours</label>
                                <input type="text" class="form-control" id="quietHours" placeholder="e.g., 10 PM - 8 AM">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pins & Saved Views Tab -->
            <div class="tab-pane fade" id="pins" role="tabpanel" aria-labelledby="pins-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pins & Saved Views</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label for="pinnedReports" class="form-label">Pinned Reports and Dashboard Tiles</label>
                                <textarea class="form-control" id="pinnedReports" rows="3" placeholder="List pinned items"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="savedFilters" class="form-label">Saved Filters</label>
                                <textarea class="form-control" id="savedFilters" rows="3" placeholder="e.g., 'AR Overdue—Manila', 'AP Due 7d—F&B'"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delegation / Out-of-Office Tab -->
            <div class="tab-pane fade" id="delegation" role="tabpanel" aria-labelledby="delegation-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Delegation / Out-of-Office</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="delegateApprover" class="form-label">Delegate Approver</label>
                                    <select class="form-select" id="delegateApprover">
                                        <option selected>Select user</option>
                                        <option>User 1</option>
                                        <option>User 2</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate">
                                </div>
                                <div class="col-md-6">
                                    <label for="scope" class="form-label">Scope (Which Approvals)</label>
                                    <textarea class="form-control" id="scope" rows="3" placeholder="Specify approvals"></textarea>
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notifyTeam">
                                    <label class="form-check-label" for="notifyTeam">Notify Team</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Privacy Tab -->
            <div class="tab-pane fade" id="privacy" role="tabpanel" aria-labelledby="privacy-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Privacy</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label class="form-label">View/Download My Data</label>
                                <button type="button" class="btn btn-secondary">Request Data Export</button>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Consent Toggles for Analytics</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="analyticsConsent">
                                    <label class="form-check-label" for="analyticsConsent">Allow Analytics Tracking</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer id="footer" class="footer-enhanced py-3" style="position: fixed; bottom: 0; left: 120px; width: calc(100% - 120px); z-index: 998; font-weight: 500;">
        <div class="container-fluid">
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-4">
                    <span class="text-muted"><i class="fas fa-shield-alt me-1" style="color: #1e2936;"></i>© 2025 ATIERA Finance — Confidential</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">
                        <span class="badge" style="background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%); color: white;">PROD</span> v1.0.0 • Updated: Sep 25, 2025
                        <span class="ms-3 text-success fw-bold"><i class="fas fa-sync-alt me-1"></i>Sync OK</span>
                    </span>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="text-muted">
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link" style="color: #6c757d !important;">Terms</a>
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link" style="color: #6c757d !important;">Privacy</a>
                        <a href="mailto:support@atiera.com" class="text-decoration-none text-muted hover-link" style="color: #6c757d !important;"><i class="fas fa-envelope me-1"></i>Support</a>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            updateFooterPosition();
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
            updateFooterPosition();
        }

        function updateFooterPosition() {
            const content = document.querySelector('.content');
            const footer = document.getElementById('footer');
            const marginLeft = content.style.marginLeft || '120px';
            footer.style.left = marginLeft;
            footer.style.width = `calc(100% - ${marginLeft})`;
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
            updateFooterPosition();
        });
    </script>
</body>
</html>
