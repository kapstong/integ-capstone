<?php
/**
 * ATIERA Financial Management System - Reports API
 * Handles report generation with integrated payroll data from HR4
 */

// Prevent any HTML output that would break JSON
error_reporting(E_ALL);
ini_set('display_errors', 1); // TEMPORARILY ENABLE for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_reports_error.log');

// Start output buffering to catch any unexpected output
ob_start();

// Log that API was called
error_log('API Reports called with params: ' . json_encode($_GET));
file_put_contents(__DIR__ . '/../logs/api_debug.txt', date('Y-m-d H:i:s') . ' - API called with: ' . json_encode($_GET) . "\n", FILE_APPEND);

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

// For AJAX/fetch requests, return JSON error instead of redirecting
// More reliable detection for fetch API and AJAX calls
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
          || (isset($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT', 'DELETE']) && isset($_GET['type']))
          || isset($_SERVER['HTTP_FETCH_ID'])
          || isset($_SERVER['HTTP_FETCH_UID']); // Fetch API headers

// Additional check: if URL contains API parameters, treat as AJAX
if (!$isAjax && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'api/') !== false) {
    $isAjax = true;
}

if (!$auth->isLoggedIn()) {
    if ($isAjax) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required'
        ]);
        exit;
    } else {
        $auth->requireLogin();
    }
}
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

        // Suppress all output during auto-import to prevent JSON corruption
        $autoImportBuffer = '';
        if ($recordCount == 0) {
            $autoImportBuffer = ob_get_clean(); // Stop buffering and save current content
            ob_start(); // Start new buffer for auto-import operations

            // Auto-import from external systems if no recent data
            try {
                require_once '../includes/api_integrations.php';
                $integrationManager = APIIntegrationManager::getInstance();

                // Check for recent data from each source and import if needed
                $sources = [
                    'HR_SYSTEM' => ['integration' => 'hr4', 'action' => 'importPayroll', 'name' => 'HR4 payroll'],
                    'LOGISTICS1' => ['integration' => 'logistics1', 'action' => 'importInvoices', 'name' => 'Logistics 1 invoices'],
                    'LOGISTICS2' => ['integration' => 'logistics2', 'action' => 'importTripCosts', 'name' => 'Logistics 2 trip costs']
                ];

                foreach ($sources as $sourceSystem => $config) {
                    // Check if this source has recent data
                    $sourceCheck = $db->prepare("
                        SELECT COUNT(*) as count
                        FROM daily_expense_summary
                        WHERE source_system = ?
                        AND business_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    ");
                    $sourceCheck->execute([$sourceSystem]);
                    $sourceCount = $sourceCheck->fetch(PDO::FETCH_ASSOC)['count'];

                    // If no recent data from this source, try to import
                    if ($sourceCount == 0) {
                        $integrationConfig = $integrationManager->getIntegrationConfig($config['integration']);
                        if ($integrationConfig && !empty($integrationConfig['api_url'])) {
                            try {
                                $result = $integrationManager->executeIntegrationAction($config['integration'], $config['action'], []);
                                Logger::getInstance()->info("Auto-imported {$config['name']}", ['result' => $result]);
                            } catch (Exception $importException) {
                                // Log import failures but don't break the report
                                Logger::getInstance()->warning("Auto-import failed for {$config['name']}", [
                                    'error' => $importException->getMessage()
                                ]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Logger::getInstance()->warning('Auto-import setup failed during report generation', [
                    'error' => $e->getMessage()
                ]);
            }

            // Clear any output from auto-import operations
            if (ob_get_length()) {
                ob_clean();
            }

            // Restore original buffer content
            echo $autoImportBuffer;
            ob_start();
        }

        // Get revenue data (from journal entries - placeholder for now)
        // Build query based on whether dates are provided
        if (!empty($dateFrom) || !empty($dateTo)) {
            $revenueQuery = $db->prepare("
                SELECT
                    coa.account_name,
                    SUM(COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                    AND je.status = 'posted'
                    " . (!empty($dateFrom) ? "AND je.entry_date >= :date_from" : "") . "
                    " . (!empty($dateTo) ? "AND je.entry_date <= :date_to" : "") . "
                WHERE coa.account_type = 'revenue'
                    AND coa.is_active = 1
                GROUP BY coa.id, coa.account_name
                HAVING amount > 0
            ");
            $params = [];
            if (!empty($dateFrom)) $params['date_from'] = $dateFrom;
            if (!empty($dateTo)) $params['date_to'] = $dateTo;
            $revenueQuery->execute($params);
        } else {
            $revenueQuery = $db->prepare("
                SELECT
                    coa.account_name,
                    SUM(COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                    AND je.status = 'posted'
                WHERE coa.account_type = 'revenue'
                    AND coa.is_active = 1
                GROUP BY coa.id, coa.account_name
                HAVING amount > 0
            ");
            $revenueQuery->execute();
        }
        $revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total revenue
        $totalRevenue = array_sum(array_column($revenueData, 'amount'));

        // Get expense data from daily_expense_summary (includes HR4 payroll)
        if (!empty($dateFrom) || !empty($dateTo)) {
            $whereConditions = [];
            if (!empty($dateFrom)) $whereConditions[] = "des.business_date >= :date_from";
            if (!empty($dateTo)) $whereConditions[] = "des.business_date <= :date_to";

            $expenseQuery = $db->prepare("
                SELECT
                    d.dept_name as department,
                    SUM(des.total_amount) as amount
                FROM daily_expense_summary des
                LEFT JOIN departments d ON des.department_id = d.id
                " . (!empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "") . "
                GROUP BY d.dept_name
                ORDER BY d.dept_name
            ");

            $params = [];
            if (!empty($dateFrom)) $params['date_from'] = $dateFrom;
            if (!empty($dateTo)) $params['date_to'] = $dateTo;
            $expenseQuery->execute($params);
        } else {
            $expenseQuery = $db->prepare("
                SELECT
                    d.dept_name as department,
                    SUM(des.total_amount) as amount
                FROM daily_expense_summary des
                LEFT JOIN departments d ON des.department_id = d.id
                GROUP BY d.dept_name
                ORDER BY d.dept_name
            ");
            $expenseQuery->execute();
        }
        $expenseData = $expenseQuery->fetchAll(PDO::FETCH_ASSOC);

        // Get expense data from journal entries too (other expenses not tracked in daily_expense_summary)
        if (!empty($dateFrom) || !empty($dateTo)) {
            $journalExpenseQuery = $db->prepare("
                SELECT
                    coa.account_name,
                    SUM(COALESCE(jel.debit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                    AND je.status = 'posted'
                    " . (!empty($dateFrom) ? "AND je.entry_date >= :date_from" : "") . "
                    " . (!empty($dateTo) ? "AND je.entry_date <= :date_to" : "") . "
                WHERE coa.account_type = 'expense'
                    AND coa.is_active = 1
                    AND coa.category NOT IN ('Payroll', 'Salary')
                GROUP BY coa.id, coa.account_name
                HAVING amount > 0
            ");
            $params = [];
            if (!empty($dateFrom)) $params['date_from'] = $dateFrom;
            if (!empty($dateTo)) $params['date_to'] = $dateTo;
            $journalExpenseQuery->execute($params);
        } else {
            $journalExpenseQuery = $db->prepare("
                SELECT
                    coa.account_name,
                    SUM(COALESCE(jel.debit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                    AND je.status = 'posted'
                WHERE coa.account_type = 'expense'
                    AND coa.is_active = 1
                    AND coa.category NOT IN ('Payroll', 'Salary')
                GROUP BY coa.id, coa.account_name
                HAVING amount > 0
            ");
            $journalExpenseQuery->execute();
        }
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
        ob_clean(); // Clear any buffered output
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

        ob_clean(); // Clear any buffered output
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
            // Operating activities - Revenue (cash inflows)
            $revenueQuery = $db->prepare("
                SELECT
                    coa.account_name as name,
                    'Revenue' as category,
                    SUM(COALESCE(jel.credit, 0) - COALESCE(jel.debit, 0)) as amount
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
                LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE coa.account_type = 'revenue'
                    AND je.status = 'posted'
                    AND je.entry_date BETWEEN :start_date AND :end_date
                GROUP BY coa.id, coa.account_name
                HAVING amount != 0
                ORDER BY amount DESC
            ");
            $revenueQuery->execute(['start_date' => $startDate, 'end_date' => $endDate]);
            $revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

            // Operating activities - Expenses by category
            $expenseByCategory = $db->prepare("
                SELECT
                    CASE des.expense_category
                        WHEN 'labor_payroll' THEN 'Payroll & Salaries'
                        WHEN 'supplies_materials' THEN 'Materials & Supplies'
                        WHEN 'fuel_transportation' THEN 'Fuel Costs'
                        WHEN 'transportation_other' THEN 'Transportation Costs'
                        ELSE 'Other Operating Expenses'
                    END as name,
                    'Operating Expense' as category,
                    des.expense_category as subcategory,
                    SUM(des.total_amount) as amount,
                    GROUP_CONCAT(DISTINCT des.source_system) as sources
                FROM daily_expense_summary des
                WHERE des.business_date BETWEEN :start_date AND :end_date
                GROUP BY des.expense_category
                ORDER BY amount DESC
            ");
            $expenseByCategory->execute(['start_date' => $startDate, 'end_date' => $endDate]);
            $expenseCategories = $expenseByCategory->fetchAll(PDO::FETCH_ASSOC);

            // Operating activities - Detailed breakdown by department for each category
            $detailedExpenses = $db->prepare("
                SELECT
                    d.dept_name as department,
                    des.expense_category,
                    des.source_system,
                    SUM(des.total_amount) as amount,
                    COUNT(*) as transaction_count
                FROM daily_expense_summary des
                LEFT JOIN departments d ON des.department_id = d.id
                WHERE des.business_date BETWEEN :start_date AND :end_date
                GROUP BY d.dept_name, des.expense_category, des.source_system
                ORDER BY des.expense_category, amount DESC
            ");
            $detailedExpenses->execute(['start_date' => $startDate, 'end_date' => $endDate]);
            $expenseDetails = $detailedExpenses->fetchAll(PDO::FETCH_ASSOC);

            // Combine into operating data structure
            $operatingData = [
                'revenue' => $revenueData,
                'expenses_by_category' => $expenseCategories,
                'expense_details' => $expenseDetails
            ];
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
        $totalRevenue = 0;
        $totalExpenses = 0;

        if (is_array($operatingData) && isset($operatingData['revenue'])) {
            $totalRevenue = array_sum(array_column($operatingData['revenue'], 'amount')) ?: 0;
        }
        if (is_array($operatingData) && isset($operatingData['expenses_by_category'])) {
            $totalExpenses = array_sum(array_column($operatingData['expenses_by_category'], 'amount')) ?: 0;
        }

        $operatingCash = $totalRevenue - $totalExpenses;
        $investingCash = -abs(array_sum(array_column($investingData, 'amount')) ?: 0); // Always negative for purchases
        $financingCash = array_sum(array_column($financingData, 'amount')) ?: 0;

        // Always return success with cash_flow structure
        ob_clean(); // Clear any buffered output
        echo json_encode([
            'success' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cash_flow' => [
                'operating_activities' => [
                    'amount' => intval($operatingCash),
                    'revenue' => $operatingData['revenue'] ?? [],
                    'total_revenue' => intval($totalRevenue),
                    'expenses_by_category' => $operatingData['expenses_by_category'] ?? [],
                    'expense_details' => $operatingData['expense_details'] ?? [],
                    'total_expenses' => intval($totalExpenses),
                    'breakdown' => $operatingData
                ],
                'investing_activities' => [
                    'amount' => intval($investingCash),
                    'accounts' => $investingData ?: []
                ],
                'financing_activities' => [
                    'amount' => intval($financingCash),
                    'accounts' => $financingData ?: []
                ],
                'net_change' => intval($operatingCash + $investingCash + $financingCash)
            ]
        ]);
        exit;
    }

    // Handle budget vs actual report
    if ($reportType === 'budget_vs_actual') {
        $budgetYear = $_GET['year'] ?? date('Y');
        $budgetMonth = $_GET['month'] ?? date('n');
        $departmentId = $_GET['department_id'] ?? null;

        // Get departmental budget vs actual data
        $budgetQuery = $db->prepare("
            SELECT
                d.dept_name,
                d.id as department_id,
                db.budget_type,
                CASE db.budget_type
                    WHEN 'revenue' THEN 'jan_revenue'
                    WHEN 'expense' THEN 'jan_amount'
                END as jan_budget,
                db.jan_amount as jan_actual,
                -- Placeholder for actual calculations - would pull from real data
                COALESCE(db.jan_amount, 0) as jan_budget_amount,
                0 as jan_actual_amount, -- Would calculate from actual transactions
                db.feb_amount as feb_budget_amount, 0 as feb_actual_amount,
                db.mar_amount as mar_budget_amount, 0 as mar_actual_amount,
                db.apr_amount as apr_budget_amount, 0 as apr_actual_amount,
                db.may_amount as may_budget_amount, 0 as may_actual_amount,
                db.jun_amount as jun_budget_amount, 0 as jun_actual_amount,
                db.jul_amount as jul_budget_amount, 0 as jul_actual_amount,
                db.aug_amount as aug_budget_amount, 0 as aug_actual_amount,
                db.sep_amount as sep_budget_amount, 0 as sep_actual_amount,
                db.oct_amount as oct_budget_amount, 0 as oct_actual_amount,
                db.nov_amount as nov_budget_amount, 0 as nov_actual_amount,
                db.dec_amount as dec_budget_amount, 0 as dec_actual_amount,
                db.annual_total
            FROM departments d
            CROSS JOIN (SELECT 'revenue' as budget_type UNION SELECT 'expense' as budget_type) bt
            LEFT JOIN department_budgets db ON d.id = db.department_id
                AND db.budget_year = ?
                AND db.budget_type = bt.budget_type
            WHERE d.is_active = 1
                " . (!empty($departmentId) ? "AND d.id = ?" : "") . "
            ORDER BY d.dept_name, db.budget_type
        ");

        $params = [$budgetYear];
        if (!empty($departmentId)) $params[] = $departmentId;
        $budgetQuery->execute($params);
        $budgetData = $budgetQuery->fetchAll(PDO::FETCH_ASSOC);

        // Calculate actuals from real data (simplified - in real implementation would be more complex)
        foreach ($budgetData as &$dept) {
            // Calculate actual revenue per month from revenue centers/transaction data
            $dept['jan_actual_amount'] = 0; // Would query from daily_revenue_summary, etc.
            $dept['feb_actual_amount'] = 0;
            $dept['mar_actual_amount'] = 0;
            $dept['apr_actual_amount'] = 0;
            $dept['may_actual_amount'] = 0;
            $dept['jun_actual_amount'] = 0;
            $dept['jul_actual_amount'] = 0;
            $dept['aug_actual_amount'] = 0;
            $dept['sep_actual_amount'] = 0;
            $dept['oct_actual_amount'] = 0;
            $dept['nov_actual_amount'] = 0;
            $dept['dec_actual_amount'] = 0;

            // Calculate variances
            $dept['jan_variance'] = $dept['jan_actual_amount'] - $dept['jan_budget_amount'];
            $dept['feb_variance'] = $dept['feb_actual_amount'] - $dept['feb_budget_amount'];
            $dept['mar_variance'] = $dept['mar_actual_amount'] - $dept['mar_budget_amount'];
            // ... and so on for all months

            // Calculate YTD totals
            $dept['ytd_budget'] = array_sum([
                $dept['jan_budget_amount'] ?? 0, $dept['feb_budget_amount'] ?? 0,
                $dept['mar_budget_amount'] ?? 0, $dept['apr_budget_amount'] ?? 0,
                $dept['may_budget_amount'] ?? 0, $dept['jun_budget_amount'] ?? 0,
                $dept['jul_budget_amount'] ?? 0, $dept['aug_budget_amount'] ?? 0,
                $dept['sep_budget_amount'] ?? 0, $dept['oct_budget_amount'] ?? 0,
                $dept['nov_budget_amount'] ?? 0, $dept['dec_budget_amount'] ?? 0
            ]);

            $dept['ytd_actual'] = array_sum([
                $dept['jan_actual_amount'], $dept['feb_actual_amount'],
                $dept['mar_actual_amount'], $dept['apr_actual_amount'],
                $dept['may_actual_amount'], $dept['jun_actual_amount'],
                $dept['jul_actual_amount'], $dept['aug_actual_amount'],
                $dept['sep_actual_amount'], $dept['oct_actual_amount'],
                $dept['nov_actual_amount'], $dept['dec_actual_amount']
            ]);

            $dept['ytd_variance'] = $dept['ytd_actual'] - $dept['ytd_budget'];
        }

        // Get summary totals across all departments
        $totals = [
            'revenue' => ['budget' => 0, 'actual' => 0, 'variance' => 0],
            'expense' => ['budget' => 0, 'actual' => 0, 'variance' => 0],
            'net_profit' => ['budget' => 0, 'actual' => 0, 'variance' => 0]
        ];

        foreach ($budgetData as $dept) {
            if ($dept['budget_type'] === 'revenue') {
                $totals['revenue']['budget'] += $dept['ytd_budget'];
                $totals['revenue']['actual'] += $dept['ytd_actual'];
                $totals['revenue']['variance'] += $dept['ytd_variance'];
            } else {
                $totals['expense']['budget'] += $dept['ytd_budget'];
                $totals['expense']['actual'] += $dept['ytd_actual'];
                $totals['expense']['variance'] += $dept['ytd_variance'];
            }
        }

        $totals['net_profit']['budget'] = $totals['revenue']['budget'] - $totals['expense']['budget'];
        $totals['net_profit']['actual'] = $totals['revenue']['actual'] - $totals['expense']['actual'];
        $totals['net_profit']['variance'] = $totals['net_profit']['actual'] - $totals['net_profit']['budget'];

        ob_clean();
        echo json_encode([
            'success' => true,
            'budget_year' => $budgetYear,
            'budget_month' => $budgetMonth,
            'department_data' => $budgetData,
            'totals' => $totals,
            'report_date' => date('Y-m-d'),
            'note' => 'This report shows budgeted vs actual figures. Actual figures are currently placeholders - requires implementation of actual calculation logic.'
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

        ob_clean(); // Clear any buffered output
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

        if ($type === 'receivable') {
            // Accounts Receivable Aging
            $agingQuery = $db->prepare("
                SELECT
                    i.invoice_number,
                    c.company_name as customer_name,
                    i.invoice_date,
                    i.due_date,
                    i.total_amount,
                    i.paid_amount,
                    (i.total_amount - i.paid_amount) as outstanding,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue,
                    CASE
                        WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 'current'
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN '1-30'
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN '31-60'
                        WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN '91+'
                        ELSE '61-90'
                    END as age_bucket,
                    CASE
                        WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 0
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN 1
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN 2
                        WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN 4
                        ELSE 3
                    END as sort_order,
                    i.status,
                    i.notes
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.balance > 0
                    AND i.status IN ('sent', 'overdue')
                ORDER BY sort_order, i.due_date
            ");
            $agingQuery->execute();
            $agingDetails = $agingQuery->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals by age bucket
            $totals = [
                'current' => 0,
                '1-30' => 0,
                '31-60' => 0,
                '61-90' => 0,
                '91+' => 0
            ];

            foreach ($agingDetails as $detail) {
                $outstanding = floatval($detail['outstanding']);
                $totals[$detail['age_bucket']] += $outstanding;
            }

            // Add total
            $totals['total'] = array_sum($totals);

            $agingData = [
                'totals' => $totals,
                'details' => $agingDetails,
                'summary' => [
                    'total_invoices' => count($agingDetails),
                    'total_outstanding' => $totals['total'],
                    'overdue_count' => count(array_filter($agingDetails, function($d) { return $d['days_overdue'] > 0; })),
                    'overdue_amount' => array_sum(array_filter($agingDetails, function($d) { return $d['days_overdue'] > 0; })),
                    'average_overdue_days' => count($agingDetails) > 0 ? array_sum(array_column($agingDetails, 'days_overdue')) / count($agingDetails) : 0
                ]
            ];

        } elseif ($type === 'payable') {
            // Accounts Payable Aging
            $agingQuery = $db->prepare("
                SELECT
                    b.bill_number,
                    v.company_name as vendor_name,
                    b.bill_date,
                    b.due_date,
                    b.total_amount,
                    b.paid_amount,
                    (b.total_amount - b.paid_amount) as outstanding,
                    DATEDIFF(CURDATE(), b.due_date) as days_overdue,
                    CASE
                        WHEN DATEDIFF(CURDATE(), b.due_date) <= 0 THEN 'current'
                        WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 1 AND 30 THEN '1-30'
                        WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 31 AND 60 THEN '31-60'
                        WHEN DATEDIFF(CURDATE(), b.due_date) > 90 THEN '91+'
                        ELSE '61-90'
                    END as age_bucket,
                    CASE
                        WHEN DATEDIFF(CURDATE(), b.due_date) <= 0 THEN 0
                        WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 1 AND 30 THEN 1
                        WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 31 AND 60 THEN 2
                        WHEN DATEDIFF(CURDATE(), b.due_date) > 90 THEN 4
                        ELSE 3
                    END as sort_order,
                    b.status,
                    b.notes
                FROM bills b
                LEFT JOIN vendors v ON b.vendor_id = v.id
                WHERE b.balance > 0
                    AND b.status IN ('draft', 'approved', 'overdue')
                ORDER BY sort_order, b.due_date
            ");
            $agingQuery->execute();
            $agingDetails = $agingQuery->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals by age bucket
            $totals = [
                'current' => 0,
                '1-30' => 0,
                '31-60' => 0,
                '61-90' => 0,
                '91+' => 0
            ];

            foreach ($agingDetails as $detail) {
                $outstanding = floatval($detail['outstanding']);
                $totals[$detail['age_bucket']] += $outstanding;
            }

            // Add total
            $totals['total'] = array_sum($totals);

            $agingData = [
                'totals' => $totals,
                'details' => $agingDetails,
                'summary' => [
                    'total_bills' => count($agingDetails),
                    'total_outstanding' => $totals['total'],
                    'overdue_count' => count(array_filter($agingDetails, function($d) { return $d['days_overdue'] > 0; })),
                    'overdue_amount' => array_sum(array_filter($agingDetails, function($d) { return $d['days_overdue'] > 0; })),
                    'average_overdue_days' => count($agingDetails) > 0 ? array_sum(array_column($agingDetails, 'days_overdue')) / count($agingDetails) : 0
                ]
            ];

        } else {
            throw new Error('Invalid aging report type. Use "receivable" or "payable".');
        }

        ob_clean(); // Clear any buffered output
        echo json_encode([
            'success' => true,
            'type' => $type,
            'report_date' => date('Y-m-d'),
            'data' => $agingData
        ]);
        exit;
    }

    // Default response for unsupported report types
    ob_clean(); // Clear any buffered output
    echo json_encode([
        'success' => false,
        'error' => 'Report type not supported: ' . $reportType
    ]);

} catch (Exception $e) {
    // Log the full error with stack trace
    error_log('API Reports Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    ob_clean(); // Clear any buffered output
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
