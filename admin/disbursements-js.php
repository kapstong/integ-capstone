<?php
header('Content-Type: application/javascript');
?>

        // Process Payment Functionality
        async function processPayment() {
            const formData = {
                action: 'process_payment',
                payee: document.getElementById('processPayee').value,
                payment_date: document.getElementById('paymentDate').value,
                payment_type: document.getElementById('paymentType').value,
                payment_method: document.getElementById('paymentMethodModal').value,
                amount: document.getElementById('processAmount').value,
                reference_number: document.getElementById('paymentReference').value,
                description: document.getElementById('processDescription').value
            };

            // Validate required fields
            if (!formData.payee || !formData.payment_date || !formData.payment_method || !formData.amount) {
                showAlert('Please fill in all required fields', 'warning');
                return;
            }

            try {
                const response = await fetch('api/disbursements.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Payment processed successfully!', 'success');
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('processPaymentModal'));
                    if (modal) modal.hide();
                    // Reset form
                    document.getElementById('processPaymentForm').reset();
                    // Reload disbursements
                    loadDisbursements();
                } else {
                    showAlert('Error: ' + result.error, 'danger');
                }
            } catch (error) {
                console.error('Error processing payment:', error);
                showAlert('An error occurred while processing payment', 'danger');
            }
        }

        // Process Payment Functionality
        async function processPayment() {
            const formData = {
                action: 'process_payment',
                payee: document.getElementById('processPayee').value,
                payment_date: document.getElementById('paymentDate').value,
                payment_type: document.getElementById('paymentType').value,
                payment_method: document.getElementById('paymentMethodModal').value,
                amount: document.getElementById('processAmount').value,
                reference_number: document.getElementById('paymentReference').value,
                description: document.getElementById('processDescription').value
            };

            // Validate required fields
            if (!formData.payee || !formData.payment_date || !formData.payment_method || !formData.amount) {
                showAlert('Please fill in all required fields', 'warning');
                return;
            }

            try {
                const response = await fetch('api/disbursements.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Payment processed successfully!', 'success');
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('processPaymentModal'));
                    if (modal) modal.hide();
                    // Reset form
                    document.getElementById('processPaymentForm').reset();
                    // Reload disbursements
                    loadDisbursements();
                } else {
                    showAlert('Error: ' + result.error, 'danger');
                }
            } catch (error) {
                console.error('Error processing payment:', error);
                showAlert('An error occurred while processing payment', 'danger');
            }
        }

        // Audit Trail Functionality
        async function loadAuditTrail() {
            try {
                const response = await fetch('api/audit.php', {
                    credentials: 'include'
                });
                const auditLogs = await response.json();

                const tbody = document.getElementById('auditTableBody');
                if (!tbody) return;

                if (auditLogs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No audit logs found</td></tr>';
                    return;
                }

                tbody.innerHTML = auditLogs.map(log => `
                    <tr>
                        <td>${log.formatted_date}</td>
                        <td>${log.full_name || log.username || 'Unknown'}</td>
                        <td><span class="badge bg-info">${log.action}</span></td>
                        <td>${log.disbursement_number || log.record_id || 'N/A'}</td>
                        <td>${log.action_description}</td>
                    </tr>
                `).join('');

            } catch (error) {
                console.error('Error loading audit trail:', error);
                const tbody = document.getElementById('auditTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Error loading audit trail</td></tr>';
                }
            }
        }

        // Reports and Analytics
        async function loadDisbursementReports() {
            try {
                // Load disbursement summary
                const response = await fetch('api/disbursements.php', {
                    credentials: 'include'
                });
                const disbursements = await response.json();

                // Calculate monthly totals for the chart
                const monthlyData = {};
                disbursements.forEach(d => {
                    const month = new Date(d.disbursement_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
                    if (!monthlyData[month]) {
                        monthlyData[month] = 0;
                    }
                    monthlyData[month] += parseFloat(d.amount);
                });

                // Create chart
                const ctx = document.getElementById('cashFlowChart');
                if (ctx && window.Chart) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: Object.keys(monthlyData),
                            datasets: [{
                                label: 'Monthly Disbursements',
                                data: Object.values(monthlyData),
                                borderColor: '#1e2936',
                                backgroundColor: 'rgba(30, 41, 54, 0.1)',
                                tension: 0.3,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Cash Flow Outflows - Disbursements'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '₱' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // Update summary cards
                const totalDisbursements = disbursements.length;
                const totalAmount = disbursements.reduce((sum, d) => sum + parseFloat(d.amount), 0);
                const pendingCount = disbursements.filter(d => d.status === 'pending').length;

                document.getElementById('totalDisbursementsCount').textContent = totalDisbursements;
                document.getElementById('totalDisbursementsAmount').textContent = '₱' + totalAmount.toLocaleString();
                document.getElementById('pendingDisbursementsCount').textContent = pendingCount;

            } catch (error) {
                console.error('Error loading reports:', error);
            }
        }

        // Voucher Management
        async function loadVouchers(disbursementId = null) {
            try {
                const url = disbursementId ?
                    `api/disbursements.php?action=get_vouchers&disbursement_id=${disbursementId}` :
                    'api/disbursements.php?action=get_vouchers';

                const response = await fetch(url, {
                    credentials: 'include'
                });
                const vouchers = await response.json();

                const tbody = disbursementId ?
                    document.querySelector('#vouchersTable tbody') :
                    document.querySelector('#vouchersTable tbody');

                if (tbody && vouchers.length > 0) {
                    tbody.innerHTML = vouchers.map(v => `
                        <tr>
                            <td>${v.file_name}</td>
                            <td>Voucher</td>
                            <td>${v.disbursement_number || 'N/A'}</td>
                            <td>${formatDate(v.uploaded_at)}</td>
                            <td><i class="fas fa-paperclip"></i> ${v.original_name}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary">View</button>
                                <button class="btn btn-sm btn-outline-secondary">Download</button>
                            </td>
                        </tr>
                    `).join('');
                }

        } catch (error) {
            console.error('Error loading vouchers:', error);
        }
    }

    // Additional functions for disbursements module

    function showFilters() {
        const filtersSection = document.getElementById('filtersSection');
        filtersSection.style.display = filtersSection.style.display === 'none' ? 'block' : 'none';
    }

    async function applyFilters() {
        currentFilters = {
            status: document.getElementById('filterStatus').value,
            date_from: document.getElementById('filterDateFrom').value,
            date_to: document.getElementById('filterDateTo').value
        };

        Object.keys(currentFilters).forEach(key => {
            if (!currentFilters[key]) {
                delete currentFilters[key];
            }
        });

        await loadDisbursements();
    }

    function clearFilters() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        currentFilters = {};
        loadDisbursements();
    }

    function showAddDisbursementModal() {
        document.getElementById('disbursementForm').reset();
        document.getElementById('disbursementId').value = '';
        document.getElementById('modalTitle').textContent = 'Add Disbursement';
        populateVendorDropdown();

        const modal = new bootstrap.Modal(document.getElementById('disbursementModal'));
        modal.show();
    }

    // View disbursement details
    async function viewDisbursement(id) {
        try {
            const response = await fetch(`api/disbursements.php?id=${id}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.error) {
                showAlert('Error loading disbursement: ' + data.error, 'danger');
                return;
            }

            showDisbursementDetails(data);
        } catch (error) {
            console.error('Error viewing disbursement:', error);
            showAlert('Error loading disbursement details', 'danger');
        }
    }

    // Edit disbursement
    async function editDisbursement(id) {
        try {
            const response = await fetch(`api/disbursements.php?id=${id}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.error) {
                showAlert('Error loading disbursement: ' + data.error, 'danger');
                return;
            }

            populateDisbursementForm(data);
            document.getElementById('modalTitle').textContent = 'Edit Disbursement';

            const modal = new bootstrap.Modal(document.getElementById('disbursementModal'));
            modal.show();
        } catch (error) {
            console.error('Error editing disbursement:', error);
            showAlert('Error loading disbursement for editing', 'danger');
        }
    }

    // Populate form with disbursement data
    function populateDisbursementForm(data) {
        document.getElementById('disbursementId').value = data.id;
        document.getElementById('vendorId').value = data.vendor_id || '';
        document.getElementById('amount').value = data.amount;
        document.getElementById('paymentMethod').value = data.payment_method;
        document.getElementById('referenceNumber').value = data.reference_number || '';
        document.getElementById('disbursementDate').value = data.disbursement_date;
        document.getElementById('notes').value = data.purpose || data.notes || '';
        document.getElementById('billId').value = data.bill_id || '';
    }

    // Delete disbursement
    async function deleteDisbursement(id) {
        if (!confirm('Are you sure you want to delete this disbursement?')) {
            return;
        }

        try {
            const response = await fetch(`api/disbursements.php?id=${id}`, {
                method: 'DELETE',
                credentials: 'include'
            });
            const data = await response.json();

            if (data.error) {
                showAlert('Error deleting disbursement: ' + data.error, 'danger');
                return;
            }

            showAlert('Disbursement deleted successfully', 'success');
            loadDisbursements();
        } catch (error) {
            console.error('Error deleting disbursement:', error);
            showAlert('Error deleting disbursement', 'danger');
        }
    }

    // Save disbursement
    async function saveDisbursement() {
        const formData = new FormData(document.getElementById('disbursementForm'));
        const data = Object.fromEntries(formData);

        // Map fields correctly
        const disbursementData = {
            payee: document.getElementById('vendorId').options[document.getElementById('vendorId').selectedIndex]?.text || '',
            disbursement_date: data.disbursement_date,
            amount: data.amount,
            payment_method: data.payment_method,
            reference_number: data.reference_number,
            purpose: data.notes,
            bill_id: data.bill_id,
            disbursement_id: data.disbursement_id // For updates
        };

        // Validate required fields
        if (!disbursementData.payee || !disbursementData.amount || !disbursementData.payment_method || !disbursementData.disbursement_date) {
            showAlert('Please fill in all required fields', 'warning');
            return;
        }

        try {
            const method = disbursementData.disbursement_id ? 'PUT' : 'POST';
            const url = disbursementData.disbursement_id
                ? `api/disbursements.php?id=${disbursementData.disbursement_id}`
                : 'api/disbursements.php';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(disbursementData)
            });

            const result = await response.json();

            if (result.success || result.message) {
                showAlert(result.message || 'Disbursement saved successfully', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('disbursementModal'));
                if (modal) modal.hide();
                loadDisbursements();
            } else {
                showAlert('Error: ' + (result.error || 'Unknown error'), 'danger');
            }
        } catch (error) {
            console.error('Error saving disbursement:', error);
            showAlert('An error occurred while saving disbursement', 'danger');
        }
    }

    // Show disbursement details modal
    function showDisbursementDetails(data) {
        const detailsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Reference:</strong> ${data.disbursement_number || 'N/A'}</p>
                    <p><strong>Payee:</strong> ${data.payee || 'N/A'}</p>
                    <p><strong>Amount:</strong> ₱${parseFloat(data.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    <p><strong>Payment Method:</strong> ${data.payment_method || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Date:</strong> ${formatDate(data.disbursement_date)}</p>
                    <p><strong>Reference Number:</strong> ${data.reference_number || 'N/A'}</p>
                    <p><strong>Status:</strong> ${getStatusBadge(data.status)}</p>
                    <p><strong>Notes:</strong> ${data.purpose || data.notes || 'N/A'}</p>
                </div>
            </div>
        `;

        // Create or update details modal
        let detailsModal = document.getElementById('disbursementDetailsModal');
        if (!detailsModal) {
            detailsModal = document.createElement('div');
            detailsModal.className = 'modal fade';
            detailsModal.id = 'disbursementDetailsModal';
            detailsModal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Disbursement Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="disbursementDetailsContent"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(detailsModal);
        }

        document.getElementById('disbursementDetailsContent').innerHTML = detailsHtml;
        const modal = new bootstrap.Modal(detailsModal);
        modal.show();
    }

        // Global variables
        let currentFilters = {};

        // Load disbursements from API
        async function loadDisbursements() {
            try {
                const params = new URLSearchParams(currentFilters);
                const response = await fetch(`api/disbursements.php?${params}`, {
                    credentials: 'include'
                });

                if (!response.ok) {
                    try {
                        const errorData = await response.text();
                        throw new Error(`HTTP ${response.status}: ${errorData || response.statusText}`);
                    } catch (e) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const errorText = await response.text();
                    throw new Error(errorText || 'Server returned an unexpected response format');
                }

                const data = await response.json();

                if (response.ok) {
                    renderDisbursementsTable(data);
                } else {
                    if (data.error) {
                        const tbody = document.getElementById('disbursementsTableBody');
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading disbursements. Please try again.</td></tr>';
                        showAlert('Error loading disbursements: ' + data.error, 'danger');
                    } else {
                        throw new Error('API returned an error');
                    }
                }
            } catch (error) {
                console.error('Error loading disbursements:', error);
                const tbody = document.getElementById('disbursementsTableBody');
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Error loading disbursements. Please try again.</td></tr>';
                showAlert('Error loading disbursements. Please try again.', 'warning');
            }
        }

        // Render disbursements table
        function renderDisbursementsTable(disbursements) {
            const tbody = document.getElementById('disbursementsTableBody');

            if (disbursements.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No disbursements found</td></tr>';
                return;
            }

            tbody.innerHTML = disbursements.map(d => `
                <tr>
                    <td>${d.disbursement_number || d.id}</td>
                    <td>${d.payee || 'N/A'}</td>
                    <td><span class="badge bg-secondary">${d.payment_method || 'N/A'}</span></td>
                    <td>${formatDate(d.disbursement_date)}</td>
                    <td>₱${parseFloat(d.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>${getStatusBadge(d.status || 'pending')}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewDisbursement(${d.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="editDisbursement(${d.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDisbursement(${d.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Load vendors for dropdown
        async function loadVendors() {
            try {
                const response = await fetch('api/vendors.php', {
                    credentials: 'include'
                });

                if (!response.ok) {
                    try {
                        const errorData = await response.text();
                        throw new Error(`HTTP ${response.status}: ${errorData || response.statusText}`);
                    } catch (e) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const errorText = await response.text();
                    throw new Error(errorText || 'Server returned an unexpected response format');
                }

                const data = await response.json();

                if (response.ok) {
                    window.vendors = data;
                    populateVendorDropdown();
                } else {
                    if (data.error) {
                        console.error('Error loading vendors:', data.error);
                        showAlert('Error loading vendors: ' + data.error, 'danger');
                    } else {
                        throw new Error('API returned an error');
                    }
                }
            } catch (error) {
                console.error('Error loading vendors:', error);
                showAlert('Error loading vendors. Please try again.', 'warning');
            }
        }

        // Populate vendor dropdown
        function populateVendorDropdown() {
            const vendorSelect = document.getElementById('vendorId');
            vendorSelect.innerHTML = '<option value="">Select Vendor</option>';

            if (window.vendors) {
                window.vendors.forEach(vendor => {
                    vendorSelect.innerHTML += `<option value="${vendor.id}">${vendor.company_name}</option>`;
                });
            }
        }

        // Connect payment processing buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for payment method buttons
            document.querySelectorAll('#processing .btn-outline-primary').forEach(btn => {
                btn.addEventListener('click', function() {
                    const method = this.textContent.toLowerCase().replace(' ', '_');
                    document.getElementById('paymentType').value = method;
                    // Open the process payment modal
                    const modal = new bootstrap.Modal(document.getElementById('processPaymentModal'));
                    modal.show();
                });
            });

            // Connect process payment button
            const processBtn = document.querySelector('#processPaymentModal .btn-primary');
            if (processBtn) {
                processBtn.addEventListener('click', processPayment);
            }

            // Connect tab events
            document.querySelectorAll('#disbursementsTabs .nav-link').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const target = e.target.getAttribute('data-bs-target');
                    switch(target) {
                        case '#audit':
                            loadAuditTrail();
                            break;
                        case '#reports':
                            loadDisbursementReports();
                            break;
                        case '#vouchers':
                            loadVouchers();
                            break;
                    }
                });
            });

            // Load initial data
            loadDisbursements();
            loadVendors();
        });

        // Utility functions
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function getStatusBadge(status) {
            const badges = {
                'completed': '<span class="badge bg-success">Completed</span>',
                'pending': '<span class="badge bg-warning">Pending</span>',
                'approved': '<span class="badge bg-info">Approved</span>',
                'cancelled': '<span class="badge bg-danger">Cancelled</span>',
                'paid': '<span class="badge bg-success">Paid</span>'
            };
            return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
        }

        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
