<?php
/**
 * ATIERA Financial Management System - Advanced Search and Filtering
 * Comprehensive search engine with full-text capabilities and advanced filtering
 */

class SearchEngine {
    private static $instance = null;
    private $db;
    private $searchableTables = [
        'customers' => [
            'fields' => ['customer_code', 'company_name', 'contact_person', 'email', 'phone', 'address'],
            'fulltext' => ['company_name', 'contact_person', 'email', 'address'],
            'filters' => ['status', 'credit_limit', 'current_balance', 'created_at']
        ],
        'vendors' => [
            'fields' => ['vendor_code', 'company_name', 'contact_person', 'email', 'phone', 'address'],
            'fulltext' => ['company_name', 'contact_person', 'email', 'address'],
            'filters' => ['status', 'payment_terms', 'created_at']
        ],
        'invoices' => [
            'fields' => ['invoice_number', 'customer_id', 'total_amount', 'balance', 'notes'],
            'fulltext' => ['invoice_number', 'notes'],
            'filters' => ['status', 'invoice_date', 'due_date', 'customer_id', 'total_amount', 'balance']
        ],
        'bills' => [
            'fields' => ['bill_number', 'vendor_id', 'total_amount', 'balance', 'notes'],
            'fulltext' => ['bill_number', 'notes'],
            'filters' => ['status', 'bill_date', 'due_date', 'vendor_id', 'total_amount', 'balance']
        ],
        'payments_received' => [
            'fields' => ['payment_number', 'customer_id', 'invoice_id', 'amount', 'reference_number', 'notes'],
            'fulltext' => ['payment_number', 'reference_number', 'notes'],
            'filters' => ['payment_date', 'payment_method', 'customer_id', 'amount']
        ],
        'payments_made' => [
            'fields' => ['payment_number', 'vendor_id', 'bill_id', 'amount', 'reference_number', 'notes'],
            'fulltext' => ['payment_number', 'reference_number', 'notes'],
            'filters' => ['payment_date', 'payment_method', 'vendor_id', 'amount']
        ],
        'journal_entries' => [
            'fields' => ['entry_number', 'description', 'reference'],
            'fulltext' => ['entry_number', 'description', 'reference'],
            'filters' => ['entry_date', 'status', 'created_by', 'total_debit', 'total_credit']
        ],
        'users' => [
            'fields' => ['username', 'email', 'full_name', 'phone', 'department'],
            'fulltext' => ['username', 'email', 'full_name', 'department'],
            'filters' => ['role', 'status', 'last_login', 'created_at']
        ],
        'tasks' => [
            'fields' => ['title', 'description'],
            'fulltext' => ['title', 'description'],
            'filters' => ['status', 'priority', 'assigned_to', 'due_date', 'created_by']
        ]
    ];

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Perform advanced search across multiple tables
     */
    public function search($query, $filters = [], $tables = null, $limit = 50, $offset = 0) {
        if (empty($query) && empty($filters)) {
            return ['results' => [], 'total' => 0, 'facets' => []];
        }

        $tables = $tables ?: array_keys($this->searchableTables);
        $results = [];
        $totalResults = 0;
        $facets = [];

        foreach ($tables as $table) {
            if (!isset($this->searchableTables[$table])) {
                continue;
            }

            $tableConfig = $this->searchableTables[$table];
            $tableResults = $this->searchTable($table, $tableConfig, $query, $filters, $limit, $offset);

            if (!empty($tableResults['results'])) {
                $results[$table] = $tableResults['results'];
                $totalResults += $tableResults['total'];

                // Collect facets
                if (isset($tableResults['facets'])) {
                    $facets[$table] = $tableResults['facets'];
                }
            }
        }

        // Log search query for analytics
        $this->logSearchQuery($query, $filters, $tables, $totalResults);

        return [
            'results' => $results,
            'total' => $totalResults,
            'facets' => $facets,
            'query' => $query,
            'filters' => $filters
        ];
    }

    /**
     * Search within a specific table
     */
    private function searchTable($table, $config, $query, $filters, $limit, $offset) {
        try {
            $where = [];
            $params = [];
            $joins = [];

            // Full-text search
            if (!empty($query)) {
                $fulltextFields = $config['fulltext'];
                if (!empty($fulltextFields)) {
                    $fulltextConditions = [];
                    foreach ($fulltextFields as $field) {
                        $fulltextConditions[] = "`$field` LIKE ?";
                        $params[] = '%' . $query . '%';
                    }
                    $where[] = '(' . implode(' OR ', $fulltextConditions) . ')';
                }
            }

            // Apply filters
            foreach ($filters as $filterKey => $filterValue) {
                if (isset($config['filters']) && in_array($filterKey, $config['filters'])) {
                    $this->applyFilter($table, $filterKey, $filterValue, $where, $params, $joins);
                }
            }

            // Special table-specific joins
            switch ($table) {
                case 'invoices':
                    $joins[] = "LEFT JOIN customers c ON invoices.customer_id = c.id";
                    break;
                case 'bills':
                    $joins[] = "LEFT JOIN vendors v ON bills.vendor_id = v.id";
                    break;
                case 'payments_received':
                    $joins[] = "LEFT JOIN customers c ON payments_received.customer_id = c.id";
                    $joins[] = "LEFT JOIN invoices i ON payments_received.invoice_id = i.id";
                    break;
                case 'payments_made':
                    $joins[] = "LEFT JOIN vendors v ON payments_made.vendor_id = v.id";
                    $joins[] = "LEFT JOIN bills b ON payments_made.bill_id = b.id";
                    break;
                case 'tasks':
                    $joins[] = "LEFT JOIN users u_assigned ON tasks.assigned_to = u_assigned.id";
                    $joins[] = "LEFT JOIN users u_created ON tasks.created_by = u_created.id";
                    break;
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
            $joinClause = empty($joins) ? '' : implode(' ', $joins);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM `$table` $joinClause $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];

            if ($total === 0) {
                return ['results' => [], 'total' => 0];
            }

            // Get results with relevance scoring
            $selectFields = $this->getSelectFields($table);
            $sql = "SELECT $selectFields FROM `$table` $joinClause $whereClause ORDER BY $table.id DESC LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate facets for filtering
            $facets = $this->calculateFacets($table, $config, $query, $filters);

            return [
                'results' => $rows,
                'total' => $total,
                'facets' => $facets
            ];

        } catch (Exception $e) {
            Logger::getInstance()->error("Search error in table $table: " . $e->getMessage());
            return ['results' => [], 'total' => 0];
        }
    }

    /**
     * Apply filter conditions
     */
    private function applyFilter($table, $filterKey, $filterValue, &$where, &$params, &$joins) {
        switch ($filterKey) {
            case 'status':
                if (is_array($filterValue)) {
                    $placeholders = str_repeat('?,', count($filterValue) - 1) . '?';
                    $where[] = "`$table`.`status` IN ($placeholders)";
                    $params = array_merge($params, $filterValue);
                } else {
                    $where[] = "`$table`.`status` = ?";
                    $params[] = $filterValue;
                }
                break;

            case 'customer_id':
            case 'vendor_id':
            case 'assigned_to':
            case 'created_by':
            case 'approved_by':
            case 'recorded_by':
                if (is_array($filterValue)) {
                    $placeholders = str_repeat('?,', count($filterValue) - 1) . '?';
                    $where[] = "`$table`.`$filterKey` IN ($placeholders)";
                    $params = array_merge($params, $filterValue);
                } else {
                    $where[] = "`$table`.`$filterKey` = ?";
                    $params[] = $filterValue;
                }
                break;

            case 'total_amount':
            case 'balance':
            case 'amount':
            case 'credit_limit':
            case 'current_balance':
                if (is_array($filterValue) && count($filterValue) === 2) {
                    $where[] = "`$table`.`$filterKey` BETWEEN ? AND ?";
                    $params[] = $filterValue[0];
                    $params[] = $filterValue[1];
                } elseif (isset($filterValue['min'])) {
                    $where[] = "`$table`.`$filterKey` >= ?";
                    $params[] = $filterValue['min'];
                } elseif (isset($filterValue['max'])) {
                    $where[] = "`$table`.`$filterKey` <= ?";
                    $params[] = $filterValue['max'];
                }
                break;

            case 'invoice_date':
            case 'due_date':
            case 'bill_date':
            case 'payment_date':
            case 'entry_date':
            case 'last_login':
            case 'created_at':
                if (is_array($filterValue) && count($filterValue) === 2) {
                    $where[] = "`$table`.`$filterKey` BETWEEN ? AND ?";
                    $params[] = $filterValue[0] . ' 00:00:00';
                    $params[] = $filterValue[1] . ' 23:59:59';
                } elseif (isset($filterValue['from'])) {
                    $where[] = "`$table`.`$filterKey` >= ?";
                    $params[] = $filterValue['from'] . ' 00:00:00';
                } elseif (isset($filterValue['to'])) {
                    $where[] = "`$table`.`$filterKey` <= ?";
                    $params[] = $filterValue['to'] . ' 23:59:59';
                }
                break;

            case 'priority':
                if (is_array($filterValue)) {
                    $placeholders = str_repeat('?,', count($filterValue) - 1) . '?';
                    $where[] = "`$table`.`$filterKey` IN ($placeholders)";
                    $params = array_merge($params, $filterValue);
                } else {
                    $where[] = "`$table`.`$filterKey` = ?";
                    $params[] = $filterValue;
                }
                break;
        }
    }

    /**
     * Get select fields for search results
     */
    private function getSelectFields($table) {
        $baseFields = ["$table.*"];

        switch ($table) {
            case 'invoices':
                return $baseFields[0] . ", c.company_name as customer_name, c.customer_code";
            case 'bills':
                return $baseFields[0] . ", v.company_name as vendor_name, v.vendor_code";
            case 'payments_received':
                return $baseFields[0] . ", c.company_name as customer_name, i.invoice_number";
            case 'payments_made':
                return $baseFields[0] . ", v.company_name as vendor_name, b.bill_number";
            case 'tasks':
                return $baseFields[0] . ", u_assigned.full_name as assigned_to_name, u_created.full_name as created_by_name";
            default:
                return $baseFields[0];
        }
    }

    /**
     * Calculate facets for filtering
     */
    private function calculateFacets($table, $config, $query, $filters) {
        $facets = [];

        try {
            // Status facet
            if (in_array('status', $config['filters'])) {
                $stmt = $this->db->prepare("SELECT status, COUNT(*) as count FROM `$table` GROUP BY status");
                $stmt->execute();
                $facets['status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            }

            // Date range facets (for date fields)
            $dateFields = array_intersect($config['filters'], ['created_at', 'invoice_date', 'due_date', 'bill_date', 'payment_date']);
            if (!empty($dateFields)) {
                $facets['date_ranges'] = [
                    'today' => 'Today',
                    'week' => 'This Week',
                    'month' => 'This Month',
                    'quarter' => 'This Quarter',
                    'year' => 'This Year'
                ];
            }

            // Amount range facets
            $amountFields = array_intersect($config['filters'], ['total_amount', 'balance', 'amount', 'credit_limit']);
            if (!empty($amountFields)) {
                $stmt = $this->db->prepare("SELECT MIN($amountFields[0]) as min_val, MAX($amountFields[0]) as max_val FROM `$table` WHERE $amountFields[0] > 0");
                $stmt->execute();
                $range = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($range && $range['max_val'] > 0) {
                    $facets['amount_ranges'] = [
                        'min' => $range['min_val'],
                        'max' => $range['max_val']
                    ];
                }
            }

        } catch (Exception $e) {
            // Continue without facets if there's an error
        }

        return $facets;
    }

    /**
     * Save a search query for reuse
     */
    public function saveSearch($name, $query, $filters, $tables, $userId = null) {
        try {
            $userId = $userId ?: ($_SESSION['user']['id'] ?? null);

            $stmt = $this->db->prepare("
                INSERT INTO saved_searches (name, query, filters, tables, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name,
                $query,
                json_encode($filters),
                json_encode($tables),
                $userId
            ]);

            return ['success' => true, 'id' => $this->db->lastInsertId()];

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to save search: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get saved searches for a user
     */
    public function getSavedSearches($userId = null) {
        try {
            $userId = $userId ?: ($_SESSION['user']['id'] ?? null);

            $stmt = $this->db->prepare("
                SELECT * FROM saved_searches
                WHERE user_id = ? OR user_id IS NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId]);
            $searches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($searches as &$search) {
                $search['filters'] = json_decode($search['filters'], true);
                $search['tables'] = json_decode($search['tables'], true);
            }

            return $searches;

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to get saved searches: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a saved search
     */
    public function deleteSavedSearch($searchId, $userId = null) {
        try {
            $userId = $userId ?: ($_SESSION['user']['id'] ?? null);

            $stmt = $this->db->prepare("
                DELETE FROM saved_searches WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$searchId, $userId]);

            return ['success' => true];

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to delete saved search: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get search analytics
     */
    public function getSearchAnalytics($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_searches,
                    COUNT(DISTINCT user_id) as unique_users,
                    AVG(result_count) as avg_results,
                    MAX(result_count) as max_results,
                    DATE(created_at) as search_date
                FROM search_queries
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY search_date DESC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to get search analytics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearches($limit = 10, $days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT query, COUNT(*) as count, AVG(result_count) as avg_results
                FROM search_queries
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND query != ''
                GROUP BY query
                ORDER BY count DESC
                LIMIT ?
            ");
            $stmt->execute([$days, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to get popular searches: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Log search query for analytics
     */
    private function logSearchQuery($query, $filters, $tables, $resultCount) {
        try {
            $userId = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

            $stmt = $this->db->prepare("
                INSERT INTO search_queries (user_id, query, filters, tables, result_count, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $query,
                json_encode($filters),
                json_encode($tables),
                $resultCount,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

        } catch (Exception $e) {
            // Don't fail the search if logging fails
        }
    }

    /**
     * Get searchable tables configuration
     */
    public function getSearchableTables() {
        return $this->searchableTables;
    }

    /**
     * Quick search across all tables (for global search)
     */
    public function quickSearch($query, $limit = 5) {
        if (empty($query)) {
            return [];
        }

        $results = [];
        $tables = ['customers', 'vendors', 'invoices', 'bills', 'users'];

        foreach ($tables as $table) {
            if (!isset($this->searchableTables[$table])) {
                continue;
            }

            $config = $this->searchableTables[$table];
            $tableResults = $this->searchTable($table, $config, $query, [], $limit, 0);

            if (!empty($tableResults['results'])) {
                $results[$table] = [
                    'count' => $tableResults['total'],
                    'results' => array_slice($tableResults['results'], 0, $limit),
                    'label' => $this->getTableLabel($table)
                ];
            }
        }

        return $results;
    }

    /**
     * Get human-readable table label
     */
    private function getTableLabel($table) {
        $labels = [
            'customers' => 'Customers',
            'vendors' => 'Vendors',
            'invoices' => 'Invoices',
            'bills' => 'Bills',
            'payments_received' => 'Payments Received',
            'payments_made' => 'Payments Made',
            'journal_entries' => 'Journal Entries',
            'users' => 'Users',
            'tasks' => 'Tasks'
        ];

        return $labels[$table] ?? ucfirst(str_replace('_', ' ', $table));
    }
}
?>

