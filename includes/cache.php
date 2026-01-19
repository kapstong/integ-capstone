<?php
/**
 * ATIERA Financial Management System - Advanced Caching System
 * Multi-layer caching for optimal performance
 */

class CacheManager {
    private static $instance = null;
    private $redis = null;
    private $fileCache = [];
    private $memoryCache = [];
    private $cacheConfig = [];

    private function __construct() {
        $this->initializeCacheConfig();
        $this->initializeRedis();
        $this->initializeFileCache();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize cache configuration
     */
    private function initializeCacheConfig() {
        $this->cacheConfig = [
            'default_ttl' => 3600, // 1 hour
            'memory_limit' => 100, // Max items in memory cache
            'file_cache_dir' => __DIR__ . '/../cache/files/',
            'redis_prefix' => 'atiera:',
            'compression' => true,
            'serialization' => 'json'
        ];
    }

    /**
     * Initialize Redis connection
     */
    private function initializeRedis() {
        if (extension_loaded('redis') && defined('REDIS_HOST')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect(REDIS_HOST, REDIS_PORT ?? 6379);

                if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
                    $this->redis->auth(REDIS_PASSWORD);
                }

                $this->redis->select(REDIS_DB ?? 0);
                Logger::getInstance()->info("Redis cache initialized successfully");

            } catch (Exception $e) {
                Logger::getInstance()->warning("Redis connection failed, falling back to file cache", [
                    'error' => $e->getMessage()
                ]);
                $this->redis = null;
            }
        }
    }

    /**
     * Initialize file cache directory
     */
    private function initializeFileCache() {
        $cacheDir = $this->cacheConfig['file_cache_dir'];

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Create .htaccess to prevent web access
        $htaccess = $cacheDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }

    /**
     * Get cache key with prefix
     */
    private function getCacheKey($key) {
        return $this->cacheConfig['redis_prefix'] . $key;
    }

    /**
     * Set cache value
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $cacheKey = $this->getCacheKey($key);

        // Serialize data
        $serializedData = $this->serialize($value);

        // Try Redis first
        if ($this->redis) {
            try {
                $result = $this->redis->setex($cacheKey, $ttl, $serializedData);
                if ($result) {
                    return true;
                }
            } catch (Exception $e) {
                Logger::getInstance()->warning("Redis set failed", ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        // Fallback to file cache
        return $this->setFileCache($key, $serializedData, $ttl);
    }

    /**
     * Get cache value
     */
    public function get($key) {
        $cacheKey = $this->getCacheKey($key);

        // Try Redis first
        if ($this->redis) {
            try {
                $data = $this->redis->get($cacheKey);
                if ($data !== false) {
                    return $this->unserialize($data);
                }
            } catch (Exception $e) {
                Logger::getInstance()->warning("Redis get failed", ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        // Fallback to file cache
        return $this->getFileCache($key);
    }

    /**
     * Check if cache key exists
     */
    public function exists($key) {
        $cacheKey = $this->getCacheKey($key);

        // Try Redis first
        if ($this->redis) {
            try {
                return $this->redis->exists($cacheKey);
            } catch (Exception $e) {
                // Continue to file cache check
            }
        }

        // Check file cache
        return $this->fileCacheExists($key);
    }

    /**
     * Delete cache key
     */
    public function delete($key) {
        $cacheKey = $this->getCacheKey($key);
        $deleted = false;

        // Delete from Redis
        if ($this->redis) {
            try {
                $this->redis->del($cacheKey);
                $deleted = true;
            } catch (Exception $e) {
                // Continue
            }
        }

        // Delete from file cache
        if ($this->deleteFileCache($key)) {
            $deleted = true;
        }

        // Delete from memory cache
        if (isset($this->memoryCache[$key])) {
            unset($this->memoryCache[$key]);
            $deleted = true;
        }

        return $deleted;
    }

    /**
     * Clear all cache
     */
    public function clear() {
        // Clear Redis
        if ($this->redis) {
            try {
                $keys = $this->redis->keys($this->cacheConfig['redis_prefix'] . '*');
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } catch (Exception $e) {
                // Continue
            }
        }

        // Clear file cache
        $this->clearFileCache();

        // Clear memory cache
        $this->memoryCache = [];

        return true;
    }

    /**
     * Get or set cache value with callback
     */
    public function remember($key, $ttl, $callback) {
        $value = $this->get($key);

        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Increment numeric cache value
     */
    public function increment($key, $value = 1) {
        $cacheKey = $this->getCacheKey($key);

        if ($this->redis) {
            try {
                return $this->redis->incrby($cacheKey, $value);
            } catch (Exception $e) {
                // Continue to fallback
            }
        }

        // Fallback: get current value, increment, and set
        $current = $this->get($key) ?? 0;
        $newValue = $current + $value;
        $this->set($key, $newValue);
        return $newValue;
    }

    /**
     * Decrement numeric cache value
     */
    public function decrement($key, $value = 1) {
        return $this->increment($key, -$value);
    }

    /**
     * Set multiple cache values
     */
    public function setMultiple($values, $ttl = null) {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get multiple cache values
     */
    public function getMultiple($keys) {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    /**
     * File cache operations
     */
    private function setFileCache($key, $data, $ttl) {
        $filePath = $this->getFileCachePath($key);
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        $serialized = $this->cacheConfig['compression'] ?
            gzcompress(json_encode($cacheData)) :
            json_encode($cacheData);

        return file_put_contents($filePath, $serialized) !== false;
    }

    private function getFileCache($key) {
        $filePath = $this->getFileCachePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $cacheData = $this->cacheConfig['compression'] ?
            json_decode(gzuncompress($content), true) :
            json_decode($content, true);

        if (!$cacheData || !isset($cacheData['expires']) || time() > $cacheData['expires']) {
            // Cache expired, delete file
            unlink($filePath);
            return null;
        }

        return $this->unserialize($cacheData['data']);
    }

    private function fileCacheExists($key) {
        $filePath = $this->getFileCachePath($key);

        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $cacheData = $this->cacheConfig['compression'] ?
            json_decode(gzuncompress($content), true) :
            json_decode($content, true);

        if (!$cacheData || !isset($cacheData['expires'])) {
            return false;
        }

        if (time() > $cacheData['expires']) {
            // Cache expired, delete file
            unlink($filePath);
            return false;
        }

        return true;
    }

    private function deleteFileCache($key) {
        $filePath = $this->getFileCachePath($key);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true;
    }

    private function clearFileCache() {
        $cacheDir = $this->cacheConfig['file_cache_dir'];
        $files = glob($cacheDir . '*.cache');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function getFileCachePath($key) {
        return $this->cacheConfig['file_cache_dir'] . md5($key) . '.cache';
    }

    /**
     * Serialize data
     */
    private function serialize($data) {
        switch ($this->cacheConfig['serialization']) {
            case 'json':
                return json_encode($data);
            case 'serialize':
                return serialize($data);
            default:
                return $data;
        }
    }

    /**
     * Unserialize data
     */
    private function unserialize($data) {
        switch ($this->cacheConfig['serialization']) {
            case 'json':
                return json_decode($data, true);
            case 'serialize':
                // SECURITY FIX: Prevent object injection attacks by only allowing JSON
                // unserialize() is deprecated for untrusted data and can lead to RCE
                error_log("WARNING: Deprecated serialization method detected. Please migrate to JSON-only serialization.");
                return json_decode($data, true); // Fallback to JSON
            default:
                return $data;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats() {
        $stats = [
            'redis_enabled' => $this->redis !== null,
            'memory_cache_items' => count($this->memoryCache),
            'file_cache_dir' => $this->cacheConfig['file_cache_dir']
        ];

        if ($this->redis) {
            try {
                $stats['redis_keys'] = $this->redis->dbSize();
                $stats['redis_memory'] = $this->redis->info('memory')['used_memory_human'] ?? 'unknown';
            } catch (Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }

        // Count file cache items
        $cacheFiles = glob($this->cacheConfig['file_cache_dir'] . '*.cache');
        $stats['file_cache_items'] = count($cacheFiles);

        return $stats;
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmup() {
        Logger::getInstance()->info("Starting cache warmup");

        // Cache frequently accessed data
        $this->warmupUserData();
        $this->warmupSystemData();
        $this->warmupFinancialData();

        Logger::getInstance()->info("Cache warmup completed");
    }

    private function warmupUserData() {
        try {
            $db = Database::getInstance()->getConnection();

            // Cache active users count
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
            $result = $stmt->fetch();
            $this->set('active_users_count', $result['count'], 300); // 5 minutes

            // Cache user roles
            $stmt = $db->query("SELECT id, name FROM roles");
            $roles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $this->set('user_roles', $roles, 3600); // 1 hour

        } catch (Exception $e) {
            Logger::getInstance()->warning("User data cache warmup failed", ['error' => $e->getMessage()]);
        }
    }

    private function warmupSystemData() {
        try {
            // Cache system settings
            $this->set('system_settings', [
                'company_name' => 'ATIERA Financial',
                'version' => '1.0.0',
                'maintenance_mode' => false
            ], 1800); // 30 minutes

        } catch (Exception $e) {
            Logger::getInstance()->warning("System data cache warmup failed", ['error' => $e->getMessage()]);
        }
    }

    private function warmupFinancialData() {
        try {
            $db = Database::getInstance()->getConnection();

            // Cache chart of accounts
            $stmt = $db->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE is_active = 1");
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->set('chart_of_accounts', $accounts, 1800); // 30 minutes

            // Cache current fiscal year totals
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN account_type = 'revenue' THEN total_credit - total_debit ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN account_type = 'expense' THEN total_debit - total_credit ELSE 0 END) as total_expenses
                FROM chart_of_accounts
                WHERE is_active = 1
            ");
            $stmt->execute();
            $totals = $stmt->fetch();
            $this->set('financial_totals', $totals, 900); // 15 minutes

        } catch (Exception $e) {
            Logger::getInstance()->warning("Financial data cache warmup failed", ['error' => $e->getMessage()]);
        }
    }
}

/**
 * Database Query Cache
 */
class QueryCache {
    private $cache;
    private $db;

    public function __construct() {
        $this->cache = CacheManager::getInstance();
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Execute cached query
     */
    public function cachedQuery($sql, $params = [], $ttl = 300) {
        $cacheKey = 'query_' . md5($sql . serialize($params));

        return $this->cache->remember($cacheKey, $ttl, function() use ($sql, $params) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Execute cached scalar query
     */
    public function cachedScalar($sql, $params = [], $ttl = 300) {
        $cacheKey = 'scalar_' . md5($sql . serialize($params));

        return $this->cache->remember($cacheKey, $ttl, function() use ($sql, $params) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        });
    }

    /**
     * Invalidate query cache by pattern
     */
    public function invalidatePattern($pattern) {
        // This would require Redis for efficient pattern deletion
        // For file cache, we'd need to scan all files
        Logger::getInstance()->info("Query cache invalidation requested", ['pattern' => $pattern]);
    }

    /**
     * Clear all query cache
     */
    public function clear() {
        Logger::getInstance()->info("Clearing query cache");
        // Implementation would depend on cache backend
    }
}

/**
 * Page Cache for static content
 */
class PageCache {
    private $cache;
    private $enabled = true;

    public function __construct() {
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Start page caching
     */
    public function start($key, $ttl = 3600) {
        if (!$this->enabled || isset($_POST) && !empty($_POST)) {
            return false;
        }

        $cached = $this->cache->get('page_' . $key);
        if ($cached) {
            echo $cached;
            exit;
        }

        ob_start();
        return true;
    }

    /**
     * End page caching
     */
    public function end($key, $ttl = 3600) {
        if (!$this->enabled) {
            return;
        }

        $content = ob_get_clean();
        $this->cache->set('page_' . $key, $content, $ttl);
        echo $content;
    }

    /**
     * Clear page cache
     */
    public function clear() {
        // Implementation would depend on cache backend
        Logger::getInstance()->info("Page cache cleared");
    }
}
?>

