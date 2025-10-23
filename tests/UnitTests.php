<?php
/**
 * ATIERA Financial Management System - Unit Tests
 * Testing individual components and functions
 */

class UnitTests extends TestCase {
    public function testValidationEngineCreation() {
        $validator = ValidationEngine::getInstance();
        $this->assertInstanceOf('ValidationEngine', $validator);
        $this->assertNotNull($validator);
    }

    public function testCacheManagerCreation() {
        $cache = CacheManager::getInstance();
        $this->assertInstanceOf('CacheManager', $cache);
        $this->assertNotNull($cache);
    }

    public function testWorkflowEngineCreation() {
        $workflow = WorkflowEngine::getInstance();
        $this->assertInstanceOf('WorkflowEngine', $workflow);
        $this->assertNotNull($workflow);
    }

    public function testInputSanitizerString() {
        $sanitized = InputSanitizer::sanitizeString('<script>alert("xss")</script>Hello World');
        $this->assertEquals('alert("xss")Hello World', $sanitized);
        $this->assertStringContains('Hello World', $sanitized);
    }

    public function testInputSanitizerEmail() {
        $sanitized = InputSanitizer::sanitizeEmail('  test@example.com  ');
        $this->assertEquals('test@example.com', $sanitized);
    }

    public function testInputSanitizerNumeric() {
        $sanitized = InputSanitizer::sanitizeNumeric('123.45');
        $this->assertEquals(123.45, $sanitized);
        $this->assertTrue(is_float($sanitized));
    }

    public function testInputSanitizerDate() {
        $sanitized = InputSanitizer::sanitizeDate('2025-01-15');
        $this->assertEquals('2025-01-15', $sanitized);
    }

    public function testCSRFTokenGeneration() {
        $csrf = CSRFProtection::getInstance();
        $token1 = $csrf->generateToken();
        $token2 = $csrf->generateToken();

        $this->assertNotNull($token1);
        $this->assertEquals($token1, $token2); // Should be the same token
        $this->assertTrue(strlen($token1) > 0);
    }

    public function testCSRFTokenValidation() {
        $csrf = CSRFProtection::getInstance();
        $token = $csrf->generateToken();

        $this->assertTrue($csrf->validateToken($token));
        $this->assertFalse($csrf->validateToken('invalid_token'));
    }

    public function testLoggerCreation() {
        $logger = Logger::getInstance();
        $this->assertInstanceOf('Logger', $logger);
        $this->assertNotNull($logger);
    }

    public function testPerformanceMonitorCreation() {
        $monitor = PerformanceMonitor::getInstance();
        $this->assertInstanceOf('PerformanceMonitor', $monitor);
        $this->assertNotNull($monitor);
    }

    public function testValidationRulesExist() {
        global $VALIDATION_RULES;
        $this->assertArrayHasKey('user_create', $VALIDATION_RULES);
        $this->assertArrayHasKey('customer_create', $VALIDATION_RULES);
        $this->assertArrayHasKey('invoice_create', $VALIDATION_RULES);
    }

    public function testBusinessRulesExist() {
        global $BUSINESS_RULES;
        $this->assertArrayHasKey('invoice_total_calculation', $BUSINESS_RULES);
        $this->assertArrayHasKey('journal_entry_balance', $BUSINESS_RULES);
    }

    public function testSanitizationRulesExist() {
        global $SANITIZATION_RULES;
        $this->assertArrayHasKey('user_input', $SANITIZATION_RULES);
        $this->assertArrayHasKey('financial_data', $SANITIZATION_RULES);
    }

    public function testFileUploadValidation() {
        $validator = ValidationEngine::getInstance();

        // Test valid file
        $validFile = [
            'name' => 'document.pdf',
            'type' => 'application/pdf',
            'size' => 1024000, // 1MB
            'tmp_name' => '/tmp/test.pdf',
            'error' => UPLOAD_ERR_OK
        ];

        $errors = $validator->validateFile($validFile, [
            'max_size' => 5242880, // 5MB
            'allowed_types' => ['pdf'],
            'allowed_mimes' => ['application/pdf']
        ]);

        $this->assertEmpty($errors);
    }

    public function testFileUploadValidationFailure() {
        $validator = ValidationEngine::getInstance();

        // Test oversized file
        $invalidFile = [
            'name' => 'large.pdf',
            'type' => 'application/pdf',
            'size' => 10485760, // 10MB
            'tmp_name' => '/tmp/large.pdf',
            'error' => UPLOAD_ERR_OK
        ];

        $errors = $validator->validateFile($invalidFile, [
            'max_size' => 5242880 // 5MB limit
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContains('exceeds maximum limit', $errors[0]);
    }

    public function testDataIntegrityValidation() {
        $validator = ValidationEngine::getInstance();

        $data = ['name' => 'John Doe', 'amount' => 100.50];
        $checksum = $validator->calculateChecksum($data);

        $this->assertTrue($validator->validateDataIntegrity($data, $checksum));
        $this->assertFalse($validator->validateDataIntegrity($data, 'invalid_checksum'));
    }

    public function testArrayOperations() {
        // Test array key existence
        $array = ['name' => 'John', 'age' => 30];
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('age', $array);

        // Test array manipulation
        $filtered = array_filter($array, fn($value) => is_string($value));
        $this->assertEquals(['name' => 'John'], $filtered);
    }

    public function testStringOperations() {
        $string = 'Hello World';

        $this->assertStringContains('Hello', $string);
        $this->assertStringContains('World', $string);
        $this->assertEquals(11, strlen($string));

        // Test string manipulation
        $upper = strtoupper($string);
        $this->assertEquals('HELLO WORLD', $upper);

        $replaced = str_replace('World', 'Universe', $string);
        $this->assertEquals('Hello Universe', $replaced);
    }

    public function testNumericOperations() {
        $number = 123.45;

        $this->assertTrue(is_numeric($number));
        $this->assertTrue(is_float($number));

        // Test numeric operations
        $rounded = round($number, 1);
        $this->assertEquals(123.5, $rounded);

        $formatted = number_format($number, 2);
        $this->assertEquals('123.45', $formatted);
    }

    public function testDateTimeOperations() {
        $timestamp = time();
        $dateString = date('Y-m-d H:i:s', $timestamp);

        $this->assertNotNull($dateString);
        $this->assertStringContains('-', $dateString);
        $this->assertStringContains(':', $dateString);

        // Test date parsing
        $parsed = strtotime($dateString);
        $this->assertEquals($timestamp, $parsed);
    }

    public function testJSONOperations() {
        $data = ['name' => 'John', 'age' => 30, 'active' => true];
        $json = json_encode($data);

        $this->assertNotNull($json);
        $this->assertStringContains('John', $json);

        // Test JSON decoding
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('age', $decoded);
        $this->assertArrayHasKey('active', $decoded);
    }

    public function testExceptionHandling() {
        // Test exception throwing
        $this->assertThrows('Exception', function() {
            throw new Exception('Test exception');
        });

        // Test specific exception type
        $this->assertThrows('InvalidArgumentException', function() {
            throw new InvalidArgumentException('Invalid argument');
        });
    }

    public function testTypeChecking() {
        // Test various type checks
        $this->assertTrue(is_string('hello'));
        $this->assertTrue(is_int(42));
        $this->assertTrue(is_float(3.14));
        $this->assertTrue(is_bool(true));
        $this->assertTrue(is_array([]));
        $this->assertTrue(is_object(new stdClass()));
        $this->assertTrue(is_null(null));
    }

    public function testRegularExpressions() {
        // Test email regex
        $this->assertTrue(preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', 'test@example.com'));
        $this->assertFalse(preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', 'invalid-email'));

        // Test phone regex
        $this->assertTrue(preg_match('/^[\+]?[1-9][\d]{0,15}$/', '+1234567890'));
        $this->assertTrue(preg_match('/^[\+]?[1-9][\d]{0,15}$/', '1234567890'));
    }

    public function testMathOperations() {
        // Test basic math
        $this->assertEquals(4, 2 + 2);
        $this->assertEquals(6, 2 * 3);
        $this->assertEquals(2, 6 / 3);
        $this->assertEquals(1, 5 % 2);

        // Test floating point comparison
        $this->assertTrue(abs(0.1 + 0.2 - 0.3) < 0.0001);
    }

    public function testBooleanLogic() {
        // Test boolean operations
        $this->assertTrue(true && true);
        $this->assertFalse(true && false);
        $this->assertTrue(true || false);
        $this->assertFalse(false || false);

        // Test ternary operator
        $result = (5 > 3) ? 'greater' : 'lesser';
        $this->assertEquals('greater', $result);
    }
}
