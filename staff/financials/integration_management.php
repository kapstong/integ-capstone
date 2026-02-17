<?php
require_once '../../includes/auth.php';
require_once '../../includes/api_integrations.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../../index.php');
    exit;
}

$manager = APIIntegrationManager::getInstance();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['integration_name'])) {
    $name = $_POST['integration_name'];
    $config = $_POST['config'] ?? [];
    $result = $manager->configureIntegration($name, $config);
    if (!($result['success'] ?? false)) {
        $messageType = 'danger';
        if (is_array($result['error'] ?? null)) {
            $message = implode(', ', $result['error']);
        } else {
            $message = $result['error'] ?? 'Failed to save configuration.';
        }
    } else {
        $message = $result['message'] ?? 'Integration configured successfully.';
    }
}

$integrations = [];
foreach ($manager->getAllIntegrations() as $name => $integration) {
    $meta = $integration->getMetadata();
    $config = $manager->getIntegrationConfig($name) ?? [];
    $status = $manager->getIntegrationStatus($name);
    $integrations[] = [
        'name' => $name,
        'meta' => $meta,
        'config' => $config,
        'status' => $status
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Management</title>
    <link rel="icon" type="image/png" href="../../logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/staff_navigation.php'; ?>

    <div class="content">
        <?php include '../../includes/global_navbar.php'; ?>

        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="mb-1">Integration Management</h3>
                    <p class="text-muted mb-0">Configure and test external system integrations.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Integration</th>
                                    <th>Status</th>
                                    <th>Webhook</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($integrations as $item): ?>
                                    <?php
                                        $meta = $item['meta'];
                                        $configured = !empty($item['config']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($meta['display_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($meta['description']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $configured ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo $configured ? 'Configured' : 'Not Configured'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($meta['webhook_support'])): ?>
                                                <span class="badge bg-info">Supported</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary me-2"
                                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                    data-required="<?php echo htmlspecialchars(json_encode($meta['required_config'])); ?>"
                                                    data-config="<?php echo htmlspecialchars(json_encode($item['config'])); ?>"
                                                    onclick="openConfigModal(this)">
                                                <i class="fas fa-cog me-1"></i>Configure
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="testIntegration('<?php echo htmlspecialchars($item['name']); ?>')">
                                                <i class="fas fa-plug me-1"></i>Test
                                            </button>
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

    <div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="configForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Configure Integration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="integration_name" id="integration_name">
                        <div id="configFields" class="row g-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const integrationsApi = '../../api/integrations.php';

        function fieldType(name) {
            const lowered = name.toLowerCase();
            if (lowered.includes('secret') || lowered.includes('token') || lowered.includes('key')) return 'password';
            if (lowered.includes('url')) return 'url';
            if (lowered.includes('email')) return 'email';
            return 'text';
        }

        function openConfigModal(btn) {
            const name = btn.dataset.name;
            const required = JSON.parse(btn.dataset.required || '[]');
            const config = JSON.parse(btn.dataset.config || '{}');

            document.getElementById('integration_name').value = name;
            const container = document.getElementById('configFields');
            container.innerHTML = '';

            required.forEach(field => {
                const type = fieldType(field);
                const value = config[field] || '';
                container.innerHTML += `
                    <div class="col-md-6">
                        <label class="form-label">${field.replace(/_/g, ' ')}</label>
                        <input class="form-control" type="${type}" name="config[${field}]" value="${value}" required>
                    </div>
                `;
            });

            const modal = new bootstrap.Modal(document.getElementById('configModal'));
            modal.show();
        }

        function testIntegration(name) {
            const body = new FormData();
            body.append('action', 'test');
            body.append('integration_name', name);

            fetch(integrationsApi, { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Integration test successful.');
                    } else {
                        alert(data.error || data.message || 'Integration test failed.');
                    }
                })
                .catch(err => alert('Error: ' + err.message));
        }
    </script>
</body>
</html>
