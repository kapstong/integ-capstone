<?php
/**
 * ATIERA Financial Management System - Validation Rules Configuration
 * Centralized validation rules for forms and API endpoints
 */

// User Management Validation Rules
$VALIDATION_RULES = [
    'user_create' => [
        'username' => ['required', 'min:3', 'max:50', 'alphanumeric', 'unique_username'],
        'email' => ['required', 'email', 'unique_email'],
        'password' => ['required', 'strong_password', 'min:8'],
        'first_name' => ['required', 'max:50', 'no_special_chars'],
        'last_name' => ['required', 'max:50', 'no_special_chars'],
        'phone' => ['phone'],
        'department' => ['max:100'],
        'role' => ['required', 'valid_role']
    ],

    'user_update' => [
        'username' => ['required', 'min:3', 'max:50', 'alphanumeric', 'unique_username'],
        'email' => ['required', 'email', 'unique_email'],
        'first_name' => ['required', 'max:50', 'no_special_chars'],
        'last_name' => ['required', 'max:50', 'no_special_chars'],
        'phone' => ['phone'],
        'department' => ['max:100'],
        'role' => ['required', 'valid_role'],
        'status' => ['in:active,inactive,suspended']
    ],

    'user_password_change' => [
        'current_password' => ['required'],
        'new_password' => ['required', 'strong_password', 'min:8'],
        'confirm_password' => ['required']
    ],

    // Customer Management
    'customer_create' => [
        'customer_code' => ['required', 'valid_customer_code', 'unique_customer_code'],
        'company_name' => ['required', 'max:100'],
        'contact_person' => ['max:100'],
        'email' => ['email'],
        'phone' => ['phone'],
        'address' => ['max:500'],
        'credit_limit' => ['numeric', 'non_negative', 'max:999999999.99']
    ],

    'customer_update' => [
        'customer_code' => ['required', 'valid_customer_code'],
        'company_name' => ['required', 'max:100'],
        'contact_person' => ['max:100'],
        'email' => ['email'],
        'phone' => ['phone'],
        'address' => ['max:500'],
        'credit_limit' => ['numeric', 'non_negative', 'max:999999999.99'],
        'status' => ['in:active,inactive,suspended']
    ],

    // Vendor Management
    'vendor_create' => [
        'vendor_code' => ['required', 'valid_vendor_code', 'unique_vendor_code'],
        'company_name' => ['required', 'max:100'],
        'contact_person' => ['max:100'],
        'email' => ['email'],
        'phone' => ['phone'],
        'address' => ['max:500'],
        'payment_terms' => ['max:50']
    ],

    'vendor_update' => [
        'vendor_code' => ['required', 'valid_vendor_code'],
        'company_name' => ['required', 'max:100'],
        'contact_person' => ['max:100'],
        'email' => ['email'],
        'phone' => ['phone'],
        'address' => ['max:500'],
        'payment_terms' => ['max:50'],
        'status' => ['in:active,inactive,suspended']
    ],

    // Chart of Accounts
    'account_create' => [
        'account_code' => ['required', 'valid_account_code', 'unique_account_code'],
        'account_name' => ['required', 'max:100'],
        'account_type' => ['required', 'in:asset,liability,equity,revenue,expense'],
        'category' => ['max:50'],
        'description' => ['max:500']
    ],

    'account_update' => [
        'account_code' => ['required', 'valid_account_code'],
        'account_name' => ['required', 'max:100'],
        'account_type' => ['required', 'in:asset,liability,equity,revenue,expense'],
        'category' => ['max:50'],
        'description' => ['max:500'],
        'is_active' => ['boolean']
    ],

    // Invoice Management
    'invoice_create' => [
        'invoice_number' => ['required', 'valid_invoice_number', 'unique_invoice_number'],
        'customer_id' => ['required', 'valid_customer'],
        'invoice_date' => ['required', 'date', 'past_date'],
        'due_date' => ['required', 'date', 'future_date'],
        'notes' => ['max:1000']
    ],

    'invoice_update' => [
        'invoice_number' => ['required', 'valid_invoice_number'],
        'customer_id' => ['required', 'valid_customer'],
        'invoice_date' => ['required', 'date'],
        'due_date' => ['required', 'date'],
        'status' => ['in:draft,sent,paid,overdue,cancelled'],
        'notes' => ['max:1000']
    ],

    'invoice_item' => [
        'description' => ['required', 'max:255'],
        'quantity' => ['required', 'numeric', 'positive', 'max:999999.99'],
        'unit_price' => ['required', 'numeric', 'non_negative', 'max:999999999.99'],
        'account_id' => ['valid_chart_of_accounts']
    ],

    // Bill Management
    'bill_create' => [
        'bill_number' => ['required', 'valid_bill_number', 'unique_bill_number'],
        'vendor_id' => ['required', 'valid_vendor'],
        'bill_date' => ['required', 'date', 'past_date'],
        'due_date' => ['required', 'date', 'future_date'],
        'notes' => ['max:1000']
    ],

    'bill_update' => [
        'bill_number' => ['required', 'valid_bill_number'],
        'vendor_id' => ['required', 'valid_vendor'],
        'bill_date' => ['required', 'date'],
        'due_date' => ['required', 'date'],
        'status' => ['in:draft,approved,paid,overdue,cancelled'],
        'notes' => ['max:1000']
    ],

    'bill_item' => [
        'description' => ['required', 'max:255'],
        'quantity' => ['required', 'numeric', 'positive', 'max:999999.99'],
        'unit_price' => ['required', 'numeric', 'non_negative', 'max:999999999.99'],
        'account_id' => ['valid_chart_of_accounts']
    ],

    // Payment Processing
    'payment_received' => [
        'payment_number' => ['required', 'valid_payment_number', 'unique_payment_number'],
        'customer_id' => ['required', 'valid_customer'],
        'invoice_id' => ['valid_invoice'],
        'payment_date' => ['required', 'date', 'past_date'],
        'amount' => ['required', 'numeric', 'positive', 'max:999999999.99'],
        'payment_method' => ['required', 'in:cash,check,bank_transfer,credit_card,other'],
        'reference_number' => ['max:100'],
        'notes' => ['max:500']
    ],

    'payment_made' => [
        'payment_number' => ['required', 'valid_payment_number', 'unique_payment_number'],
        'vendor_id' => ['required', 'valid_vendor'],
        'bill_id' => ['valid_bill'],
        'payment_date' => ['required', 'date', 'past_date'],
        'amount' => ['required', 'numeric', 'positive', 'max:999999999.99'],
        'payment_method' => ['required', 'in:cash,check,bank_transfer,credit_card,other'],
        'reference_number' => ['max:100'],
        'notes' => ['max:500']
    ],

    // Journal Entries
    'journal_entry' => [
        'entry_number' => ['required', 'valid_journal_number', 'unique_journal_number'],
        'entry_date' => ['required', 'date'],
        'description' => ['required', 'max:1000'],
        'reference' => ['max:100']
    ],

    'journal_entry_line' => [
        'account_id' => ['required', 'valid_chart_of_accounts'],
        'debit' => ['numeric', 'non_negative', 'max:999999999.99'],
        'credit' => ['numeric', 'non_negative', 'max:999999999.99'],
        'description' => ['max:255']
    ],

    // Budget Management
    'budget_create' => [
        'budget_year' => ['required', 'integer', 'between:2020,2030'],
        'budget_name' => ['required', 'max:100'],
        'description' => ['max:1000']
    ],

    'budget_item' => [
        'category_id' => ['required', 'valid_budget_category'],
        'account_id' => ['valid_chart_of_accounts'],
        'budgeted_amount' => ['required', 'numeric', 'non_negative', 'max:999999999.99']
    ],

    // Task Management
    'task_create' => [
        'title' => ['required', 'max:255'],
        'description' => ['max:1000'],
        'priority' => ['in:low,medium,high,urgent'],
        'assigned_to' => ['valid_user'],
        'due_date' => ['date', 'future_date'],
        'category' => ['max:50']
    ],

    'task_update' => [
        'title' => ['required', 'max:255'],
        'description' => ['max:1000'],
        'priority' => ['in:low,medium,high,urgent'],
        'status' => ['in:pending,in_progress,completed,cancelled'],
        'assigned_to' => ['valid_user'],
        'due_date' => ['date'],
        'category' => ['max:50']
    ],

    // Workflow Management
    'workflow_create' => [
        'name' => ['required', 'max:100'],
        'description' => ['max:1000'],
        'definition' => ['required', 'json', 'valid_workflow_definition']
    ],

    'workflow_update' => [
        'name' => ['required', 'max:100'],
        'description' => ['max:1000'],
        'definition' => ['required', 'json', 'valid_workflow_definition'],
        'is_active' => ['boolean']
    ],

    // File Upload Validation
    'file_upload_general' => [
        'max_size' => 5242880, // 5MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png'],
        'allowed_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'image/jpeg',
            'image/png'
        ],
        'scan_malware' => true
    ],

    'file_upload_documents' => [
        'max_size' => 10485760, // 10MB
        'allowed_types' => ['pdf', 'doc', 'docx'],
        'allowed_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
        'scan_malware' => true
    ],

    'file_upload_images' => [
        'max_size' => 2097152, // 2MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif'
        ],
        'scan_malware' => true
    ],

    // API Request Validation
    'api_customer_create' => [
        'customer_code' => ['required', 'valid_customer_code'],
        'company_name' => ['required', 'max:100'],
        'email' => ['email'],
        'phone' => ['phone']
    ],

    'api_invoice_create' => [
        'invoice_number' => ['required', 'valid_invoice_number'],
        'customer_id' => ['required', 'integer', 'valid_customer'],
        'total_amount' => ['required', 'numeric', 'positive'],
        'due_date' => ['required', 'date', 'future_date']
    ],

    // Search and Filter Validation
    'search_query' => [
        'query' => ['max:255'],
        'limit' => ['integer', 'min:1', 'max:1000'],
        'offset' => ['integer', 'min:0'],
        'sort_by' => ['max:50'],
        'sort_order' => ['in:asc,desc']
    ],

    // Report Generation
    'report_generate' => [
        'report_type' => ['required', 'in:financial,tax,aging,customer,vendor'],
        'start_date' => ['date'],
        'end_date' => ['date'],
        'format' => ['in:pdf,excel,csv'],
        'filters' => ['json']
    ],

    // Settings and Configuration
    'system_settings' => [
        'company_name' => ['required', 'max:100'],
        'company_address' => ['max:500'],
        'company_phone' => ['phone'],
        'company_email' => ['email'],
        'tax_rate' => ['numeric', 'min:0', 'max:100'],
        'currency' => ['max:10'],
        'timezone' => ['max:50']
    ],

    'user_preferences' => [
        'theme' => ['in:light,dark,auto'],
        'language' => ['max:10'],
        'timezone' => ['max:50'],
        'items_per_page' => ['integer', 'min:5', 'max:100'],
        'date_format' => ['max:20'],
        'currency' => ['max:10'],
        'email_notifications' => ['boolean'],
        'sms_notifications' => ['boolean']
    ]
];

// Business Rules Validation
$BUSINESS_RULES = [
    'invoice_total_calculation' => [
        'validator' => function($data) {
            $calculatedTotal = 0;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $calculatedTotal += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                }
            }

            $taxAmount = $calculatedTotal * (($data['tax_rate'] ?? 0) / 100);
            $expectedTotal = $calculatedTotal + $taxAmount;

            return abs(($data['total_amount'] ?? 0) - $expectedTotal) < 0.01;
        },
        'message' => 'Invoice total does not match calculated amount'
    ],

    'journal_entry_balance' => [
        'validator' => function($data) {
            $totalDebit = 0;
            $totalCredit = 0;

            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $totalDebit += $line['debit'] ?? 0;
                    $totalCredit += $line['credit'] ?? 0;
                }
            }

            return abs($totalDebit - $totalCredit) < 0.01;
        },
        'message' => 'Journal entry debits and credits must balance'
    ],

    'payment_amount_validation' => [
        'validator' => function($data) {
            if (!isset($data['invoice_id']) || !isset($data['amount'])) {
                return true; // Skip if no invoice specified
            }

            // Check if payment amount doesn't exceed outstanding balance
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT balance FROM invoices WHERE id = ?");
            $stmt->execute([$data['invoice_id']]);
            $invoice = $stmt->fetch();

            return $invoice && $data['amount'] <= $invoice['balance'];
        },
        'message' => 'Payment amount cannot exceed outstanding invoice balance'
    ],

    'budget_limit_check' => [
        'validator' => function($data) {
            if (!isset($data['account_id']) || !isset($data['amount'])) {
                return true;
            }

            // Check if transaction would exceed budget
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT bi.budgeted_amount, bi.actual_amount
                FROM budget_items bi
                INNER JOIN budgets b ON bi.budget_id = b.id
                WHERE bi.account_id = ? AND b.status = 'active' AND YEAR(b.budget_year) = YEAR(CURDATE())
                LIMIT 1
            ");
            $stmt->execute([$data['account_id']]);
            $budget = $stmt->fetch();

            if ($budget) {
                $remainingBudget = $budget['budgeted_amount'] - $budget['actual_amount'];
                return $data['amount'] <= $remainingBudget;
            }

            return true; // No budget constraint
        },
        'message' => 'Transaction would exceed budget limit'
    ],

    'duplicate_invoice_check' => [
        'validator' => function($data) {
            if (!isset($data['invoice_number'])) return true;

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
            $stmt->execute([$data['invoice_number']]);

            return $stmt->rowCount() === 0;
        },
        'message' => 'Invoice number already exists'
    ],

    'customer_credit_limit' => [
        'validator' => function($data) {
            if (!isset($data['customer_id']) || !isset($data['total_amount'])) return true;

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT credit_limit, current_balance
                FROM customers
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$data['customer_id']]);
            $customer = $stmt->fetch();

            if (!$customer) return false;

            $availableCredit = $customer['credit_limit'] - $customer['current_balance'];
            return $data['total_amount'] <= $availableCredit;
        },
        'message' => 'Invoice total exceeds customer credit limit'
    ]
];

// Sanitization Rules
$SANITIZATION_RULES = [
    'user_input' => [
        'username' => 'string',
        'email' => 'email',
        'first_name' => 'string',
        'last_name' => 'string',
        'phone' => 'string',
        'department' => 'string'
    ],

    'financial_data' => [
        'amount' => 'float',
        'quantity' => 'float',
        'unit_price' => 'float',
        'total_amount' => 'float',
        'tax_rate' => 'float',
        'credit_limit' => 'float'
    ],

    'dates' => [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'entry_date' => 'date'
    ],

    'text_content' => [
        'description' => 'string',
        'notes' => 'string',
        'comments' => 'string'
    ],

    'file_names' => [
        'original_name' => 'filename',
        'file_name' => 'filename'
    ]
];

// CSRF Protected Forms
$CSRF_PROTECTED_FORMS = [
    'user_create',
    'user_update',
    'customer_create',
    'customer_update',
    'vendor_create',
    'vendor_update',
    'invoice_create',
    'invoice_update',
    'bill_create',
    'bill_update',
    'payment_create',
    'journal_entry_create',
    'workflow_create',
    'workflow_update'
];

// Rate Limiting Rules
$RATE_LIMIT_RULES = [
    'api_requests' => [
        'limit' => 1000, // requests per hour
        'window' => 3600 // seconds
    ],

    'login_attempts' => [
        'limit' => 5, // attempts per 15 minutes
        'window' => 900,
        'block_duration' => 1800 // 30 minutes
    ],

    'file_uploads' => [
        'limit' => 50, // uploads per hour
        'window' => 3600
    ],

    'report_generation' => [
        'limit' => 20, // reports per hour
        'window' => 3600
    ]
];

// Input Filtering Rules (for XSS prevention)
$INPUT_FILTER_RULES = [
    'allow_html_tags' => [
        'description',
        'notes',
        'comments'
    ],

    'strip_all_tags' => [
        'username',
        'email',
        'first_name',
        'last_name',
        'phone',
        'company_name',
        'account_code',
        'invoice_number'
    ],

    'allow_basic_formatting' => [
        'email_content',
        'notification_template'
    ]
];
?>
