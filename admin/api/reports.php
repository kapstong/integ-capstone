<?php
// For API endpoints, we don't want to redirect on auth failure
// So we'll handle authentication differently
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

// Check authentication for API calls
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $logger);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("API Error in reports.php: " . $e->getMessage());
    if (isset($logger)) {
        $logger->log("API Error in reports.php: " . $e->getMessage(), 'ERROR');
    }
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function handleGet($db, $logger) {
    try {
        $reportType = isset($_GET['type']) ? $_GET['type'] : null;
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
        $format = isset($_GET['format']) ? $_GET['format'] : 'json'; // json, csv, pdf

        if (!$reportType) {
            http_response_code(400);
            echo json_encode(['error' => 'Report type is required']);
            return;
        }

        // Set default date range if not provided (current month)
        if (!$dateFrom) {
            $dateFrom = date('Y-m-01'); // First day of current month
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-t'); // Last day of current month
        }

        switch ($reportType) {
            case 'balance_sheet':
                generateBalanceSheet($db, $dateFrom, $dateTo, $format);
                break;
            case 'income_statement':
                generateIncomeStatement($db, $dateFrom, $dateTo, $format);
                break;
            case 'cash_flow':
                generateCashFlowStatement($db, $dateFrom, $dateTo, $format);
                break;
            case 'trial_balance':
                generateTrialBalance($db, $dateFrom, $dateTo, $format);
                break;
            case 'aging_receivable':
                generateAgingReceivable($db, $format);
                break;
            case 'aging_payable':
                generateAgingPayable($db, $format);
                break;
            case 'vendor_summary':
                generateVendorSummary($db, $dateFrom, $dateTo, $format);
                break;
            case 'customer_summary':
                generateCustomerSummary($db, $dateFrom, $dateTo, $format);
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid report type']);
                return;
        }

    } catch (Exception $e) {
        $logger->log("Error in handleGet reports: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate report']);
    }
}

function generateBalanceSheet($db, $dateFrom, $dateTo, $format) {
    // Get asset accounts balances
    $stmt = $db->prepare("
        SELECT
            coa.account_name,
            coa.account_code,
            coa.account_type,
            COALESCE(SUM(CASE WHEN jel.debit > 0 THEN jel.debit ELSE -jel.credit END), 0) as balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE coa.account_type = 'asset'
        AND coa.is_active = 1
        AND je.entry_date <= ?
        GROUP BY coa.id, coa.account_name, coa.account_code, coa.account_type
        HAVING balance != 0
        ORDER BY coa.account_code
    ");
    $stmt->execute([$dateTo]);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get liability accounts balances
    $stmt = $db->prepare("
        SELECT
            coa.account_name,
            coa.account_code,
            coa.account_type,
            COALESCE(SUM(CASE WHEN jel.credit > 0 THEN jel.credit ELSE -jel.debit END), 0) as balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE coa.account_type = 'liability'
        AND coa.is_active = 1
        AND je.entry_date <= ?
        GROUP BY coa.id, coa.account_name, coa.account_code, coa.account_type
        HAVING balance != 0
        ORDER BY coa.account_code
    ");
    $stmt->execute([$dateTo]);
    $liabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get equity accounts balances
    $stmt = $db->prepare("
        SELECT
            coa.account_name,
            coa.account_code,
            coa.account_type,
            COALESCE(SUM(CASE WHEN jel.credit > 0 THEN jel.credit ELSE -jel.debit END), 0) as balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE coa.account_type = 'equity'
        AND coa.is_active = 1
        AND je.entry_date <= ?
        GROUP BY coa.id, coa.account_name, coa.account_code, coa.account_type
        HAVING balance != 0
        ORDER BY coa.account_code
    ");
    $stmt->execute([$dateTo]);
    $equities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate retained earnings (net profit)
    $netProfit = calculateNetProfit($db, $dateFrom, $dateTo);

    $totalAssets = array_sum(array_column($assets, 'balance'));
    $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
    $totalEquity = array_sum(array_column($equities, 'balance')) + $netProfit;

    $report = [
        'report_type' => 'Balance Sheet',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'generated_at' => date('Y-m-d H:i:s'),
        'assets' => [
            'accounts' => $assets,
            'total' => $totalAssets
        ],
        'liabilities' => [
            'accounts' => $liabilities,
            'total' => $totalLiabilities
        ],
        'equity' => [
            'accounts' => $equities,
            'retained_earnings' => $netProfit,
            'total' => $totalEquity
        ],
        'total_liabilities_equity' => $totalLiabilities + $totalEquity
    ];

    outputReport($report, $format, 'balance_sheet');
}

function generateIncomeStatement($db, $dateFrom, $dateTo, $format) {
    try {
        // Get revenue accounts
        $stmt = $db->prepare("
            SELECT
                coa.account_name,
                coa.account_code,
                COALESCE(SUM(jel.credit - jel.debit), 0) as amount
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE coa.account_type = 'revenue'
            AND coa.is_active = 1
            AND je.entry_date BETWEEN ? AND ?
            GROUP BY coa.id, coa.account_name, coa.account_code
            HAVING amount != 0
            ORDER BY coa.account_code
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get expense accounts
        $stmt = $db->prepare("
            SELECT
                coa.account_name,
                coa.account_code,
                COALESCE(SUM(jel.debit - jel.credit), 0) as amount
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE coa.account_type = 'expense'
            AND coa.is_active = 1
            AND je.entry_date BETWEEN ? AND ?
            GROUP BY coa.id, coa.account_name, coa.account_code
            HAVING amount != 0
            ORDER BY coa.account_code
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalRevenue = array_sum(array_column($revenues, 'amount'));
        $totalExpenses = array_sum(array_column($expenses, 'amount'));
        $netProfit = $totalRevenue - $totalExpenses;

        $report = [
            'report_type' => 'Income Statement',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'generated_at' => date('Y-m-d H:i:s'),
            'revenue' => [
                'accounts' => $revenues,
                'total' => $totalRevenue
            ],
            'expenses' => [
                'accounts' => $expenses,
                'total' => $totalExpenses
            ],
            'net_profit' => $netProfit
        ];

        outputReport($report, $format, 'income_statement');
    } catch (Exception $e) {
        // Return empty report structure on error
        $report = [
            'report_type' => 'Income Statement',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'generated_at' => date('Y-m-d H:i:s'),
            'error' => 'Unable to generate report: ' . $e->getMessage(),
            'revenue' => [
                'accounts' => [],
                'total' => 0
            ],
            'expenses' => [
                'accounts' => [],
                'total' => 0
            ],
            'net_profit' => 0
        ];

        outputReport($report, $format, 'income_statement');
    }
}

function generateCashFlowStatement($db, $dateFrom, $dateTo, $format) {
    // Operating activities - simplified calculation
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN jel.debit > 0 THEN jel.debit ELSE -jel.credit END), 0) as cash_flow
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        WHERE coa.account_code LIKE 'CASH%'
        AND je.entry_date BETWEEN ? AND ?
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $operatingCashFlow = $stmt->fetch()['cash_flow'] ?? 0;

    // Calculate net profit for the period
    $netProfit = calculateNetProfit($db, $dateFrom, $dateTo);

    $report = [
        'report_type' => 'Cash Flow Statement',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'generated_at' => date('Y-m-d H:i:s'),
        'operating_activities' => [
            'net_profit' => $netProfit,
            'adjustments' => 0, // Simplified
            'net_cash_operating' => $operatingCashFlow
        ],
        'investing_activities' => [
            'net_cash_investing' => 0 // Simplified
        ],
        'financing_activities' => [
            'net_cash_financing' => 0 // Simplified
        ],
        'net_cash_flow' => $operatingCashFlow
    ];

    outputReport($report, $format, 'cash_flow_statement');
}

function generateTrialBalance($db, $dateFrom, $dateTo, $format) {
    $stmt = $db->prepare("
        SELECT
            coa.account_code,
            coa.account_name,
            coa.account_type,
            COALESCE(SUM(jel.debit), 0) as debit_total,
            COALESCE(SUM(jel.credit), 0) as credit_total,
            (COALESCE(SUM(jel.debit), 0) - COALESCE(SUM(jel.credit), 0)) as balance
        FROM chart_of_accounts coa
        LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
        LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE coa.is_active = 1
        AND (je.entry_date IS NULL OR je.entry_date <= ?)
        GROUP BY coa.id, coa.account_code, coa.account_name, coa.account_type
        HAVING debit_total != 0 OR credit_total != 0
        ORDER BY coa.account_code
    ");
    $stmt->execute([$dateTo]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDebits = array_sum(array_column($accounts, 'debit_total'));
    $totalCredits = array_sum(array_column($accounts, 'credit_total'));

    $report = [
        'report_type' => 'Trial Balance',
        'date_to' => $dateTo,
        'generated_at' => date('Y-m-d H:i:s'),
        'accounts' => $accounts,
        'totals' => [
            'debit' => $totalDebits,
            'credit' => $totalCredits,
            'difference' => abs($totalDebits - $totalCredits)
        ]
    ];

    outputReport($report, $format, 'trial_balance');
}

function generateAgingReceivable($db, $format) {
    $stmt = $db->prepare("
        SELECT
            c.company_name as customer_name,
            i.invoice_number,
            i.total_amount,
            i.balance,
            DATEDIFF(CURDATE(), i.due_date) as days_overdue,
            CASE
                WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 'current'
                WHEN DATEDIFF(CURDATE(), i.due_date) <= 30 THEN '1-30'
                WHEN DATEDIFF(CURDATE(), i.due_date) <= 60 THEN '31-60'
                WHEN DATEDIFF(CURDATE(), i.due_date) <= 90 THEN '61-90'
                ELSE '90+'
            END as aging_category
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.balance > 0
        AND i.status IN ('sent', 'overdue')
        ORDER BY c.company_name, i.due_date
    ");
    $stmt->execute();
    $agingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by aging categories
    $categories = ['current' => [], '1-30' => [], '31-60' => [], '61-90' => [], '90+' => []];
    foreach ($agingData as $item) {
        $categories[$item['aging_category']][] = $item;
    }

    $totals = [];
    foreach ($categories as $category => $items) {
        $totals[$category] = array_sum(array_column($items, 'balance'));
    }

    $report = [
        'report_type' => 'Aging of Accounts Receivable',
        'generated_at' => date('Y-m-d H:i:s'),
        'aging_data' => $agingData,
        'categories' => $categories,
        'totals' => $totals,
        'grand_total' => array_sum($totals)
    ];

    outputReport($report, $format, 'aging_receivable');
}

function generateAgingPayable($db, $format) {
    $stmt = $db->prepare("
        SELECT
            v.company_name as vendor_name,
            b.bill_number,
            b.total_amount,
            b.balance,
            DATEDIFF(CURDATE(), b.due_date) as days_overdue,
            CASE
                WHEN DATEDIFF(CURDATE(), b.due_date) <= 0 THEN 'current'
                WHEN DATEDIFF(CURDATE(), b.due_date) <= 30 THEN '1-30'
                WHEN DATEDIFF(CURDATE(), b.due_date) <= 60 THEN '31-60'
                WHEN DATEDIFF(CURDATE(), b.due_date) <= 90 THEN '61-90'
                ELSE '90+'
            END as aging_category
        FROM bills b
        JOIN vendors v ON b.vendor_id = v.id
        WHERE b.balance > 0
        AND b.status IN ('approved', 'overdue')
        ORDER BY v.company_name, b.due_date
    ");
    $stmt->execute();
    $agingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by aging categories
    $categories = ['current' => [], '1-30' => [], '31-60' => [], '61-90' => [], '90+' => []];
    foreach ($agingData as $item) {
        $categories[$item['aging_category']][] = $item;
    }

    $totals = [];
    foreach ($categories as $category => $items) {
        $totals[$category] = array_sum(array_column($items, 'balance'));
    }

    $report = [
        'report_type' => 'Aging of Accounts Payable',
        'generated_at' => date('Y-m-d H:i:s'),
        'aging_data' => $agingData,
        'categories' => $categories,
        'totals' => $totals,
        'grand_total' => array_sum($totals)
    ];

    outputReport($report, $format, 'aging_payable');
}

function generateVendorSummary($db, $dateFrom, $dateTo, $format) {
    $stmt = $db->prepare("
        SELECT
            v.company_name,
            v.contact_person,
            COUNT(b.id) as bills_count,
            COALESCE(SUM(b.total_amount), 0) as total_billed,
            COALESCE(SUM(b.balance), 0) as outstanding_balance,
            COALESCE(SUM(p.amount), 0) as payments_received
        FROM vendors v
        LEFT JOIN bills b ON v.id = b.vendor_id AND b.created_at BETWEEN ? AND ?
        LEFT JOIN payments_made p ON v.id = p.vendor_id AND p.payment_date BETWEEN ? AND ?
        WHERE v.is_active = 1
        GROUP BY v.id, v.company_name, v.contact_person
        ORDER BY outstanding_balance DESC
    ");
    $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
    $vendorData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = [
        'report_type' => 'Vendor Summary Report',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'generated_at' => date('Y-m-d H:i:s'),
        'vendors' => $vendorData,
        'totals' => [
            'total_vendors' => count($vendorData),
            'total_billed' => array_sum(array_column($vendorData, 'total_billed')),
            'total_outstanding' => array_sum(array_column($vendorData, 'outstanding_balance')),
            'total_payments' => array_sum(array_column($vendorData, 'payments_received'))
        ]
    ];

    outputReport($report, $format, 'vendor_summary');
}

function generateCustomerSummary($db, $dateFrom, $dateTo, $format) {
    $stmt = $db->prepare("
        SELECT
            c.company_name,
            c.contact_person,
            COUNT(i.id) as invoices_count,
            COALESCE(SUM(i.total_amount), 0) as total_invoiced,
            COALESCE(SUM(i.balance), 0) as outstanding_balance,
            COALESCE(SUM(p.amount), 0) as payments_received
        FROM customers c
        LEFT JOIN invoices i ON c.id = i.customer_id AND i.created_at BETWEEN ? AND ?
        LEFT JOIN payments_received p ON c.id = p.customer_id AND p.payment_date BETWEEN ? AND ?
        WHERE c.is_active = 1
        GROUP BY c.id, c.company_name, c.contact_person
        ORDER BY outstanding_balance DESC
    ");
    $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
    $customerData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = [
        'report_type' => 'Customer Summary Report',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'generated_at' => date('Y-m-d H:i:s'),
        'customers' => $customerData,
        'totals' => [
            'total_customers' => count($customerData),
            'total_invoiced' => array_sum(array_column($customerData, 'total_invoiced')),
            'total_outstanding' => array_sum(array_column($customerData, 'outstanding_balance')),
            'total_payments' => array_sum(array_column($customerData, 'payments_received'))
        ]
    ];

    outputReport($report, $format, 'customer_summary');
}

function calculateNetProfit($db, $dateFrom, $dateTo) {
    // Calculate total revenue
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(jel.credit - jel.debit), 0) as total_revenue
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        WHERE coa.account_type = 'revenue'
        AND je.entry_date BETWEEN ? AND ?
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $totalRevenue = $stmt->fetch()['total_revenue'] ?? 0;

    // Calculate total expenses
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(jel.debit - jel.credit), 0) as total_expenses
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        WHERE coa.account_type = 'expense'
        AND je.entry_date BETWEEN ? AND ?
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $totalExpenses = $stmt->fetch()['total_expenses'] ?? 0;

    return $totalRevenue - $totalExpenses;
}

function outputReport($report, $format, $filename) {
    switch ($format) {
        case 'csv':
            outputCSV($report, $filename);
            break;
        case 'pdf':
            // For now, return JSON with PDF note
            $report['note'] = 'PDF format not yet implemented. Use JSON format.';
            echo json_encode($report);
            break;
        case 'json':
        default:
            echo json_encode($report);
            break;
    }
}

function outputCSV($report, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Write report header
    fputcsv($output, ['Report Type', $report['report_type']]);
    fputcsv($output, ['Generated At', $report['generated_at']]);
    fputcsv($output, []);

    // Write report-specific data based on type
    switch ($filename) {
        case 'balance_sheet':
            fputcsv($output, ['ASSETS']);
            fputcsv($output, ['Account', 'Balance']);
            foreach ($report['assets']['accounts'] as $account) {
                fputcsv($output, [$account['account_name'], $account['balance']]);
            }
            fputcsv($output, ['Total Assets', $report['assets']['total']]);
            fputcsv($output, []);

            fputcsv($output, ['LIABILITIES']);
            fputcsv($output, ['Account', 'Balance']);
            foreach ($report['liabilities']['accounts'] as $account) {
                fputcsv($output, [$account['account_name'], $account['balance']]);
            }
            fputcsv($output, ['Total Liabilities', $report['liabilities']['total']]);
            fputcsv($output, []);

            fputcsv($output, ['EQUITY']);
            fputcsv($output, ['Account', 'Balance']);
            foreach ($report['equity']['accounts'] as $account) {
                fputcsv($output, [$account['account_name'], $account['balance']]);
            }
            fputcsv($output, ['Retained Earnings', $report['equity']['retained_earnings']]);
            fputcsv($output, ['Total Equity', $report['equity']['total']]);
            break;

        case 'trial_balance':
            fputcsv($output, ['Account Code', 'Account Name', 'Debit Total', 'Credit Total', 'Balance']);
            foreach ($report['accounts'] as $account) {
                fputcsv($output, [
                    $account['account_code'],
                    $account['account_name'],
                    $account['debit_total'],
                    $account['credit_total'],
                    $account['balance']
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Total Debits', $report['totals']['debit']]);
            fputcsv($output, ['Total Credits', $report['totals']['credit']]);
            fputcsv($output, ['Difference', $report['totals']['difference']]);
            break;

        // Add more cases for other report types as needed
        default:
            fputcsv($output, ['Report data in JSON format']);
            fputcsv($output, [json_encode($report)]);
    }

    fclose($output);
    exit();
}
?>
