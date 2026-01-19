<?php
/**
 * ENHANCED REPORTS API
 * Returns detailed breakdowns showing data source and detailed information
 * Every piece of data includes: source table, source record ID, timestamp, user who created it
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $reportType = $_GET['type'] ?? null;
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');

    if (!$reportType) {
        http_response_code(400);
        echo json_encode(['error' => 'Report type is required']);
        exit();
    }

    switch ($reportType) {
        case 'income_statement_detailed':
            generateDetailedIncomeStatement($db, $dateFrom, $dateTo);
            break;
        case 'budget_detailed':
            generateDetailedBudgetReport($db, $dateFrom, $dateTo);
            break;
        case 'transaction_breakdown':
            generateTransactionBreakdown($db, $dateFrom, $dateTo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid report type']);
    }

} catch (Exception $e) {
    error_log("Enhanced Reports API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Generate detailed income statement with complete data source tracking
 */
function generateDetailedIncomeStatement($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT
            je.id as journal_entry_id,
            je.entry_number,
            je.entry_date,
            je.description as transaction_description,
            je.created_at as transaction_created_at,
            creator.username as created_by_user,
            creator.full_name as created_by_name,
            coa.id as account_id,
            coa.account_code,
            coa.account_name,
            coa.account_type,
            jel.id as line_id,
            jel.debit,
            jel.credit,
            jel.description as line_description,
            CASE
                WHEN coa.account_type = 'revenue' THEN (jel.credit - jel.debit)
                WHEN coa.account_type = 'expense' THEN (jel.debit - jel.credit)
                ELSE 0
            END as amount
        FROM journal_entries je
        JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        LEFT JOIN users creator ON je.created_by = creator.id
        WHERE (coa.account_type = 'revenue' OR coa.account_type = 'expense')
        AND je.entry_date BETWEEN ? AND ?
        AND je.status = 'posted'
        ORDER BY coa.account_type, coa.account_code, je.entry_date
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by account type
    $revenues = [];
    $expenses = [];

    foreach ($transactions as $txn) {
        $detail = [
            'source' => [
                'table' => 'journal_entries',
                'record_id' => $txn['journal_entry_id'],
                'line_id' => $txn['line_id'],
                'entry_number' => $txn['entry_number']
            ],
            'transaction' => [
                'date' => $txn['entry_date'],
                'description' => $txn['transaction_description'],
                'created_at' => $txn['transaction_created_at'],
                'created_by' => [
                    'username' => $txn['created_by_user'],
                    'full_name' => $txn['created_by_name']
                ]
            ],
            'account' => [
                'id' => $txn['account_id'],
                'code' => $txn['account_code'],
                'name' => $txn['account_name'],
                'type' => $txn['account_type']
            ],
            'amounts' => [
                'debit' => floatval($txn['debit']),
                'credit' => floatval($txn['credit']),
                'net_amount' => floatval($txn['amount'])
            ],
            'line_description' => $txn['line_description']
        ];

        if ($txn['account_type'] === 'revenue') {
            $revenues[] = $detail;
        } else {
            $expenses[] = $detail;
        }
    }

    $totalRevenue = array_sum(array_column($revenues, 'amounts')['net_amount'] ?? [0]);
    $totalExpenses = array_sum(array_column($expenses, 'amounts')['net_amount'] ?? [0]);

    // Calculate totals properly
    $revenueTotal = 0;
    foreach ($revenues as $rev) {
        $revenueTotal += $rev['amounts']['net_amount'];
    }

    $expenseTotal = 0;
    foreach ($expenses as $exp) {
        $expenseTotal += $exp['amounts']['net_amount'];
    }

    $report = [
        'report_type' => 'Detailed Income Statement',
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ],
        'generated' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'by_user' => $_SESSION['user']['username'],
            'full_name' => $_SESSION['user']['name']
        ],
        'data_sources' => [
            'primary_table' => 'journal_entries',
            'related_tables' => ['journal_entry_lines', 'chart_of_accounts', 'users'],
            'total_transactions' => count($transactions),
            'date_range_filter' => "entry_date BETWEEN '{$dateFrom}' AND '{$dateTo}'"
        ],
        'revenue' => [
            'total' => $revenueTotal,
            'transaction_count' => count($revenues),
            'details' => $revenues
        ],
        'expenses' => [
            'total' => $expenseTotal,
            'transaction_count' => count($expenses),
            'details' => $expenses
        ],
        'summary' => [
            'total_revenue' => $revenueTotal,
            'total_expenses' => $expenseTotal,
            'net_profit' => $revenueTotal - $expenseTotal,
            'profit_margin_percent' => $revenueTotal > 0 ? round(($revenueTotal - $expenseTotal) / $revenueTotal * 100, 2) : 0
        ]
    ];

    echo json_encode($report, JSON_PRETTY_PRINT);
}

/**
 * Generate detailed budget report with complete tracking
 */
function generateDetailedBudgetReport($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT
            b.id as budget_id,
            b.budget_year,
            b.budget_name,
            b.description,
            b.total_budgeted,
            b.status,
            b.created_at,
            b.updated_at,
            creator.username as created_by_user,
            creator.full_name as created_by_name,
            approver.username as approved_by_user,
            approver.full_name as approved_by_name,
            d.id as department_id,
            d.dept_name,
            d.dept_code,
            bl.id as liquidation_id,
            bl.liquidation_number,
            bl.total_amount as liquidated_amount,
            bl.status as liquidation_status,
            bl.submission_date,
            COUNT(lr.id) as receipt_count,
            COALESCE(SUM(lr.amount), 0) as total_receipts_amount
        FROM budgets b
        LEFT JOIN users creator ON b.created_by = creator.id
        LEFT JOIN users approver ON b.approved_by = approver.id
        LEFT JOIN departments d ON b.department_id = d.id
        LEFT JOIN budget_liquidations bl ON b.id = bl.budget_id
        LEFT JOIN liquidation_receipts lr ON bl.id = lr.liquidation_id
        WHERE b.budget_year BETWEEN YEAR(?) AND YEAR(?)
        GROUP BY b.id, bl.id
        ORDER BY b.budget_year DESC, d.dept_name, b.budget_name
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedBudgets = [];
    foreach ($budgets as $budget) {
        $formattedBudgets[] = [
            'source' => [
                'table' => 'budgets',
                'record_id' => $budget['budget_id']
            ],
            'budget' => [
                'id' => $budget['budget_id'],
                'year' => $budget['budget_year'],
                'name' => $budget['budget_name'],
                'description' => $budget['description'],
                'amount' => floatval($budget['total_budgeted']),
                'status' => $budget['status']
            ],
            'department' => [
                'id' => $budget['department_id'],
                'name' => $budget['dept_name'],
                'code' => $budget['dept_code']
            ],
            'audit_trail' => [
                'created_at' => $budget['created_at'],
                'updated_at' => $budget['updated_at'],
                'created_by' => [
                    'username' => $budget['created_by_user'],
                    'full_name' => $budget['created_by_name']
                ],
                'approved_by' => [
                    'username' => $budget['approved_by_user'],
                    'full_name' => $budget['approved_by_name']
                ]
            ],
            'liquidation' => $budget['liquidation_id'] ? [
                'source' => [
                    'table' => 'budget_liquidations',
                    'record_id' => $budget['liquidation_id']
                ],
                'liquidation_number' => $budget['liquidation_number'],
                'amount' => floatval($budget['liquidated_amount']),
                'status' => $budget['liquidation_status'],
                'submission_date' => $budget['submission_date'],
                'receipts' => [
                    'count' => $budget['receipt_count'],
                    'total_amount' => floatval($budget['total_receipts_amount'])
                ]
            ] : null
        ];
    }

    $report = [
        'report_type' => 'Detailed Budget Report',
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ],
        'generated' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'by_user' => $_SESSION['user']['username'],
            'full_name' => $_SESSION['user']['name']
        ],
        'data_sources' => [
            'primary_table' => 'budgets',
            'related_tables' => ['departments', 'users', 'budget_liquidations', 'liquidation_receipts'],
            'total_budgets' => count($budgets)
        ],
        'budgets' => $formattedBudgets,
        'summary' => [
            'total_budgeted' => array_sum(array_column(array_column($formattedBudgets, 'budget'), 'amount')),
            'total_liquidated' => array_sum(array_map(function($b) {
                return $b['liquidation']['amount'] ?? 0;
            }, $formattedBudgets))
        ]
    ];

    echo json_encode($report, JSON_PRETTY_PRINT);
}

/**
 * Generate complete transaction breakdown
 */
function generateTransactionBreakdown($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT
            'journal_entry' as transaction_type,
            je.id as transaction_id,
            je.entry_number as reference_number,
            je.entry_date as transaction_date,
            je.description,
            je.total_amount,
            je.status,
            je.created_at,
            creator.username as created_by,
            creator.full_name as created_by_name,
            COUNT(jel.id) as line_items_count
        FROM journal_entries je
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        LEFT JOIN users creator ON je.created_by = creator.id
        WHERE je.entry_date BETWEEN ? AND ?
        GROUP BY je.id
        ORDER BY je.entry_date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedTransactions = array_map(function($txn) {
        return [
            'source' => [
                'table' => 'journal_entries',
                'record_id' => $txn['transaction_id'],
                'line_items_count' => $txn['line_items_count']
            ],
            'transaction' => [
                'type' => $txn['transaction_type'],
                'reference' => $txn['reference_number'],
                'date' => $txn['transaction_date'],
                'description' => $txn['description'],
                'amount' => floatval($txn['total_amount']),
                'status' => $txn['status']
            ],
            'audit' => [
                'created_at' => $txn['created_at'],
                'created_by' => [
                    'username' => $txn['created_by'],
                    'full_name' => $txn['created_by_name']
                ]
            ]
        ];
    }, $transactions);

    $report = [
        'report_type' => 'Transaction Breakdown',
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ],
        'generated' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'by_user' => $_SESSION['user']['username']
        ],
        'data_sources' => [
            'tables' => ['journal_entries', 'journal_entry_lines', 'users'],
            'filters_applied' => [
                'date_range' => "entry_date BETWEEN '{$dateFrom}' AND '{$dateTo}'"
            ]
        ],
        'transactions' => $formattedTransactions,
        'summary' => [
            'total_transactions' => count($transactions),
            'total_amount' => array_sum(array_column(array_column($formattedTransactions, 'transaction'), 'amount'))
        ]
    ];

    echo json_encode($report, JSON_PRETTY_PRINT);
}
?>
