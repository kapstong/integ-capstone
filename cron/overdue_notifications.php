<?php
/**
 * ATIERA Financial Management System - Overdue Notifications Cron Job
 * Processes overdue invoice and bill notifications
 *
 * This script should be run daily via cron:
 * 0 9 * * * /usr/bin/php /path/to/integ-capstone/cron/overdue_notifications.php
 */

require_once '../config.php';
require_once '../includes/notifications.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';

try {
    $notificationManager = NotificationManager::getInstance();
    $logger = Logger::getInstance();
    $db = Database::getInstance()->getConnection();

    $logger->info("Starting overdue notifications cron job");

    // Get overdue invoices (customer collections)
    $stmt = $db->prepare("
        SELECT i.*, c.company_name, c.email, u.email as creator_email
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON i.created_by = u.id
        WHERE i.status IN ('sent', 'partial')
        AND i.due_date < CURDATE()
        AND i.balance > 0
        AND c.email IS NOT NULL
        AND i.last_overdue_notification IS NULL
        OR DATE(i.last_overdue_notification) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $overdueInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $invoiceCount = 0;
    foreach ($overdueInvoices as $invoice) {
        $daysOverdue = floor((time() - strtotime($invoice['due_date'])) / (60*60*24));

        // Send email notification to customer
        $notificationManager->sendOverdueInvoiceNotification($invoice['id']);

        // Update last notification timestamp
        $updateStmt = $db->prepare("
            UPDATE invoices
            SET last_overdue_notification = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$invoice['id']]);

        $invoiceCount++;
        $logger->info("Sent overdue notification for invoice", [
            'invoice_id' => $invoice['id'],
            'customer' => $invoice['company_name'],
            'days_overdue' => $daysOverdue
        ]);
    }

    // Get overdue bills (vendor payments)
    $stmt = $db->prepare("
        SELECT b.*, v.company_name, v.email, u.email as approver_email
        FROM bills b
        LEFT JOIN vendors v ON b.vendor_id = v.id
        LEFT JOIN users u ON b.approved_by = u.id
        WHERE b.status = 'approved'
        AND b.due_date < CURDATE()
        AND b.balance > 0
        AND u.email IS NOT NULL
        AND b.last_overdue_notification IS NULL
        OR DATE(b.last_overdue_notification) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $overdueBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $billCount = 0;
    foreach ($overdueBills as $bill) {
        $daysOverdue = floor((time() - strtotime($bill['due_date'])) / (60*60*24));

        // Send internal notification to approver/staff
        $notificationManager->sendOverdueBillNotification($bill['id']);

        // Update last notification timestamp
        $updateStmt = $db->prepare("
            UPDATE bills
            SET last_overdue_notification = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$bill['id']]);

        $billCount++;
        $logger->info("Sent overdue notification for bill", [
            'bill_id' => $bill['id'],
            'vendor' => $bill['company_name'],
            'days_overdue' => $daysOverdue
        ]);
    }

    $logger->info("Overdue notifications completed", [
        'invoices_notified' => $invoiceCount,
        'bills_notified' => $billCount
    ]);

    echo "Overdue notifications completed successfully\n";
    echo "Invoices notified: $invoiceCount\n";
    echo "Bills notified: $billCount\n";

} catch (Exception $e) {
    $logger = Logger::getInstance();
    $logger->error("Overdue notifications failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    echo "ERROR: Overdue notifications failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
