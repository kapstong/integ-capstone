<?php
/**
 * ATIERA Financial Management System - Performance Monitoring & Optimization
 * Comprehensive performance monitoring and optimization tools
 */

class PerformanceMonitor {
    private static $instance = null;
    private $startTime;
    private $memoryStart;
    private $queries = [];
    private $cacheHits = 0;
    private $cacheMisses = 0;
    private $enabled = true;

    private function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage(true);
        $this->initializeDatabaseMonitoring();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize database query monitoring
     */
    private function initializeDatabaseMonitoring() {
        if ($this->enabled) {
            $db = Database::getInstance()->getConnection();
            $db->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['PerformanceStatement', []]);
        }
    }

    /**
     * Start timing a specific operation
     */
    public function startTimer($operation) {
        if (!$this->enabled) return null;

        return [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
    }

    /**
     * End timing and record metrics
     */
    public function endTimer($timerData) {
        if (!$this->enabled || !$timerData) return null;

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'operation' => $timerData['operation'],
            'duration' => $endTime - $timerData['start_time'],
            'memory_used' => $endMemory - $timerData['start_memory'],
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => time()
        ];

        // Log slow operations
        if ($metrics['duration'] > 1.0) { // More than 1 second
            Logger::getInstance()->warning("Slow operation detected", $metrics);
        }

        return $metrics;
    }

    /**
     * Record database query
     */
    public function recordQuery($sql, $duration, $params = []) {
        if (!$this->enabled) return;

        $this->queries[] = [
            'sql' => $sql,
            'duration' => $duration,
            'params' => $params,
            'timestamp' => microtime(true)
        ];

        // Log slow queries
        if ($duration > 0.5) { // More than 500ms
            Logger::getInstance()->warning("Slow query detected", [
                'sql' => $this->sanitizeSql($sql),
                'duration' => $duration,
                'params' => $params
            ]);
        }
    }

    /**
     * Record cache hit
     */
    public function recordCacheHit() {
        $this->cacheHits++;
    }

    /**
     * Record cache miss
     */
    public function recordCacheMiss() {
        $this->cacheMisses++;
    }

    /**
     * Get performance statistics
     */
    public function getStats() {
        if (!$this->enabled) {
            return ['monitoring_disabled' => true];
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $stats = [
            'total_execution_time' => $endTime - $this->startTime,
            'total_memory_used' => $endMemory - $this->memoryStart,
            'peak_memory_usage' => memory_get_peak_usage(true),
            'queries_executed' => count($this->queries),
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'cache_hit_ratio' => $this->cacheHits + $this->cacheMisses > 0 ?
                ($this->cacheHits / ($this->cacheHits + $this->cacheMisses)) * 100 : 0
        ];

        // Calculate query statistics
        if (!empty($this->queries)) {
            $queryTimes = array_column($this->queries, 'duration');
            $stats['query_stats'] = [
                'total_time' => array_sum($queryTimes),
                'average_time' => array_sum($queryTimes) / count($queryTimes),
                'slowest_query' => max($queryTimes),
                'fastest_query' => min($queryTimes)
            ];
        }

        return $stats;
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries($threshold = 0.1) {
        return array_filter($this->queries, function($query) use ($threshold) {
            return $query['duration'] > $threshold;
        });
    }

    /**
     * Sanitize SQL for logging
     */
    private function sanitizeSql($sql) {
        // Remove actual values from INSERT/UPDATE statements for security
        $sql = preg_replace('/(VALUES\s*\()\s*[^)]+\s*(\))/i', '$1...$2', $sql);
        $sql = preg_replace('/(SET\s+[^=]+=\s*)\'[^\']*\'/i', '$1\'...\'', $sql);
        $sql = preg_replace('/(SET\s+[^=]+=\s*)"[^"]*"/i', '$1"..."', $sql);
        return $sql;
    }

    /**
     * Enable/disable monitoring
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }

    /**
     * Reset monitoring data
     */
    public function reset() {
        $this->queries = [];
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage(true);
    }
}

/**
 * Performance PDO Statement Wrapper
 */
class PerformanceStatement extends PDOStatement {
    protected function __construct() {}

    public function execute($params = null) {
        $monitor = PerformanceMonitor::getInstance();
        $timer = $monitor->startTimer('database_query');

        $result = parent::execute($params);

        $metrics = $monitor->endTimer($timer);
        if ($metrics) {
            $monitor->recordQuery($this->queryString, $metrics['duration'], $params);
        }

        return $result;
    }
}

/**
 * Database Query Optimizer
 */
class QueryOptimizer {
    private $db;
    private $cache;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Analyze query performance
     */
    public function analyzeQuery($sql, $params = []) {
        $analysis = [
            'query' => $sql,
            'params' => $params,
            'execution_time' => 0,
            'result_count' => 0,
            'suggestions' => []
        ];

        // Execute query with timing
        $monitor = PerformanceMonitor::getInstance();
        $timer = $monitor->startTimer('query_analysis');

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $metrics = $monitor->endTimer($timer);
            $analysis['execution_time'] = $metrics['duration'];
            $analysis['result_count'] = count($results);

            // Analyze query for optimization opportunities
            $analysis['suggestions'] = $this->analyzeQueryStructure($sql, $results);

        } catch (Exception $e) {
            $analysis['error'] = $e->getMessage();
        }

        return $analysis;
    }

    /**
     * Analyze query structure for optimization
     */
    private function analyzeQueryStructure($sql, $results) {
        $suggestions = [];

        // Check for SELECT * usage
        if (preg_match('/SELECT\s+\*/i', $sql)) {
            $suggestions[] = "Consider selecting specific columns instead of SELECT *";
        }

        // Check for missing WHERE clauses on large tables
        if (preg_match('/FROM\s+(\w+)/i', $sql, $matches)) {
            $table = $matches[1];
            if (!preg_match('/WHERE/i', $sql) && $this->isLargeTable($table)) {
                $suggestions[] = "Consider adding WHERE clause for table '$table'";
            }
        }

        // Check for unindexed queries
        if (preg_match_all('/WHERE\s+([^=<>!\s]+)\s*[=<>!]/i', $sql, $matches)) {
            foreach ($matches[1] as $column) {
                $column = trim($column);
                if (!$this->isColumnIndexed($column)) {
                    $suggestions[] = "Consider adding index on column '$column'";
                }
            }
        }

        // Check result set size
        if (count($results) > 1000) {
            $suggestions[] = "Large result set (" . count($results) . " rows) - consider pagination";
        }

        return $suggestions;
    }

    /**
     * Check if table is considered large
     */
    private function isLargeTable($tableName) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM `$tableName`");
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'] > 10000; // Consider tables with >10k rows as large
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if column is indexed
     */
    private function isColumnIndexed($columnName) {
        // This is a simplified check - in production you'd query information_schema
        $indexedColumns = [
            'id', 'user_id', 'customer_id', 'vendor_id', 'invoice_id',
            'bill_id', 'account_id', 'created_at', 'updated_at', 'status'
        ];

        return in_array($columnName, $indexedColumns);
    }

    /**
     * Get table statistics
     */
    public function getTableStats($tableName) {
        try {
            $stmt = $this->db->prepare("SHOW TABLE STATUS LIKE ?");
            $stmt->execute([$tableName]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'table' => $tableName,
                'rows' => $stats['Rows'] ?? 0,
                'data_size' => $stats['Data_length'] ?? 0,
                'index_size' => $stats['Index_length'] ?? 0,
                'engine' => $stats['Engine'] ?? 'Unknown'
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Suggest database optimizations
     */
    public function getOptimizationSuggestions() {
        $suggestions = [];

        // Check for missing indexes on foreign keys
        $foreignKeys = $this->getForeignKeyColumns();
        foreach ($foreignKeys as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->isColumnIndexed($column)) {
                    $suggestions[] = "Add index on foreign key column '$table.$column'";
                }
            }
        }

        // Check table sizes
        $tables = ['users', 'invoices', 'bills', 'journal_entries', 'payments_received'];
        foreach ($tables as $table) {
            $stats = $this->getTableStats($table);
            if (isset($stats['rows']) && $stats['rows'] > 50000) {
                $suggestions[] = "Consider partitioning table '$table' (" . $stats['rows'] . " rows)";
            }
        }

        return $suggestions;
    }

    /**
     * Get foreign key columns
     */
    private function getForeignKeyColumns() {
        // Simplified - in production you'd query information_schema
        return [
            'users' => [],
            'invoices' => ['customer_id'],
            'bills' => ['vendor_id'],
            'payments_received' => ['customer_id', 'invoice_id'],
            'payments_made' => ['vendor_id', 'bill_id'],
            'journal_entries' => ['created_by', 'posted_by'],
            'tasks' => ['assigned_to', 'created_by']
        ];
    }
}

/**
 * Asset Optimization Manager
 */
class AssetOptimizer {
    private $cache;

    public function __construct() {
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Optimize CSS files
     */
    public function optimizeCSS($cssFiles) {
        $combinedCSS = '';
        $cacheKey = 'css_' . md5(implode('', $cssFiles));

        // Check cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Combine and minify CSS
        foreach ($cssFiles as $file) {
            if (file_exists($file)) {
                $css = file_get_contents($file);
                $css = $this->minifyCSS($css);
                $combinedCSS .= $css . "\n";
            }
        }

        // Cache the result
        $this->cache->set($cacheKey, $combinedCSS, 3600); // 1 hour

        return $combinedCSS;
    }

    /**
     * Optimize JavaScript files
     */
    public function optimizeJS($jsFiles) {
        $combinedJS = '';
        $cacheKey = 'js_' . md5(implode('', $jsFiles));

        // Check cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Combine and minify JS
        foreach ($jsFiles as $file) {
            if (file_exists($file)) {
                $js = file_get_contents($file);
                $js = $this->minifyJS($js);
                $combinedJS .= $js . ";\n";
            }
        }

        // Cache the result
        $this->cache->set($cacheKey, $combinedJS, 3600); // 1 hour

        return $combinedJS;
    }

    /**
     * Minify CSS
     */
    private function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace([' {', '{ '], '{', $css);
        $css = str_replace([' }', '} '], '}', $css);
        $css = str_replace([' ,', ', '], ',', $css);
        $css = str_replace([' :', ': '], ':', $css);
        $css = str_replace([' ;', '; '], ';', $css);

        return $css;
    }

    /**
     * Minify JavaScript
     */
    private function minifyJS($js) {
        // Basic minification - remove comments and extra whitespace
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        $js = preg_replace('!//[^\n]*!', '', $js);
        $js = str_replace(["\r\n", "\r", "\n", "\t"], '', $js);
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }

    /**
     * Generate asset URLs with cache busting
     */
    public function getAssetUrl($file, $type = 'css') {
        $filePath = $file;
        $version = '';

        if (file_exists($filePath)) {
            $version = filemtime($filePath);
        }

        return $file . '?v=' . $version;
    }
}

/**
 * Memory Optimization Manager
 */
class MemoryOptimizer {
    /**
     * Optimize memory usage for large datasets
     */
    public function optimizeLargeDataset($data, $chunkSize = 1000) {
        if (!is_array($data) || count($data) <= $chunkSize) {
            return $data;
        }

        // Process in chunks to reduce memory usage
        $optimized = [];
        $chunks = array_chunk($data, $chunkSize);

        foreach ($chunks as $chunk) {
            // Process chunk (you can add filtering, transformation here)
            $optimized = array_merge($optimized, $chunk);

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return $optimized;
    }

    /**
     * Stream large file processing
     */
    public function processLargeFile($filePath, $callback, $chunkSize = 8192) {
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new Exception("Cannot open file: $filePath");
        }

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $callback($chunk);
        }

        fclose($handle);
    }

    /**
     * Monitor memory usage
     */
    public function getMemoryStats() {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'available' => $this->getAvailableMemory()
        ];
    }

    /**
     * Get available memory
     */
    private function getAvailableMemory() {
        $limit = ini_get('memory_limit');
        $current = memory_get_usage(true);

        if ($limit === '-1') {
            return 'unlimited';
        }

        $limitBytes = $this->convertToBytes($limit);
        return $limitBytes - $current;
    }

    /**
     * Convert memory string to bytes
     */
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return (int)$value;
    }
}

/**
 * Performance Profiler
 */
class PerformanceProfiler {
    private $profiles = [];
    private $currentProfile = null;

    /**
     * Start profiling
     */
    public function start($name) {
        $this->currentProfile = [
            'name' => $name,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'checkpoints' => []
        ];
    }

    /**
     * Add checkpoint
     */
    public function checkpoint($name) {
        if (!$this->currentProfile) return;

        $this->currentProfile['checkpoints'][] = [
            'name' => $name,
            'time' => microtime(true),
            'memory' => memory_get_usage(true)
        ];
    }

    /**
     * End profiling
     */
    public function end() {
        if (!$this->currentProfile) return null;

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $this->currentProfile['end_time'] = $endTime;
        $this->currentProfile['end_memory'] = $endMemory;
        $this->currentProfile['total_time'] = $endTime - $this->currentProfile['start_time'];
        $this->currentProfile['total_memory'] = $endMemory - $this->currentProfile['start_memory'];

        // Calculate checkpoint deltas
        $previousTime = $this->currentProfile['start_time'];
        $previousMemory = $this->currentProfile['start_memory'];

        foreach ($this->currentProfile['checkpoints'] as &$checkpoint) {
            $checkpoint['time_delta'] = $checkpoint['time'] - $previousTime;
            $checkpoint['memory_delta'] = $checkpoint['memory'] - $previousMemory;
            $previousTime = $checkpoint['time'];
            $previousMemory = $checkpoint['memory'];
        }

        $profile = $this->currentProfile;
        $this->profiles[] = $profile;
        $this->currentProfile = null;

        return $profile;
    }

    /**
     * Get all profiles
     */
    public function getProfiles() {
        return $this->profiles;
    }

    /**
     * Get slow profiles
     */
    public function getSlowProfiles($threshold = 1.0) {
        return array_filter($this->profiles, function($profile) use ($threshold) {
            return $profile['total_time'] > $threshold;
        });
    }

    /**
     * Export profiles for analysis
     */
    public function exportProfiles($format = 'json') {
        switch ($format) {
            case 'json':
                return json_encode($this->profiles, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV();
            default:
                return serialize($this->profiles);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCSV() {
        $csv = "Profile Name,Total Time,Total Memory,Checkpoints\n";

        foreach ($this->profiles as $profile) {
            $checkpointCount = count($profile['checkpoints']);
            $csv .= sprintf(
                "%s,%.4f,%d,%d\n",
                $profile['name'],
                $profile['total_time'],
                $profile['total_memory'],
                $checkpointCount
            );
        }

        return $csv;
    }
}

/**
 * System Health Monitor
 */
class SystemHealthMonitor {
    private $cache;
    private $db;

    public function __construct() {
        $this->cache = CacheManager::getInstance();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get system health status
     */
    public function getHealthStatus() {
        $status = [
            'overall' => 'healthy',
            'checks' => []
        ];

        // Database connectivity
        $status['checks']['database'] = $this->checkDatabaseHealth();

        // Cache health
        $status['checks']['cache'] = $this->checkCacheHealth();

        // File system
        $status['checks']['filesystem'] = $this->checkFilesystemHealth();

        // Memory usage
        $status['checks']['memory'] = $this->checkMemoryHealth();

        // Determine overall status
        foreach ($status['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $status['overall'] = 'critical';
                break;
            } elseif ($check['status'] === 'warning' && $status['overall'] === 'healthy') {
                $status['overall'] = 'warning';
            }
        }

        return $status;
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth() {
        try {
            $start = microtime(true);
            $stmt = $this->db->query("SELECT 1");
            $result = $stmt->fetch();
            $responseTime = microtime(true) - $start;

            $status = $responseTime > 1.0 ? 'warning' : 'healthy';

            return [
                'status' => $status,
                'response_time' => $responseTime,
                'message' => $status === 'healthy' ? 'Database responding normally' : 'Slow database response'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    /**
     * Check cache health
     */
    private function checkCacheHealth() {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';

            $this->cache->set($testKey, $testValue, 60);
            $retrieved = $this->cache->get($testKey);
            $this->cache->delete($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache system operational'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Cache read/write inconsistency'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
                'message' => 'Cache system failure'
            ];
        }
    }

    /**
     * Check filesystem health
     */
    private function checkFilesystemHealth() {
        $checks = [];

        // Check writable directories
        $writableDirs = ['logs', 'cache/files', 'uploads'];
        foreach ($writableDirs as $dir) {
            $fullPath = __DIR__ . '/../' . $dir;
            if (!is_writable($fullPath)) {
                $checks[] = "Directory not writable: $dir";
            }
        }

        // Check disk space
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $freePercentage = ($diskFree / $diskTotal) * 100;

        if ($freePercentage < 10) {
            $checks[] = 'Low disk space: ' . round($freePercentage, 1) . '% free';
        }

        if (empty($checks)) {
            return [
                'status' => 'healthy',
                'message' => 'Filesystem healthy',
                'disk_free_percentage' => round($freePercentage, 1)
            ];
        } else {
            return [
                'status' => 'warning',
                'message' => 'Filesystem issues detected',
                'issues' => $checks
            ];
        }
    }

    /**
     * Check memory health
     */
    private function checkMemoryHealth() {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return [
                'status' => 'healthy',
                'message' => 'Memory usage normal (no limit set)',
                'current' => $current,
                'peak' => $peak
            ];
        }

        $limitBytes = $this->convertToBytes($limit);
        $usagePercentage = ($current / $limitBytes) * 100;

        if ($usagePercentage > 80) {
            return [
                'status' => 'warning',
                'message' => 'High memory usage: ' . round($usagePercentage, 1) . '%',
                'current' => $current,
                'peak' => $peak,
                'limit' => $limitBytes
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Memory usage normal',
            'current' => $current,
            'peak' => $peak,
            'limit' => $limitBytes
        ];
    }

    /**
     * Convert memory string to bytes
     */
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return (int)$value;
    }
}
?>

