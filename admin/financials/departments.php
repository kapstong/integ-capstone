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
        'name' => 'Human Resource 1',
        'scope' => 'Talent Acquisition & Workforce Entry',
        'modules' => [
            'Applicant Management',
            'Recruitment Management',
            'New Hire Onboarding',
            'Performance Management (Initial)',
            'Social Recognition'
        ],
        'integration_key' => null,
        'integrated' => false
    ],
    [
        'name' => 'Human Resource 2',
        'scope' => 'Talent Development & Career Pathing',
        'modules' => [
            'Competency Management',
            'Learning Management',
            'Training Management',
            'Succession Planning',
            'Employee Self-Service (ESS)'
        ],
        'integration_key' => null,
        'integrated' => false
    ],
    [
        'name' => 'Human Resource 3',
        'scope' => 'Workforce Operations & Time Management',
        'modules' => [
            'Time and Attendance System',
            'Shift and Schedule Management',
            'Timesheet Management',
            'Leave Management',
            'Claims and Reimbursement'
        ],
        'integration_key' => 'hr3',
        'integrated' => true
    ],
    [
        'name' => 'Human Resource 4',
        'scope' => 'Compensation & HR Intelligence',
        'modules' => [
            'Core Human Capital Management (HCM)',
            'Payroll Management',
            'Compensation Planning',
            'HR Analytics Dashboard',
            'HMO & Benefits Administration'
        ],
        'integration_key' => 'hr4',
        'integrated' => true
    ],
    [
        'name' => 'Administrative',
        'scope' => 'Core Administration',
        'modules' => [
            'Legal Management',
            'Facilities Reservation',
            'Document Management (Archiving)',
            'Visitor Management'
        ],
        'integration_key' => null,
        'integrated' => false
    ],
    [
        'name' => 'Logistics 1',
        'scope' => 'Smart Supply Chain & Procurement Management',
        'modules' => [
            'Smart Warehousing System (SWS)',
            'Procurement & Sourcing Management (PSM)',
            'Project Logistics Tracker (PLT)',
            'Asset Lifecycle & Maintenance (ALMS)',
            'Document Tracking & Logistics Records (DTRS)'
        ],
        'integration_key' => 'logistics1',
        'integrated' => true
    ],
    [
        'name' => 'Logistics 2',
        'scope' => 'Fleet and Transportation Operations',
        'modules' => [
            'Fleet & Vehicle Management (FVM)',
            'Vehicle Reservation & Dispatch System (VRDS)',
            'Driver and Trip Performance Monitoring',
            'Transport Cost Analysis & Optimization (TCAO)',
            'Mobile Fleet Command App (optional)'
        ],
        'integration_key' => 'logistics2',
        'integrated' => true
    ],
    [
        'name' => 'Core 1 - Hotel',
        'scope' => 'Hotel Operations',
        'modules' => [
            'Front Desk and Reception Module',
            'Reservation and Booking Module',
            'Loyalty and Rewards Program Module',
            'Billing and Payment Module',
            'Point of Sale (POS) Module',
            'Inventory and Stock Management Module',
            'Event and Conference Management Module',
            'Guest Relationship Management (CRM) Module',
            'Room Management and Service Module',
            'Integration with Door Lock Systems Module',
            'Housekeeping and Maintenance Module',
            'Hotel Marketing and Promotion Module',
            'Channel Management Module (online travel agencies and room availability)',
            'Analytics and Reporting Module'
        ],
        'integration_key' => null,
        'integrated' => false
    ],
    [
        'name' => 'Core 2 - Restaurant',
        'scope' => 'Restaurant Operations',
        'modules' => [
            'Table Reservation and Seating Module',
            'Reservation and Event Management Module',
            'Table Turnover and Wait Time Module',
            'Menu Management Module',
            'Order Taking and POS Module',
            'Kitchen Order Ticket (KOT) Module',
            'Billing and Payment Module',
            'Wait Staff and Server Management Module',
            'Integration with Payment Gateways Module',
            'Customer Feedback and Reviews Module',
            'Inventory and Stock Management Module',
            'Analytics and Reporting Module',
            'Integration with Online Ordering Module',
            'Integration with Loyalty Programs Module'
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
                                    <th>Status</th>
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
                                    <td data-label="Status">
                                        <?php if ($department['integrated']): ?>
                                            <span class="badge bg-success">Integrated</span>
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
