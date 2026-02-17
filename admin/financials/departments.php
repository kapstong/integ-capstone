<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments</title>
    <link rel="icon" type="image/png" href="../../logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/admin_navigation.php'; ?>

    <div class="content">
        <?php include '../../includes/global_navbar.php'; ?>

        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="mb-1">Departments</h3>
                    <p class="text-muted mb-0">Manage financial departments and cost/revenue centers.</p>
                </div>
                <button class="btn btn-primary" onclick="openDepartmentModal()">
                    <i class="fas fa-plus me-2"></i>Add Department
                </button>
            </div>

            <div id="deptAlert"></div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="deptTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deptModalTitle">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="deptForm">
                        <input type="hidden" id="dept_id" name="id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Code</label>
                                <input type="text" class="form-control" id="dept_code" name="dept_code" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" id="dept_name" name="dept_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Type</label>
                                <select class="form-select" id="dept_type" name="dept_type" required>
                                    <option value="">Select type</option>
                                    <option value="revenue_center">Revenue Center</option>
                                    <option value="cost_center">Cost Center</option>
                                    <option value="support">Support</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Revenue Account</label>
                                <select class="form-select" id="revenue_account_id" name="revenue_account_id" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expense Account</label>
                                <select class="form-select" id="expense_account_id" name="expense_account_id" required></select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Parent Department</label>
                                <select class="form-select" id="parent_dept_id" name="parent_dept_id"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="is_active" name="is_active">
                                    <option value="1" selected>Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveDepartment()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deptApi = '../../api/financials/departments.php';
        const coaApi = '../../api/chart_of_accounts.php';
        let departments = [];
        let accounts = [];

        function showAlert(message, type = 'info') {
            const el = document.getElementById('deptAlert');
            el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        }

        async function loadAccounts() {
            const res = await fetch(coaApi);
            const data = await res.json();
            accounts = data?.accounts || data || [];
            const options = accounts.map(a => `<option value="${a.id}">${a.account_code} - ${a.account_name}</option>`).join('');
            document.getElementById('revenue_account_id').innerHTML = '<option value="">Select</option>' + options;
            document.getElementById('expense_account_id').innerHTML = '<option value="">Select</option>' + options;
        }

        async function loadDepartments() {
            const res = await fetch(`${deptApi}?action=list`);
            const data = await res.json();
            if (!data.success) {
                showAlert(data.error || 'Failed to load departments', 'danger');
                return;
            }
            departments = data.departments || [];
            renderDepartments();
            fillParentOptions();
        }

        function renderDepartments() {
            const tbody = document.getElementById('deptTableBody');
            if (!departments.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No departments found.</td></tr>';
                return;
            }
            tbody.innerHTML = departments.map(d => `
                <tr>
                    <td>${d.dept_code}</td>
                    <td>${d.dept_name}</td>
                    <td>${d.dept_type}</td>
                    <td>${d.category}</td>
                    <td><span class="badge ${d.is_active == 1 ? 'bg-success' : 'bg-secondary'}">${d.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="openDepartmentModal(${d.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deactivateDepartment(${d.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }

        function fillParentOptions() {
            const select = document.getElementById('parent_dept_id');
            const options = departments.map(d => `<option value="${d.id}">${d.dept_code} - ${d.dept_name}</option>`).join('');
            select.innerHTML = '<option value="">None</option>' + options;
        }

        function openDepartmentModal(id = null) {
            const modal = new bootstrap.Modal(document.getElementById('deptModal'));
            document.getElementById('deptForm').reset();
            document.getElementById('dept_id').value = '';
            document.getElementById('dept_code').readOnly = false;
            document.getElementById('deptModalTitle').textContent = id ? 'Edit Department' : 'Add Department';

            if (id) {
                const dept = departments.find(d => String(d.id) === String(id));
                if (!dept) return;
                document.getElementById('dept_id').value = dept.id;
                document.getElementById('dept_code').value = dept.dept_code;
                document.getElementById('dept_code').readOnly = true;
                document.getElementById('dept_name').value = dept.dept_name || '';
                document.getElementById('dept_type').value = dept.dept_type || '';
                document.getElementById('category').value = dept.category || '';
                document.getElementById('description').value = dept.description || '';
                document.getElementById('parent_dept_id').value = dept.parent_dept_id || '';
                document.getElementById('revenue_account_id').value = dept.revenue_account_id || '';
                document.getElementById('expense_account_id').value = dept.expense_account_id || '';
                document.getElementById('is_active').value = dept.is_active == 1 ? '1' : '0';
            }
            modal.show();
        }

        async function saveDepartment() {
            const id = document.getElementById('dept_id').value;
            const payload = {
                dept_code: document.getElementById('dept_code').value.trim(),
                dept_name: document.getElementById('dept_name').value.trim(),
                dept_type: document.getElementById('dept_type').value,
                category: document.getElementById('category').value.trim(),
                description: document.getElementById('description').value.trim(),
                parent_dept_id: document.getElementById('parent_dept_id').value || null,
                revenue_account_id: document.getElementById('revenue_account_id').value,
                expense_account_id: document.getElementById('expense_account_id').value,
                is_active: document.getElementById('is_active').value
            };

            const opts = {
                method: id ? 'PUT' : 'POST',
                headers: { 'Content-Type': id ? 'application/x-www-form-urlencoded' : 'application/json' },
                body: id ? new URLSearchParams({ id, ...payload }).toString() : JSON.stringify(payload)
            };

            const res = await fetch(deptApi, opts);
            const data = await res.json();
            if (!data.success) {
                showAlert(data.error || 'Failed to save department', 'danger');
                return;
            }
            showAlert(data.message || 'Department saved', 'success');
            bootstrap.Modal.getInstance(document.getElementById('deptModal')).hide();
            loadDepartments();
        }

        async function deactivateDepartment(id) {
            if (!confirm('Deactivate this department?')) return;
            const res = await fetch(`${deptApi}?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (!data.success) {
                showAlert(data.error || 'Failed to deactivate department', 'danger');
                return;
            }
            showAlert(data.message || 'Department deactivated', 'success');
            loadDepartments();
        }

        document.addEventListener('DOMContentLoaded', async () => {
            await loadAccounts();
            await loadDepartments();
        });
    </script>
</body>
</html>
