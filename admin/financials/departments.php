<?php
/**
 * ATIERA FINANCIALS - Departments
 * Static view of integrated departments and core systems.
 */

require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('departments.view');

$pageTitle = 'Departments';
include '../legacy_header.php';

$departments = [
    [
        'name' => 'Human Resource 3',
        'scope' => 'Workforce Operations & Time Management',
        'modules' => [
            'Claims and Reimbursement'
        ],
        'integration_key' => 'hr3',
        'integrated' => true
    ],
    [
        'name' => 'Human Resource 4',
        'scope' => 'Compensation & HR Intelligence',
        'modules' => [
            'Payroll Management'
        ],
        'integration_key' => 'hr4',
        'integrated' => true
    ],
    [
        'name' => 'Logistics 1',
        'scope' => 'Smart Supply Chain & Procurement Management',
        'modules' => [
            'Procurement & Sourcing Management (PSM)',
            'Document Tracking & Logistics Records (DTRS)'
        ],
        'integration_key' => 'logistics1',
        'integrated' => true
    ],
    [
        'name' => 'Logistics 2',
        'scope' => 'Fleet and Transportation Operations',
        'modules' => [
            'Driver and Trip Performance Monitoring',
            'Transport Cost Analysis & Optimization (TCAO)'
        ],
        'integration_key' => 'logistics2',
        'integrated' => true
    ],
    [
        'name' => 'Core 1 - Hotel',
        'scope' => 'Hotel Operations',
        'modules' => [
            'Billing and Payment Module',
            'Point of Sale (POS) Module',
            'Inventory and Stock Management Module',
            'Reservation and Booking Module',
            'Analytics and Reporting Module'
        ],
        'integration_key' => null,
        'integrated' => false
    ],
    [
        'name' => 'Core 2 - Restaurant',
        'scope' => 'Restaurant Operations',
        'modules' => [
            'Billing and Payment Module',
            'Order Taking and POS Module',
            'Inventory and Stock Management Module',
            'Analytics and Reporting Module',
            'Integration with Payment Gateways Module'
        ],
        'integration_key' => null,
        'integrated' => false
    ]
];
?>

<link rel="stylesheet" href="../../responsive.css">

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-building"></i> Departments</h2>
            </div>

            <div id="alertContainer"></div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sitemap"></i> Integrated Departments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-mobile-stack">
                            <thead class="table-dark">
                                <tr>
                                    <th>Department</th>
                                    <th>Scope</th>
                                    <th>Modules</th>
                                    <th>API Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td data-label="Department"><strong><?php echo htmlspecialchars($department['name']); ?></strong></td>
                                    <td data-label="Scope"><?php echo htmlspecialchars($department['scope']); ?></td>
                                    <td data-label="Modules">
                                        <?php echo htmlspecialchars(implode(', ', $department['modules'])); ?>
                                    </td>
                                    <td data-label="API Status">
                                        <?php if ($department['integration_key']): ?>
                                            <span class="badge bg-secondary" id="status-<?php echo htmlspecialchars($department['integration_key']); ?>">Checking...</span>
                                            <div class="text-muted small mt-1" id="status-detail-<?php echo htmlspecialchars($department['integration_key']); ?>"></div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Integrated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <?php if ($department['integration_key']): ?>
                                            <button class="btn btn-outline-primary btn-sm" onclick="testIntegration('<?php echo $department['integration_key']; ?>')">
                                                <i class="fas fa-vial"></i> Test
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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

function checkIntegrationStatus(name) {
    const badge = document.getElementById(`status-${name}`);
    const detail = document.getElementById(`status-detail-${name}`);
    if (!badge) return;

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
            badge.className = 'badge bg-success';
            badge.textContent = 'Working';
            if (detail) {
                detail.textContent = '';
            }
        } else {
            badge.className = 'badge bg-danger';
            badge.textContent = 'Failed';
            if (detail) {
                detail.textContent = result.error || result.message || 'No error details returned.';
            }
        }
    })
    .catch(() => {
        badge.className = 'badge bg-danger';
        badge.textContent = 'Failed';
        if (detail) {
            detail.textContent = 'Request failed. Check API URL, network, or server response.';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    ['hr3', 'hr4', 'logistics1', 'logistics2'].forEach(checkIntegrationStatus);
});

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
</script>

<?php include '../legacy_footer.php'; ?>
