<?php
require_once '../includes/auth.php';
require_once '../includes/api_integrations.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$manager = APIIntegrationManager::getInstance();
$integrations = $manager->getAllIntegrations();
$webhookIntegrations = [];
foreach ($integrations as $name => $integration) {
    $meta = $integration->getMetadata();
    if (!empty($meta['webhook_support'])) {
        $config = $manager->getIntegrationConfig($name);
        $webhookIntegrations[] = [
            'name' => $name,
            'display_name' => $meta['display_name'],
            'description' => $meta['description'],
            'configured' => !empty($config),
            'has_secret' => !empty($config['webhook_secret'] ?? null),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Management</title>
    <link rel="icon" type="image/png" href="../logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_navigation.php'; ?>

    <div class="content">
        <?php include '../includes/global_navbar.php'; ?>

        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="mb-1">Webhook Management</h3>
                    <p class="text-muted mb-0">Manage inbound webhook endpoints per integration.</p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($webhookIntegrations)): ?>
                        <div class="alert alert-info mb-0">No integrations with webhook support are available.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Integration</th>
                                        <th>Endpoint</th>
                                        <th>Status</th>
                                        <th>Secret</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($webhookIntegrations as $item): ?>
                                        <?php $endpoint = '/api/webhooks.php?integration=' . urlencode($item['name']); ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($item['display_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                            </td>
                                            <td>
                                                <code class="small" data-endpoint><?php echo htmlspecialchars($endpoint); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $item['configured'] ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                    <?php echo $item['configured'] ? 'Configured' : 'Not Configured'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $item['has_secret'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $item['has_secret'] ? 'Set' : 'Not Set'; ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" onclick="copyEndpoint(this)">
                                                    <i class="fas fa-copy me-1"></i>Copy
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

    <script>
        function copyEndpoint(btn) {
            const code = btn.closest('tr').querySelector('[data-endpoint]');
            if (!code) return;
            navigator.clipboard.writeText(code.textContent.trim()).then(() => {
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy';
                }, 1500);
            });
        }
    </script>
</body>
</html>
