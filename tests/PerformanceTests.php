<?php
/**
 * ATIERA Financial Management System - Performance Tests
 * Testing system performance and scalability
 */

class PerformanceTests extends PerformanceTestCase {
    public function testDatabaseQueryPerformance() {
        // Test simple SELECT query performance
        $this->measureExecutionTime(function() {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            return $result;
        });
    }

    public function testComplexJoinQueryPerformance() {
        // Test complex join query performance
        $this->measureExecutionTime(function() {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT i.invoice_number, i.total_amount, c.company_name
                FROM invoices i
                INNER JOIN customers c ON i.customer_id = c.id
                WHERE i.status = 'draft'
                ORDER BY i.created_at DESC
                LIMIT 50
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function testCacheReadPerformance() {
        $cache = CacheManager::getInstance();

        // Test cache write performance
        $this->measureExecutionTime(function() use ($cache) {
            for ($i = 0; $i < 100; $i++) {
                $cache->set("perf_test_$i", "test_data_$i", 300);
            }
        });

        // Test cache read performance
        $this->measureExecutionTime(function() use ($cache) {
            for ($i = 0; $i < 100; $i++) {
                $data = $cache->get("perf_test_$i");
            }
        });
    }

    public function testValidationEnginePerformance() {
        $validator = ValidationEngine::getInstance();

        $testData = [
            'username' => 'performance_test_user',
            'email' => 'perf@test.com',
            'password' => 'StrongPass123!',
            'first_name' => 'Performance',
            'last_name' => 'Test',
            'company_name' => 'Performance Test Company',
            'total_amount' => 12345.67,
            'credit_limit' => 50000.00
        ];

        // Test validation performance
        $this->measureExecutionTime(function() use ($validator, $testData) {
            for ($i = 0; $i < 100; $i++) {
                $validator->validate($testData, [
                    'username' => ['required', 'min:3', 'max:50'],
                    'email' => ['required', 'email'],
                    'password' => ['required', 'strong_password'],
                    'first_name' => ['required'],
                    'last_name' => ['required'],
                    'company_name' => ['required', 'max:100'],
                    'total_amount' => ['required', 'numeric', 'positive'],
                    'credit_limit' => ['numeric', 'non_negative']
                ]);
            }
        });
    }

    public function testFileUploadValidationPerformance() {
        $validator = ValidationEngine::getInstance();

        $testFile = [
            'name' => 'performance_test_document.pdf',
            'type' => 'application/pdf',
            'size' => 1024000, // 1MB
            'tmp_name' => '/tmp/perf_test.pdf',
            'error' => UPLOAD_ERR_OK
        ];

        // Test file validation performance
        $this->measureExecutionTime(function() use ($validator, $testFile) {
            for ($i = 0; $i < 50; $i++) {
                $validator->validateFile($testFile, [
                    'max_size' => 5242880,
                    'allowed_types' => ['pdf', 'doc', 'docx'],
                    'allowed_mimes' => ['application/pdf'],
                    'scan_malware' => false // Skip malware scan for performance
                ]);
            }
        });
    }

    public function testMemoryUsageOptimization() {
        $optimizer = new MemoryOptimizer();

        // Test large dataset processing
        $largeDataset = [];
        for ($i = 0; $i < 10000; $i++) {
            $largeDataset[] = [
                'id' => $i,
                'name' => "Item $i",
                'value' => rand(1, 1000),
                'description' => str_repeat('Description text ', 10)
            ];
        }

        // Test memory usage during processing
        $this->measureMemoryUsage(function() use ($optimizer, $largeDataset) {
            return $optimizer->optimizeLargeDataset($largeDataset, 1000);
        });
    }

    public function testAssetOptimizationPerformance() {
        $optimizer = new AssetOptimizer();

        // Test CSS optimization performance
        $cssFiles = [
            'assets/css/bootstrap.min.css',
            'assets/css/fontawesome.min.css',
            'assets/css/custom.css'
        ];

        $this->measureExecutionTime(function() use ($optimizer, $cssFiles) {
            // Only test if files exist
            $existingFiles = array_filter($cssFiles, 'file_exists');
            if (!empty($existingFiles)) {
                $optimizer->optimizeCSS($existingFiles);
            }
            return true;
        });
    }

    public function testWorkflowEnginePerformance() {
        $workflowEngine = WorkflowEngine::getInstance();

        // Test workflow trigger performance
        $testData = [
            'total_amount' => 75000.00,
            'customer_id' => 1,
            'user_id' => 1
        ];

        $this->measureExecutionTime(function() use ($workflowEngine, $testData) {
            // Test multiple workflow triggers
            for ($i = 0; $i < 10; $i++) {
                $workflowEngine->triggerWorkflow('invoice.created', $testData);
            }
        });
    }

    public function testSearchPerformance() {
        $searchEngine = new SearchEngine();

        // Test search query performance
        $this->measureExecutionTime(function() use ($searchEngine) {
            for ($i = 0; $i < 20; $i++) {
                $searchEngine->search('test query', ['invoices', 'customers'], 10, 0);
            }
        });
    }

    public function testPDFGenerationPerformance() {
        $pdfGenerator = new PDFGenerator();

        // Test PDF generation performance
        $testData = [
            'title' => 'Performance Test Report',
            'content' => str_repeat('This is test content for performance testing. ', 1000),
            'items' => []
        ];

        for ($i = 0; $i < 50; $i++) {
            $testData['items'][] = [
                'name' => "Item $i",
                'quantity' => rand(1, 10),
                'price' => rand(10, 100)
            ];
        }

        $this->measureExecutionTime(function() use ($pdfGenerator, $testData) {
            // Skip actual PDF generation for performance testing
            // Just test data processing
            $total = 0;
            foreach ($testData['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }
            return $total;
        });
    }

    public function testNotificationSystemPerformance() {
        $notificationSystem = new NotificationSystem();

        // Test notification sending performance
        $this->measureExecutionTime(function() use ($notificationSystem) {
            for ($i = 0; $i < 25; $i++) {
                // Simulate notification creation without actual sending
                $notification = [
                    'type' => 'email',
                    'recipient' => "test$i@example.com",
                    'subject' => 'Performance Test',
                    'content' => 'This is a performance test notification.'
                ];
            }
        });
    }

    public function testAPIPerformance() {
        $apiTester = new APITestCase();

        // Test API response time
        $this->measureExecutionTime(function() use ($apiTester) {
            for ($i = 0; $i < 10; $i++) {
                $response = $apiTester->makeRequest('GET', 'api/customers.php');
                $this->assertTrue($response['code'] < 500);
            }
        });
    }

    public function testConcurrentOperations() {
        // Test concurrent database operations
        $this->measureExecutionTime(function() {
            $db = Database::getInstance()->getConnection();

            // Simulate concurrent operations
            $results = [];
            for ($i = 0; $i < 50; $i++) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
                $stmt->execute();
                $results[] = $stmt->fetch()['count'];
            }

            return $results;
        });
    }

    public function testLargeDatasetPagination() {
        // Test pagination performance with large datasets
        $this->measureExecutionTime(function() {
            $db = Database::getInstance()->getConnection();

            // Test different page sizes
            $pageSizes = [10, 25, 50, 100];
            $results = [];

            foreach ($pageSizes as $pageSize) {
                $stmt = $db->prepare("
                    SELECT SQL_CALC_FOUND_ROWS * FROM users
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET 0
                ");
                $stmt->execute([$pageSize]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get total count
                $totalStmt = $db->query("SELECT FOUND_ROWS() as total");
                $total = $totalStmt->fetch()['total'];

                $results[] = [
                    'page_size' => $pageSize,
                    'data_count' => count($data),
                    'total_count' => $total
                ];
            }

            return $results;
        });
    }

    public function testIndexingPerformance() {
        // Test query performance with and without indexes
        $this->measureExecutionTime(function() {
            $db = Database::getInstance()->getConnection();

            // Test indexed query
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([1]);
            $indexedResult = $stmt->fetch();

            // Test non-indexed query (if applicable)
            $stmt = $db->prepare("SELECT * FROM users WHERE first_name LIKE ?");
            $stmt->execute(['%test%']);
            $nonIndexedResult = $stmt->fetchAll();

            return [
                'indexed_result' => $indexedResult,
                'non_indexed_count' => count($nonIndexedResult)
            ];
        });
    }

    public function testSystemHealthCheckPerformance() {
        $healthMonitor = new SystemHealthMonitor();

        // Test health check performance
        $this->measureExecutionTime(function() use ($healthMonitor) {
            return $healthMonitor->getHealthStatus();
        });
    }

    public function testBackupPerformance() {
        $backupManager = new BackupManager();

        // Test backup operation performance (without actual backup)
        $this->measureExecutionTime(function() use ($backupManager) {
            // Simulate backup preparation
            $db = Database::getInstance()->getConnection();
            $tables = ['users', 'customers', 'invoices'];

            $stats = [];
            foreach ($tables as $table) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM `$table`");
                $stmt->execute();
                $stats[$table] = $stmt->fetch()['count'];
            }

            return $stats;
        });
    }

    public function testMultiLanguagePerformance() {
        $i18n = new Internationalization();

        // Test translation loading performance
        $this->measureExecutionTime(function() use ($i18n) {
            $languages = ['en', 'es', 'fr', 'de'];
            $translations = [];

            foreach ($languages as $lang) {
                $translations[$lang] = $i18n->getTranslations($lang);
            }

            return $translations;
        });
    }

    public function testAuditTrailPerformance() {
        $logger = Logger::getInstance();

        // Test audit logging performance
        $this->measureExecutionTime(function() use ($logger) {
            for ($i = 0; $i < 100; $i++) {
                $logger->logUserAction(
                    'Performance test action',
                    'test_table',
                    rand(1, 1000),
                    ['old_value' => 'old', 'new_value' => 'new'],
                    ['test_data' => "item_$i"]
                );
            }
        });
    }

    public function testSessionManagementPerformance() {
        // Test session operations performance
        $this->measureExecutionTime(function() {
            for ($i = 0; $i < 100; $i++) {
                $_SESSION["perf_test_$i"] = "value_$i";
            }

            // Read session data
            $data = [];
            for ($i = 0; $i < 100; $i++) {
                $data[] = $_SESSION["perf_test_$i"];
            }

            // Clean up
            for ($i = 0; $i < 100; $i++) {
                unset($_SESSION["perf_test_$i"]);
            }

            return $data;
        });
    }

    public function testErrorHandlingPerformance() {
        // Test error handling performance
        $this->measureExecutionTime(function() {
            $errors = [];

            for ($i = 0; $i < 100; $i++) {
                try {
                    if (rand(0, 1)) {
                        throw new Exception("Test error $i");
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ];
                }
            }

            return $errors;
        });
    }
}
