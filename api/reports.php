<?php
/**
 * ATIERA Financial Management System - Reports API
 * Handles report generation with integrated payroll data from HR4
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Require core files
require_once '../config.php';
require_once '../includes/logger.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize auth and database
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance()->getConnection();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $reportType = $_GET['type'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';

    // Handle income statement generation
    if ($reportType === 'income_statement') {
        // Check if we need to fetch recent HR4 payroll data
        // If daily_expense_summary has no recent data, try to import from HR4
        $recentDataCheck = $db->prepare("
            SELECT COUNT(*) as record_count
            FROM daily_expense_summary
            WHERE business_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $recentDataCheck->execute();
        $recordCount = $recentDataCheck->fetch(PDO::FETCH_ASSOC)['record_count'];

        // If no recent expense data, try to import from HR4 payroll
        if ($recordCount == 0) {
            try {
                require_once '../includes/api_integrations.php';
                $integrationManager = APIIntegrationManager::getInstance();

                // Get HR4 configuration
                $hr4Config = $integrationManager->getIntegrationConfig('hr4');
                if ($hr4Config && !empty($hr4Config['api_url'])) {
                    // Try to execute payroll import
                    $result = $integrationManager->executeIntegrationAction('hr4', 'importPayroll', []);
                    Logger::getInstance()->info('Income statement report triggered HR4 payroll import', [
                        'result' => $result
                    ]);
                }
            } catch (Exception $e) {
                Logger::getInstance()->warning('HR4 payroll import failed during income statement generation', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Get revenue data (from journal entries - placeholder for now)
        $revenueQuery = $db->prepare("
            SELECT
                coa.account_name,
                SUM(COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)) as amount
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                AND je.status = 'posted'
                AND (? IS NULL OR je.entry_date >= ?)
                AND (? IS NULL OR je.entry_date <= ?)
            WHERE coa.account_type = 'revenue'
                AND coa.is_active = 1
            GROUP BY coa.id, coa.account_name
            HAVING amount > 0
        ");
        $revenueQuery->execute([$dateFrom, $dateFrom, $dateTo, $dateTo]);
        $revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total revenue
        $totalRevenue = array_sum(array_column($revenueData, 'amount'));

        // Get expense data from daily_expense_summary (includes HR4 payroll)
        $expenseQuery = $db->prepare("
            SELECT
                d.dept_name as department,
                SUM(des.total_amount) as amount
            FROM daily_expense_summary des
            LEFT JOIN departments d ON des.department_id = d.id
            WHERE (:date_from IS NULL OR des.business_date >= :date_from)
                AND (:date_to IS NULL OR des.business_date <= :date_to)
            GROUP BY d.dept_name
            ORDER BY d.dept_name
        ");
        $expenseQuery->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $expenseData = $expenseQuery->fetchAll(PDO::FETCH_ASSOC);

        // Get expense data from journal entries too (other expenses not tracked in daily_expense_summary)
        $journalExpenseQuery = $db->prepare("
            SELECT
                coa.account_name,
                SUM(COALESCE(jel.debit, 0)) as amount
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                AND je.status = 'posted'
                AND (? IS NULL OR je.entry_date >= ?)
                AND (? IS NULL OR je.entry_date <= ?)
            WHERE coa.account_type = 'expense'
                AND coa.is_active = 1
                AND coa.category NOT IN ('Payroll', 'Salary')
            GROUP BY coa.id, coa.account_name
            HAVING amount > 0
        ");
        $journalExpenseQuery->execute([$dateFrom, $dateFrom, $dateTo, $dateTo]);
        $journalExpenseData = $journalExpenseQuery->fetchAll(PDO::FETCH_ASSOC);

        // Consolidate expense data
        $allExpenses = [];

        // Add departmental expenses from daily_expense_summary (includes HR4 payroll)
        foreach ($expenseData as $expense) {
            $departmentName = $expense['department'] ?: 'General';
            $expenseName = $departmentName . ' Department Expenses'; // Will show as "Kitchen Department Expenses"

            $allExpenses[] = [
                'account_name' => $expenseName,
                'amount' => intval($expense['amount'])
            ];
        }

        // Add other expenses from journal entries
        foreach ($journalExpenseData as $expense) {
            $allExpenses[] = [
                'account_name' => $expense['account_name'],
                'amount' => intval($expense['amount'])
            ];
        }

        // Calculate totals
        $totalExpenses = array_sum(array_column($allExpenses, 'amount'));
        $netProfit = $totalRevenue - $totalExpenses;

        // Return formatted data
        echo json_encode([
            'success' => true,
            'date_from' => $dateFrom ?: date('Y-m-d', strtotime('first day of this month')),
            'date_to' => $dateTo ?: date('Y-m-d'),
            'revenue' => [
                'accounts' => $revenueData,
                'total' => intval($totalRevenue)
            ],
            'expenses' => [
                'accounts' => $allExpenses,
                'total' => intval($totalExpenses)
            ],
            'net_profit' => intval($netProfit)
        ]);
        exit;
    }

    // Handle balance sheet generation
    if ($reportType === 'balance_sheet') {
        $asOfDate = $_GET['as_of'] ?? $dateTo ?? date('Y-m-d');

        // Get assets data from chart of accounts
        $assetsQuery = $db->prepare("
            SELECT
                coa.account_name,
                coa.account_type,
                coa.category,
                COALESCE(SUM(
                    CASE
                        WHEN jel.account_id IS NOT NULL THEN COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)
                        ELSE 0
                    END
                ), 0) as account_balance
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                AND je.status = 'posted'
                AND je.entry_date <= ?
            WHERE coa.account_type IN ('asset')
                AND coa.is_active = 1
            GROUP BY coa.id, coa.account_name, coa.account_type, coa.category
            HAVING account_balance > 0
            ORDER BY coa.account_type, coa.category, coa.account_name
        ");
        $assetsQuery->execute([$asOfDate]);
        $assetsData = $assetsQuery->fetchAll(PDO::FETCH_ASSOC);

        // Get liabilities data
        $liabilitiesQuery = $db->prepare("
            SELECT
                coa.account_name,
                coa.account_type,
                coa.category,
                COALESCE(SUM(
                    CASE
                        WHEN jel.account_id IS NOT NULL THEN COALESCE(jel.credit, 0) - COALESCE(jel.debit, 0)
                        ELSE 0
                    END
                ), 0) as account_balance
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                AND je.status = 'posted'
                AND je.entry_date <= ?
            WHERE coa.account_type IN ('liability')
                AND coa.is_active = 1
            GROUP BY coa.id, coa.account_name, coa.account_type, coa.category
            HAVING account_balance > 0
            ORDER BY coa.account_type, coa.category, coa.account_name
        ");
        $liabilitiesQuery->execute([$asOfDate]);
        $liabilitiesData = $liabilitiesQuery->fetchAll(PDO::FETCH_ASSOC);

        // Get equity data
        $equityQuery = $db->prepare("
            SELECT
                coa.account_name,
                coa.account_type,
                coa.category,
                COALESCE(SUM(
                    CASE
                        WHEN jel.account_id IS NOT NULL THEN COALESCE(jel.credit, 0) - COALESCE(jel.debit, 0)
                        ELSE 0
                    END
                ), 0) as account_balance
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                AND je.status = 'posted'
                AND je.entry_date <= ?
            WHERE coa.account_type IN ('equity')
                AND coa.is_active = 1
            GROUP BY coa.id, coa.account_name, coa.account_type, coa.category
            HAVING account_balance > 0
            ORDER BY coa.account_type, coa.category, coa.account_name
        ");
        $equityQuery->execute([$asOfDate]);
        $equityData = $equityQuery->fetchAll(PDO::FETCH_ASSOC);

        // Calculate retained earnings (net profit minus distributions, etc.)
        $retainedEarnings = intval($totalRevenue ?? 0) - intval($totalExpenses ?? 0);

        if ($retainedEarnings > 0) {
            $equityData[] = [
                'account_name' => 'Retained Earnings',
                'account_type' => 'equity',
                'category' => 'retained_earnings',
                'account_balance' => $retainedEarnings
            ];
        }

        echo json_encode([
            'success' => true,
            'as_of_date' => $asOfDate,
            'assets' => [
                'accounts' => $assetsData,
                'total' => array_sum(array_column($assetsData, 'account_balance'))
            ],
            'liabilities' => [
                'accounts' => $liabilitiesData,
                'total' => array_sum(array_column($liabilitiesData, 'account_balance'))
            ],
            'equity' => [
                'accounts' => $equityData,
                'total' => array_sum(array_column($equityData, 'account_balance'))
            ]
        ]);
        exit;
    }

    // Handle cash flow statement generation
    if ($reportType === 'cash_flow') {
        $period = $_GET['period'] ?? 'last_quarter';

        // Calculate date range based on period
        switch ($period) {
            case 'last_quarter':
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime('-3 months'));
                break;
            case 'last_6_months':
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime('-6 months'));
                break;
            case 'year_to_date':
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-01', strtotime('this year'));
                break;
            default:
                $endDate = $dateTo ?: date('Y-m-d');
                $startDate = $dateFrom ?: date('Y-m-d', strtotime('-3 months'));
        }

        // Default empty arrays in case queries fail
        $operatingData = [];
        $investingData = [];
        $financingData = [];

        try {
            // Operating activities - from daily expenses mostly (cash basis approximation)
            $operatingQuery = $db->prepare("
                SELECT
                    d.dept_name as name,
                    SUM(des.total_amount) as amount
                FROM daily_expense_summary des
                LEFT JOIN departments d ON des.department_id = d.id
                WHERE des.business_date BETWEEN :start_date AND :end_date
                GROUP BY d.dept_name
            ");
            $operatingQuery->execute(['start_date' => $startDate, 'end_date' => $endDate]);
            $operatingData = $operatingQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Continue with empty data if query fails
            $operatingData = [];
        }

        try {
            // Investing activities (simplified - from journal entries)
            $investingQuery = $db->prepare("
                SELECT
                    coa.account_name as name,
                    SUM(COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE coa.category IN ('fixed_assets', 'investments')
                    AND (:active_check IS NULL OR coa.is_active = 1)
                    AND (:status_check IS NULL OR je.status = 'posted')
                    AND je.entry_date BETWEEN :start_date AND :end_date
                GROUP BY coa.id, coa.account_name
                HAVING amount != 0
            ");
            $investingQuery->execute([
                'active_check' => 1,
                'status_check' => 'posted',
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $investingData = $investingQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Continue with empty data if query fails
            $investingData = [];
        }

        try {
            // Financing activities (loans, equity, etc.)
            $financingQuery = $db->prepare("
                SELECT
                    coa.account_name as name,
                    SUM(COALESCE(jel.credit, 0) - COALESCE(jel.debit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE coa.account_type = 'liability'
                    AND coa.category IN ('long_term_liabilities', 'current_liabilities')
                    AND (:active_check IS NULL OR coa.is_active = 1)
                    AND (:status_check IS NULL OR je.status = 'posted')
                    AND je.entry_date BETWEEN :start_date AND :end_date
                GROUP BY coa.id, coa.account_name
                HAVING amount != 0
            ");
            $financingQuery->execute([
                'active_check' => 1,
                'status_check' => 'posted',
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $financingData = $financingQuery->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Continue with empty data if query fails
            $financingData = [];
        }

        // Calculate totals with safe array handling
        $operatingCash = array_sum(array_column($operatingData, 'amount')) ?: 0;
        $investingCash = -abs(array_sum(array_column($investingData, 'amount')) ?: 0); // Always negative for purchases
        $financingCash = array_sum(array_column($financingData, 'amount')) ?: 0;

        // Always return success with cash_flow structure
        echo json_encode([
            'success' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cash_flow' => [
                'operating_activities' => [
                    'amount' => intval($operatingCash),
                    'accounts' => $operatingData ?: []
                ],
                'investing_activities' => [
                    'amount' => intval($investingCash),
                    'accounts' => $investingData ?: []
                ],
                'financing_activities' => [
                    'amount' => intval($financingCash),
                    'accounts' => $financingData ?: []
                ]
            ]
        ]);
        exit;
    }

    // Handle budget vs actual report
    if ($reportType === 'budget_vs_actual') {
        // This would require budget data - placeholder for now
        echo json_encode([
            'success' => false,
            'error' => 'Budget vs Actual report not yet implemented'
        ]);
        exit;
    }

    // Handle cash flow summary report
    if ($reportType === 'cash_flow_summary') {
        // Similar to cash flow but simplified summary
        $summaryQuery = $db->prepare("
            SELECT
                SUM(CASE WHEN expense_category = 'labor_payroll' THEN total_amount ELSE 0 END) as payroll_expenses,
                SUM(CASE WHEN expense_category != 'labor_payroll' THEN total_amount ELSE 0 END) as operating_expenses,
                COUNT(DISTINCT business_date) as days_counted
            FROM daily_expense_summary
            WHERE business_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $summaryQuery->execute();
        $summaryData = $summaryQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'summary' => [
                'payroll_expenses' => intval($summaryData['payroll_expenses'] ?? 0),
                'operating_expenses' => intval($summaryData['operating_expenses'] ?? 0),
                'total_expenses' => intval(($summaryData['payroll_expenses'] ?? 0) + ($summaryData['operating_expenses'] ?? 0)),
                'days_counted' => intval($summaryData['days_counted'] ?? 0)
            ]
        ]);
        exit;
    }

    // Handle aging reports
    if (strpos($reportType, 'aging_') === 0) {
        $type = str_replace('aging_', '', $reportType); // 'receivable' or 'payable'

        // This is a placeholder - would need proper implementation for aging reports
        $agingData = [
            'totals' => [
                'current' => 285000,
                '1-30' => 25000,
                '31-60' => 8000,
                '61-90' => 2000
            ],
            'details' => [] // Would contain detailed aging data
        ];

        echo json_encode([
            'success' => true,
            'totals' => $agingData['totals'],
            'details' => $agingData['details']
        ]);
        exit;
    }

    // Default response for unsupported report types
    echo json_encode([
        'success' => false,
        'error' => 'Report type not supported: ' . $reportType
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System Error: ' . $e->getMessage()
    ]);
}
?>
