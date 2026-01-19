<?php
/**
 * ATIERA Financial Management System - Performance Monitoring & Optimization
 * Admin interface for monitoring system performance and optimization
 */

require_once '../includes/auth.php';
require_once '../includes/performance.php';
require_once '../includes/cache.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('settings.view'); // Require settings view permission

$performanceMonitor = PerformanceMonitor::getInstance();
$cacheManager = CacheManager::getInstance();
$queryOptimizer = new QueryOptimizer();
$systemHealth = new SystemHealthMonitor();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'clear_cache':
            try {
                $cacheManager->clear();
                $message = 'Cache cleared successfully.';
            } catch (Exception $e) {
                $error = 'Failed to clear cache: ' . $e->getMessage();
            }
            break;

        case 'warmup_cache':
            try {
                $cacheManager->warmup();
                $message = 'Cache warmup completed successfully.';
            } catch (Exception $e) {
                $error = 'Failed to warmup cache: ' . $e->getMessage();
            }
            break;

        case 'analyze_query':
            $sql = trim($_POST['query_sql'] ?? '');
            if (empty($sql)) {
                $error = 'Please enter a SQL query to analyze.';
            } else {
                // Store for display
                $_SESSION['last_query_analysis'] = $queryOptimizer->analyzeQuery($sql);
                $message = 'Query analysis completed.';
            }
            break;

        case 'reset_monitoring':
            $performanceMonitor->reset();
            $message = 'Performance monitoring data reset.';
            break;

        case 'toggle_monitoring':
            $enabled = isset($_POST['monitoring_enabled']);
            $performanceMonitor->setEnabled($enabled);
            $message = 'Performance monitoring ' . ($enabled ? 'enabled' : 'disabled') . '.';
            break;
    }
}

// Get performance statistics
$performanceStats = $performanceMonitor->getStats();
$cacheStats = $cacheManager->getStats();
$healthStatus = $systemHealth->getHealthStatus();
$slowQueries = $performanceMonitor->getSlowQueries(0.1);

// Get optimization suggestions
$optimizationSuggestions = $queryOptimizer->getOptimizationSuggestions();

// Get last query analysis if exists
$lastQueryAnalysis = $_SESSION['last_query_analysis'] ?? null;
unset($_SESSION['last_query_analysis']); // Clear after display

$pageTitle = 'Performance Monitoring';
include 'legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-tachometer-alt"></i> Performance Monitoring</h2>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-info" onclick="showHealthStatus()">
                        <i class="fas fa-heartbeat"></i> Health Check
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="showOptimizationSuggestions()">
                        <i class="fas fa-lightbulb"></i> Optimization
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="showCacheStats()">
                        <i class="fas fa-memory"></i> Cache Stats
                    </button>
                </div>
            </div>

            <!-- System Health Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-<?php echo $healthStatus['overall'] === 'healthy' ? 'success' : ($healthStatus['overall'] === 'warning' ? 'warning' : 'danger'); ?>">
                                <i class="fas fa-<?php echo $healthStatus['overall'] === 'healthy' ? 'check-circle' : ($healthStatus['overall'] === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                            </h3>
                            <p class="text-muted mb-0">System Health</p>
                            <small class="text-uppercase"><?php echo $healthStatus['overall']; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?php echo number_format($performanceStats['total_execution_time'], 2); ?>s</h3>
                            <p class="text-muted mb-0">Execution Time</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?php echo $performanceStats['queries_executed']; ?></h3>
                            <p class="text-muted mb-0">Queries Executed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?php echo number_format($performanceStats['cache_hit_ratio'], 1); ?>%</h3>
                            <p class="text-muted mb-0">Cache Hit Rate</p>
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

            <!-- Performance Controls -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sliders-h"></i> Performance Controls</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Clear all cache?')">
                                    <i class="fas fa-trash"></i> Clear Cache
                                </button>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="warmup_cache">
                                <button type="submit" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-fire"></i> Warmup Cache
                                </button>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="reset_monitoring">
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-undo"></i> Reset Monitoring
                                </button>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_monitoring">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="monitoring_enabled"
                                           id="monitoringToggle" <?php echo $performanceMonitor ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="monitoringToggle">
                                        Performance Monitoring
                                    </label>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Performance Statistics -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Performance Metrics</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Total Execution Time:</td>
                                        <td class="text-end"><?php echo number_format($performanceStats['total_execution_time'], 4); ?>s</td>
                                    </tr>
                                    <tr>
                                        <td>Peak Memory Usage:</td>
                                        <td class="text-end"><?php echo number_format($performanceStats['peak_memory_usage'] / 1024 / 1024, 2); ?> MB</td>
                                    </tr>
                                    <tr>
                                        <td>Queries Executed:</td>
                                        <td class="text-end"><?php echo $performanceStats['queries_executed']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Cache Hits:</td>
                                        <td class="text-end"><?php echo $performanceStats['cache_hits']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Cache Misses:</td>
                                        <td class="text-end"><?php echo $performanceStats['cache_misses']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Cache Hit Ratio:</td>
                                        <td class="text-end"><?php echo number_format($performanceStats['cache_hit_ratio'], 1); ?>%</td>
                                    </tr>
                                    <?php if (isset($performanceStats['query_stats'])): ?>
                                    <tr>
                                        <td>Average Query Time:</td>
                                        <td class="text-end"><?php echo number_format($performanceStats['query_stats']['average_time'], 4); ?>s</td>
                                    </tr>
                                    <tr>
                                        <td>Slowest Query:</td>
                                        <td class="text-end"><?php echo number_format($performanceStats['query_stats']['slowest_query'], 4); ?>s</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-server"></i> System Health</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($healthStatus['checks'] as $checkName => $check): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-capitalize"><?php echo $checkName; ?>:</span>
                                <span class="badge bg-<?php echo $check['status'] === 'healthy' ? 'success' : ($check['status'] === 'warning' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($check['status']); ?>
                                </span>
                            </div>
                            <small class="text-muted d-block mb-3"><?php echo htmlspecialchars($check['message']); ?></small>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slow Queries -->
            <?php if (!empty($slowQueries)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Slow Queries (>100ms)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Query</th>
                                    <th>Duration</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($slowQueries, 0, 10) as $query): ?>
                                <tr>
                                    <td>
                                        <code class="small"><?php echo htmlspecialchars(substr($query['sql'], 0, 100)); ?><?php echo strlen($query['sql']) > 100 ? '...' : ''; ?></code>
                                    </td>
                                    <td><?php echo number_format($query['duration'], 4); ?>s</td>
                                    <td><?php echo date('H:i:s', $query['timestamp']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Query Analysis Tool -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search"></i> Query Analysis Tool</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="analyze_query">
                        <div class="mb-3">
                            <label for="querySql" class="form-label">SQL Query to Analyze</label>
                            <textarea class="form-control" id="querySql" name="query_sql" rows="3"
                                      placeholder="SELECT * FROM users WHERE status = 'active'"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Analyze Query
                        </button>
                    </form>

                    <?php if ($lastQueryAnalysis): ?>
                    <div class="mt-4">
                        <h6>Analysis Results:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Execution Time:</strong> <?php echo number_format($lastQueryAnalysis['execution_time'], 4); ?>s</p>
                                <p><strong>Result Count:</strong> <?php echo $lastQueryAnalysis['result_count']; ?> rows</p>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($lastQueryAnalysis['suggestions'])): ?>
                                <p><strong>Optimization Suggestions:</strong></p>
                                <ul>
                                    <?php foreach ($lastQueryAnalysis['suggestions'] as $suggestion): ?>
                                    <li><?php echo htmlspecialchars($suggestion); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="text-success"><strong>No optimization suggestions</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Optimization Suggestions -->
            <?php if (!empty($optimizationSuggestions)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Database Optimization Suggestions</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($optimizationSuggestions as $suggestion): ?>
                        <li class="list-group-item">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            <?php echo htmlspecialchars($suggestion); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Health Status Modal -->
<div class="modal fade" id="healthStatusModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">System Health Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php foreach ($healthStatus['checks'] as $checkName => $check): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card border-<?php echo $check['status'] === 'healthy' ? 'success' : ($check['status'] === 'warning' ? 'warning' : 'danger'); ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="card-title mb-0 text-capitalize"><?php echo $checkName; ?></h6>
                                    <span class="badge bg-<?php echo $check['status'] === 'healthy' ? 'success' : ($check['status'] === 'warning' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($check['status']); ?>
                                    </span>
                                </div>
                                <p class="card-text small"><?php echo htmlspecialchars($check['message']); ?></p>
                                <?php if (isset($check['response_time'])): ?>
                                <small class="text-muted">Response time: <?php echo number_format($check['response_time'], 4); ?>s</small>
                                <?php endif; ?>
                                <?php if (isset($check['issues']) && !empty($check['issues'])): ?>
                                <div class="mt-2">
                                    <small class="text-danger">Issues:</small>
                                    <ul class="small text-danger mb-0">
                                        <?php foreach ($check['issues'] as $issue): ?>
                                        <li><?php echo htmlspecialchars($issue); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Cache Statistics Modal -->
<div class="modal fade" id="cacheStatsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cache Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td>Redis Enabled:</td>
                            <td><?php echo $cacheStats['redis_enabled'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning">No</span>'; ?></td>
                        </tr>
                        <?php if ($cacheStats['redis_enabled']): ?>
                        <tr>
                            <td>Redis Keys:</td>
                            <td><?php echo $cacheStats['redis_keys'] ?? 'Unknown'; ?></td>
                        </tr>
                        <tr>
                            <td>Redis Memory:</td>
                            <td><?php echo $cacheStats['redis_memory'] ?? 'Unknown'; ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>File Cache Items:</td>
                            <td><?php echo $cacheStats['file_cache_items']; ?></td>
                        </tr>
                        <tr>
                            <td>Cache Directory:</td>
                            <td><code><?php echo htmlspecialchars($cacheStats['file_cache_dir']); ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show health status modal
function showHealthStatus() {
    const modalEl = document.getElementById('healthStatusModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}

// Show cache statistics modal
function showCacheStats() {
    const modalEl = document.getElementById('cacheStatsModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}

// Show optimization suggestions
function showOptimizationSuggestions() {
    const suggestions = <?php echo json_encode($optimizationSuggestions); ?>;
    let message = 'Database Optimization Suggestions:\n\n';

    if (suggestions.length === 0) {
        message += 'No optimization suggestions at this time.';
    } else {
        suggestions.forEach((suggestion, index) => {
            message += `${index + 1}. ${suggestion}\n`;
        });
    }

    alert(message);
}

// Auto-refresh performance stats every 30 seconds
setInterval(function() {
    // This would typically make an AJAX call to refresh stats
    // For now, we'll just update the timestamp
}, 30000);
</script>

<?php include 'legacy_footer.php'; ?>

