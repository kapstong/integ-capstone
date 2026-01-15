<?php
/**
 * ATIERA FINANCIALS - Cashier Shifts
 * Track front desk and outlet cashiering activity
 */

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();
if (!$auth->hasAnyPermission(['cashier.operate', 'cashier.view_all'])) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Cashier / Collection';
include '../legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-cash-register"></i> Cashier Shifts</h2>
                <div class="action-buttons">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#openShiftModal">
                        <i class="fas fa-play"></i> <span class="btn-text-mobile-hide">Open Shift</span>
                    </button>
                </div>
            </div>

            <div id="alertContainer"></div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Shift Log</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-mobile-stack" id="shiftTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Outlet</th>
                                    <th>Cashier</th>
                                    <th>Opened</th>
                                    <th>Closed</th>
                                    <th>Opening Cash</th>
                                    <th>Closing Cash</th>
                                    <th>Variance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="10" class="text-center">
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

<!-- Open Shift Modal -->
<div class="modal fade" id="openShiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-play"></i> Open Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="openShiftForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Outlet *</label>
                            <select class="form-control" id="shift_outlet_id" name="outlet_id" required></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Shift Date *</label>
                            <input type="date" class="form-control" id="shift_date" name="shift_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Opening Cash *</label>
                            <input type="number" class="form-control" id="opening_cash" name="opening_cash" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="shift_notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> Open Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Close Shift Modal -->
<div class="modal fade" id="closeShiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-stop"></i> Close Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="closeShiftForm">
                <input type="hidden" id="close_shift_id" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Closing Cash *</label>
                            <input type="number" class="form-control" id="closing_cash" name="closing_cash" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Expected Cash</label>
                            <input type="number" class="form-control" id="expected_cash" name="expected_cash" step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="close_notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-stop"></i> Close Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let shifts = [];
let outlets = [];

document.addEventListener('DOMContentLoaded', function() {
    loadOutlets();
    setDefaultDate();
    loadShifts();
});

function setDefaultDate() {
    const today = new Date().toISOString().slice(0, 10);
    document.getElementById('shift_date').value = today;
}

function loadOutlets() {
    fetch('../api/financials/outlets.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                outlets = data.outlets.filter(o => o.is_active == 1);
                const select = document.getElementById('shift_outlet_id');
                select.innerHTML = '<option value="">Select Outlet</option>' +
                    outlets.map(o => `<option value="${o.id}">${escapeHtml(o.outlet_code)} - ${escapeHtml(o.outlet_name)}</option>`).join('');
            }
        });
}

function loadShifts() {
    fetch('../api/financials/cashier_shifts.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                shifts = data.shifts;
                renderShiftTable();
            } else {
                showAlert('Error loading shifts: ' + data.error, 'danger');
            }
        })
        .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function renderShiftTable() {
    const tbody = document.querySelector('#shiftTable tbody');
    if (shifts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">No shifts found</td></tr>';
        return;
    }

    tbody.innerHTML = shifts.map(shift => `
        <tr>
            <td data-label="Date">${escapeHtml(shift.shift_date)}</td>
            <td data-label="Outlet">${escapeHtml(shift.outlet_name || 'Unassigned')}</td>
            <td data-label="Cashier">${escapeHtml(shift.cashier_name || 'N/A')}</td>
            <td data-label="Opened">${escapeHtml(shift.opened_at)}</td>
            <td data-label="Closed">${escapeHtml(shift.closed_at || 'Open')}</td>
            <td data-label="Opening Cash">ƒ,ñ${formatAmount(shift.opening_cash)}</td>
            <td data-label="Closing Cash">${shift.closing_cash !== null ? 'ƒ,ñ' + formatAmount(shift.closing_cash) : '-'}</td>
            <td data-label="Variance">${formatVariance(shift.variance)}</td>
            <td data-label="Status">${formatStatus(shift.status)}</td>
            <td data-label="Actions">
                <div class="btn-group btn-group-sm" role="group">
                    ${shift.status === 'open' ? `
                    <button class="btn btn-success" onclick="openCloseModal(${shift.id})" title="Close Shift">
                        <i class="fas fa-stop"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

document.getElementById('openShiftForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'open_shift';

    fetch('../api/financials/cashier_shifts.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('Shift opened successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('openShiftModal')).hide();
            this.reset();
            setDefaultDate();
            loadShifts();
        } else {
            showAlert('Error: ' + result.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

document.getElementById('closeShiftForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'close_shift';

    fetch('../api/financials/cashier_shifts.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('Shift closed successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('closeShiftModal')).hide();
            this.reset();
            loadShifts();
        } else {
            showAlert('Error: ' + result.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

function openCloseModal(shiftId) {
    document.getElementById('close_shift_id').value = shiftId;
    new bootstrap.Modal(document.getElementById('closeShiftModal')).show();
}

function formatVariance(value) {
    if (value === null || value === undefined) return '-';
    const amount = Number(value || 0);
    const klass = amount === 0 ? 'text-muted' : (amount > 0 ? 'text-success' : 'text-danger');
    const sign = amount > 0 ? '+' : '';
    return `<span class="${klass}">${sign}ƒ,ñ${formatAmount(amount)}</span>`;
}

function formatStatus(status) {
    const map = {
        open: '<span class="badge bg-warning">Open</span>',
        closed: '<span class="badge bg-info">Closed</span>',
        reconciled: '<span class="badge bg-success">Reconciled</span>'
    };
    return map[status] || status;
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

function formatAmount(value) {
    return Number(value || 0).toLocaleString();
}

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '';
}
</script>

<?php include '../legacy_footer.php'; ?>
