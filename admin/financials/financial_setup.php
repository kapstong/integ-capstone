<?php
/**
 * ATIERA FINANCIALS - Initial Financial Setup
 */

require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.edit');

$pageTitle = 'Financial Setup';
include '../legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-cogs"></i> Financial Setup Checklist</h2>
                <div class="action-buttons">
                    <a class="btn btn-outline-primary" href="departments.php">
                        <i class="fas fa-building"></i> Departments
                    </a>
                </div>
            </div>

            <div id="alertContainer"></div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-check"></i> Setup Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded p-4 h-100">
                                <h5><i class="fas fa-sitemap"></i> Default Departments</h5>
                                <p class="text-muted">Creates standard hotel/restaurant departments and cost centers.</p>
                                <button class="btn btn-success" onclick="runSetup('departments')">
                                    <i class="fas fa-play"></i> Generate Departments
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-4 h-100">
                                <h5><i class="fas fa-store"></i> Default Outlets</h5>
                                <p class="text-muted">Creates room, restaurant, bar, and banquet outlets for daily revenue.</p>
                                <button class="btn btn-success" onclick="runSetup('outlets')">
                                    <i class="fas fa-play"></i> Generate Outlets
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-4 h-100">
                                <h5><i class="fas fa-book"></i> Chart of Accounts</h5>
                                <p class="text-muted">Verify revenue and expense accounts for hotel and restaurant operations.</p>
                                <a class="btn btn-outline-primary" href="../general_ledger.php">
                                    <i class="fas fa-book-open"></i> Open Chart of Accounts
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-4 h-100">
                                <h5><i class="fas fa-plug"></i> Integrations</h5>
                                <p class="text-muted">Connect HR, logistics, and payroll sources for automatic expenses.</p>
                                <a class="btn btn-outline-primary" href="integration_management.php">
                                    <i class="fas fa-plug"></i> Manage Integrations
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function runSetup(type) {
    fetch('../api/financials/setup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: type })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert(result.message || 'Setup completed', 'success');
        } else {
            showAlert(result.error || 'Setup failed', 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function showAlert(message, type) {
    const alert = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alert;

    setTimeout(() => {
        document.querySelector('.alert')?.remove();
    }, 5000);
}
</script>

<?php include '../legacy_footer.php'; ?>
