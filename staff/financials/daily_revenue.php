<?php
/**
 * ATIERA FINANCIALS - Daily Revenue Entry
 * Capture room, restaurant, and other outlet revenue by business date
 */

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('departments.view');

$pageTitle = 'Daily Revenue';
include '../../includes/admin_navigation.php';
?>

    <div class="content">
        <!-- Top Navbar -->
        <?php include '../../includes/global_navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-mobile-column">
                <h2><i class="fas fa-receipt"></i> Daily Revenue Entry</h2>
                <div class="action-buttons">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dailyRevenueModal">
                        <i class="fas fa-plus"></i> <span class="btn-text-mobile-hide">Add Daily Revenue</span>
                    </button>
                </div>
            </div>

            <div id="alertContainer"></div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" id="filterDateFrom">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" id="filterDateTo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Outlet</label>
                            <select class="form-control" id="filterOutlet"></select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-success w-100" onclick="loadDailyRevenue()">
                                <i class="fas fa-search"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Revenue Log</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-mobile-stack" id="dailyRevenueTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Outlet</th>
                                    <th>Gross Sales</th>
                                    <th>Discounts</th>
                                    <th>Service Charge</th>
                                    <th>Taxes</th>
                                    <th>Net Sales</th>
                                    <th class="d-mobile-none">Covers</th>
                                    <th class="d-mobile-none">Room Nights</th>
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

<!-- Daily Revenue Modal -->
<div class="modal fade" id="dailyRevenueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Daily Revenue Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="dailyRevenueForm">
                <input type="hidden" id="entry_id" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Business Date *</label>
                            <input type="date" class="form-control" id="business_date" name="business_date" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Outlet *</label>
                            <select class="form-control" id="outlet_id" name="outlet_id" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gross Sales *</label>
                            <input type="number" class="form-control" id="gross_sales" name="gross_sales" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Discounts</label>
                            <input type="number" class="form-control" id="discounts" name="discounts" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Service Charge</label>
                            <input type="number" class="form-control" id="service_charge" name="service_charge" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Taxes</label>
                            <input type="number" class="form-control" id="taxes" name="taxes" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Net Sales</label>
                            <input type="number" class="form-control" id="net_sales" name="net_sales" step="0.01">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Covers</label>
                            <input type="number" class="form-control" id="covers" name="covers" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Room Nights</label>
                            <input type="number" class="form-control" id="room_nights" name="room_nights" min="0">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let outlets = [];
let revenueEntries = [];

document.addEventListener('DOMContentLoaded', function() {
    loadOutlets();
    setDefaultDates();
    loadDailyRevenue();
    bindNetSalesAutoCalc();
});

function setDefaultDates() {
    const today = new Date().toISOString().slice(0, 10);
    document.getElementById('filterDateTo').value = today;
    const start = new Date();
    start.setDate(start.getDate() - 30);
    document.getElementById('filterDateFrom').value = start.toISOString().slice(0, 10);
}

function loadOutlets() {
    fetch('../api/financials/outlets.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                outlets = data.outlets.filter(o => o.is_active == 1);
                populateOutletSelects();
            }
        });
}

function populateOutletSelects() {
    const filterSelect = document.getElementById('filterOutlet');
    const entrySelect = document.getElementById('outlet_id');

    filterSelect.innerHTML = '<option value="">All Outlets</option>' +
        outlets.map(o => `<option value="${o.id}">${escapeHtml(o.outlet_code)} - ${escapeHtml(o.outlet_name)}</option>`).join('');

    entrySelect.innerHTML = '<option value="">Select Outlet</option>' +
        outlets.map(o => `<option value="${o.id}">${escapeHtml(o.outlet_code)} - ${escapeHtml(o.outlet_name)}</option>`).join('');
}

function loadDailyRevenue() {
    const params = new URLSearchParams();
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const outletId = document.getElementById('filterOutlet').value;

    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (outletId) params.append('outlet_id', outletId);

    fetch(`../api/financials/daily_revenue.php?action=list&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                revenueEntries = data.entries;
                renderRevenueTable();
            } else {
                showAlert('Error loading revenue entries: ' + data.error, 'danger');
            }
        })
        .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function bindNetSalesAutoCalc() {
    ['gross_sales', 'discounts', 'service_charge', 'taxes'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', updateNetSales);
        }
    });
}

function updateNetSales() {
    const gross = parseFloat(document.getElementById('gross_sales').value || 0);
    const discounts = parseFloat(document.getElementById('discounts').value || 0);
    const serviceCharge = parseFloat(document.getElementById('service_charge').value || 0);
    const taxes = parseFloat(document.getElementById('taxes').value || 0);
    const netSales = gross - discounts + serviceCharge + taxes;
    document.getElementById('net_sales').value = netSales.toFixed(2);
}

function renderRevenueTable() {
    const tbody = document.querySelector('#dailyRevenueTable tbody');
    if (revenueEntries.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">No revenue entries found</td></tr>';
        return;
    }

    tbody.innerHTML = revenueEntries.map(entry => `
        <tr>
            <td data-label="Date">${escapeHtml(entry.business_date)}</td>
            <td data-label="Outlet">${escapeHtml(entry.outlet_name)}</td>
            <td data-label="Gross">ƒ,ñ${formatAmount(entry.gross_sales)}</td>
            <td data-label="Discounts">ƒ,ñ${formatAmount(entry.discounts)}</td>
            <td data-label="Service Charge">ƒ,ñ${formatAmount(entry.service_charge)}</td>
            <td data-label="Taxes">ƒ,ñ${formatAmount(entry.taxes)}</td>
            <td data-label="Net">ƒ,ñ${formatAmount(entry.net_sales)}</td>
            <td data-label="Covers" class="d-mobile-none">${entry.covers ?? '-'}</td>
            <td data-label="Room Nights" class="d-mobile-none">${entry.room_nights ?? '-'}</td>
            <td data-label="Actions">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-warning" onclick="editEntry(${entry.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

document.getElementById('dailyRevenueForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.action = 'save';

    fetch('../api/financials/daily_revenue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('Daily revenue entry saved', 'success');
            bootstrap.Modal.getInstance(document.getElementById('dailyRevenueModal')).hide();
            this.reset();
            loadDailyRevenue();
        } else {
            showAlert('Error: ' + result.error, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
});

function editEntry(id) {
    const entry = revenueEntries.find(r => r.id == id);
    if (!entry) return;

    document.getElementById('entry_id').value = entry.id;
    document.getElementById('business_date').value = entry.business_date;
    document.getElementById('outlet_id').value = entry.outlet_id;
    document.getElementById('gross_sales').value = entry.gross_sales;
    document.getElementById('discounts').value = entry.discounts;
    document.getElementById('service_charge').value = entry.service_charge;
    document.getElementById('taxes').value = entry.taxes;
    document.getElementById('net_sales').value = entry.net_sales;
    document.getElementById('covers').value = entry.covers ?? '';
    document.getElementById('room_nights').value = entry.room_nights ?? '';
    document.getElementById('notes').value = entry.notes ?? '';

    new bootstrap.Modal(document.getElementById('dailyRevenueModal')).show();
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
