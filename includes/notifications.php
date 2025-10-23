<?php
/**
 * ATIERA Financial Management System - Notification System
 * Handles email and SMS notifications for the application
 */

class NotificationManager {
    private static $instance = null;
    private $db;
    private $smtpConfig;
    private $smsConfig;

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfiguration();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfiguration() {
        // Load SMTP configuration
        $this->smtpConfig = [
            'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => getenv('SMTP_PORT') ?: 587,
            'username' => getenv('SMTP_USERNAME') ?: '',
            'password' => getenv('SMTP_PASSWORD') ?: '',
            'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
            'from_email' => getenv('FROM_EMAIL') ?: 'noreply@atiera.com',
            'from_name' => getenv('FROM_NAME') ?: 'ATIERA Finance'
        ];

        // Load SMS configuration (for future implementation)
        $this->smsConfig = [
            'provider' => getenv('SMS_PROVIDER') ?: 'twilio',
            'api_key' => getenv('SMS_API_KEY') ?: '',
            'api_secret' => getenv('SMS_API_SECRET') ?: '',
            'from_number' => getenv('SMS_FROM_NUMBER') ?: ''
        ];
    }

    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $body, $template = null, $variables = []) {
        try {
            // If template is specified, load and process it
            if ($template) {
                $body = $this->processTemplate($template, $variables);
            }

            // For development/demo purposes, we'll log the email instead of sending
            // In production, integrate with PHPMailer or similar

            $this->logNotification('email', $to, $subject, $body, 'sent');

            // Simulate email sending
            error_log("EMAIL TO: $to | SUBJECT: $subject | BODY: " . substr($body, 0, 100) . "...");

            return ['success' => true, 'message' => 'Email queued for sending'];

        } catch (Exception $e) {
            $this->logNotification('email', $to, $subject, $body, 'failed', $e->getMessage());
            throw new Exception('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * Send SMS notification
     */
    public function sendSMS($to, $message, $template = null, $variables = []) {
        try {
            // If template is specified, load and process it
            if ($template) {
                $message = $this->processTemplate($template, $variables);
            }

            // For development/demo purposes, we'll log the SMS instead of sending
            $this->logNotification('sms', $to, 'SMS Notification', $message, 'sent');

            // Simulate SMS sending
            error_log("SMS TO: $to | MESSAGE: $message");

            return ['success' => true, 'message' => 'SMS sent successfully'];

        } catch (Exception $e) {
            $this->logNotification('sms', $to, 'SMS Notification', $message, 'failed', $e->getMessage());
            throw new Exception('Failed to send SMS: ' . $e->getMessage());
        }
    }

    /**
     * Process notification template
     */
    private function processTemplate($template, $variables = []) {
        $templatePath = __DIR__ . '/../templates/notifications/' . $template . '.html';

        if (!file_exists($templatePath)) {
            throw new Exception("Template '$template' not found");
        }

        $content = file_get_contents($templatePath);

        // Replace variables
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Log notification
     */
    private function logNotification($type, $recipient, $subject, $content, $status, $error = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notification_log
                (type, recipient, subject, content, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$type, $recipient, $subject, $content, $status, $error]);
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }

    /**
     * Send invoice notification
     */
    public function sendInvoiceNotification($invoiceId, $type = 'created') {
        try {
            // Get invoice details
            $stmt = $this->db->prepare("
                SELECT i.*, c.company_name, c.email, u.email as user_email
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('Invoice not found');
            }

            $variables = [
                'invoice_number' => $invoice['invoice_number'],
                'customer_name' => $invoice['company_name'],
                'amount' => number_format($invoice['total_amount'], 2),
                'due_date' => date('M j, Y', strtotime($invoice['due_date'])),
                'invoice_date' => date('M j, Y', strtotime($invoice['invoice_date']))
            ];

            // Send to customer
            if ($invoice['email'] && $type === 'created') {
                $this->sendEmail(
                    $invoice['email'],
                    "Invoice {$invoice['invoice_number']} from ATIERA Finance",
                    '',
                    'invoice_created',
                    $variables
                );
            }

            // Send internal notification
            if ($invoice['user_email']) {
                $this->sendEmail(
                    $invoice['user_email'],
                    "Invoice {$invoice['invoice_number']} - {$type}",
                    '',
                    'invoice_internal',
                    $variables
                );
            }

            return ['success' => true, 'message' => 'Invoice notifications sent'];

        } catch (Exception $e) {
            error_log("Failed to send invoice notification: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send payment notification
     */
    public function sendPaymentNotification($paymentId, $type = 'received') {
        try {
            // Get payment details
            $stmt = $this->db->prepare("
                SELECT p.*, c.company_name, c.email, i.invoice_number
                FROM payments_received p
                LEFT JOIN customers c ON p.customer_id = c.id
                LEFT JOIN invoices i ON p.invoice_id = i.id
                WHERE p.id = ?
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            $variables = [
                'payment_number' => $payment['payment_number'],
                'customer_name' => $payment['company_name'],
                'amount' => number_format($payment['amount'], 2),
                'payment_date' => date('M j, Y', strtotime($payment['payment_date'])),
                'invoice_number' => $payment['invoice_number'] ?: 'N/A'
            ];

            // Send confirmation to customer
            if ($payment['email']) {
                $this->sendEmail(
                    $payment['email'],
                    "Payment Confirmation - {$payment['payment_number']}",
                    '',
                    'payment_received',
                    $variables
                );
            }

            return ['success' => true, 'message' => 'Payment notifications sent'];

        } catch (Exception $e) {
            error_log("Failed to send payment notification: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send task notification
     */
    public function sendTaskNotification($taskId, $type = 'assigned') {
        try {
            // Get task details
            $stmt = $this->db->prepare("
                SELECT t.*, u.email, u.first_name, u.last_name,
                       creator.email as creator_email, creator.first_name as creator_first, creator.last_name as creator_last
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users creator ON t.created_by = creator.id
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                throw new Exception('Task not found');
            }

            $variables = [
                'task_title' => $task['title'],
                'assignee_name' => $task['first_name'] . ' ' . $task['last_name'],
                'creator_name' => $task['creator_first'] . ' ' . $task['creator_last'],
                'due_date' => $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date',
                'priority' => ucfirst($task['priority'])
            ];

            // Send to assignee
            if ($task['email'] && $type === 'assigned') {
                $this->sendEmail(
                    $task['email'],
                    "New Task Assigned: {$task['title']}",
                    '',
                    'task_assigned',
                    $variables
                );
            }

            // Send to creator when task is completed
            if ($task['creator_email'] && $type === 'completed') {
                $this->sendEmail(
                    $task['creator_email'],
                    "Task Completed: {$task['title']}",
                    '',
                    'task_completed',
                    $variables
                );
            }

            return ['success' => true, 'message' => 'Task notifications sent'];

        } catch (Exception $e) {
            error_log("Failed to send task notification: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send overdue notifications
     */
    public function sendOverdueNotifications() {
        try {
            $notifications = [];

            // Get overdue invoices
            $stmt = $this->db->prepare("
                SELECT i.*, c.company_name, c.email
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.status = 'sent'
                AND i.due_date < CURDATE()
                AND i.balance > 0
                AND c.email IS NOT NULL
            ");
            $stmt->execute();
            $overdueInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($overdueInvoices as $invoice) {
                $variables = [
                    'invoice_number' => $invoice['invoice_number'],
                    'customer_name' => $invoice['company_name'],
                    'amount' => number_format($invoice['balance'], 2),
                    'due_date' => date('M j, Y', strtotime($invoice['due_date'])),
                    'days_overdue' => floor((time() - strtotime($invoice['due_date'])) / (60*60*24))
                ];

                $this->sendEmail(
                    $invoice['email'],
                    "OVERDUE: Invoice {$invoice['invoice_number']}",
                    '',
                    'invoice_overdue',
                    $variables
                );

                $notifications[] = "Overdue invoice notification sent to {$invoice['company_name']}";
            }

            // Get overdue bills (internal notification)
            $stmt = $this->db->prepare("
                SELECT b.*, v.company_name, u.email
                FROM bills b
                LEFT JOIN vendors v ON b.vendor_id = v.id
                LEFT JOIN users u ON b.created_by = u.id
                WHERE b.status = 'approved'
                AND b.due_date < CURDATE()
                AND b.balance > 0
                AND u.email IS NOT NULL
            ");
            $stmt->execute();
            $overdueBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($overdueBills as $bill) {
                $variables = [
                    'bill_number' => $bill['bill_number'],
                    'vendor_name' => $bill['company_name'],
                    'amount' => number_format($bill['balance'], 2),
                    'due_date' => date('M j, Y', strtotime($bill['due_date'])),
                    'days_overdue' => floor((time() - strtotime($bill['due_date'])) / (60*60*24))
                ];

                $this->sendEmail(
                    $bill['email'],
                    "OVERDUE: Bill {$bill['bill_number']} Payment Due",
                    '',
                    'bill_overdue',
                    $variables
                );

                $notifications[] = "Overdue bill notification sent for {$bill['company_name']}";
            }

            return [
                'success' => true,
                'message' => 'Overdue notifications sent',
                'count' => count($notifications),
                'details' => $notifications
            ];

        } catch (Exception $e) {
            error_log("Failed to send overdue notifications: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
