<?php
/**
 * ATIERA Financial Management System - Advanced Search and Filtering
 * Admin interface for comprehensive search across all system data
 */

require_once '../includes/auth.php';
require_once '../includes/search.php';

$auth = new Auth();
$auth->requireLogin();

$searchEngine = SearchEngine::getInstance();
$user = $auth->getCurrentUser();

// Get search parameters
$query = $_GET['q'] ?? '';
$selectedTables = isset($_GET['tables']) ? (array)$_GET['tables'] : [];
$filters = [];
$limit = (int)($_GET['limit'] ?? 25);
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Parse filters from GET parameters
$filterKeys = ['status', 'customer_id', 'vendor_id', 'assigned_to', 'created_by', 'priority', 'total_amount_min', 'total_amount_max', 'date_from', 'date_to'];
foreach ($filterKeys as $key) {
    if (!empty($_GET[$key])) {
        if (strpos($key, '_min') !== false || strpos($key, '_max') !== false) {
            $baseKey = str_replace(['_min', '_max'], '', $key);
            $type = strpos($key, '_min') !== false ? 'min' : 'max';
            $filters[$baseKey][$type] = $_GET[$key];
        } elseif ($key === 'date_from' || $key === 'date_to') {
            $baseKey = 'created_at';
            $type = $key === 'date_from' ? 'from' : 'to';
            $filters[$baseKey][$type] = $_GET[$key];
        } else {
            $filters[$key] = $_GET[$key];
        }
    }
}

// Perform search if query is provided
$searchResults = [];
$facets = [];
$totalResults = 0;

if (!empty($query) || !empty($filters)) {
    $result = $searchEngine->search($query, $filters, $selectedTables ?: null, $limit, $offset);
    $searchResults = $result['results'];
    $facets = $result['facets'];
    $totalResults = $result['total'];
}

// Get searchable tables configuration
$searchableTables = $searchEngine->getSearchableTables();

// Get saved searches for current user
$savedSearches = $searchEngine->getSavedSearches();

// Get popular searches
$popularSearches = $searchEngine->getPopularSearches(10);

// Get search analytics
$searchAnalytics = $searchEngine->getSearchAnalytics();

$pageTitle = 'Advanced Search';
include 'legacy_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-search"></i> Advanced Search & Filtering</h2>
                <div>
                    <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#saveSearchModal">
                        <i class="fas fa-save"></i> Save Search
                    </button>
                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#savedSearchesModal">
                        <i class="fas fa-history"></i> Saved Searches
                    </button>
                </div>
            </div>

            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search"></i> Search Parameters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" id="searchForm">
                        <div class="row g-3">
                            <!-- Search Query -->
                            <div class="col-md-6">
                                <label for="searchQuery" class="form-label">Search Query</label>
                                <input type="text" class="form-control" id="searchQuery" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Enter search terms...">
                            </div>

                            <!-- Tables to Search -->
                            <div class="col-md-6">
                                <label class="form-label">Search In</label>
                                <div class="row">
                                    <?php foreach ($searchableTables as $table => $config): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tables[]" value="<?php echo $table; ?>" id="table_<?php echo $table; ?>"
                                                   <?php echo (empty($selectedTables) || in_array($table, $selectedTables)) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="table_<?php echo $table; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $table)); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Filters -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                    <i class="fas fa-filter"></i> Advanced Filters
                                </button>
                            </div>
                        </div>

                        <div class="collapse <?php echo !empty($filters) ? 'show' : ''; ?>" id="advancedFilters">
                            <div class="row g-3 mt-2">
                                <!-- Status Filter -->
                                <div class="col-md-2">
                                    <label for="statusFilter" class="form-label">Status</label>
                                    <select class="form-control form-control-sm" id="statusFilter" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="active" <?php echo ($filters['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($filters['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="draft" <?php echo ($filters['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="sent" <?php echo ($filters['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                        <option value="paid" <?php echo ($filters['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="approved" <?php echo ($filters['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="overdue" <?php echo ($filters['status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    </select>
                                </div>

                                <!-- Amount Range -->
                                <div class="col-md-2">
                                    <label for="amountMin" class="form-label">Min Amount</label>
                                    <input type="number" class="form-control form-control-sm" id="amountMin" name="total_amount_min" step="0.01"
                                           value="<?php echo $filters['total_amount']['min'] ?? ''; ?>" placeholder="0.00">
                                </div>
                                <div class="col-md-2">
                                    <label for="amountMax" class="form-label">Max Amount</label>
                                    <input type="number" class="form-control form-control-sm" id="amountMax" name="total_amount_max" step="0.01"
                                           value="<?php echo $filters['total_amount']['max'] ?? ''; ?>" placeholder="999999.99">
                                </div>

                                <!-- Date Range -->
                                <div class="col-md-2">
                                    <label for="dateFrom" class="form-label">From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="dateFrom" name="date_from"
                                           value="<?php echo $filters['created_at']['from'] ?? ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="dateTo" class="form-label">To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="dateTo" name="date_to"
                                           value="<?php echo $filters['created_at']['to'] ?? ''; ?>">
                                </div>

                                <!-- Priority (for tasks) -->
                                <div class="col-md-2">
                                    <label for="priorityFilter" class="form-label">Priority</label>
                                    <select class="form-control form-control-sm" id="priorityFilter" name="priority">
                                        <option value="">All Priorities</option>
                                        <option value="low" <?php echo ($filters['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($filters['priority'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($filters['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo ($filters['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Search Actions -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="search.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                                <div class="btn-group" role="group">
                                    <input type="number" id="limit" name="limit" class="form-control form-control-sm" value="<?php echo $limit; ?>" min="10" max="100" style="width: 80px;">
                                    <span class="input-group-text">per page</span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <?php if (!empty($query) || !empty($filters)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Search Results
                        <span class="badge bg-primary ms-2"><?php echo number_format($totalResults); ?> results</span>
                    </h5>
                    <?php if ($totalResults > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="exportResults()">
                        <i class="fas fa-download"></i> Export Results
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($searchResults)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <h5>No results found</h5>
                        <p>Try adjusting your search criteria or filters.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($searchResults as $table => $results): ?>
                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-table"></i> <?php echo ucfirst(str_replace('_', ' ', $table)); ?>
                            <span class="badge bg-secondary ms-2"><?php echo count($results); ?></span>
                        </h6>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <?php
                                        $firstResult = reset($results);
                                        foreach ($firstResult as $key => $value):
                                            if (!is_numeric($key) && $key !== 'id'):
                                        ?>
                                        <th><?php echo ucfirst(str_replace('_', ' ', $key)); ?></th>
                                        <?php endif; endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                    <tr>
                                        <?php foreach ($result as $key => $value):
                                            if (!is_numeric($key) && $key !== 'id'):
                                                // Format specific fields
                                                if (in_array($key, ['total_amount', 'balance', 'amount', 'credit_limit', 'current_balance'])) {
                                                    $value = $value ? '₱' . number_format($value, 2) : '';
                                                } elseif (in_array($key, ['created_at', 'updated_at', 'invoice_date', 'due_date', 'bill_date', 'payment_date'])) {
                                                    $value = $value ? date('M j, Y', strtotime($value)) : '';
                                                } elseif ($key === 'status') {
                                                    $value = '<span class="badge bg-' .
                                                        ($value === 'active' ? 'success' :
                                                        ($value === 'paid' ? 'success' :
                                                        ($value === 'draft' ? 'secondary' :
                                                        ($value === 'sent' ? 'info' :
                                                        ($value === 'approved' ? 'primary' :
                                                        ($value === 'overdue' ? 'danger' : 'warning')))))) .
                                                        '">' . ucfirst($value) . '</span>';
                                                }
                                        ?>
                                        <td><?php echo $value; ?></td>
                                        <?php endif; endforeach; ?>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewRecord('<?php echo $table; ?>', <?php echo $result['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalResults > $limit): ?>
                    <nav aria-label="Search results pagination">
                        <ul class="pagination justify-content-center">
                            <?php
                            $totalPages = ceil($totalResults / $limit);
                            $prevPage = max(1, $page - 1);
                            $nextPage = min($totalPages, $page + 1);
                            ?>
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $prevPage])); ?>">Previous</a>
                            </li>
                            <li class="page-item active">
                                <span class="page-link">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            </li>
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $nextPage])); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search Analytics -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Popular Searches</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($popularSearches)): ?>
                            <p class="text-muted">No search data available</p>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($popularSearches as $search): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($search['query']); ?></span>
                                    <span class="badge bg-primary rounded-pill"><?php echo $search['count']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar"></i> Search Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($searchAnalytics)): ?>
                            <p class="text-muted">No analytics data available</p>
                            <?php else: ?>
                            <canvas id="searchChart" width="400" height="200"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Save Search Modal -->
<div class="modal fade" id="saveSearchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Save Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="api/search.php?action=save">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="searchName" class="form-label">Search Name *</label>
                        <input type="text" class="form-control" id="searchName" name="name" required>
                    </div>
                    <input type="hidden" name="query" value="<?php echo htmlspecialchars($query); ?>">
                    <input type="hidden" name="filters" value="<?php echo htmlspecialchars(json_encode($filters)); ?>">
                    <input type="hidden" name="tables" value="<?php echo htmlspecialchars(json_encode($selectedTables)); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Search</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Saved Searches Modal -->
<div class="modal fade" id="savedSearchesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Saved Searches</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($savedSearches)): ?>
                <p class="text-muted">No saved searches</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($savedSearches as $search): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($search['name']); ?></h6>
                            <small class="text-muted">
                                Query: "<?php echo htmlspecialchars($search['query'] ?: 'Advanced filters'); ?>"
                                (<?php echo date('M j, Y', strtotime($search['created_at'])); ?>)
                            </small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-primary me-2" onclick="loadSavedSearch(<?php echo $search['id']; ?>)">
                                <i class="fas fa-play"></i> Load
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteSavedSearch(<?php echo $search['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Load saved search
function loadSavedSearch(searchId) {
    fetch(`api/search.php?action=load&id=${searchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const search = data.search;

                // Populate form
                document.getElementById('searchQuery').value = search.query || '';
                document.getElementById('statusFilter').value = search.filters.status || '';
                document.getElementById('amountMin').value = search.filters.total_amount?.min || '';
                document.getElementById('amountMax').value = search.filters.total_amount?.max || '';
                document.getElementById('dateFrom').value = search.filters.created_at?.from || '';
                document.getElementById('dateTo').value = search.filters.created_at?.to || '';
                document.getElementById('priorityFilter').value = search.filters.priority || '';

                // Check table checkboxes
                document.querySelectorAll('input[name="tables[]"]').forEach(checkbox => {
                    checkbox.checked = search.tables.includes(checkbox.value);
                });

                // Close modal and submit form
                const modalEl = document.getElementById('savedSearchesModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                document.getElementById('searchForm').submit();
            } else {
                alert('Error loading saved search: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// Delete saved search
function deleteSavedSearch(searchId) {
    showConfirmDialog(
        'Delete Search',
        'Delete this saved search?',
        async () => {
            try {
                const response = await fetch(`api/search.php?action=delete&id=${searchId}`, { method: 'DELETE' });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    showAlert('Delete failed: ' + data.error, 'danger');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'danger');
            }
        }
    );
}

// View record details
function viewRecord(table, id) {
    // Redirect to appropriate view page based on table
    const urls = {
        'customers': `customers.php?action=view&id=${id}`,
        'vendors': `vendors.php?action=view&id=${id}`,
        'invoices': `accounts_receivable.php?action=view&id=${id}`,
        'bills': `accounts_payable.php?action=view&id=${id}`,
        'payments_received': `accounts_receivable.php?action=view_payment&id=${id}`,
        'payments_made': `accounts_payable.php?action=view_payment&id=${id}`,
        'journal_entries': `general_ledger.php?action=view&id=${id}`,
        'users': `users.php?action=view&id=${id}`,
        'tasks': `tasks.php?action=view&id=${id}`
    };

    if (urls[table]) {
        window.open(urls[table], '_blank');
    } else {
        alert('View not available for this record type');
    }
}

// Export results
function exportResults() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.open(`api/search.php?${params.toString()}`, '_blank');
}

// Initialize chart if data exists
<?php if (!empty($searchAnalytics)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('searchChart').getContext('2d');
    const data = <?php echo json_encode($searchAnalytics); ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.search_date),
            datasets: [{
                label: 'Total Searches',
                data: data.map(item => item.total_searches),
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }, {
                label: 'Unique Users',
                data: data.map(item => item.unique_users),
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php include 'legacy_footer.php'; ?>



<?php include '../includes/csrf_auto_form.php'; ?>

