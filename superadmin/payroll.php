<?php
// Payroll functionality has been integrated into Disbursements module
// Redirect to Disbursements Payroll Processing tab
header('Location: disbursements.php#payroll');
exit;

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Initialize API integration manager
$integrationManager = APIIntegrationManager::getInstance();

// Handle refresh/sync action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_payroll'])) {
    try {
        // Sync payroll data from HR4
        $result = $integrationManager->executeIntegrationAction('hr4', 'importPayroll', []);
        $syncMessage = $result['message'] ?? 'Payroll sync completed successfully';
        $syncType = isset($result['errors']) && count($result['errors']) > 0 ? 'warning' : 'success';
    } catch (Exception $e) {
        $syncMessage = 'Error syncing payroll: ' . $e->getMessage();
        $syncType = 'danger';
    }
}

// Fetch payroll data from HR4 integration
$payrollData = [];
$payrollSummary = [];

try {
    $integration = $integrationManager->getIntegration('hr4');
    if ($integration) {
        $config = $integrationManager->getIntegrationConfig('hr4');
        if ($config && isset($config['api_url'])) {
            $payrollData = $integration->getPayrollData($config);

            // Calculate payroll summary
            $departmentSummary = [];
            $totalEmployees = count($payrollData);
            $totalPayroll = 0;
            $totalDeductions = 0;

            foreach ($payrollData as $employee) {
                $dept = $employee['department_id'] ?? 1;
                $payrollAmount = floatval($employee['net_pay'] ?? $employee['amount'] ?? 0);
                $deductionAmount = floatval($employee['deductions'] ?? 0);

                if (!isset($departmentSummary[$dept])) {
                    $departmentSummary[$dept] = [
                        'department' => $employee['department'] ?? 'Unknown',
                        'employee_count' => 0,
                        'total_payroll' => 0,
                        'total_deductions' => 0
                    ];
                }

                $departmentSummary[$dept]['employee_count']++;
                $departmentSummary[$dept]['total_payroll'] += $payrollAmount;
                $departmentSummary[$dept]['total_deductions'] += $deductionAmount;

                $totalPayroll += $payrollAmount;
                $totalDeductions += $deductionAmount;
            }

            $payrollSummary = [
                'total_employees' => $totalEmployees,
                'total_payroll' => $totalPayroll,
                'total_deductions' => $totalDeductions,
                'departments' => $departmentSummary
            ];
        }
    }
} catch (Exception $e) {
    $payrollError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Payroll Breakdown</title>
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

        /* Payroll-specific styles */
        .payroll-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .payroll-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: rotate(0deg) translate(-50%, -50%); }
            50% { transform: rotate(180deg) translate(-50%, -50%); }
        }

        .payroll-card .card-body {
            padding: 2.5rem;
            position: relative;
            z-index: 2;
        }

        .payroll-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .department-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .department-breakdown {
            background: linear-gradient(135deg, #1e2936 0%, #2c3e50 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        /* Responsive Design */
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
              <hr class="my-3">
          </nav>
    </div>
    <div class="sidebar-toggle" onclick="toggleSidebarDesktop()">
        <i class="fas fa-chevron-right" id="sidebarArrow"></i>
    </div>

    <div class="content">
        <?php include '../includes/global_navbar.php'; ?>

        <!-- Payroll Summary Cards -->
        <?php if (!empty($payrollSummary)): ?>
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card payroll-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-3"></i>
                        <h6>Total Employees</h6>
                        <h3><?php echo number_format($payrollSummary['total_employees']); ?></h3>
                        <small>Current Payroll Period</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card payroll-card">
                    <div class="card-body text-center">
                        <i class="fas fa-peso-sign fa-2x mb-3"></i>
                        <h6>Total Payroll Amount</h6>
                        <h3>₱<?php echo number_format($payrollSummary['total_payroll'], 2); ?></h3>
                        <small>Net Pay This Period</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card payroll-card">
                    <div class="card-body text-center">
                        <i class="fas fa-minus-circle fa-2x mb-3"></i>
                        <h6>Total Deductions</h6>
                        <h3>₱<?php echo number_format($payrollSummary['total_deductions'], 2); ?></h3>
                        <small>Across All Departments</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Department Breakdown -->
        <?php if (!empty($payrollSummary['departments'])): ?>
        <div class="department-breakdown mb-4">
            <h5 class="mb-3"><i class="fas fa-building me-2"></i>Payroll Breakdown by Department</h5>
            <div class="row">
                <?php foreach ($payrollSummary['departments'] as $deptId => $deptData): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card bg-white text-dark">
                        <div class="card-body text-center">
                            <h6 class="card-title"><?php echo htmlspecialchars($deptData['department']); ?></h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Employees</small>
                                    <h4 class="text-primary"><?php echo $deptData['employee_count']; ?></h4>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Total Pay</small>
                                    <h4 class="text-success">₱<?php echo number_format($deptData['total_payroll'], 2); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payroll Details Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Employee Payroll Details</h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportPayroll('pdf')">
                                <i class="fas fa-file-pdf me-1"></i>Export PDF
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportPayroll('excel')">
                                <i class="fas fa-file-excel me-1"></i>Export Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($payrollError)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($payrollError); ?>
                            </div>
                        <?php elseif (empty($payrollData)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Payroll Data Available</h5>
                                <p class="text-muted">Try syncing payroll data from HR4 or check the integration configuration.</p>
                                <form method="post" class="d-inline">
                                    <button type="submit" name="sync_payroll" class="btn btn-primary">
                                        <i class="fas fa-sync-alt me-2"></i>Sync Payroll Data
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="payrollTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Position</th>
                                            <th>Basic Salary</th>
                                            <th>Allowances</th>
                                            <th>Deductions</th>
                                            <th>Net Pay</th>
                                            <th>Payroll Period</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payrollData as $employee): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 0.8em;">
                                                        <?php echo substr($employee['employee_name'] ?? 'N', 0, 1); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($employee['employee_name'] ?? 'Unknown'); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($employee['employee_id'] ?? '-'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($employee['position'] ?? 'Staff'); ?></td>
                                            <td>₱<?php echo number_format($employee['basic_salary'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($employee['allowances'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($employee['deductions'] ?? 0, 2); ?></td>
                                            <td><strong class="text-success">₱<?php echo number_format($employee['net_pay'] ?? $employee['amount'] ?? 0, 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars($employee['payroll_period'] ?? 'Current Month'); ?></td>
                                            <td>
                                                <span class="badge bg-success">Processed</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../includes/alert-modal.js"></script>
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

            // Initialize search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const filter = this.value.toUpperCase();
                    const table = document.getElementById('payrollTable');
                    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                    for (let i = 0; i < rows.length; i++) {
                        const cells = rows[i].getElementsByTagName('td');
                        let match = false;

                        for (let j = 0; j < cells.length; j++) {
                            if (cells[j].textContent.toUpperCase().indexOf(filter) > -1) {
                                match = true;
                                break;
                            }
                        }

                        rows[i].style.display = match ? '' : 'none';
                    }
                });
            }
        });

        function exportPayroll(format) {
            const table = document.getElementById('payrollTable');
            if (!table) {
                alert('No payroll data to export');
                return;
            }

            if (format === 'pdf') {
                // For now, just trigger browser print
                window.print();
            } else if (format === 'excel') {
                // Simple CSV export
                let csv = '';
                const rows = table.querySelectorAll('tr');

                for (let i = 0; i < rows.length; i++) {
                    const cells = rows[i].querySelectorAll('th, td');
                    const row = [];

                    for (let j = 0; j < cells.length; j++) {
                        row.push('"' + cells[j].textContent.replace(/"/g, '""') + '"');
                    }

                    csv += row.join(',') + '\n';
                }

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'payroll_breakdown.csv';
                a.click();
                window.URL.revokeObjectURL(url);
            }
        }
    </script>

    <!-- Privacy Mode - Hide amounts with asterisks + Eye button -->
    <script src="../includes/privacy_mode.js?v=12"></script>

    <!-- Inactivity Timeout - Blur screen + Auto logout -->
    <script src="../includes/inactivity_timeout.js?v=3"></script>
<script src="../includes/navbar_datetime.js"></script>
<script src="../includes/tab_persistence.js?v=1"></script>
</body>
</html>
    <script src="../includes/inactivity_timeout.js?v=3"></script>
<script src="../includes/navbar_datetime.js"></script>
    <script src="../includes/inactivity_timeout.js?v=3"></script>
<script src="../includes/navbar_datetime.js"></script>




