<?php
/**
 * ATIERA FINANCIALS - Integration Management (Financial Data Sources)
 */

require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.edit');

$pageTitle = 'Financial Integrations';
include '../legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-exchange-alt"></i> Financial Integrations</h2>
            </div>

            <div id="alertContainer"></div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plug"></i> Connected Systems</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-mobile-stack" id="integrationsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Integration</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Configured</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const syncActions = {
    hr4: { action: 'importPayroll', label: 'Sync HR4 Payroll' },
    logistics1: { action: 'importInvoices', label: 'Sync Logistics1 Invoices' },
    logistics2: { action: 'importTripCosts', label: 'Sync Logistics2 Trips' }
};

document.addEventListener('DOMContentLoaded', function() {
    loadIntegrations();
});

function loadIntegrations() {
    fetch('../api/integrations.php?action=list_integrations')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderIntegrations(data.integrations);
                autoSyncIntegrations(data.integrations);
            } else {
                showAlert('Error loading integrations: ' + data.error, 'danger');
            }
        })
        .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function renderIntegrations(integrations) {
    const tbody = document.querySelector('#integrationsTable tbody');
    if (!integrations || integrations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No integrations found</td></tr>';
        return;
    }

    tbody.innerHTML = integrations.map(item => `
        <tr>
            <td data-label="Integration"><strong>${escapeHtml(item.display_name)}</strong></td>
            <td data-label="Description">${escapeHtml(item.description || '')}</td>
            <td data-label="Status">
                ${item.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}
            </td>
            <td data-label="Configured">
                ${item.is_configured ? '<span class="badge bg-info">Configured</span>' : '<span class="badge bg-warning">Not Configured</span>'}
            </td>
            <td data-label="Actions">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary" onclick="testIntegration('${item.name}')">
                        <i class="fas fa-vial"></i> Test
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function testIntegration(name) {
    const formData = new FormData();
    formData.append('action', 'test');
    formData.append('integration_name', name);

    fetch('../api/integrations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert(result.message || 'Connection successful', 'success');
        } else {
            showAlert(result.error || result.message || 'Connection failed', 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function syncIntegration(name) {
    const actionConfig = syncActions[name];
    if (!actionConfig) return;

    const formData = new FormData();
    formData.append('action', 'execute');
    formData.append('integration_name', name);
    formData.append('action_name', actionConfig.action);

    fetch('../api/integrations.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert(`${actionConfig.label} completed`, 'success');
        } else {
            showAlert(result.error || 'Sync failed', 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function autoSyncIntegrations(integrations) {
    integrations
        .filter(item => item.is_active && item.is_configured && syncActions[item.name])
        .forEach(item => syncIntegration(item.name));
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

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '';
}
</script>

<?php include '../legacy_footer.php'; ?>
