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
        // Get revenue data (from journal entries - placeholder for now)
        $revenueQuery = $db->prepare("
            SELECT
                coa.account_name,
                SUM(COALESCE(jel.debit, 0) - COALESCE(jel.credit, 0)) as amount
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.id = jel.account_id
            LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE coa.account_type = 'revenue'
                AND coa.is_active = 1
                AND je.status = 'posted'
                AND (:date_from IS NULL OR je.entry_date >= :date_from)
                AND (:date_to IS NULL OR je.entry_date <= :date_to)
            GROUP BY coa.id, coa.account_name
            HAVING amount > 0
        ");
        $revenueQuery->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total revenue
        $totalRevenue = array_sum(array_column($revenueData, 'amount'));

        // Get expense data from daily_expense_summary (includes HR4 payroll)
        $expenseQuery = $db->prepare("
            SELECT
                department,
                SUM(daily_expenses) as amount
            FROM daily_expense_summary
            WHERE (:date_from IS NULL OR expense_date >= :date_from)
                AND (:date_to IS NULL OR expense_date <= :date_to)
            GROUP BY department
            ORDER BY department
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
            WHERE coa.account_type = 'expense'
                AND coa.is_active = 1
                AND je.status = 'posted'
                AND coa.category NOT IN ('Payroll', 'Salary')  -- Exclude payroll since it's in daily_expense_summary
                AND (:date_from IS NULL OR je.entry_date >= :date_from)
                AND (:date_to IS NULL OR je.entry_date <= :date_to)
            GROUP BY coa.id, coa.account_name
            HAVING amount > 0
        ");
        $journalExpenseQuery->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
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
