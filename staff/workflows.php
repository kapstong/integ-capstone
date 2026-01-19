<?php
/**
 * ATIERA Financial Management System - Workflow Automation Management
 * Admin interface for managing automated business processes and workflows
 */

require_once '../includes/auth.php';
require_once '../includes/workflow.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.edit'); // Require settings edit permission for workflows

$workflowEngine = WorkflowEngine::getInstance();
$user = $auth->getCurrentUser();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'toggle_workflow':
            $workflowId = (int)$_POST['workflow_id'] ?? 0;
            $active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;

            if ($workflowId > 0) {
                try {
                    $stmt = $auth->getDatabase()->prepare("UPDATE workflows SET is_active = ? WHERE id = ?");
                    $stmt->execute([$active ? 1 : 0, $workflowId]);

                    Logger::getInstance()->logUserAction(
                        ($active ? 'Enabled' : 'Disabled') . ' workflow',
                        'workflows',
                        $workflowId,
                        null,
                        ['is_active' => $active]
                    );

                    $message = 'Workflow ' . ($active ? 'enabled' : 'disabled') . ' successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to update workflow: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid workflow ID.';
            }
            break;

        case 'create_workflow':
            $name = trim($_POST['workflow_name'] ?? '');
            $description = trim($_POST['workflow_description'] ?? '');
            $definition = $_POST['workflow_definition'] ?? '';

            if (empty($name) || empty($definition)) {
                $error = 'Workflow name and definition are required.';
            } else {
                try {
                    $definitionJson = json_decode($definition, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error = 'Invalid workflow definition JSON.';
                    } else {
                        $stmt = $auth->getDatabase()->prepare("
                            INSERT INTO workflows (name, description, definition, created_by)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $description, json_encode($definitionJson), $user['id']]);

                        Logger::getInstance()->logUserAction(
                            'Created workflow',
                            'workflows',
                            $auth->getDatabase()->lastInsertId(),
                            null,
                            ['name' => $name]
                        );

                        $message = 'Workflow created successfully.';
                    }
                } catch (Exception $e) {
                    $error = 'Failed to create workflow: ' . $e->getMessage();
                }
            }
            break;

        case 'test_workflow':
            $workflowId = (int)$_POST['workflow_id'] ?? 0;
            $testData = $_POST['test_data'] ?? '';

            if ($workflowId > 0) {
                try {
                    $testDataArray = json_decode($testData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error = 'Invalid test data JSON.';
                    } else {
                        // Get workflow definition
                        $stmt = $auth->getDatabase()->prepare("SELECT definition FROM workflows WHERE id = ?");
                        $stmt->execute([$workflowId]);
                        $workflow = $stmt->fetch();

                        if ($workflow) {
                            $definition = json_decode($workflow['definition'], true);
                            $workflowEngine->triggerWorkflow($definition['trigger'], $testDataArray);
                            $message = 'Workflow test executed successfully. Check logs for details.';
                        } else {
                            $error = 'Workflow not found.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Failed to test workflow: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid workflow ID.';
            }
            break;
    }
}

// Get workflow statistics
$stats = $workflowEngine->getWorkflowStats();

// Get all workflows
$workflows = [];
try {
    $stmt = $auth->getDatabase()->prepare("
        SELECT w.*, u.username as created_by_name
        FROM workflows w
        LEFT JOIN users u ON w.created_by = u.id
        ORDER BY w.created_at DESC
    ");
    $stmt->execute();
    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Continue without workflow data
}

// Get recent workflow instances
$recentInstances = [];
try {
    $stmt = $auth->getDatabase()->prepare("
        SELECT wi.*, w.name as workflow_name, u.username as triggered_by
        FROM workflow_instances wi
        INNER JOIN workflows w ON wi.workflow_id = w.id
        LEFT JOIN users u ON JSON_UNQUOTE(JSON_EXTRACT(wi.trigger_data, '$.user_id')) = u.id
        ORDER BY wi.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentInstances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Continue without instance data
}

// Get available triggers
$availableTriggers = array_keys($workflowEngine->getAvailableWorkflows());
$triggerDescriptions = [
    'invoice.created' => 'When a new invoice is created',
    'invoice.updated' => 'When an invoice is updated',
    'invoice.paid' => 'When an invoice is paid',
    'invoice.overdue' => 'When an invoice becomes overdue',
    'bill.created' => 'When a new bill is created',
    'bill.approved' => 'When a bill is approved',
    'bill.paid' => 'When a bill is paid',
    'payment.received' => 'When a payment is received',
    'payment.made' => 'When a payment is made',
    'customer.created' => 'When a new customer is created',
    'customer.updated' => 'When a customer is updated',
    'vendor.created' => 'When a new vendor is created',
    'transaction.posted' => 'When a transaction is posted',
    'user.login' => 'When a user logs in',
    'user.failed_login' => 'When a login attempt fails'
];

$pageTitle = 'Workflow Automation';
include 'legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-cogs"></i> Workflow Automation</h2>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-info" onclick="showWorkflowStats()">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="showWorkflowInstances()">
                        <i class="fas fa-history"></i> Instances
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="createNewWorkflow()">
                        <i class="fas fa-plus"></i> Create Workflow
                    </button>
                </div>
            </div>

            <!-- Workflow Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo $stats['total_instances']; ?></h3>
                            <p class="text-muted mb-0">Total Executions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?php echo $stats['completed_instances']; ?></h3>
                            <p class="text-muted mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?php echo $stats['running_instances']; ?></h3>
                            <p class="text-muted mb-0">Running</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-danger"><?php echo $stats['failed_instances']; ?></h3>
                            <p class="text-muted mb-0">Failed</p>
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

            <!-- Available Workflows -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Available Workflows</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($workflows)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-cogs fa-3x mb-3"></i>
                        <h5>No workflows configured</h5>
                        <p>Create automated workflows to streamline your business processes.</p>
                        <button type="button" class="btn btn-primary" onclick="createNewWorkflow()">
                            <i class="fas fa-plus"></i> Create Your First Workflow
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($workflows as $workflow): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($workflow['name']); ?></h6>
                                    <span class="badge bg-<?php echo $workflow['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $workflow['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text small text-muted"><?php echo htmlspecialchars($workflow['description']); ?></p>

                                    <?php
                                    $definition = json_decode($workflow['definition'], true);
                                    $trigger = $definition['trigger'] ?? 'unknown';
                                    $stepsCount = count($definition['steps'] ?? []);
                                    ?>

                                    <div class="mb-2">
                                        <small class="text-muted">Trigger:</small>
                                        <span class="badge bg-info ms-1"><?php echo htmlspecialchars($trigger); ?></span>
                                    </div>

                                    <div class="mb-2">
                                        <small class="text-muted">Steps:</small>
                                        <span class="badge bg-secondary ms-1"><?php echo $stepsCount; ?> steps</span>
                                    </div>

                                    <div class="mb-2">
                                        <small class="text-muted">Created:</small>
                                        <span class="text-muted ms-1"><?php echo date('M j, Y', strtotime($workflow['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-outline-info btn-sm"
                                                onclick="viewWorkflow(<?php echo $workflow['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm"
                                                onclick="testWorkflow(<?php echo $workflow['id']; ?>, '<?php echo htmlspecialchars($workflow['name']); ?>')">
                                            <i class="fas fa-vial"></i> Test
                                        </button>
                                        <button type="button" class="btn btn-outline-<?php echo $workflow['is_active'] ? 'danger' : 'success'; ?> btn-sm"
                                                onclick="toggleWorkflow(<?php echo $workflow['id']; ?>, <?php echo $workflow['is_active'] ? 'false' : 'true'; ?>, '<?php echo htmlspecialchars($workflow['name']); ?>')">
                                            <i class="fas fa-<?php echo $workflow['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            <?php echo $workflow['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Workflow Instances -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Workflow Executions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentInstances)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <h5>No workflow executions</h5>
                        <p>Workflow execution history will appear here.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Workflow</th>
                                    <th>Status</th>
                                    <th>Started</th>
                                    <th>Triggered By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentInstances as $instance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($instance['workflow_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $instance['status'] === 'completed' ? 'success' :
                                                ($instance['status'] === 'running' ? 'warning' :
                                                ($instance['status'] === 'failed' ? 'danger' : 'secondary'));
                                        ?>">
                                            <?php echo ucfirst($instance['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($instance['started_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($instance['triggered_by'] ?: 'System'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="viewWorkflowInstance(<?php echo $instance['id']; ?>)">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Workflow Modal -->
<div class="modal fade" id="createWorkflowModal" tabindex="-1" aria-labelledby="createWorkflowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createWorkflowModalLabel">Create New Workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_workflow">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="workflowName" class="form-label">Workflow Name *</label>
                            <input type="text" class="form-control" id="workflowName" name="workflow_name" required>
                        </div>

                        <div class="col-md-6">
                            <label for="workflowTrigger" class="form-label">Trigger Event</label>
                            <select class="form-control" id="workflowTrigger" name="trigger_select">
                                <option value="">Select a trigger...</option>
                                <?php foreach ($triggerDescriptions as $trigger => $description): ?>
                                <option value="<?php echo $trigger; ?>"><?php echo htmlspecialchars($description); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label for="workflowDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="workflowDescription" name="workflow_description" rows="2"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label for="workflowDefinition" class="form-label">Workflow Definition (JSON) *</label>
                            <textarea class="form-control" id="workflowDefinition" name="workflow_definition" rows="15" required
                                      placeholder='{
  "trigger": "invoice.created",
  "conditions": [{"field": "total_amount", "operator": ">", "value": 50000}],
  "steps": [
    {
      "name": "Admin Approval",
      "type": "approval",
      "assignee_role": "admin",
      "timeout_hours": 24
    }
  ]
}'></textarea>
                            <small class="form-text text-muted">
                                Define your workflow in JSON format. See documentation for available options.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Workflow</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Test Workflow Modal -->
<div class="modal fade" id="testWorkflowModal" tabindex="-1" aria-labelledby="testWorkflowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testWorkflowModalLabel">Test Workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="test_workflow">
                    <input type="hidden" name="workflow_id" id="test_workflow_id">

                    <div class="mb-3">
                        <h6 id="testWorkflowName"></h6>
                        <p class="text-muted">Provide test data to simulate the workflow trigger.</p>
                    </div>

                    <div class="mb-3">
                        <label for="testData" class="form-label">Test Data (JSON)</label>
                        <textarea class="form-control" id="testData" name="test_data" rows="8" placeholder='{
  "total_amount": 60000,
  "customer_id": 1,
  "user_id": 1
}'></textarea>
                        <small class="form-text text-muted">
                            Enter sample data that would trigger this workflow.
                        </small>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will execute the workflow with real actions.
                        Use test data that won't affect production data.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Execute Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Create new workflow
function createNewWorkflow() {
    // Reset form
    document.getElementById('workflowName').value = '';
    document.getElementById('workflowDescription').value = '';
    document.getElementById('workflowDefinition').value = '';

    const modalEl = document.getElementById('createWorkflowModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}

// Test workflow
function testWorkflow(workflowId, workflowName) {
    document.getElementById('test_workflow_id').value = workflowId;
    document.getElementById('testWorkflowName').textContent = workflowName;

    // Reset test data
    document.getElementById('testData').value = '';

    const modalEl = document.getElementById('testWorkflowModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}

// Toggle workflow status
function toggleWorkflow(workflowId, activate, workflowName) {
    const action = activate ? 'enable' : 'disable';
    showConfirmDialog(
        'Toggle Workflow',
        `Are you sure you want to ${action} the workflow "${workflowName}"?`,
        () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_workflow">
                <input type="hidden" name="workflow_id" value="${workflowId}">
                <input type="hidden" name="is_active" value="${activate}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// View workflow details
function viewWorkflow(workflowId) {
    fetch(`api/workflows.php?action=get_workflow&id=${workflowId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Workflow Details:\n\n' + JSON.stringify(data.workflow, null, 2));
            } else {
                alert('Error loading workflow: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// View workflow instance details
function viewWorkflowInstance(instanceId) {
    fetch(`api/workflows.php?action=get_instance&id=${instanceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Workflow Instance Details:\n\n' + JSON.stringify(data.instance, null, 2));
            } else {
                alert('Error loading instance: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Show workflow statistics
function showWorkflowStats() {
    const stats = {
        total_instances: <?php echo $stats['total_instances']; ?>,
        completed_instances: <?php echo $stats['completed_instances']; ?>,
        running_instances: <?php echo $stats['running_instances']; ?>,
        failed_instances: <?php echo $stats['failed_instances']; ?>,
        total_steps: <?php echo $stats['total_steps']; ?>,
        completed_steps: <?php echo $stats['completed_steps']; ?>,
        pending_steps: <?php echo $stats['pending_steps']; ?>,
        timed_out_steps: <?php echo $stats['timed_out_steps']; ?>
    };

    let message = 'Workflow Statistics:\n\n';
    message += `Instances: ${stats.total_instances} total (${stats.completed_instances} completed, ${stats.running_instances} running, ${stats.failed_instances} failed)\n`;
    message += `Steps: ${stats.total_steps} total (${stats.completed_steps} completed, ${stats.pending_steps} pending, ${stats.timed_out_steps} timed out)`;

    alert(message);
}

// Show workflow instances
function showWorkflowInstances() {
    const instances = <?php echo json_encode($recentInstances); ?>;
    let message = 'Recent Workflow Instances:\n\n';

    instances.forEach(instance => {
        message += `${instance.workflow_name} - ${instance.status} (${new Date(instance.started_at).toLocaleString()})\n`;
    });

    alert(message);
}

// Auto-populate trigger in workflow definition when selected
document.getElementById('workflowTrigger').addEventListener('change', function() {
    const trigger = this.value;
    if (trigger) {
        const textarea = document.getElementById('workflowDefinition');
        let currentValue = textarea.value.trim();

        if (!currentValue) {
            // Create basic template
            const template = {
                trigger: trigger,
                conditions: [],
                steps: [
                    {
                        name: "Sample Step",
                        type: "notification",
                        template: "sample_template",
                        recipients: ["user"]
                    }
                ]
            };
            textarea.value = JSON.stringify(template, null, 2);
        } else {
            // Update trigger in existing JSON
            try {
                const parsed = JSON.parse(currentValue);
                parsed.trigger = trigger;
                textarea.value = JSON.stringify(parsed, null, 2);
            } catch (e) {
                // Invalid JSON, don't modify
            }
        }
    }
});
</script>

<?php include 'legacy_footer.php'; ?>
