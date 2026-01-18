<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance();
$user = $_SESSION['user'];

$pageTitle = 'Currency Management';
include 'legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Currency Management</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCurrencyModal">
                        <i class="fas fa-plus"></i> Add Currency
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="currenciesTable">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Symbol</th>
                                    <th>Decimal Places</th>
                                    <th>Exchange Rate</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Currency Modal -->
<div class="modal fade" id="addCurrencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Currency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCurrencyForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currency_code" class="form-label">Currency Code *</label>
                                <input type="text" class="form-control" id="currency_code" name="currency_code" maxlength="3" required>
                                <div class="form-text">ISO 4217 currency code (e.g., USD, EUR, PHP)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currency_name" class="form-label">Currency Name *</label>
                                <input type="text" class="form-control" id="currency_name" name="currency_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="symbol" class="form-label">Symbol *</label>
                                <input type="text" class="form-control" id="symbol" name="symbol" maxlength="10" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="decimal_places" class="form-label">Decimal Places</label>
                                <select class="form-control" id="decimal_places" name="decimal_places">
                                    <option value="0">0</option>
                                    <option value="2" selected>2</option>
                                    <option value="3">3</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="exchange_rate" class="form-label">Exchange Rate (to base currency)</label>
                        <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" step="0.0001" value="1.0000" required>
                        <div class="form-text">Rate relative to your base currency (PHP)</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Currency</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Currency Modal -->
<div class="modal fade" id="editCurrencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Currency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCurrencyForm">
                <input type="hidden" id="edit_currency_id" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_currency_name" class="form-label">Currency Name</label>
                                <input type="text" class="form-control" id="edit_currency_name" name="currency_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_symbol" class="form-label">Symbol</label>
                                <input type="text" class="form-control" id="edit_symbol" name="symbol" maxlength="10" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_decimal_places" class="form-label">Decimal Places</label>
                                <select class="form-control" id="edit_decimal_places" name="decimal_places">
                                    <option value="0">0</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_exchange_rate" class="form-label">Exchange Rate</label>
                                <input type="number" class="form-control" id="edit_exchange_rate" name="exchange_rate" step="0.0001" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Currency</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadCurrencies();

    // Add currency form submission
    $('#addCurrencyForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('../api/currencies.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#addCurrencyModal').modal('hide');
                $('#addCurrencyForm')[0].reset();
                loadCurrencies();
                showToast('Currency added successfully', 'success');
            } else {
                showToast(data.error || 'Failed to add currency', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred', 'error');
        });
    });

    // Edit currency form submission
    $('#editCurrencyForm').on('submit', function(e) {
        e.preventDefault();

        const currencyId = $('#edit_currency_id').val();
        const formData = new FormData(this);

        fetch(`../api/currencies.php?id=${currencyId}`, {
            method: 'PUT',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#editCurrencyModal').modal('hide');
                loadCurrencies();
                showToast('Currency updated successfully', 'success');
            } else {
                showToast(data.error || 'Failed to update currency', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred', 'error');
        });
    });

    // Delete currency
    window.deleteCurrency = function(id, name) {
        if (confirm(`Are you sure you want to delete the currency "${name}"?`)) {
            fetch(`../api/currencies.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCurrencies();
                    showToast('Currency deleted successfully', 'success');
                } else {
                    showToast(data.error || 'Failed to delete currency', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            });
        }
    };

    // Edit currency button click
    window.editCurrency = function(id) {
        fetch(`../api/currencies.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            $('#edit_currency_id').val(data.id);
            $('#edit_currency_name').val(data.currency_name);
            $('#edit_symbol').val(data.symbol);
            $('#edit_decimal_places').val(data.decimal_places);
            $('#edit_exchange_rate').val(data.exchange_rate);
            $('#edit_is_active').prop('checked', data.is_active == 1);

            $('#editCurrencyModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load currency data', 'error');
        });
    };
});

function loadCurrencies() {
    fetch('../api/currencies.php')
    .then(response => response.json())
    .then(data => {
        const tbody = $('#currenciesTable tbody');
        tbody.empty();

        data.forEach(currency => {
            const statusBadge = currency.is_active == 1
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';

            const actions = `
                <button class="btn btn-sm btn-outline-primary" onclick="editCurrency(${currency.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteCurrency(${currency.id}, '${currency.currency_name}')">
                    <i class="fas fa-trash"></i> Delete
                </button>
            `;

            tbody.append(`
                <tr>
                    <td><strong>${currency.currency_code}</strong></td>
                    <td>${currency.currency_name}</td>
                    <td>${currency.symbol}</td>
                    <td>${currency.decimal_places}</td>
                    <td>${parseFloat(currency.exchange_rate).toFixed(4)}</td>
                    <td>${statusBadge}</td>
                    <td>${actions}</td>
                </tr>
            `);
        });
    })
    .catch(error => {
        console.error('Error loading currencies:', error);
        showToast('Failed to load currencies', 'error');
    });
}

function showToast(message, type = 'info') {
    // Simple toast implementation - you can enhance this
    alert(message);
}
</script>

<style>
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.btn-outline-primary:hover, .btn-outline-danger:hover {
    color: white;
}
</style>

<?php include 'legacy_footer.php'; ?>
