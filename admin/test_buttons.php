<?php
require_once '../includes/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button Test - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-vial me-2"></i>Button Functionality Test</h2>
                        <p class="text-muted mb-0">Test all dashboard buttons and navigation links</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>Hero Section Buttons</h4>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" onclick="window.location.href='accounts_receivable.php'">
                                        <i class="fas fa-plus me-2"></i>Add Invoice (Should go to Accounts Receivable)
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="window.location.href='reports.php'">
                                        <i class="fas fa-chart-bar me-2"></i>View Reports (Should go to Reports)
                                    </button>
                                    <button class="btn btn-outline-success" onclick="showQuickActionsModal()">
                                        <i class="fas fa-cog me-2"></i>Quick Setup (Should open modal)
                                    </button>
                                </div>

                                <h4 class="mt-4">Reports Tab Buttons</h4>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="accounts_receivable.php" class="btn btn-outline-primary btn-sm w-100">
                                            <i class="fas fa-money-bill-wave me-1"></i>Receivable
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="accounts_payable.php" class="btn btn-outline-success btn-sm w-100">
                                            <i class="fas fa-credit-card me-1"></i>Payable
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="disbursements.php" class="btn btn-outline-info btn-sm w-100">
                                            <i class="fas fa-money-check me-1"></i>Disbursements
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="budget_management.php" class="btn btn-outline-warning btn-sm w-100">
                                            <i class="fas fa-chart-line me-1"></i>Budget
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="reports.php" class="btn btn-outline-secondary btn-sm w-100">
                                            <i class="fas fa-chart-bar me-1"></i>Reports
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="general_ledger.php" class="btn btn-outline-dark btn-sm w-100">
                                            <i class="fas fa-book me-1"></i>Ledger
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h4>Navigation Menu Links</h4>
                                <div class="list-group">
                                    <a href="index.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard (Current Page)
                                    </a>
                                    <a href="general_ledger.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-book me-2"></i>General Ledger
                                    </a>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-credit-card me-2"></i>Accounts Payable/Payable
                                        </div>
                                        <div class="ms-3">
                                            <a href="accounts_payable.php" class="btn btn-sm btn-outline-success me-1">AP</a>
                                            <a href="accounts_receivable.php" class="btn btn-sm btn-outline-primary">AR</a>
                                        </div>
                                    </div>
                                    <a href="disbursements.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-money-check me-2"></i>Disbursements
                                    </a>
                                    <a href="budget_management.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-line me-2"></i>Budget Management
                                    </a>
                                    <a href="reports.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-bar me-2"></i>Reports
                                    </a>
                                    <a href="index.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-chart-pie me-2"></i>Analytics (Same as Dashboard)
                                    </a>
                                </div>

                                <h4 class="mt-4">Quick Actions Modal Test</h4>
                                <p>The Quick Setup button should open a modal with setup options. Test it by clicking the button in the hero section above.</p>

                                <div class="alert alert-success mt-4">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>All buttons should be functional!</strong>
                                    <p class="mb-0">If any button doesn't work, there may be an issue with the target file or path.</p>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-link me-2"></i>Page Status Check</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li><i id="check-index" class="fas fa-spinner fa-spin me-2"></i>Dashboard (index.php)</li>
                                            <li><i id="check-ledger" class="fas fa-spinner fa-spin me-2"></i>General Ledger (general_ledger.php)</li>
                                            <li><i id="check-ap" class="fas fa-spinner fa-spin me-2"></i>Accounts Payable (accounts_payable.php)</li>
                                            <li><i id="check-ar" class="fas fa-spinner fa-spin me-2"></i>Accounts Receivable (accounts_receivable.php)</li>
                                            <li><i id="check-disbursements" class="fas fa-spinner fa-spin me-2"></i>Disbursements (disbursements.php)</li>
                                            <li><i id="check-budget" class="fas fa-spinner fa-spin me-2"></i>Budget Management (budget_management.php)</li>
                                            <li><i id="check-reports" class="fas fa-spinner fa-spin me-2"></i>Reports (reports.php)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Modal (same as main dashboard) -->
    <div class="modal fade" id="quickActionsModal" tabindex="-1" aria-labelledby="quickActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickActionsModalLabel">
                        <i class="fas fa-cog me-2"></i>Quick Setup Actions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                    <h6>Add Sample Data</h6>
                                    <p class="text-muted small">Create sample customers, vendors, and initial transactions</p>
                                    <button class="btn btn-primary btn-sm" onclick="addSampleData()">Add Sample Data</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                    <h6>Configure Budgets</h6>
                                    <p class="text-muted small">Set up annual budgets for departments</p>
                                    <button class="btn btn-success btn-sm" onclick="configureBudgets()">Setup Budgets</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-info h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-key fa-3x text-info mb-3"></i>
                                    <h6>Generate API Keys</h6>
                                    <p class="text-muted small">Create API credentials for integrations</p>
                                    <button class="btn btn-info btn-sm" onclick="generateAPIKeys()">Create API Key</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-warning h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope fa-3x text-warning mb-3"></i>
                                    <h6>Email Configuration</h6>
                                    <p class="text-muted small">Configure email settings for notifications</p>
                                    <button class="btn btn-warning btn-sm" onclick="configureEmail()">Configure Email</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="runAllQuickSetup()">Run All Setup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showQuickActionsModal() {
            const modal = new bootstrap.Modal(document.getElementById('quickActionsModal'));
            modal.show();
        }

        function addSampleData() {
            alert('Sample data functionality will be implemented in a future update.');
        }

        function configureBudgets() {
            window.location.href = 'budget_management.php';
        }

        function generateAPIKeys() {
            window.location.href = 'api_clients.php';
        }

        function configureEmail() {
            window.location.href = 'settings.php';
        }

        function runAllQuickSetup() {
            alert('Complete quick setup will be implemented in a future update.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('quickActionsModal'));
            modal.hide();
        }

        // Test page existence (simple XMLHttpRequest check)
        document.addEventListener('DOMContentLoaded', function() {
            const pages = [
                { id: 'check-index', url: 'index.php', name: 'index.php' },
                { id: 'check-ledger', url: 'general_ledger.php', name: 'general_ledger.php' },
                { id: 'check-ap', url: 'accounts_payable.php', name: 'accounts_payable.php' },
                { id: 'check-ar', url: 'accounts_receivable.php', name: 'accounts_receivable.php' },
                { id: 'check-disbursements', url: 'disbursements.php', name: 'disbursements.php' },
                { id: 'check-budget', url: 'budget_management.php', name: 'budget_management.php' },
                { id: 'check-reports', url: 'reports.php', name: 'reports.php' }
            ];

            pages.forEach(page => {
                const icon = document.getElementById(page.id);
                // Just check if the page returns 200 (without full authentication check)
                fetch(page.url, { method: 'HEAD' })
                    .then(response => {
                        if (response.ok) {
                            icon.className = 'fas fa-check-circle text-success me-2';
                        } else {
                            icon.className = 'fas fa-times-circle text-danger me-2';
                        }
                    })
                    .catch(() => {
                        icon.className = 'fas fa-question-circle text-warning me-2';
                    });
            });
        });
    </script>
</body>
</html>
