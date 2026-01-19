<?php
/**
 * ATIERA Financial Management System - Comprehensive Data Validation
 * Advanced validation system for data integrity and security
 */

class ValidationEngine {
    private static $instance = null;
    private $errors = [];
    private $rules = [];
    private $customValidators = [];

    private function __construct() {
        $this->initializeDefaultRules();
        $this->initializeCustomValidators();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize default validation rules
     */
    private function initializeDefaultRules() {
        $this->rules = [
            'required' => [
                'message' => 'This field is required',
                'validator' => function($value) {
                    return !empty($value) && $value !== null && $value !== '';
                }
            ],
            'email' => [
                'message' => 'Please enter a valid email address',
                'validator' => function($value) {
                    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                }
            ],
            'numeric' => [
                'message' => 'Please enter a valid number',
                'validator' => function($value) {
                    return is_numeric($value);
                }
            ],
            'integer' => [
                'message' => 'Please enter a valid integer',
                'validator' => function($value) {
                    return filter_var($value, FILTER_VALIDATE_INT) !== false;
                }
            ],
            'float' => [
                'message' => 'Please enter a valid decimal number',
                'validator' => function($value) {
                    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
                }
            ],
            'boolean' => [
                'message' => 'Please enter a valid boolean value',
                'validator' => function($value) {
                    return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
                }
            ],
            'date' => [
                'message' => 'Please enter a valid date',
                'validator' => function($value) {
                    return strtotime($value) !== false;
                }
            ],
            'datetime' => [
                'message' => 'Please enter a valid date and time',
                'validator' => function($value) {
                    return DateTime::createFromFormat('Y-m-d H:i:s', $value) !== false;
                }
            ],
            'url' => [
                'message' => 'Please enter a valid URL',
                'validator' => function($value) {
                    return filter_var($value, FILTER_VALIDATE_URL) !== false;
                }
            ],
            'phone' => [
                'message' => 'Please enter a valid phone number',
                'validator' => function($value) {
                    return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $value);
                }
            ],
            'postal_code' => [
                'message' => 'Please enter a valid postal code',
                'validator' => function($value) {
                    return preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $value);
                }
            ],
            'currency' => [
                'message' => 'Please enter a valid currency amount',
                'validator' => function($value) {
                    return preg_match('/^\d+(\.\d{1,2})?$/', $value);
                }
            ],
            'percentage' => [
                'message' => 'Please enter a valid percentage (0-100)',
                'validator' => function($value) {
                    return is_numeric($value) && $value >= 0 && $value <= 100;
                }
            ],
            'positive' => [
                'message' => 'Please enter a positive number',
                'validator' => function($value) {
                    return is_numeric($value) && $value > 0;
                }
            ],
            'non_negative' => [
                'message' => 'Please enter a non-negative number',
                'validator' => function($value) {
                    return is_numeric($value) && $value >= 0;
                }
            ],
            'alphanumeric' => [
                'message' => 'Please use only letters and numbers',
                'validator' => function($value) {
                    return ctype_alnum($value);
                }
            ],
            'alpha' => [
                'message' => 'Please use only letters',
                'validator' => function($value) {
                    return ctype_alpha($value);
                }
            ],
            'no_special_chars' => [
                'message' => 'Special characters are not allowed',
                'validator' => function($value) {
                    return !preg_match('/[^A-Za-z0-9\s]/', $value);
                }
            ],
            'strong_password' => [
                'message' => 'Password must be at least 8 characters with uppercase, lowercase, and numbers',
                'validator' => function($value) {
                    return strlen($value) >= 8 &&
                           preg_match('/[A-Z]/', $value) &&
                           preg_match('/[a-z]/', $value) &&
                           preg_match('/[0-9]/', $value);
                }
            ],
            'json' => [
                'message' => 'Please enter valid JSON',
                'validator' => function($value) {
                    json_decode($value);
                    return json_last_error() === JSON_ERROR_NONE;
                }
            ]
        ];
    }

    /**
     * Initialize custom business rule validators
     */
    private function initializeCustomValidators() {
        $this->customValidators = [
            'unique_username' => function($value, $data = []) {
                if (empty($value)) return true;
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$value, $data['exclude_id'] ?? 0]);
                return $stmt->rowCount() === 0;
            },

            'unique_email' => function($value, $data = []) {
                if (empty($value)) return true;
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$value, $data['exclude_id'] ?? 0]);
                return $stmt->rowCount() === 0;
            },

            'valid_account_code' => function($value) {
                return preg_match('/^[A-Z0-9]{4,20}$/', $value);
            },

            'valid_invoice_number' => function($value) {
                return preg_match('/^[A-Z]{2,4}-\d{4,8}$/', $value);
            },

            'valid_customer_code' => function($value) {
                return preg_match('/^[A-Z]{1,3}\d{3,6}$/', $value);
            },

            'valid_vendor_code' => function($value) {
                return preg_match('/^[A-Z]{1,3}\d{3,6}$/', $value);
            },

            'future_date' => function($value) {
                return strtotime($value) > time();
            },

            'past_date' => function($value) {
                return strtotime($value) < time();
            },

            'valid_date_range' => function($value, $data = []) {
                if (!isset($data['start_date']) || !isset($data['end_date'])) return true;
                return strtotime($data['start_date']) <= strtotime($data['end_date']);
            },

            'sufficient_credit' => function($value, $data = []) {
                if (!isset($data['customer_id']) || !isset($data['invoice_total'])) return true;

                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT credit_limit, current_balance
                    FROM customers
                    WHERE id = ?
                ");
                $stmt->execute([$data['customer_id']]);
                $customer = $stmt->fetch();

                if (!$customer) return false;

                $available_credit = $customer['credit_limit'] - $customer['current_balance'];
                return $available_credit >= $data['invoice_total'];
            },

            'valid_chart_of_accounts' => function($value) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE id = ? AND is_active = 1");
                $stmt->execute([$value]);
                return $stmt->rowCount() > 0;
            },

            'valid_customer' => function($value) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM customers WHERE id = ? AND status = 'active'");
                $stmt->execute([$value]);
                return $stmt->rowCount() > 0;
            },

            'valid_vendor' => function($value) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM vendors WHERE id = ? AND status = 'active'");
                $stmt->execute([$value]);
                return $stmt->rowCount() > 0;
            },

            'valid_user' => function($value) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$value]);
                return $stmt->rowCount() > 0;
            },

            'valid_role' => function($value) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
                $stmt->execute([$value]);
                return $stmt->rowCount() > 0;
            },

            'valid_file_type' => function($value, $data = []) {
                if (!isset($data['allowed_types'])) return true;
                $allowedTypes = is_array($data['allowed_types']) ? $data['allowed_types'] : [$data['allowed_types']];

                $fileInfo = pathinfo($value);
                $extension = strtolower($fileInfo['extension'] ?? '');

                return in_array($extension, array_map('strtolower', $allowedTypes));
            },

            'max_file_size' => function($value, $data = []) {
                if (!isset($data['max_size'])) return true;
                return filesize($value) <= $data['max_size'];
            },

            'valid_workflow_definition' => function($value) {
                $data = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) return false;

                // Check required fields
                if (!isset($data['trigger']) || !isset($data['steps'])) return false;

                // Validate steps
                foreach ($data['steps'] as $step) {
                    if (!isset($step['name']) || !isset($step['type'])) return false;
                    if (!in_array($step['type'], ['approval', 'action', 'delay'])) return false;
                }

                return true;
            }
        ];
    }

    /**
     * Validate data against rules
     */
    public function validate($data, $rules) {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Handle array of rules
            if (!is_array($fieldRules)) {
                $fieldRules = [$fieldRules];
            }

            foreach ($fieldRules as $rule) {
                if (!$this->validateField($field, $value, $rule, $data)) {
                    break; // Stop validating this field if one rule fails
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single field
     */
    private function validateField($field, $value, $rule, $allData) {
        // Parse rule (rule:param or rule)
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;

        // Skip validation if field is empty and rule is not 'required'
        if ($ruleName !== 'required' && $this->isEmpty($value)) {
            return true;
        }

        // Apply validation rule
        if (isset($this->rules[$ruleName])) {
            $ruleConfig = $this->rules[$ruleName];
            $validator = $ruleConfig['validator'];

            if (!$validator($value, $ruleParam)) {
                $this->addError($field, $ruleConfig['message']);
                return false;
            }
        } elseif (isset($this->customValidators[$ruleName])) {
            $validator = $this->customValidators[$ruleName];
            $contextData = array_merge($allData, ['field' => $field, 'param' => $ruleParam]);

            if (!$validator($value, $contextData)) {
                $this->addError($field, $this->getCustomErrorMessage($ruleName, $ruleParam));
                return false;
            }
        } else {
            // Handle parameterized rules
            if ($this->validateParameterizedRule($ruleName, $value, $ruleParam)) {
                return true;
            }

            $this->addError($field, "Unknown validation rule: $ruleName");
            return false;
        }

        return true;
    }

    /**
     * Validate parameterized rules (min, max, length, etc.)
     */
    private function validateParameterizedRule($ruleName, $value, $param) {
        switch ($ruleName) {
            case 'min':
                if (is_numeric($value) && is_numeric($param)) {
                    return $value >= $param;
                }
                if (is_string($value)) {
                    return strlen($value) >= $param;
                }
                return false;

            case 'max':
                if (is_numeric($value) && is_numeric($param)) {
                    return $value <= $param;
                }
                if (is_string($value)) {
                    return strlen($value) <= $param;
                }
                return false;

            case 'between':
                $params = explode(',', $param);
                if (count($params) === 2) {
                    $min = trim($params[0]);
                    $max = trim($params[1]);
                    if (is_numeric($value)) {
                        return $value >= $min && $value <= $max;
                    }
                    if (is_string($value)) {
                        $length = strlen($value);
                        return $length >= $min && $length <= $max;
                    }
                }
                return false;

            case 'in':
                $options = explode(',', $param);
                $options = array_map('trim', $options);
                return in_array($value, $options);

            case 'not_in':
                $options = explode(',', $param);
                $options = array_map('trim', $options);
                return !in_array($value, $options);

            case 'regex':
                return preg_match($param, $value);

            case 'before':
                return strtotime($value) < strtotime($param);

            case 'after':
                return strtotime($value) > strtotime($param);

            default:
                return false;
        }
    }

    /**
     * Check if value is empty
     */
    private function isEmpty($value) {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Add validation error
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get custom error message
     */
    private function getCustomErrorMessage($ruleName, $param) {
        $messages = [
            'unique_username' => 'This username is already taken',
            'unique_email' => 'This email address is already registered',
            'valid_account_code' => 'Please enter a valid account code (4-20 alphanumeric characters)',
            'valid_invoice_number' => 'Please enter a valid invoice number (e.g., INV-12345)',
            'valid_customer_code' => 'Please enter a valid customer code (e.g., C001)',
            'valid_vendor_code' => 'Please enter a valid vendor code (e.g., V001)',
            'future_date' => 'Please select a future date',
            'past_date' => 'Please select a past date',
            'valid_date_range' => 'End date must be after start date',
            'sufficient_credit' => 'Insufficient credit limit for this transaction',
            'valid_chart_of_accounts' => 'Please select a valid account',
            'valid_customer' => 'Please select a valid customer',
            'valid_vendor' => 'Please select a valid vendor',
            'valid_user' => 'Please select a valid user',
            'valid_role' => 'Please select a valid role',
            'valid_file_type' => 'File type not allowed',
            'max_file_size' => 'File size exceeds maximum limit',
            'valid_workflow_definition' => 'Please enter a valid workflow definition'
        ];

        return $messages[$ruleName] ?? "Validation failed for rule: $ruleName";
    }

    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all errors as flat array
     */
    public function getAllErrors() {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $flat[] = "$field: $message";
            }
        }
        return $flat;
    }

    /**
     * Sanitize input data
     */
    public function sanitize($data, $rules = []) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value, $rules[$key] ?? []);
            } else {
                $sanitized[$key] = $this->sanitizeValue($value, $rules[$key] ?? 'string');
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value
     */
    private function sanitizeValue($value, $type) {
        switch ($type) {
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            case 'int':
            case 'integer':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate file upload
     */
    public function validateFile($file, $rules = []) {
        $errors = [];

        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }

        // Check file size
        if (isset($rules['max_size']) && $file['size'] > $rules['max_size']) {
            $errors[] = 'File size exceeds maximum limit';
        }

        // Check file type
        if (isset($rules['allowed_types'])) {
            $allowedTypes = is_array($rules['allowed_types']) ? $rules['allowed_types'] : [$rules['allowed_types']];
            $fileInfo = pathinfo($file['name']);
            $extension = strtolower($fileInfo['extension'] ?? '');

            if (!in_array($extension, array_map('strtolower', $allowedTypes))) {
                $errors[] = 'File type not allowed';
            }
        }

        // Check MIME type
        if (isset($rules['allowed_mimes'])) {
            $allowedMimes = is_array($rules['allowed_mimes']) ? $rules['allowed_mimes'] : [$rules['allowed_mimes']];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes)) {
                $errors[] = 'File MIME type not allowed';
            }
        }

        // Check for malicious content
        if (isset($rules['scan_malware']) && $rules['scan_malware']) {
            if ($this->scanFileForMalware($file['tmp_name'])) {
                $errors[] = 'File contains potentially malicious content';
            }
        }

        return $errors;
    }

    /**
     * Basic malware scan (check for suspicious patterns)
     */
    private function scanFileForMalware($filePath) {
        $content = file_get_contents($filePath);

        // Check for common malicious patterns
        $maliciousPatterns = [
            '/<script[^>]*>.*?<\/script>/si',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec/i',
            '/passthru/i'
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRF($token) {
        return CSRFProtection::getInstance()->validateToken($token);
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        return CSRFProtection::getInstance()->generateToken();
    }

    /**
     * Validate API request
     */
    public function validateAPIRequest($data, $requiredFields = []) {
        $errors = [];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }

        // Validate API key if required
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            if (!$this->validateAPIKey($_SERVER['HTTP_X_API_KEY'])) {
                $errors[] = 'Invalid API key';
            }
        }

        // Rate limiting check
        if ($this->isRateLimited()) {
            $errors[] = 'Rate limit exceeded';
        }

        return $errors;
    }

    /**
     * Validate API key
     */
    private function validateAPIKey($apiKey) {
        // Implementation would check against stored API keys
        return true; // Placeholder
    }

    /**
     * Check rate limiting
     */
    private function isRateLimited() {
        // Implementation would check request frequency
        return false; // Placeholder
    }

    /**
     * Validate business rules
     */
    public function validateBusinessRules($data, $rules) {
        $errors = [];

        foreach ($rules as $ruleName => $rule) {
            $validator = $rule['validator'];
            $params = $rule['params'] ?? [];

            if (!$validator($data, $params)) {
                $errors[] = $rule['message'] ?? "Business rule '$ruleName' failed";
            }
        }

        return $errors;
    }

    /**
     * Validate data integrity (checksums, etc.)
     */
    public function validateDataIntegrity($data, $checksum = null) {
        if ($checksum === null) {
            return true; // No checksum to validate
        }

        $calculatedChecksum = $this->calculateChecksum($data);
        return hash_equals($calculatedChecksum, $checksum);
    }

    /**
     * Calculate data checksum
     */
    private function calculateChecksum($data) {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Validate user permissions for data access
     */
    public function validatePermissions($userId, $resource, $action, $resourceId = null) {
        $permissions = new Permissions();
        return $permissions->hasPermission($userId, $resource, $action, $resourceId);
    }

    /**
     * Log validation failures for security monitoring
     */
    public function logValidationFailure($type, $data, $errors) {
        Logger::getInstance()->warning("Validation failure: $type", [
            'data' => $data,
            'errors' => $errors,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

/**
 * CSRF Protection Class
 */
class CSRFProtection {
    private static $instance = null;
    private $tokenName = 'csrf_token';
    private $tokenLength = 32;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate CSRF token
     */
    public function generateToken() {
        if (!isset($_SESSION[$this->tokenName])) {
            $_SESSION[$this->tokenName] = bin2hex(random_bytes($this->tokenLength));
        }
        return $_SESSION[$this->tokenName];
    }

    /**
     * Validate CSRF token
     */
    public function validateToken($token) {
        if (!isset($_SESSION[$this->tokenName])) {
            return false;
        }

        return hash_equals($_SESSION[$this->tokenName], $token);
    }

    /**
     * Regenerate token
     */
    public function regenerateToken() {
        unset($_SESSION[$this->tokenName]);
        return $this->generateToken();
    }

    /**
     * Get token name for forms
     */
    public function getTokenName() {
        return $this->tokenName;
    }
}

/**
 * Input Sanitization Helper
 */
class InputSanitizer {
    /**
     * Sanitize string input
     */
    public static function sanitizeString($input, $maxLength = null) {
        $sanitized = trim($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        if ($maxLength !== null && strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize email
     */
    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize numeric input
     */
    public static function sanitizeNumeric($input, $decimals = 2) {
        $input = trim($input);
        if (!is_numeric($input)) {
            return 0;
        }

        return round((float)$input, $decimals);
    }

    /**
     * Sanitize date input
     */
    public static function sanitizeDate($date) {
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Sanitize datetime input
     */
    public static function sanitizeDateTime($datetime) {
        $timestamp = strtotime($datetime);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * Sanitize HTML (allow safe tags)
     */
    public static function sanitizeHTML($html, $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>') {
        return strip_tags($html, $allowedTags);
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename) {
        // Remove path components
        $filename = basename($filename);

        // Replace dangerous characters
        $filename = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        return $filename;
    }

    /**
     * Sanitize URL
     */
    public static function sanitizeURL($url) {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitize phone number
     */
    public static function sanitizePhone($phone) {
        return preg_replace('/[^\d\+\-\(\)\s]/', '', trim($phone));
    }

    /**
     * Sanitize SQL-like input (prevent SQL injection patterns)
     */
    public static function sanitizeSQLInput($input) {
        // Remove common SQL injection patterns
        $patterns = [
            '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
            '/(-{2}|\/\*|\*\/)/',
            '/(;|\'|"|`)/'
        ];

        $sanitized = $input;
        foreach ($patterns as $pattern) {
            $sanitized = preg_replace($pattern, '', $sanitized);
        }

        return trim($sanitized);
    }
}
?>

