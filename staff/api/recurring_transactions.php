<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user']['id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['process'])) {
                // Process due recurring transactions
                processRecurringTransactions($db);
            } elseif (isset($_GET['id'])) {
                // Get single recurring transaction
                $transaction = $db->select(
                    "SELECT rt.*, u.full_name as created_by_name
                     FROM recurring_transactions rt
                     LEFT JOIN users u ON rt.created_by = u.id
                     WHERE rt.id = ?",
                    [$_GET['id']]
                );

                if (empty($transaction)) {
                    echo json_encode(['error' => 'Recurring transaction not found']);
                    exit;
                }

                // Parse template data
                $transaction[0]['template_data'] = json_decode($transaction[0]['template_data'], true);
                echo json_encode($transaction[0]);
            } else {
                // Get all recurring transactions
                $transactions = $db->select(
                    "SELECT rt.*, u.full_name as created_by_name,
                            CASE
                                WHEN rt.next_run_date < CURDATE() THEN 'Overdue'
                                WHEN rt.next_run_date = CURDATE() THEN 'Due Today'
                                WHEN rt.next_run_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Due Soon'
                                ELSE 'Scheduled'
                            END as status_text
                     FROM recurring_transactions rt
                     LEFT JOIN users u ON rt.created_by = u.id
                     WHERE rt.is_active = 1
                     ORDER BY rt.next_run_date ASC"
                );

                // Parse template data for each
                foreach ($transactions as &$transaction) {
                    $transaction['template_data'] = json_decode($transaction['template_data'], true);
                }

                echo json_encode($transactions);
            }
            break;

        case 'POST':
            // Create new recurring transaction
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            // Validate required fields
            if (empty($data['name']) || empty($data['transaction_type']) || empty($data['frequency']) ||
                empty($data['start_date']) || empty($data['template_data'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Calculate next run date
            $nextRunDate = calculateNextRunDate($data['start_date'], $data['frequency'], $data['frequency_value'] ?? 1);

            $transactionId = $db->insert(
                "INSERT INTO recurring_transactions (transaction_type, name, description, frequency, frequency_value,
                                                  start_date, end_date, next_run_date, is_active, template_data, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['transaction_type'],
                    $data['name'],
                    $data['description'] ?? null,
                    $data['frequency'],
                    $data['frequency_value'] ?? 1,
                    $data['start_date'],
                    $data['end_date'] ?? null,
                    $nextRunDate,
                    $data['is_active'] ?? 1,
                    json_encode($data['template_data']),
                    $userId
                ]
            );

            Logger::getInstance()->logUserAction('Created recurring transaction', 'recurring_transactions', $transactionId, null, $data);
            echo json_encode(['success' => true, 'id' => $transactionId]);

            break;

        case 'PUT':
            // Update recurring transaction
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            $oldTransaction = $db->select("SELECT * FROM recurring_transactions WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldTransaction[0] ?? null;

            $fields = [];
            $params = [];

            if (isset($data['name'])) {
                $fields[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['description'])) {
                $fields[] = "description = ?";
                $params[] = $data['description'];
            }
            if (isset($data['frequency'])) {
                $fields[] = "frequency = ?";
                $params[] = $data['frequency'];
            }
            if (isset($data['frequency_value'])) {
                $fields[] = "frequency_value = ?";
                $params[] = $data['frequency_value'];
            }
            if (isset($data['end_date'])) {
                $fields[] = "end_date = ?";
                $params[] = $data['end_date'];
            }
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }
            if (isset($data['template_data'])) {
                $fields[] = "template_data = ?";
                $params[] = json_encode($data['template_data']);
            }

            // Recalculate next run date if frequency changed
            if (isset($data['frequency']) || isset($data['frequency_value'])) {
                $frequency = $data['frequency'] ?? $oldValues['frequency'];
                $frequencyValue = $data['frequency_value'] ?? $oldValues['frequency_value'];
                $nextRunDate = calculateNextRunDate($oldValues['start_date'], $frequency, $frequencyValue);
                $fields[] = "next_run_date = ?";
                $params[] = $nextRunDate;
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            $params[] = $_GET['id'];
            $sql = "UPDATE recurring_transactions SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            Logger::getInstance()->logUserAction('Updated recurring transaction', 'recurring_transactions', $_GET['id'], $oldValues, $data);
            echo json_encode(['success' => $affected > 0]);

            break;

        case 'DELETE':
            // Delete recurring transaction
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
                exit;
            }

            $oldTransaction = $db->select("SELECT * FROM recurring_transactions WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldTransaction[0] ?? null;

            $affected = $db->execute("DELETE FROM recurring_transactions WHERE id = ?", [$_GET['id']]);

            Logger::getInstance()->logUserAction('Deleted recurring transaction', 'recurring_transactions', $_GET['id'], $oldValues, null);
            echo json_encode(['success' => $affected > 0]);

            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Recurring Transaction API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

/**
 * Calculate next run date based on frequency
 */
function calculateNextRunDate($startDate, $frequency, $frequencyValue = 1) {
    $start = new DateTime($startDate);
    $now = new DateTime();

    // If start date is in the future, use it
    if ($start > $now) {
        return $start->format('Y-m-d');
    }

    // Calculate next run date based on frequency
    switch ($frequency) {
        case 'daily':
            $interval = new DateInterval("P{$frequencyValue}D");
            break;
        case 'weekly':
            $interval = new DateInterval("P{$frequencyValue}W");
            break;
        case 'monthly':
            $interval = new DateInterval("P{$frequencyValue}M");
            break;
        case 'quarterly':
            $interval = new DateInterval("P" . ($frequencyValue * 3) . "M");
            break;
        case 'yearly':
            $interval = new DateInterval("P{$frequencyValue}Y");
            break;
        default:
            return $start->format('Y-m-d');
    }

    $nextDate = clone $start;
    while ($nextDate <= $now) {
        $nextDate->add($interval);
    }

    return $nextDate->format('Y-m-d');
}

/**
 * Process due recurring transactions
 */
function processRecurringTransactions($db) {
    try {
        $now = new DateTime();
        $dueTransactions = $db->select(
            "SELECT * FROM recurring_transactions
             WHERE is_active = 1 AND next_run_date <= ?
             AND (end_date IS NULL OR end_date >= ?)",
            [$now->format('Y-m-d'), $now->format('Y-m-d')]
        );

        $processed = 0;
        $errors = 0;

        foreach ($dueTransactions as $transaction) {
            try {
                $templateData = json_decode($transaction['template_data'], true);

                switch ($transaction['transaction_type']) {
                    case 'journal_entry':
                        processJournalEntry($db, $templateData, $transaction['name']);
                        break;
                    case 'invoice':
                        processInvoice($db, $templateData, $transaction['name']);
                        break;
                    case 'bill':
                        processBill($db, $templateData, $transaction['name']);
                        break;
                    case 'payment':
                        processPayment($db, $templateData, $transaction['name']);
                        break;
                }

                // Update last run and calculate next run
                $nextRunDate = calculateNextRunDate(
                    $transaction['next_run_date'],
                    $transaction['frequency'],
                    $transaction['frequency_value']
                );

                $db->execute(
                    "UPDATE recurring_transactions SET last_run_date = ?, next_run_date = ? WHERE id = ?",
                    [$now->format('Y-m-d'), $nextRunDate, $transaction['id']]
                );

                $processed++;

            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Recurring Transaction Processing', $e->getMessage());
                $errors++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Processed {$processed} recurring transactions, {$errors} errors",
            'processed' => $processed,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        Logger::getInstance()->logDatabaseError('Recurring Transaction Processing', $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process recurring transactions']);
    }
}

/**
 * Process journal entry from template
 */
function processJournalEntry($db, $template, $description) {
    // Generate journal entry number
    $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, posted_by, posted_at)
         VALUES (?, CURDATE(), ?, 0, 0, 'posted', 1, 1, NOW())",
        [$entryNumber, $description]
    );

    $totalDebit = $totalCredit = 0;

    // Process debit entries
    if (isset($template['debits'])) {
        foreach ($template['debits'] as $debit) {
            $db->insert(
                "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
                 VALUES (?, ?, ?, ?)",
                [$entryId, $debit['account_id'], $debit['amount'], $debit['description'] ?? '']
            );
            $totalDebit += $debit['amount'];
        }
    }

    // Process credit entries
    if (isset($template['credits'])) {
        foreach ($template['credits'] as $credit) {
            $db->insert(
                "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
                 VALUES (?, ?, ?, ?)",
                [$entryId, $credit['account_id'], $credit['amount'], $credit['description'] ?? '']
            );
            $totalCredit += $credit['amount'];
        }
    }

    // Update totals
    $db->execute(
        "UPDATE journal_entries SET total_debit = ?, total_credit = ? WHERE id = ?",
        [$totalDebit, $totalCredit, $entryId]
    );
}

/**
 * Process invoice from template
 */
function processInvoice($db, $template, $description) {
    // Generate invoice number
    $stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    // Calculate totals
    $subtotal = $template['subtotal'] ?? 0;
    $taxRate = $template['tax_rate'] ?? 0;
    $taxAmount = $subtotal * ($taxRate / 100);
    $totalAmount = $subtotal + $taxAmount;

    $invoiceId = $db->insert(
        "INSERT INTO invoices (invoice_number, customer_id, invoice_date, due_date, subtotal, tax_rate,
                              tax_amount, total_amount, balance, status, notes, created_by)
         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'sent', ?, 1)",
        [
            $invoiceNumber,
            $template['customer_id'],
            $template['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
            $subtotal,
            $taxRate,
            $taxAmount,
            $totalAmount,
            $totalAmount,
            $description
        ]
    );

    // Add invoice items
    if (isset($template['items'])) {
        foreach ($template['items'] as $item) {
            $db->insert(
                "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, account_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $invoiceId,
                    $item['description'],
                    $item['quantity'] ?? 1,
                    $item['unit_price'],
                    ($item['quantity'] ?? 1) * $item['unit_price'],
                    $item['account_id'] ?? null
                ]
            );
        }
    }
}

/**
 * Process bill from template (similar to invoice)
 */
function processBill($db, $template, $description) {
    // Generate bill number
    $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $billNumber = 'BILL-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $subtotal = $template['subtotal'] ?? 0;
    $taxRate = $template['tax_rate'] ?? 0;
    $taxAmount = $subtotal * ($taxRate / 100);
    $totalAmount = $subtotal + $taxAmount;

    $billId = $db->insert(
        "INSERT INTO bills (bill_number, vendor_id, bill_date, due_date, subtotal, tax_rate,
                           tax_amount, total_amount, balance, status, notes, created_by)
         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'approved', ?, 1)",
        [
            $billNumber,
            $template['vendor_id'],
            $template['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
            $subtotal,
            $taxRate,
            $taxAmount,
            $totalAmount,
            $totalAmount,
            $description
        ]
    );

    // Add bill items
    if (isset($template['items'])) {
        foreach ($template['items'] as $item) {
            $db->insert(
                "INSERT INTO bill_items (bill_id, description, quantity, unit_price, line_total, account_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $billId,
                    $item['description'],
                    $item['quantity'] ?? 1,
                    $item['unit_price'],
                    ($item['quantity'] ?? 1) * $item['unit_price'],
                    $item['account_id'] ?? null
                ]
            );
        }
    }
}

/**
 * Process payment from template
 */
function processPayment($db, $template, $description) {
    // Generate payment number
    $stmt = $db->query("SELECT COUNT(*) as count FROM payments_made WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $paymentNumber = 'PAY-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $db->insert(
        "INSERT INTO payments_made (payment_number, vendor_id, payment_date, amount, payment_method,
                                   reference_number, notes, approved_by, recorded_by)
         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 1, 1)",
        [
            $paymentNumber,
            $template['vendor_id'] ?? $template['customer_id'],
            $template['amount'],
            $template['payment_method'] ?? 'bank_transfer',
            $template['reference'] ?? '',
            $description
        ]
    );
}
?>