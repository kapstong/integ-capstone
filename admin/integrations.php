<?php
/**
 * ATIERA Financial Management System - API Integrations Management
 * Admin interface for configuring and managing external API integrations
 */

require_once '../includes/auth.php';
require_once '../includes/api_integrations.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.edit'); // Require settings edit permission for integrations

$integrationManager = APIIntegrationManager::getInstance();
$user = $auth->getCurrentUser();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'configure':
            $integrationName = $_POST['integration_name'] ?? '';
            $config = $_POST['config'] ?? [];

            $result = $integrationManager->configureIntegration($integrationName, $config);
            if ($result['success']) {
                $message = 'Integration configured successfully.';
            } else {
                $error = $result['error'];
            }
            break;

        case 'test':
            $integrationName = $_POST['integration_name'] ?? '';
            $result = $integrationManager->testIntegration($integrationName);
            if ($result['success']) {
                $message = 'Connection test successful: ' . $result['message'];
            } else {
                $error = 'Connection test failed: ' . $result['error'];
            }
            break;

        case 'disable':
            $integrationName = $_POST['integration_name'] ?? '';
            $integrationManager->updateIntegrationStatus($integrationName, false);
            $message = 'Integration disabled successfully.';
            break;
    }
}

// Get all integrations and their status
$integrations = $integrationManager->getAllIntegrations();
$integrationStatuses = [];
$integrationConfigs = [];

foreach ($integrations as $name => $integration) {
    $status = $integrationManager->getIntegrationStatus($name);
    $integrationStatuses[$name] = $status ? $status['is_active'] : false;
    $integrationConfigs[$name] = $integrationManager->getIntegrationConfig($name);
}

// Get integration statistics
$stats = $integrationManager->getIntegrationStats();

$pageTitle = 'API Integrations';
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h2><i class="fas fa-plug"></i> API Integrations</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Integrations</li>
                        </ol>
                    </nav>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-info" onclick="showIntegrationStats()">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="showIntegrationLogs()">
                        <i class="fas fa-history"></i> Activity Logs
                    </button>
                </div>
            </div>

            <!-- Integration Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo $stats['total_integrations']; ?></h3>
                            <p class="text-muted mb-0">Total Integrations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?php echo $stats['active_integrations']; ?></h3>
                            <p class="text-muted mb-0">Active Integrations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?php echo $stats['recently_used']; ?></h3>
                            <p class="text-muted mb-0">Recently Used</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?php echo count($integrations) - $stats['total_integrations']; ?></h3>
                            <p class="text-muted mb-0">Available</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Integration Cards -->
            <div class="row">
                <?php foreach ($integrations as $name => $integration): ?>
                <?php $metadata = $integration->getMetadata(); ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo getIntegrationIcon($name); ?> me-2"></i>
                                <?php echo htmlspecialchars($metadata['display_name']); ?>
                            </h5>
                            <span class="badge bg-<?php echo $integrationStatuses[$name] ? 'success' : 'secondary'; ?>">
                                <?php echo $integrationStatuses[$name] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small"><?php echo htmlspecialchars($metadata['description']); ?></p>

                            <!-- Configuration Status -->
                            <div class="mb-3">
                                <small class="text-muted">Configuration:</small>
                                <span class="badge bg-<?php echo $integrationConfigs[$name] ? 'success' : 'warning'; ?> ms-1">
                                    <?php echo $integrationConfigs[$name] ? 'Configured' : 'Not Configured'; ?>
                                </span>
                                <?php if ($metadata['webhook_support']): ?>
                                <span class="badge bg-info ms-1">Webhooks</span>
                                <?php endif; ?>
                            </div>

                            <!-- Required Configuration Fields -->
                            <div class="mb-3">
                                <small class="text-muted">Required Fields:</small>
                                <div class="mt-1">
                                    <?php foreach ($metadata['required_config'] as $field): ?>
                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($field); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="configureIntegration('<?php echo $name; ?>')">
                                    <i class="fas fa-cog"></i> Configure
                                </button>
                                <?php if ($integrationConfigs[$name]): ?>
                                <button type="button" class="btn btn-outline-success btn-sm"
                                        onclick="testIntegration('<?php echo $name; ?>')">
                                    <i class="fas fa-vial"></i> Test
                                </button>
                                <?php endif; ?>
                                <?php if ($integrationStatuses[$name]): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        onclick="disableIntegration('<?php echo $name; ?>')">
                                    <i class="fas fa-ban"></i> Disable
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Integration Categories -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <h4>Integration Categories</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <h6>HR Systems</h6>
                                    <small class="text-muted">HR3, HR4</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-truck fa-2x text-success mb-2"></i>
                                    <h6>Logistics</h6>
                                    <small class="text-muted">Logistics 1, Logistics 2</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configure Integration Modal -->
<div class="modal fade" id="configureModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Integration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="configure">
                    <input type="hidden" name="integration_name" id="modal_integration_name">

                    <div id="configFields">
                        <!-- Configuration fields will be loaded here -->
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Security Note:</strong> Configuration data is encrypted and stored securely.
                        Make sure to use appropriate API keys and credentials.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Integration Stats Modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Integration Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="statsContent">
                    <!-- Stats will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Integration Logs Modal -->
<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Integration Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="logsContent">
                    <!-- Logs will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
  // Integration icons mapping
  const totalIntegrations = <?php echo count($integrations); ?>;

  function getIntegrationIcon(integrationName) {
    const icons = {
        'hr3': 'users',
        'hr4': 'user-tie',
        'logistics1': 'box-open',
        'logistics2': 'truck'
    };
    return icons[integrationName] || 'plug';
}

// Configure integration
function configureIntegration(integrationName) {
    fetch(`api/integrations.php?action=get_config_form&integration=${integrationName}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modal_integration_name').value = integrationName;
                document.getElementById('configFields').innerHTML = data.form_html;
                const modalEl = document.getElementById('configureModal');
                if (modalEl) {
                    new bootstrap.Modal(modalEl).show();
                }
            } else {
                alert('Error loading configuration form: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Test integration
function testIntegration(integrationName) {
    if (confirm(`Test connection to ${integrationName}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="test">
            <input type="hidden" name="integration_name" value="${integrationName}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Disable integration
function disableIntegration(integrationName) {
    if (confirm(`Disable ${integrationName} integration?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="disable">
            <input type="hidden" name="integration_name" value="${integrationName}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Show integration statistics
function showIntegrationStats() {
    fetch('api/integrations.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="row">';
                html += '<div class="col-md-6"><h6>Integration Usage</h6><canvas id="usageChart"></canvas></div>';
                html += '<div class="col-md-6"><h6>Recent Activity</h6><div id="recentActivity"></div></div>';
                html += '</div>';

                document.getElementById('statsContent').innerHTML = html;

                // Initialize chart
                const ctx = document.getElementById('usageChart').getContext('2d');
                  new Chart(ctx, {
                      type: 'doughnut',
                      data: {
                          labels: ['Active', 'Inactive', 'Available'],
                          datasets: [{
                              data: [
                                  data.stats.active_integrations,
                                  data.stats.total_integrations - data.stats.active_integrations,
                                  totalIntegrations - data.stats.total_integrations
                              ],
                              backgroundColor: ['#28a745', '#6c757d', '#ffc107']
                          }]
                      }
                  });

                const modalEl = document.getElementById('statsModal');
                if (modalEl) {
                    new bootstrap.Modal(modalEl).show();
                }
            } else {
                alert('Error loading statistics: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Show integration logs
function showIntegrationLogs() {
    fetch('api/integrations.php?action=get_logs&limit=50')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="table-responsive"><table class="table table-sm">';
                html += '<thead><tr><th>Date</th><th>Integration</th><th>Action</th><th>Status</th><th>Message</th></tr></thead><tbody>';

                data.logs.forEach(log => {
                    const statusClass = log.status === 'success' ? 'success' : (log.status === 'error' ? 'danger' : 'warning');
                    html += `<tr>
                        <td>${new Date(log.created_at).toLocaleString()}</td>
                        <td>${log.integration_name}</td>
                        <td>${log.action}</td>
                        <td><span class="badge bg-${statusClass}">${log.status}</span></td>
                        <td>${log.message || ''}</td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                document.getElementById('logsContent').innerHTML = html;
                const modalEl = document.getElementById('logsModal');
                if (modalEl) {
                    new bootstrap.Modal(modalEl).show();
                }
            } else {
                alert('Error loading logs: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Auto-refresh stats every 30 seconds
  setInterval(() => {
      // Update stats if modal is open
      const statsModal = document.getElementById('statsModal');
      if (statsModal && statsModal.classList.contains('show')) {
          showIntegrationStats();
      }
  }, 30000);
</script>

<?php include 'footer.php'; ?>

<?php
function getIntegrationIcon($name) {
    $icons = [
        'hr3' => 'users',
        'hr4' => 'user-tie',
        'logistics1' => 'box-open',
        'logistics2' => 'truck'
    ];
    return $icons[$name] ?? 'plug';
}
?>
