<?php
/**
 * ATIERA Financial Management System - Testing Framework
 * Comprehensive testing framework for unit, integration, and API testing
 */

class TestFramework {
    private static $instance = null;
    private $tests = [];
    private $results = [];
    private $currentTest = null;
    private $startTime;
    private $memoryStart;
    private $config = [];

    private function __construct() {
        $this->initializeConfig();
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage(true);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize testing configuration
     */
    private function initializeConfig() {
        $this->config = [
            'test_database' => 'atiera_test',
            'backup_database' => true,
            'verbose' => true,
            'stop_on_failure' => false,
            'coverage_enabled' => true,
            'report_format' => 'html',
            'timeout' => 30, // seconds
            'memory_limit' => '256M'
        ];
    }

    /**
     * Add a test case
     */
    public function addTest($testClass, $testMethod, $description = '') {
        $this->tests[] = [
            'class' => $testClass,
            'method' => $testMethod,
            'description' => $description ?: $testMethod,
            'status' => 'pending'
        ];
    }

    /**
     * Run all tests
     */
    public function runTests() {
        $this->results = [];
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        echo "Starting Test Suite...\n";
        echo "================================\n\n";

        foreach ($this->tests as $index => &$test) {
            $this->currentTest = $test;

            echo "Running: {$test['class']}::{$test['method']} - {$test['description']}\n";

            try {
                $testInstance = new $test['class']();
                $startTime = microtime(true);

                // Run setup if exists
                if (method_exists($testInstance, 'setUp')) {
                    $testInstance->setUp();
                }

                // Run the test
                $result = $testInstance->{$test['method']}();

                // Run teardown if exists
                if (method_exists($testInstance, 'tearDown')) {
                    $testInstance->tearDown();
                }

                $endTime = microtime(true);
                $duration = $endTime - $startTime;

                if ($result === true || $result === null) {
                    $test['status'] = 'passed';
                    $test['duration'] = $duration;
                    $passed++;
                    echo "✓ PASSED ({$duration}s)\n";
                } else {
                    $test['status'] = 'failed';
                    $test['duration'] = $duration;
                    $test['error'] = 'Test returned false';
                    $failed++;
                    echo "✗ FAILED ({$duration}s): Test returned false\n";
                }

            } catch (Exception $e) {
                $test['status'] = 'failed';
                $test['error'] = $e->getMessage();
                $test['trace'] = $e->getTraceAsString();
                $failed++;
                echo "✗ FAILED: " . $e->getMessage() . "\n";

                if ($this->config['stop_on_failure']) {
                    break;
                }
            } catch (AssertionError $e) {
                $test['status'] = 'failed';
                $test['error'] = $e->getMessage();
                $failed++;
                echo "✗ FAILED: " . $e->getMessage() . "\n";
            }

            echo "\n";
        }

        $this->generateReport($passed, $failed, $skipped);
        return ['passed' => $passed, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * Generate test report
     */
    private function generateReport($passed, $failed, $skipped) {
        $total = $passed + $failed + $skipped;
        $endTime = microtime(true);
        $totalTime = $endTime - $this->startTime;
        $memoryUsed = memory_get_usage(true) - $this->memoryStart;

        echo "================================\n";
        echo "Test Results Summary\n";
        echo "================================\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Skipped: $skipped\n";
        echo "Success Rate: " . ($total > 0 ? round(($passed / $total) * 100, 2) : 0) . "%\n";
        echo "Total Time: " . round($totalTime, 2) . "s\n";
        echo "Memory Used: " . round($memoryUsed / 1024 / 1024, 2) . " MB\n";
        echo "================================\n";

        // Generate detailed report
        $this->generateDetailedReport();
    }

    /**
     * Generate detailed HTML report
     */
    private function generateDetailedReport() {
        $reportDir = __DIR__ . '/../test-reports';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }

        $reportFile = $reportDir . '/test-report-' . date('Y-m-d-H-i-s') . '.html';

        $html = $this->generateHTMLReport();
        file_put_contents($reportFile, $html);

        echo "Detailed report saved to: $reportFile\n";
    }

    /**
     * Generate HTML report content
     */
    private function generateHTMLReport() {
        $total = count($this->tests);
        $passed = count(array_filter($this->tests, fn($t) => $t['status'] === 'passed'));
        $failed = count(array_filter($this->tests, fn($t) => $t['status'] === 'failed'));

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Test Report - ATIERA Financial System</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .summary { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .test { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 3px; }
                .passed { border-left: 5px solid #28a745; }
                .failed { border-left: 5px solid #dc3545; background: #fff5f5; }
                .pending { border-left: 5px solid #ffc107; }
                .error { color: #dc3545; font-family: monospace; white-space: pre-wrap; }
                .duration { color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <h1>ATIERA Financial System - Test Report</h1>
            <div class="summary">
                <h2>Summary</h2>
                <p><strong>Total Tests:</strong> ' . $total . '</p>
                <p><strong>Passed:</strong> ' . $passed . '</p>
                <p><strong>Failed:</strong> ' . $failed . '</p>
                <p><strong>Success Rate:</strong> ' . ($total > 0 ? round(($passed / $total) * 100, 2) : 0) . '%</p>
                <p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>
            </div>

            <h2>Test Results</h2>';

        foreach ($this->tests as $test) {
            $class = $test['status'];
            $html .= '
            <div class="test ' . $class . '">
                <h3>' . htmlspecialchars($test['class'] . '::' . $test['method']) . '</h3>
                <p>' . htmlspecialchars($test['description']) . '</p>
                <p class="duration">Duration: ' . ($test['duration'] ?? 0) . 's</p>';

            if (isset($test['error'])) {
                $html .= '<div class="error">Error: ' . htmlspecialchars($test['error']) . '</div>';
            }

            if (isset($test['trace'])) {
                $html .= '<details><summary>Stack Trace</summary><pre class="error">' . htmlspecialchars($test['trace']) . '</pre></details>';
            }

            $html .= '</div>';
        }

        $html .= '
        </body>
        </html>';

        return $html;
    }

    /**
     * Get test results
     */
    public function getResults() {
        return $this->results;
    }

    /**
     * Set configuration
     */
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
    }

    /**
     * Get configuration
     */
    public function getConfig($key = null) {
        return $key ? ($this->config[$key] ?? null) : $this->config;
    }
}

/**
 * Base Test Case Class
 */
abstract class TestCase {
    protected $assertions = 0;
    protected $failedAssertions = 0;

    /**
     * Assert that a condition is true
     */
    protected function assertTrue($condition, $message = '') {
        $this->assertions++;
        if (!$condition) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: 'Expected true, got false');
        }
    }

    /**
     * Assert that a condition is false
     */
    protected function assertFalse($condition, $message = '') {
        $this->assertions++;
        if ($condition) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: 'Expected false, got true');
        }
    }

    /**
     * Assert that two values are equal
     */
    protected function assertEquals($expected, $actual, $message = '') {
        $this->assertions++;
        if ($expected != $actual) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: "Expected '$expected', got '$actual'");
        }
    }

    /**
     * Assert that two values are strictly equal
     */
    protected function assertSame($expected, $actual, $message = '') {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: "Expected (strict) '$expected', got '$actual'");
        }
    }

    /**
     * Assert that a value is null
     */
    protected function assertNull($value, $message = '') {
        $this->assertions++;
        if ($value !== null) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: "Expected null, got '$value'");
        }
    }

    /**
     * Assert that a value is not null
     */
    protected function assertNotNull($value, $message = '') {
        $this->assertions++;
        if ($value === null) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: 'Expected not null, got null');
        }
    }

    /**
     * Assert that an array contains a key
     */
    protected function assertArrayHasKey($key, $array, $message = '') {
        $this->assertions++;
        if (!is_array($array) || !array_key_exists($key, $array)) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: "Array does not contain key '$key'");
        }
    }

    /**
     * Assert that a string contains a substring
     */
    protected function assertStringContains($needle, $haystack, $message = '') {
        $this->assertions++;
        if (strpos($haystack, $needle) === false) {
            $this->failedAssertions++;
            throw new AssertionError($message ?: "String does not contain '$needle'");
        }
    }

    /**
     * Assert that a value is an instance of a class
     */
    protected function assertInstanceOf($expectedClass, $actual, $message = '') {
        $this->assertions++;
        if (!$actual instanceof $expectedClass) {
            $this->failedAssertions++;
            $actualClass = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new AssertionError($message ?: "Expected instance of $expectedClass, got $actualClass");
        }
    }

    /**
     * Assert that an exception is thrown
     */
    protected function assertThrows($expectedException, $callback, $message = '') {
        $this->assertions++;
        try {
            $callback();
            $this->failedAssertions++;
            throw new AssertionError($message ?: "Expected exception $expectedException was not thrown");
        } catch (Exception $e) {
            if (!$e instanceof $expectedException) {
                $this->failedAssertions++;
                throw new AssertionError($message ?: "Expected $expectedException, got " . get_class($e));
            }
        }
    }

    /**
     * Skip a test
     */
    protected function skip($message = '') {
        throw new Exception("Test skipped: $message");
    }

    /**
     * Get assertion counts
     */
    public function getAssertionCounts() {
        return [
            'total' => $this->assertions,
            'failed' => $this->failedAssertions,
            'passed' => $this->assertions - $this->failedAssertions
        ];
    }
}

/**
 * Custom Assertion Error
 */
class AssertionError extends Exception {}

/**
 * Database Test Case
 */
abstract class DatabaseTestCase extends TestCase {
    protected $db;
    protected $testData = [];

    /**
     * Set up database connection for testing
     */
    public function setUp() {
        // Use test database
        $this->db = new PDO(
            "mysql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') .
            ";dbname=" . ($_ENV['DB_TEST_NAME'] ?? 'atiera_test'),
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? ''
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Start transaction for test isolation
        $this->db->beginTransaction();
    }

    /**
     * Tear down - rollback changes
     */
    public function tearDown() {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Insert test data
     */
    protected function insertTestData($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->db->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
        $stmt->execute(array_values($data));

        return $this->db->lastInsertId();
    }

    /**
     * Clean up test data
     */
    protected function cleanTable($table) {
        $this->db->exec("DELETE FROM $table");
    }
}

/**
 * API Test Case
 */
abstract class APITestCase extends TestCase {
    protected $baseUrl = 'http://localhost/atiera';
    protected $authToken = null;

    /**
     * Make HTTP request
     */
    protected function makeRequest($method, $url, $data = null, $headers = []) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("HTTP request failed: $error");
        }

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Assert HTTP response code
     */
    protected function assertResponseCode($expected, $response, $message = '') {
        $this->assertEquals($expected, $response['code'], $message ?: "Expected HTTP $expected, got {$response['code']}");
    }

    /**
     * Assert JSON response has key
     */
    protected function assertResponseHasKey($key, $response, $message = '') {
        $this->assertArrayHasKey($key, $response['body'], $message ?: "Response missing key: $key");
    }

    /**
     * Assert API success
     */
    protected function assertAPISuccess($response, $message = '') {
        $this->assertResponseHasKey('success', $response, $message);
        $this->assertTrue($response['body']['success'], $message ?: 'API call was not successful');
    }
}

/**
 * Performance Test Case
 */
abstract class PerformanceTestCase extends TestCase {
    protected $maxExecutionTime = 1.0; // seconds
    protected $maxMemoryUsage = 50; // MB

    /**
     * Measure execution time
     */
    protected function measureExecutionTime($callback) {
        $start = microtime(true);
        $result = $callback();
        $end = microtime(true);

        $executionTime = $end - $start;

        $this->assertTrue($executionTime <= $this->maxExecutionTime,
            "Execution time {$executionTime}s exceeded limit {$this->maxExecutionTime}s");

        return ['result' => $result, 'time' => $executionTime];
    }

    /**
     * Measure memory usage
     */
    protected function measureMemoryUsage($callback) {
        $startMemory = memory_get_usage(true);
        $result = $callback();
        $endMemory = memory_get_usage(true);

        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB

        $this->assertTrue($memoryUsed <= $this->maxMemoryUsage,
            "Memory usage {$memoryUsed}MB exceeded limit {$this->maxMemoryUsage}MB");

        return ['result' => $result, 'memory' => $memoryUsed];
    }
}

/**
 * Test Runner Script
 */
class TestRunner {
    private $framework;

    public function __construct() {
        $this->framework = TestFramework::getInstance();
    }

    /**
     * Discover and run all tests
     */
    public function runAllTests() {
        $this->discoverTests();
        return $this->framework->runTests();
    }

    /**
     * Discover test files
     */
    private function discoverTests() {
        $testDir = __DIR__;

        $files = glob($testDir . '/*.php');
        foreach ($files as $file) {
            if (basename($file) !== 'TestFramework.php' && basename($file) !== 'TestRunner.php') {
                require_once $file;

                $className = basename($file, '.php');
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);
                    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

                    foreach ($methods as $method) {
                        if (strpos($method->name, 'test') === 0) {
                            $this->framework->addTest($className, $method->name);
                        }
                    }
                }
            }
        }
    }

    /**
     * Run specific test class
     */
    public function runTestClass($className) {
        if (!class_exists($className)) {
            echo "Test class '$className' not found.\n";
            return;
        }

        $reflection = new ReflectionClass($className);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (strpos($method->name, 'test') === 0) {
                $this->framework->addTest($className, $method->name);
            }
        }

        return $this->framework->runTests();
    }

    /**
     * Run specific test method
     */
    public function runTestMethod($className, $methodName) {
        if (!class_exists($className)) {
            echo "Test class '$className' not found.\n";
            return;
        }

        $this->framework->addTest($className, $methodName);
        return $this->framework->runTests();
    }
}

// Command line interface
if (isset($argv) && basename($argv[0]) === 'TestFramework.php') {
    $runner = new TestRunner();

    if (count($argv) === 1) {
        // Run all tests
        $results = $runner->runAllTests();
    } elseif (count($argv) === 2) {
        // Run specific test class
        $results = $runner->runTestClass($argv[1]);
    } elseif (count($argv) === 3) {
        // Run specific test method
        $results = $runner->runTestMethod($argv[1], $argv[2]);
    } else {
        echo "Usage: php TestFramework.php [className] [methodName]\n";
        exit(1);
    }

    // Exit with appropriate code
    if ($results['failed'] > 0) {
        exit(1);
    } else {
        exit(0);
    }
}
?>
