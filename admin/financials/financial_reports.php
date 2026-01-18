<?php
/**
 * ATIERA FINANCIALS - Hotel & Restaurant Financial Reports
 */

require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('reports.view');

$pageTitle = 'Financial Reports';
include '../../includes/admin_navigation.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-file-invoice-dollar"></i> Financial Reports</h2>
                <div class="action-buttons">
                    <a class="btn btn-primary" href="../reports.php">
                        <i class="fas fa-chart-bar"></i> <span class="btn-text-mobile-hide">Open Reporting Suite</span>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-day"></i> Daily Revenue</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Outlet sales summary by business date. Includes rooms, restaurant, bar, and events.</p>
                            <a class="btn btn-outline-primary" href="daily_revenue.php">
                                <i class="fas fa-receipt"></i> Open Daily Revenue
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line"></i> Profit & Loss</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Income statement built from revenue centers and departmental expenses.</p>
                            <a class="btn btn-outline-primary" href="../reports.php#income">
                                <i class="fas fa-file-alt"></i> View P&L
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5><i class="fas fa-wallet"></i> Cash Flow</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Operating, investing, and financing cash flow overview.</p>
                            <a class="btn btn-outline-primary" href="../reports.php#cashflow">
                                <i class="fas fa-water"></i> View Cash Flow
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5><i class="fas fa-balance-scale"></i> Balance Sheet</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Assets, liabilities, and equity snapshots for management.</p>
                            <a class="btn btn-outline-primary" href="../reports.php#balance">
                                <i class="fas fa-scale-balanced"></i> View Balance Sheet
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie"></i> Budget vs Actual</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Departmental budget tracking against actual revenue and expenses.</p>
                            <a class="btn btn-outline-primary" href="../reports.php#analytics">
                                <i class="fas fa-bullseye"></i> View Budget Report
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5><i class="fas fa-money-check-alt"></i> AR/AP Aging</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Guest receivables and supplier payables aging schedules.</p>
                            <a class="btn btn-outline-primary" href="../reports.php#analytics">
                                <i class="fas fa-hourglass-half"></i> View Aging
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../legacy_footer.php'; ?>
