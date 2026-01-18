<?php
/**
 * ATIERA API Clients Management
 * Manage external API clients and their access keys
 */

require_once '../includes/auth.php';
require_once '../includes/api_auth.php';

session_start();

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user']['id'];
$auth = new Auth();

// Require admin permission for API client management
if (!$auth->hasPermission('admin.api_clients')) {
    header('Location: index.php?error=access_denied');
    exit;
}

$apiAuth = APIAuth::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_client':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $error = 'Client name is required';
            } else {
                $result = $apiAuth->createApiClient($name, $description, $userId);
                if ($result['success']) {
                    $message = 'API client created successfully. API Key: ' . $result['api_key'];
                } else {
                    $error = $result['error'];
                }
            }
            break;

        case 'toggle_status':
            $clientId = (int)($_POST['client_id'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $result = $apiAuth->updateApiClientStatus($clientId, $isActive);
            if ($result['success']) {
                $message = 'API client status updated successfully';
            } else {
                $error = $result['error'];
            }
            break;

        case 'regenerate_key':
            $clientId = (int)($_POST['client_id'] ?? 0);

            $result = $apiAuth->regenerateApiKey($clientId);
            if ($result['success']) {
                $message = 'API key regenerated successfully. New Key: ' . $result['api_key'];
            } else {
                $error = $result['error'];
            }
            break;

        case 'delete_client':
            $clientId = (int)($_POST['client_id'] ?? 0);

            $result = $apiAuth->deleteApiClient($clientId);
            if ($result['success']) {
                $message = 'API client deleted successfully';
            } else {
                $error = $result['error'];
            }
            break;
    }
}

// Get all API clients
$clients = $apiAuth->getApiClients();

$pageTitle = 'API Clients Management';
require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-key"></i> API Clients Management
                    </h4>
                    <p class="card-description">
                        Manage external API clients and their access keys for third-party integrations.
                    </p>
                </div>

                <div class="card-body">
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

                    <!-- Create New Client Button -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClientModal">
                            <i class="fas fa-plus"></i> Create New API Client
                        </button>
                    </div>

                    <!-- API Clients Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>API Key</th>
                                    <th>Status</th>
                                    <th>Requests Today</th>
                                    <th>Total Requests</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($clients)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> No API clients found. Create your first client to get started.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td><?php echo $client['id']; ?></td>
                                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                                            <td><?php echo htmlspecialchars($client['description'] ?? ''); ?></td>
                                            <td>
                                                <code class="api-key" style="font-size: 0.8em;">
                                                    <?php echo htmlspecialchars(substr($client['api_key'], 0, 20) . '...'); ?>
                                                </code>
                                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="showFullKey('<?php echo htmlspecialchars($client['api_key']); ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="is_active" value="1"
                                                               <?php echo $client['is_active'] ? 'checked' : ''; ?>
                                                               onchange="this.form.submit()">
                                                        <label class="form-check-label">
                                                            <?php echo $client['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>'; ?>
                                                        </label>
                                                    </div>
                                                </form>
                                            </td>
                                            <td><?php echo number_format($client['requests_today']); ?></td>
                                            <td><?php echo number_format($client['total_requests']); ?></td>
                                            <td><?php echo htmlspecialchars($client['created_by_name'] ?? 'System'); ?></td>
                                            <td><?php echo date('M j, Y H:i', strtotime($client['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="regenerateKey(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>')">
                                                        <i class="fas fa-sync"></i> Regenerate Key
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- API Documentation Link -->
                    <div class="mt-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-book"></i> API Documentation
                                </h6>
                                <p class="card-text">
                                    Learn how to use the ATIERA External API to integrate with your systems.
                                </p>
                                <a href="api_docs.php" class="btn btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i> View API Documentation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Client Modal -->
<div class="modal fade" id="createClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New API Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_client">

                    <div class="mb-3">
                        <label for="clientName" class="form-label">Client Name *</label>
                        <input type="text" class="form-control" id="clientName" name="name" required
                               placeholder="e.g., My E-commerce Platform">
                        <div class="form-text">A descriptive name for this API client</div>
                    </div>

                    <div class="mb-3">
                        <label for="clientDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="clientDescription" name="description" rows="3"
                                  placeholder="Describe what this client will be used for..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show full API key
function showFullKey(fullKey) {
    alert('Full API Key:\n\n' + fullKey + '\n\nKeep this key secure and do not share it publicly.');
}

// Regenerate API key
function regenerateKey(clientId, clientName) {
    if (confirm('Are you sure you want to regenerate the API key for "' + clientName + '"?\n\nThis will invalidate the current key and any systems using it will need to be updated.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="action" value="regenerate_key">
            <input type="hidden" name="client_id" value="${clientId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete API client
function deleteClient(clientId, clientName) {
    if (confirm('Are you sure you want to delete the API client "' + clientName + '"?\n\nThis action cannot be undone and will permanently remove the client and all associated request logs.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_client">
            <input type="hidden" name="client_id" value="${clientId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once 'templates/footer.php'; ?>
