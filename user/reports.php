<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Get user-specific financial data
$user_id = $_SESSION['user']['id'];

try {
    // Initialize database connection
    $db = Database::getInstance()->getConnection();
    // Get user's recent transactions
    $stmt = $db->prepare("
        SELECT
            'invoice' as type,
            i.id,
            i.invoice_number as reference,
            i.total_amount as amount,
            i.invoice_date as date,
            c.company_name as related_party,
            i.status
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.created_by = ?
        UNION ALL
        SELECT
            'bill' as type,
            b.id,
            b.bill_number as reference,
            b.total_amount as amount,
            b.bill_date as date,
            v.name as related_party,
            b.status
        FROM bills b
        LEFT JOIN vendors v ON b.vendor_id = v.id
        WHERE b.created_by = ?
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's financial summary
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'invoice' THEN amount END), 0) as total_invoiced,
            COALESCE(SUM(CASE WHEN type = 'bill' THEN amount END), 0) as total_billed,
            COALESCE(AVG(CASE WHEN type = 'invoice' THEN amount END), 0) as avg_invoice_amount,
            COUNT(CASE WHEN type = 'invoice' THEN 1 END) as invoice_count,
            COUNT(CASE WHEN type = 'bill' THEN 1 END) as bill_count
        FROM (
            SELECT 'invoice' as type, total_amount as amount FROM invoices WHERE created_by = ?
            UNION ALL
            SELECT 'bill' as type, total_amount as amount FROM bills WHERE created_by = ?
        ) as combined
    ");
    $stmt->execute([$user_id, $user_id]);
    $financial_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get chart data for transaction trends (last 6 months)
    $transaction_trends = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $stmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'invoice' THEN amount END), 0) as invoices,
                COALESCE(SUM(CASE WHEN type = 'bill' THEN amount END), 0) as bills
            FROM (
                SELECT 'invoice' as type, total_amount as amount FROM invoices
                WHERE created_by = ? AND invoice_date BETWEEN ? AND ?
                UNION ALL
                SELECT 'bill' as type, total_amount as amount FROM bills
                WHERE created_by = ? AND bill_date BETWEEN ? AND ?
            ) as monthly_data
        ");
        $stmt->execute([$user_id, $monthStart, $monthEnd, $user_id, $monthStart, $monthEnd]);
        $monthly_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $transaction_trends[] = [
            'month' => date('M Y', strtotime($monthStart)),
            'invoices' => (float)$monthly_data['invoices'],
            'bills' => (float)$monthly_data['bills']
        ];
    }

    // Get status distribution data
    $stmt = $db->prepare("
        SELECT
            status,
            COUNT(*) as count
        FROM (
            SELECT status FROM invoices WHERE created_by = ?
            UNION ALL
            SELECT status FROM bills WHERE created_by = ?
        ) as status_data
        GROUP BY status
    ");
    $stmt->execute([$user_id, $user_id]);
    $status_distribution_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format status distribution for chart
    $status_distribution = [
        'labels' => [],
        'data' => [],
        'colors' => []
    ];

    $status_colors = [
        'paid' => '#28a745',
        'pending' => '#ffc107',
        'overdue' => '#dc3545',
        'draft' => '#6c757d',
        'sent' => '#17a2b8',
        'approved' => '#007bff'
    ];

    foreach ($status_distribution_raw as $status) {
        $status_distribution['labels'][] = ucfirst(str_replace('_', ' ', $status['status']));
        $status_distribution['data'][] = (int)$status['count'];
        $status_distribution['colors'][] = $status_colors[$status['status']] ?? '#6c757d';
    }

    // If no data, provide defaults
    if (empty($status_distribution['labels'])) {
        $status_distribution = [
            'labels' => ['No Data'],
            'data' => [0],
            'colors' => ['#6c757d']
        ];
    }

} catch (Exception $e) {
    error_log("Error fetching user reports data: " . $e->getMessage());
    $recent_transactions = [];
    $financial_summary = [
        'total_invoiced' => 0,
        'total_billed' => 0,
        'avg_invoice_amount' => 0,
        'invoice_count' => 0,
        'bill_count' => 0
    ];
    $transaction_trends = [];
    $status_distribution = [
        'labels' => ['No Data'],
        'data' => [0],
        'colors' => ['#6c757d']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - My Reports</title>
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
            <a class="nav-link active" href="reports.php">
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
                <span class="navbar-brand mb-0 h1 me-4">My Reports</span>
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
                        <input type="text" class="form-control" placeholder="Search reports..." aria-label="Search">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Financial Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="reports-card">
                    <i class="fas fa-file-invoice-dollar fa-2x mb-3 text-success"></i>
                    <h6>Total Invoiced</h6>
                    <h3>₱<?php echo number_format($financial_summary['total_invoiced'], 2); ?></h3>
                    <small><?php echo $financial_summary['invoice_count']; ?> invoices</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="reports-card">
                    <i class="fas fa-receipt fa-2x mb-3 text-danger"></i>
                    <h6>Total Billed</h6>
                    <h3>₱<?php echo number_format($financial_summary['total_billed'], 2); ?></h3>
                    <small><?php echo $financial_summary['bill_count']; ?> bills</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="reports-card">
                    <i class="fas fa-calculator fa-2x mb-3 text-info"></i>
                    <h6>Avg Invoice Amount</h6>
                    <h3>₱<?php echo number_format($financial_summary['avg_invoice_amount'], 2); ?></h3>
                    <small>Per invoice</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="reports-card">
                    <i class="fas fa-balance-scale fa-2x mb-3 text-warning"></i>
                    <h6>Net Position</h6>
                    <h3 class="<?php echo ($financial_summary['total_invoiced'] - $financial_summary['total_billed']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        ₱<?php echo number_format($financial_summary['total_invoiced'] - $financial_summary['total_billed'], 2); ?>
                    </h3>
                    <small><?php echo ($financial_summary['total_invoiced'] - $financial_summary['total_billed']) >= 0 ? 'Positive' : 'Negative'; ?></small>
                </div>
            </div>
        </div>

        <!-- Report Filters and Actions -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <select class="form-select form-select-sm me-2" id="reportPeriod" onchange="changeReportPeriod()">
                    <option value="last_30_days">Last 30 Days</option>
                    <option value="last_3_months">Last 3 Months</option>
                    <option value="last_6_months">Last 6 Months</option>
                    <option value="year_to_date">Year to Date</option>
                    <option value="custom">Custom Range</option>
                </select>
                <div id="customDateRange" class="d-inline-block" style="display: none;">
                    <input type="date" class="form-control form-control-sm d-inline-block me-2" id="startDate" style="width: auto;">
                    <input type="date" class="form-control form-control-sm d-inline-block me-2" id="endDate" style="width: auto;">
                    <button class="btn btn-sm btn-outline-primary" onclick="applyCustomRange()">Apply</button>
                </div>
            </div>
            <div>
                <button class="btn btn-outline-secondary me-2" onclick="exportReport('pdf')">
                    <i class="fas fa-download me-2"></i>Export PDF
                </button>
                <button class="btn btn-primary" onclick="generateReport()">
                    <i class="fas fa-sync me-2"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6>Recent Transactions</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th>Related Party</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsTable">
                                    <?php if (empty($recent_transactions)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No transactions found for the selected period.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?php echo $transaction['type'] === 'invoice' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo ucfirst($transaction['type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['reference']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['related_party'] ?? 'N/A'); ?></td>
                                                <td>₱<?php echo number_format($transaction['amount'], 2); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($transaction['date'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($transaction['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction('<?php echo $transaction['type']; ?>', <?php echo $transaction['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Charts and Analytics -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>Transaction Trends</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="transactionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>Status Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports Section -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6>Available Reports</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-primary mb-3">
                                    <div class="card-body text-center">
                                        <i class="fas fa-file-invoice fa-3x mb-3 text-primary"></i>
                                        <h6>Invoice Summary</h6>
                                        <p class="text-muted small">Summary of all invoices created</p>
                                        <button class="btn btn-outline-primary" onclick="generateInvoiceReport()">Generate</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-danger mb-3">
                                    <div class="card-body text-center">
                                        <i class="fas fa-receipt fa-3x mb-3 text-danger"></i>
                                        <h6>Bill Summary</h6>
                                        <p class="text-muted small">Summary of all bills received</p>
                                        <button class="btn btn-outline-danger" onclick="generateBillReport()">Generate</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-line fa-3x mb-3 text-success"></i>
                                        <h6>Financial Overview</h6>
                                        <p class="text-muted small">Complete financial summary</p>
                                        <button class="btn btn-outline-success" onclick="generateFinancialOverview()">Generate</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer id="footer" class="py-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-top: 2px solid #1e2936; position: fixed; bottom: 0; left: 120px; width: calc(100% - 120px); z-index: 998; font-weight: 500;">
        <div class="container-fluid">
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-4">
                    <span class="text-muted"><i class="fas fa-shield-alt me-1 text-primary"></i>© 2025 ATIERA Finance — User Portal</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">
                        <span class="badge bg-success me-2">USER</span> v1.0.0 • Updated: <?php echo date('M j, Y'); ?>
                    </span>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="text-muted">
                        <a href="#" class="text-decoration-none text-muted me-3 hover-link">Help</a>
                        <a href="mailto:support@atiera.com" class="text-decoration-none text-muted hover-link"><i class="fas fa-envelope me-1"></i>Support</a>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

            // Initialize charts
            initializeCharts();
        });

        // Report period management
        function changeReportPeriod() {
            const period = document.getElementById('reportPeriod').value;
            const customRange = document.getElementById('customDateRange');

            if (period === 'custom') {
                customRange.style.display = 'inline-block';
            } else {
                customRange.style.display = 'none';
                generateReport();
            }
        }

        function applyCustomRange() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (!startDate || !endDate) {
                showAlert('Please select both start and end dates', 'warning');
                return;
            }

            generateReport(startDate, endDate);
        }

        // Generate report
        async function generateReport(startDate = null, endDate = null) {
            const period = document.getElementById('reportPeriod').value;

            if (!startDate || !endDate) {
                const dates = getDateRange(period);
                startDate = dates.start;
                endDate = dates.end;
            }

            try {
                // Show loading
                const tableBody = document.getElementById('transactionsTable');
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="loading mb-2"></div>Loading report data...</td></tr>';

                // Fetch report data
                const response = await fetch(`../admin/api/reports.php?type=user_summary&start_date=${startDate}&end_date=${endDate}&user_id=<?php echo $user_id; ?>`);
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                // Update the table with new data
                updateTransactionsTable(data.transactions || []);
                updateCharts(data);

                showAlert('Report generated successfully', 'success');

            } catch (error) {
                console.error('Error generating report:', error);
                showAlert('Error generating report: ' + error.message, 'danger');

                // Reset table
                document.getElementById('transactionsTable').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading data. Please try again.</td></tr>';
            }
        }

        // Update transactions table
        function updateTransactionsTable(transactions) {
            const tbody = document.getElementById('transactionsTable');

            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No transactions found for the selected period.</td></tr>';
                return;
            }

            tbody.innerHTML = transactions.map(transaction => `
                <tr>
                    <td>
                        <span class="badge ${transaction.type === 'invoice' ? 'bg-success' : 'bg-danger'}">
                            ${transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1)}
                        </span>
                    </td>
                    <td>${transaction.reference}</td>
                    <td>${transaction.related_party || 'N/A'}</td>
                    <td>₱${parseFloat(transaction.amount).toLocaleString()}</td>
                    <td>${new Date(transaction.date).toLocaleDateString()}</td>
                    <td>
                        <span class="badge ${getStatusBadgeClass(transaction.status)}">
                            ${transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1).replace('_', ' ')}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction('${transaction.type}', ${transaction.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Initialize charts
        function initializeCharts() {
            // Get chart data from PHP
            const transactionTrends = <?php echo json_encode($transaction_trends); ?>;
            const statusDistribution = <?php echo json_encode($status_distribution); ?>;

            // Transaction trends chart
            const transactionCtx = document.getElementById('transactionChart').getContext('2d');
            new Chart(transactionCtx, {
                type: 'line',
                data: {
                    labels: transactionTrends.map(item => item.month),
                    datasets: [{
                        label: 'Invoices',
                        data: transactionTrends.map(item => item.invoices),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Bills',
                        data: transactionTrends.map(item => item.bills),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            enabled: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Status distribution chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusDistribution.labels,
                    datasets: [{
                        data: statusDistribution.data,
                        backgroundColor: statusDistribution.colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            enabled: false
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Update charts with new data
        function updateCharts(data) {
            // This would update the charts with real data
        }

        // Export report
        function exportReport(format) {
            const period = document.getElementById('reportPeriod').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            let url = `../admin/api/reports.php?type=user_summary&format=${format}&user_id=<?php echo $user_id; ?>`;

            if (period === 'custom' && startDate && endDate) {
                url += `&start_date=${startDate}&end_date=${endDate}`;
            } else {
                const dates = getDateRange(period);
                url += `&start_date=${dates.start}&end_date=${dates.end}`;
            }

            // For PDF export, open in new window
            if (format === 'pdf') {
                window.open(url, '_blank');
            } else {
                // For CSV, trigger download
                const link = document.createElement('a');
                link.href = url;
                link.download = `user_report_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            showAlert(`Report exported as ${format.toUpperCase()}`, 'success');
        }

        // Generate specific reports
        function generateInvoiceReport() {
            showAlert('Invoice report generation coming soon', 'info');
        }

        function generateBillReport() {
            showAlert('Bill report generation coming soon', 'info');
        }

        function generateFinancialOverview() {
            showAlert('Financial overview generation coming soon', 'info');
        }

        // View transaction details
        function viewTransaction(type, id) {
            // Redirect to appropriate page or open modal
            if (type === 'invoice') {
                window.location.href = `../admin/accounts_receivable.php?action=view&id=${id}`;
            } else {
                window.location.href = `../admin/accounts_payable.php?action=view&id=${id}`;
            }
        }

        // Get date range based on period
        function getDateRange(period) {
            const now = new Date();
            let start, end;

            switch (period) {
                case 'last_30_days':
                    start = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                    end = now;
                    break;
                case 'last_3_months':
                    start = new Date(now.getFullYear(), now.getMonth() - 3, now.getDate());
                    end = now;
                    break;
                case 'last_6_months':
                    start = new Date(now.getFullYear(), now.getMonth() - 6, now.getDate());
                    end = now;
                    break;
                case 'year_to_date':
                    start = new Date(now.getFullYear(), 0, 1);
                    end = now;
                    break;
                default:
                    start = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                    end = now;
            }

            return {
                start: start.toISOString().split('T')[0],
                end: end.toISOString().split('T')[0]
            };
        }

        // Utility functions
        function getStatusBadgeClass(status) {
            const classes = {
                'paid': 'bg-success',
                'pending': 'bg-warning',
                'overdue': 'bg-danger',
                'draft': 'bg-secondary',
                'sent': 'bg-info',
                'approved': 'bg-primary'
            };
            return classes[status] || 'bg-secondary';
        }

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
</body>
</html>

<?php
function getStatusBadgeClass($status) {
    $classes = [
        'paid' => 'bg-success',
        'pending' => 'bg-warning',
        'overdue' => 'bg-danger',
        'draft' => 'bg-secondary',
        'sent' => 'bg-info',
        'approved' => 'bg-primary'
    ];
    return $classes[$status] ?? 'bg-secondary';
}
?>
