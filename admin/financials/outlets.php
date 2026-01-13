<?php
/**
 * ATIERA FINANCIALS - Outlet Management
 * Manage hotel/restaurant outlets used for revenue tracking
 */

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('departments.view');

$db = Database::getInstance()->getConnection();

// GL revenue accounts for outlet mapping
$stmt = $db->query("
    SELECT id, account_code, account_name
    FROM chart_of_accounts
    WHERE account_type = 'revenue' AND is_active = 1
    ORDER BY account_code
");
$revenueAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Outlet Management';
include '../header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-store"></i> Outlet Management</h2>
                <?php if ($auth->hasPermission('departments.manage')): ?>
                <div class="action-buttons">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOutletModal">
                        <i class="fas fa-plus"></i> <span class="btn-text-mobile-hide">Add Outlet</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div id="alertContainer"></div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Hotel & Restaurant Outlets</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-mobile-stack" id="outletsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Code</th>
                                    <th>Outlet Name</th>
                                    <th>Type</th>
                                    <th>Department</th>
                                    <th class="d-mobile-none">Revenue Center</th>
                                    <th class="d-mobile-none">Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">
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

<!-- Add Outlet Modal -->
<div class="modal fade" id="addOutletModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Outlet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addOutletForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="outlet_code" class="form-label">Outlet Code *</label>
                            <input type="text" class="form-control" id="outlet_code" name="outlet_code" required>
                            <small class="text-muted">e.g., ROOMS, RESTO, BAR01</small>
                        </div>
                        <div class="col-md-8">
                            <label for="outlet_name" class="form-label">Outlet Name *</label>
                            <input type="text" class="form-control" id="outlet_name" name="outlet_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="outlet_type" class="form-label">Outlet Type *</label>
                            <select class="form-control" id="outlet_type" name="outlet_type" required>
                                <option value="">Select Type</option>
                                <option value="rooms">Rooms</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="bar">Bar</option>
                                <option value="banquet">Banquet/Events</option>
                                <option value="spa">Spa/Wellness</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-control" id="department_id" name="department_id">
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="revenue_center_id" class="form-label">Revenue Center</label>
                            <select class="form-control" id="revenue_center_id" name="revenue_center_id">
                                <option value="">Select Revenue Center</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="revenue_account_id" class="form-label">Revenue Account</label>
                            <select class="form-control" id="revenue_account_id" name="revenue_account_id">
                                <option value="">Select Account</option>
                                <?php foreach ($revenueAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Outlet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Outlet Modal -->
<div class="modal fade" id="editOutletModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Outlet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editOutletForm">
                <input type="hidden" id="edit_outlet_id" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Outlet Code</label>
                            <input type="text" class="form-control" id="edit_outlet_code" disabled>
                        </div>
                        <div class="col-md-8">
                            <label for="edit_outlet_name" class="form-label">Outlet Name *</label>
                            <input type="text" class="form-control" id="edit_outlet_name" name="outlet_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_outlet_type" class="form-label">Outlet Type *</label>
                            <select class="form-control" id="edit_outlet_type" name="outlet_type" required>
                                <option value="rooms">Rooms</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="bar">Bar</option>
                                <option value="banquet">Banquet/Events</option>
                                <option value="spa">Spa/Wellness</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_department_id" class="form-label">Department</label>
                            <select class="form-control" id="edit_department_id" name="department_id"></select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_revenue_center_id" class="form-label">Revenue Center</label>
                            <select class="form-control" id="edit_revenue_center_id" name="revenue_center_id"></select>
                        </div>
                        <div class="col-md-12">
                            <label for="edit_revenue_account_id" class="form-label">Revenue Account</label>
                            <select class="form-control" id="edit_revenue_account_id" name="revenue_account_id">
                                <option value="">Select Account</option>
                                <?php foreach ($revenueAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                                <label class="form-check-label" for="edit_is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Outlet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let outlets = [];
let departments = [];
let revenueCenters = [];

document.addEventListener('DOMContentLoaded', function() {
    loadOutlets();
    loadDepartments();
});

function loadOutlets() {
    fetch('../api/financials/outlets.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                outlets = data.outlets;
                renderOutletsTable();
            } else {
                showAlert('Error loading outlets: ' + data.error, 'danger');
            }
        })
        .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function loadDepartments() {
    fetch('../api/financials/departments.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                departments = data.departments;
                revenueCenters = data.departments
                    .filter(d => d.dept_type === 'revenue_center');
                populateDepartmentSelects();
                loadRevenueCenters();
            }
        });
}

function loadRevenueCenters() {
    fetch('../api/financials/outlets.php?action=revenue_centers')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                revenueCenters = data.revenue_centers;
                populateRevenueCenterSelects();
            }
        });
}

function populateDepartmentSelects() {
    const selects = [document.getElementById('department_id'), document.getElementById('edit_department_id')];
    selects.forEach(select => {
        if (!select) return;
        select.innerHTML = '<option value="">Select Department</option>' +
            departments.map(d => `<option value="${d.id}">${escapeHtml(d.dept_code)} - ${escapeHtml(d.dept_name)}</option>`).join('');
    });
}

function populateRevenueCenterSelects() {
    const selects = [document.getElementById('revenue_center_id'), document.getElementById('edit_revenue_center_id')];
    selects.forEach(select => {
        if (!select) return;
        select.innerHTML = '<option value="">Select Revenue Center</option>' +
            revenueCenters.map(rc => `<option value="${rc.id}">${escapeHtml(rc.center_code)} - ${escapeHtml(rc.center_name)}</option>`).join('');
    });
}

function renderOutletsTable() {
    const tbody = document.querySelector('#outletsTable tbody');
    if (outlets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No outlets found</td></tr>';
        return;
    }

    tbody.innerHTML = outlets.map(outlet => `
        <tr>
            <td data-label="Code">${escapeHtml(outlet.outlet_code)}</td>
            <td data-label="Outlet">${escapeHtml(outlet.outlet_name)}</td>
            <td data-label="Type">${formatOutletType(outlet.outlet_type)}</td>
            <td data-label="Department">${escapeHtml(outlet.department_name || 'Unassigned')}</td>
            <td data-label="Revenue Center" class="d-mobile-none">${escapeHtml(outlet.revenue_center_name || 'Unassigned')}</td>
            <td data-label="Status" class="d-mobile-none">
                ${outlet.is_active == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}
            </td>
            <td data-label="Actions">
                <div class="btn-group btn-group-sm" role="group">
                    <?php if ($auth->hasPermission('departments.manage')): ?>
                    <button class="btn btn-warning" onclick="editOutlet(${outlet.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    `).join('');
}

document.getElementById('addOutletForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'create';

    fetch('../api/financials/outlets.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Outlet created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addOutletModal')).hide();
            this.reset();
            loadOutlets();
        } else {
            showAlert('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

document.getElementById('editOutletForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.is_active = document.getElementById('edit_is_active').checked ? 1 : 0;

    fetch('../api/financials/outlets.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Outlet updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editOutletModal')).hide();
            loadOutlets();
        } else {
            showAlert('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

function editOutlet(id) {
    const outlet = outlets.find(o => o.id == id);
    if (!outlet) return;

    document.getElementById('edit_outlet_id').value = outlet.id;
    document.getElementById('edit_outlet_code').value = outlet.outlet_code;
    document.getElementById('edit_outlet_name').value = outlet.outlet_name;
    document.getElementById('edit_outlet_type').value = outlet.outlet_type;
    document.getElementById('edit_department_id').value = outlet.department_id || '';
    document.getElementById('edit_revenue_center_id').value = outlet.revenue_center_id || '';
    document.getElementById('edit_revenue_account_id').value = outlet.revenue_account_id || '';
    document.getElementById('edit_is_active').checked = outlet.is_active == 1;

    new bootstrap.Modal(document.getElementById('editOutletModal')).show();
}

function formatOutletType(type) {
    const map = {
        rooms: 'Rooms',
        restaurant: 'Restaurant',
        bar: 'Bar',
        banquet: 'Banquet/Events',
        spa: 'Spa/Wellness',
        other: 'Other'
    };
    return map[type] || type;
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
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '';
}
</script>

<?php include '../footer.php'; ?>
