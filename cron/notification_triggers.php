<?php
/**
 * ATIERA Financial Management System - Notification Triggers
 * Automatically checks for conditions that require notifications
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/notifications.php';

$db = Database::getInstance()->getConnection();
$notificationManager = NotificationManager::getInstance();

echo "ATIERA Notification Triggers - " . date('Y-m-d H:i:s') . "\n";
echo "=====================================\n";

try {
    // 1. Check for low cash balance
    echo "1. Checking cash balance...\n";
    $stmt = $db->query("
        SELECT COALESCE(SUM(
            CASE
                WHEN jel.debit > 0 THEN jel.debit
                WHEN jel.credit > 0 THEN -jel.credit
                ELSE 0
            END
        ), 0) as balance
        FROM journal_entry_lines jel
        JOIN chart_of_accounts coa ON jel.account_id = coa.id
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE coa.account_code = '1001' AND je.status = 'posted'
    ");
    $cashBalance = $stmt->fetch()['balance'];
    $lowBalanceThreshold = 10000; // ₱10,000 threshold

    if ($cashBalance < $lowBalanceThreshold) {
        echo "   Cash balance low: ₱" . number_format($cashBalance, 2) . "\n";
        $result = $notificationManager->sendLowCashBalanceAlert($cashBalance, $lowBalanceThreshold);
        echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";
    } else {
        echo "   Cash balance OK: ₱" . number_format($cashBalance, 2) . "\n";
    }

    // 2. Check for large transactions (created in last hour)
    echo "\n2. Checking large transactions...\n";
    $largeTransactionThreshold = 50000; // ₱50,000 threshold
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

    // Check payments made
    $stmt = $db->prepare("
        SELECT id, amount, vendor_id, payment_date
        FROM payments_made
        WHERE amount >= ? AND created_at >= ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$largeTransactionThreshold, $oneHourAgo]);
    $largePaymentsMade = $stmt->fetchAll();

    foreach ($largePaymentsMade as $payment) {
        echo "   Large payment made: ₱" . number_format($payment['amount'], 2) . "\n";
        $result = $notificationManager->sendLargeTransactionAlert($payment['id'], 'payment');
        echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";
    }

    // Check payments received
    $stmt = $db->prepare("
        SELECT id, amount, customer_id, payment_date
        FROM payments_received
        WHERE amount >= ? AND created_at >= ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$largeTransactionThreshold, $oneHourAgo]);
    $largePaymentsReceived = $stmt->fetchAll();

    foreach ($largePaymentsReceived as $payment) {
        echo "   Large payment received: ₱" . number_format($payment['amount'], 2) . "\n";
        $result = $notificationManager->sendLargeTransactionAlert($payment['id'], 'received');
        echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";
    }

    if (empty($largePaymentsMade) && empty($largePaymentsReceived)) {
        echo "   No large transactions found\n";
    }

    // 3. Check budget thresholds (80% and 100% of budget used)
    echo "\n3. Checking budget thresholds...\n";
    $currentYear = date('Y');
    $stmt = $db->prepare("
        SELECT
            d.id as department_id,
            d.dept_name,
            COALESCE(SUM(bi.budgeted_amount), 0) as total_budget,
            COALESCE(SUM(CASE WHEN pm.payment_date LIKE CONCAT(?, '%') THEN pm.amount ELSE 0 END), 0) as spent_this_year
        FROM departments d
        LEFT JOIN budgets bs ON d.id = bs.department_id AND bs.budget_year = ?
        LEFT JOIN budget_items bi ON bs.id = bi.budget_id
        LEFT JOIN payments_made pm ON pm.payment_date LIKE CONCAT(?, '%')
        GROUP BY d.id, d.dept_name
        HAVING total_budget > 0
    ");
    $stmt->execute([$currentYear . '%', $currentYear, $currentYear . '%']);
    $budgetData = $stmt->fetchAll();

    foreach ($budgetData as $budget) {
        $percentage = ($budget['spent_this_year'] / $budget['total_budget']) * 100;

        // Alert at 80% and 100%
        if ($percentage >= 80 && $percentage < 100) {
            echo "   Budget alert (80%): {$budget['dept_name']} - " . number_format($percentage, 1) . "% used\n";
            $result = $notificationManager->sendBudgetThresholdAlert($budget['department_id'], $budget['spent_this_year'], $budget['total_budget']);
            echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";
        } elseif ($percentage >= 100) {
            echo "   Budget exceeded: {$budget['dept_name']} - " . number_format($percentage, 1) . "% used\n";
            $result = $notificationManager->sendBudgetThresholdAlert($budget['department_id'], $budget['spent_this_year'], $budget['total_budget']);
            echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";
        }
    }

    if (empty($budgetData)) {
        echo "   No budget data found\n";
    }

    // 4. Check for overdue invoices (not notified in last 7 days)
    echo "\n4. Checking overdue invoices...\n";
    $stmt = $db->prepare("
        SELECT i.id, i.invoice_number, i.balance, i.due_date,
               c.company_name, c.email,
               DATEDIFF(CURDATE(), i.due_date) as days_overdue,
               i.last_overdue_notification
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.status IN ('sent', 'overdue')
        AND i.due_date < CURDATE()
        AND i.balance > 0
        AND (i.last_overdue_notification IS NULL OR i.last_overdue_notification < DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        LIMIT 10
    ");
    $stmt->execute();
    $overdueInvoices = $stmt->fetchAll();

    foreach ($overdueInvoices as $invoice) {
        echo "   Overdue invoice: {$invoice['invoice_number']} - {$invoice['days_overdue']} days\n";
        $result = $notificationManager->sendOverdueInvoiceNotification($invoice['id']);
        echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";

        // Update last notification date
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE invoices SET last_overdue_notification = CURDATE() WHERE id = ?");
            $stmt->execute([$invoice['id']]);
        }
    }

    if (empty($overdueInvoices)) {
        echo "   No overdue invoices requiring notification\n";
    }

    // 5. Check for overdue bills (not notified in last 7 days)
    echo "\n5. Checking overdue bills...\n";
    $stmt = $db->prepare("
        SELECT b.id, b.bill_number, b.balance, b.due_date,
               v.company_name,
               DATEDIFF(CURDATE(), b.due_date) as days_overdue,
               b.last_overdue_notification
        FROM bills b
        LEFT JOIN vendors v ON b.vendor_id = v.id
        WHERE b.status = 'approved'
        AND b.due_date < CURDATE()
        AND b.balance > 0
        AND (b.last_overdue_notification IS NULL OR b.last_overdue_notification < DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        LIMIT 10
    ");
    $stmt->execute();
    $overdueBills = $stmt->fetchAll();

    foreach ($overdueBills as $bill) {
        echo "   Overdue bill: {$bill['bill_number']} - {$bill['days_overdue']} days\n";
        $result = $notificationManager->sendOverdueBillNotification($bill['id']);
        echo "   Alert result: " . ($result['success'] ? 'Sent' : 'Failed - ' . $result['message']) . "\n";

        // Update last notification date
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE bills SET last_overdue_notification = CURDATE() WHERE id = ?");
            $stmt->execute([$bill['id']]);
        }
    }

    if (empty($overdueBills)) {
        echo "   No overdue bills requiring notification\n";
    }

    // 6. Check for pending approvals
    echo "\n6. Checking pending approvals...\n";

    // Pending invoices for approval
    $stmt = $db->query("
        SELECT COUNT(*) as count FROM invoices
        WHERE status = 'pending_approval'
    ");
    $pendingInvoices = $stmt->fetch()['count'];

    if ($pendingInvoices > 0) {
        echo "   {$pendingInvoices} invoice(s) pending approval\n";
        // This would be triggered when invoices are created, not in cron
    }

    // Pending bills for approval
    $stmt = $db->query("
        SELECT COUNT(*) as count FROM bills
        WHERE status = 'pending_approval'
    ");
    $pendingBills = $stmt->fetch()['count'];

    if ($pendingBills > 0) {
        echo "   {$pendingBills} bill(s) pending approval\n";
        // This would be triggered when bills are created, not in cron
    }

    if ($pendingInvoices == 0 && $pendingBills == 0) {
        echo "   No items pending approval\n";
    }

    echo "\n=====================================\n";
    echo "Notification triggers completed successfully\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Notification triggers error: " . $e->getMessage());
}
?>
