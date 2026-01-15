<?php
/**
 * ATIERA FINANCIALS - Department Management
 * Manage financial departments and revenue centers
 */

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('departments.view');

$db = Database::getInstance()->getConnection();

// Get all GL accounts for dropdowns
$stmt = $db->query("
    SELECT id, account_code, account_name, account_type
    FROM chart_of_accounts
    WHERE is_active = 1
    ORDER BY account_code
");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get revenue accounts
$revenueAccounts = array_filter($accounts, function($acc) {
    return $acc['account_type'] === 'revenue';
});

// Get expense accounts
$expenseAccounts = array_filter($accounts, function($acc) {
    return $acc['account_type'] === 'expense';
});

$pageTitle = 'Department Management';
include '../legacy_header.php';
?>

<link rel="stylesheet" href="/responsive.css">

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-building"></i> Department Management</h2>
                <?php if ($auth->hasPermission('departments.manage')): ?>
                <div class="action-buttons">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                        <i class="fas fa-plus"></i> <span class="btn-text-mobile-hide">Add Department</span>
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRevenueCenterModal">
                        <i class="fas fa-plus-circle"></i> <span class="btn-text-mobile-hide">Add Revenue Center</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Alert Messages -->
            <div id="alertContainer"></div>

            <!-- Departments Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Financial Departments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-mobile-stack" id="departmentsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Code</th>
                                    <th>Department Name</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th class="d-mobile-none">Revenue Centers</th>
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

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDepartmentForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="dept_code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="dept_code" name="dept_code" required>
                            <small class="text-muted">e.g., ROOMS, FB-REST, ADMIN</small>
                        </div>
                        <div class="col-md-6">
                            <label for="dept_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="dept_name" name="dept_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="dept_type" class="form-label">Department Type *</label>
                            <select class="form-control" id="dept_type" name="dept_type" required>
                                <option value="">Select Type</option>
                                <option value="revenue_center">Revenue Center</option>
                                <option value="cost_center">Cost Center</option>
                                <option value="support">Support</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="rooms">Rooms</option>
                                <option value="food_beverage">Food & Beverage</option>
                                <option value="events">Events</option>
                                <option value="spa">Spa & Wellness</option>
                                <option value="other_revenue">Other Revenue</option>
                                <option value="admin">Administration</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="marketing">Marketing</option>
                                <option value="other_expense">Other Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="revenue_account_id" class="form-label">Default Revenue Account</label>
                            <select class="form-control" id="revenue_account_id" name="revenue_account_id">
                                <option value="">None</option>
                                <?php foreach ($revenueAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="expense_account_id" class="form-label">Default Expense Account</label>
                            <select class="form-control" id="expense_account_id" name="expense_account_id">
                                <option value="">None</option>
                                <?php foreach ($expenseAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Revenue Center Modal -->
<div class="modal fade" id="addRevenueCenterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Revenue Center</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRevenueCenterForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="rc_department_id" class="form-label">Department *</label>
                            <select class="form-control" id="rc_department_id" name="department_id" required>
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="center_code" class="form-label">Center Code *</label>
                            <input type="text" class="form-control" id="center_code" name="center_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="center_name" class="form-label">Center Name *</label>
                            <input type="text" class="form-control" id="center_name" name="center_name" required>
                        </div>
                        <div class="col-md-12">
                            <label for="rc_revenue_account_id" class="form-label">Revenue Account *</label>
                            <select class="form-control" id="rc_revenue_account_id" name="revenue_account_id" required>
                                <option value="">Select Account</option>
                                <?php foreach ($revenueAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="rc_description" class="form-label">Description</label>
                            <textarea class="form-control" id="rc_description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Revenue Center
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDepartmentForm">
                <input type="hidden" id="edit_dept_id" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Department Code</label>
                            <input type="text" class="form-control" id="edit_dept_code" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_dept_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="edit_dept_name" name="dept_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_revenue_account_id" class="form-label">Default Revenue Account</label>
                            <select class="form-control" id="edit_revenue_account_id" name="revenue_account_id">
                                <option value="">None</option>
                                <?php foreach ($revenueAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_expense_account_id" class="form-label">Default Expense Account</label>
                            <select class="form-control" id="edit_expense_account_id" name="expense_account_id">
                                <option value="">None</option>
                                <?php foreach ($expenseAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
                        <i class="fas fa-save"></i> Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Revenue Centers Modal -->
<div class="modal fade" id="viewRevenueCentersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-line"></i> Revenue Centers - <span id="rc_dept_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Revenue Account</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="revenueCentersTableBody">
                            <tr>
                                <td colspan="4" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let departments = [];

// Load departments on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDepartments();
    loadDepartmentsForDropdown();
});

// Load all departments
function loadDepartments() {
    fetch('../api/financials/departments.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                departments = data.departments;
                renderDepartmentsTable();
            } else {
                showAlert('Error loading departments: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'danger');
        });
}

// Render departments table
function renderDepartmentsTable() {
    const tbody = document.querySelector('#departmentsTable tbody');
    if (departments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No departments found</td></tr>';
        return;
    }

    tbody.innerHTML = departments.map(dept => `
        <tr>
            <td data-label="Code">${escapeHtml(dept.dept_code)}</td>
            <td data-label="Department">${escapeHtml(dept.dept_name)}</td>
            <td data-label="Type">${formatDeptType(dept.dept_type)}</td>
            <td data-label="Category">${formatCategory(dept.category)}</td>
            <td data-label="Revenue Centers" class="d-mobile-none">
                ${dept.revenue_center_count > 0 ?
                    `<a href="#" onclick="viewRevenueCenters(${dept.id}, '${escapeHtml(dept.dept_name)}'); return false;">
                        ${dept.revenue_center_count} center(s)
                    </a>` :
                    '<span class="text-muted">None</span>'}
            </td>
            <td data-label="Status" class="d-mobile-none">
                ${dept.is_active == 1 ?
                    '<span class="badge bg-success">Active</span>' :
                    '<span class="badge bg-secondary">Inactive</span>'}
            </td>
            <td data-label="Actions">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-info" onclick="viewDepartment(${dept.id})" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($auth->hasPermission('departments.manage')): ?>
                    <button class="btn btn-warning" onclick="editDepartment(${dept.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    `).join('');
}

// Load departments for dropdown
function loadDepartmentsForDropdown() {
    fetch('../api/financials/departments.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('rc_department_id');
                select.innerHTML = '<option value="">Select Department</option>' +
                    data.departments
                        .filter(d => d.is_active == 1 && d.dept_type === 'revenue_center')
                        .map(d => `<option value="${d.id}">${escapeHtml(d.dept_code)} - ${escapeHtml(d.dept_name)}</option>`)
                        .join('');
            }
        });
}

// Add department form submission
document.getElementById('addDepartmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'create';

    fetch('../api/financials/departments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Department created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addDepartmentModal')).hide();
            this.reset();
            loadDepartments();
        } else {
            showAlert('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

// Add revenue center form submission
document.getElementById('addRevenueCenterForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'create_revenue_center';

    fetch('../api/financials/departments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Revenue center created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addRevenueCenterModal')).hide();
            this.reset();
            loadDepartments();
        } else {
            showAlert('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

// Edit department
function editDepartment(id) {
    const dept = departments.find(d => d.id == id);
    if (!dept) return;

    document.getElementById('edit_dept_id').value = dept.id;
    document.getElementById('edit_dept_code').value = dept.dept_code;
    document.getElementById('edit_dept_name').value = dept.dept_name;
    document.getElementById('edit_revenue_account_id').value = dept.revenue_account_id || '';
    document.getElementById('edit_expense_account_id').value = dept.expense_account_id || '';
    document.getElementById('edit_description').value = dept.description || '';
    document.getElementById('edit_is_active').checked = dept.is_active == 1;

    new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
}

// Update department form submission
document.getElementById('editDepartmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.is_active = document.getElementById('edit_is_active').checked ? 1 : 0;

    fetch('../api/financials/departments.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Department updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editDepartmentModal')).hide();
            loadDepartments();
        } else {
            showAlert('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

// View revenue centers
function viewRevenueCenters(deptId, deptName) {
    document.getElementById('rc_dept_name').textContent = deptName;

    fetch(`../api/financials/departments.php?action=revenue_centers&dept_id=${deptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('revenueCentersTableBody');
                if (data.revenue_centers.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No revenue centers found</td></tr>';
                } else {
                    tbody.innerHTML = data.revenue_centers.map(rc => `
                        <tr>
                            <td>${escapeHtml(rc.center_code)}</td>
                            <td>${escapeHtml(rc.center_name)}</td>
                            <td>${escapeHtml(rc.account_code)} - ${escapeHtml(rc.account_name)}</td>
                            <td>${rc.is_active == 1 ?
                                '<span class="badge bg-success">Active</span>' :
                                '<span class="badge bg-secondary">Inactive</span>'}
                            </td>
                        </tr>
                    `).join('');
                }
                new bootstrap.Modal(document.getElementById('viewRevenueCentersModal')).show();
            }
        });
}

// Helper functions
function formatDeptType(type) {
    const types = {
        'revenue_center': 'Revenue Center',
        'cost_center': 'Cost Center',
        'support': 'Support'
    };
    return types[type] || type;
}

function formatCategory(category) {
    return category.split('_').map(word =>
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
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

function viewDepartment(id) {
    // For now, just edit. Could add a read-only view later
    editDepartment(id);
}
</script>

<?php include '../legacy_footer.php'; ?>
