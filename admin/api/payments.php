<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
session_start();

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
            $type = $_GET['type'] ?? 'all'; // 'received', 'made', or 'all'

            if (isset($_GET['id'])) {
                // Get single payment
                if ($type === 'received' || $type === 'all') {
                    $payment = $db->select(
                        "SELECT pr.*, c.company_name as customer_name, i.invoice_number,
                                u.full_name as recorded_by_name
                         FROM payments_received pr
                         LEFT JOIN customers c ON pr.customer_id = c.id
                         LEFT JOIN invoices i ON pr.invoice_id = i.id
                         LEFT JOIN users u ON pr.recorded_by = u.id
                         WHERE pr.id = ?",
                        [$_GET['id']]
                    );

                    if (!empty($payment)) {
                        echo json_encode($payment[0]);
                        exit;
                    }
                }

                if ($type === 'made' || $type === 'all') {
                    $payment = $db->select(
                        "SELECT pm.*, v.company_name as vendor_name, b.bill_number,
                                u.full_name as recorded_by_name, ua.full_name as approved_by_name
                         FROM payments_made pm
                         LEFT JOIN vendors v ON pm.vendor_id = v.id
                         LEFT JOIN bills b ON pm.bill_id = b.id
                         LEFT JOIN users u ON pm.recorded_by = u.id
                         LEFT JOIN users ua ON pm.approved_by = ua.id
                         WHERE pm.id = ?",
                        [$_GET['id']]
                    );

                    if (!empty($payment)) {
                        echo json_encode($payment[0]);
                        exit;
                    }
                }

                echo json_encode(['error' => 'Payment not found']);
                exit;
            } else {
                // Get all payments based on type
                $payments = [];

                if ($type === 'received' || $type === 'all') {
                    $received = $db->select(
                        "SELECT pr.*, c.company_name as customer_name, i.invoice_number,
                                u.full_name as recorded_by_name, 'received' as payment_type
                         FROM payments_received pr
                         LEFT JOIN customers c ON pr.customer_id = c.id
                         LEFT JOIN invoices i ON pr.invoice_id = i.id
                         LEFT JOIN users u ON pr.recorded_by = u.id
                         ORDER BY pr.payment_date DESC"
                    );
                    $payments = array_merge($payments, $received);
                }

                if ($type === 'made' || $type === 'all') {
                    $made = $db->select(
                        "SELECT pm.*, v.company_name as vendor_name, b.bill_number,
                                u.full_name as recorded_by_name, ua.full_name as approved_by_name,
                                'made' as payment_type
                         FROM payments_made pm
                         LEFT JOIN vendors v ON pm.vendor_id = v.id
                         LEFT JOIN bills b ON pm.bill_id = b.id
                         LEFT JOIN users u ON pm.recorded_by = u.id
                         LEFT JOIN users ua ON pm.approved_by = ua.id
                         ORDER BY pm.payment_date DESC"
                    );
                    $payments = array_merge($payments, $made);
                }

                // Sort combined results by date
                usort($payments, function($a, $b) {
                    return strtotime($b['payment_date']) - strtotime($a['payment_date']);
                });

                echo json_encode($payments);
            }
            break;

        case 'POST':
            // Create new payment
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                $data = $_POST;
            }

            $paymentType = $data['payment_type'] ?? 'received';

            // Validate required fields
            if (empty($data['payment_date']) || empty($data['amount']) || empty($data['payment_method'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            if ($paymentType === 'received' && empty($data['customer_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Customer ID required for payments received']);
                exit;
            }

            if ($paymentType === 'made' && empty($data['vendor_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Vendor ID required for payments made']);
                exit;
            }

            $db->beginTransaction();

            try {
                if ($paymentType === 'received') {
                    // Generate payment number for collections
                    $stmt = $db->query("SELECT COUNT(*) as count FROM payments_received WHERE YEAR(created_at) = YEAR(CURDATE())");
                    $count = $stmt->fetch()['count'] + 1;
                    $paymentNumber = 'PAY-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

                    $paymentId = $db->insert(
                        "INSERT INTO payments_received (payment_number, customer_id, invoice_id, payment_date,
                                                      amount, payment_method, reference_number, notes, recorded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $paymentNumber,
                            $data['customer_id'],
                            $data['invoice_id'] ?? null,
                            $data['payment_date'],
                            $data['amount'],
                            $data['payment_method'],
                            $data['reference_number'] ?? null,
                            $data['notes'] ?? null,
                            $userId
                        ]
                    );

                    // Update invoice balance if linked to invoice
                    if (!empty($data['invoice_id'])) {
                        $db->execute(
                            "UPDATE invoices SET paid_amount = paid_amount + ?, balance = balance - ? WHERE id = ?",
                            [$data['amount'], $data['amount'], $data['invoice_id']]
                        );

                        // Update invoice status if fully paid
                        $invoice = $db->select("SELECT balance FROM invoices WHERE id = ?", [$data['invoice_id']]);
                        if (!empty($invoice) && $invoice[0]['balance'] <= 0) {
                            $db->execute("UPDATE invoices SET status = 'paid' WHERE id = ?", [$data['invoice_id']]);
                        }
                    }

                    // Post journal entry for payment received
                    postPaymentReceivedJournalEntry($db, $paymentId, $data['amount']);

                } else {
                    // Generate payment number for disbursements
                    $stmt = $db->query("SELECT COUNT(*) as count FROM payments_made WHERE YEAR(created_at) = YEAR(CURDATE())");
                    $count = $stmt->fetch()['count'] + 1;
                    $paymentNumber = 'PMT-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

                    $paymentId = $db->insert(
                        "INSERT INTO payments_made (payment_number, vendor_id, bill_id, payment_date,
                                                   amount, payment_method, reference_number, notes,
                                                   approved_by, recorded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $paymentNumber,
                            $data['vendor_id'],
                            $data['bill_id'] ?? null,
                            $data['payment_date'],
                            $data['amount'],
                            $data['payment_method'],
                            $data['reference_number'] ?? null,
                            $data['notes'] ?? null,
                            $data['approved_by'] ?? $userId,
                            $userId
                        ]
                    );

                    // Update bill balance if linked to bill
                    if (!empty($data['bill_id'])) {
                        $db->execute(
                            "UPDATE bills SET paid_amount = paid_amount + ?, balance = balance - ? WHERE id = ?",
                            [$data['amount'], $data['amount'], $data['bill_id']]
                        );

                        // Update bill status if fully paid
                        $bill = $db->select("SELECT balance FROM bills WHERE id = ?", [$data['bill_id']]);
                        if (!empty($bill) && $bill[0]['balance'] <= 0) {
                            $db->execute("UPDATE bills SET status = 'paid' WHERE id = ?", [$data['bill_id']]);
                        }
                    }

                    // Post journal entry for payment made
                    postPaymentMadeJournalEntry($db, $paymentId, $data['amount']);
                }

                $db->commit();

                // Log the action
                Logger::getInstance()->logUserAction("Created $paymentType payment", $paymentType === 'received' ? 'payments_received' : 'payments_made', $paymentId, null, $data);

                echo json_encode([
                    'success' => true,
                    'id' => $paymentId,
                    'payment_number' => $paymentNumber,
                    'payment_type' => $paymentType
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'PUT':
            // Update payment
            if (!isset($_GET['id']) || !isset($_GET['type'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Payment ID and type required']);
                exit;
            }

            $paymentType = $_GET['type'];
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $table = $paymentType === 'received' ? 'payments_received' : 'payments_made';
            $oldPayment = $db->select("SELECT * FROM $table WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldPayment[0] ?? null;

            $fields = [];
            $params = [];

            if (isset($data['payment_date'])) {
                $fields[] = "payment_date = ?";
                $params[] = $data['payment_date'];
            }
            if (isset($data['amount'])) {
                $fields[] = "amount = ?";
                $params[] = $data['amount'];
            }
            if (isset($data['payment_method'])) {
                $fields[] = "payment_method = ?";
                $params[] = $data['payment_method'];
            }
            if (isset($data['reference_number'])) {
                $fields[] = "reference_number = ?";
                $params[] = $data['reference_number'];
            }
            if (isset($data['notes'])) {
                $fields[] = "notes = ?";
                $params[] = $data['notes'];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            $params[] = $_GET['id'];
            $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            // Log the action
            Logger::getInstance()->logUserAction("Updated $paymentType payment", $table, $_GET['id'], $oldValues, $data);

            echo json_encode(['success' => $affected > 0]);
            break;

        case 'DELETE':
            // Delete payment
            if (!isset($_GET['id']) || !isset($_GET['type'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Payment ID and type required']);
                exit;
            }

            $paymentType = $_GET['type'];
            $table = $paymentType === 'received' ? 'payments_received' : 'payments_made';

            // Get old values for audit
            $oldPayment = $db->select("SELECT * FROM $table WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldPayment[0] ?? null;

            $affected = $db->execute("DELETE FROM $table WHERE id = ?", [$_GET['id']]);

            // Log the action
            Logger::getInstance()->logUserAction("Deleted $paymentType payment", $table, $_GET['id'], $oldValues, null);

            echo json_encode(['success' => $affected > 0]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Payment API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

/**
 * Post journal entry for payment received
 */
function postPaymentReceivedJournalEntry($db, $paymentId, $amount) {
    // Generate journal entry number
    $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, posted_by, posted_at)
         VALUES (?, CURDATE(), ?, ?, ?, 'posted', ?, ?, NOW())",
        [
            $entryNumber,
            "Payment Received $paymentId",
            $amount,
            $amount,
            1, // System user
            1  // System user
        ]
    );

    // Debit: Cash/Bank
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '1001'), ?, 'Cash - Payment Received')",
        [$entryId, $amount]
    );

    // Credit: Accounts Receivable
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '1002'), ?, 'Accounts Receivable - Payment')",
        [$entryId, $amount]
    );
}

/**
 * Post journal entry for payment made
 */
function postPaymentMadeJournalEntry($db, $paymentId, $amount) {
    // Generate journal entry number
    $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, posted_by, posted_at)
         VALUES (?, CURDATE(), ?, ?, ?, 'posted', ?, ?, NOW())",
        [
            $entryNumber,
            "Payment Made $paymentId",
            $amount,
            $amount,
            1, // System user
            1  // System user
        ]
    );

    // Debit: Accounts Payable
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '2001'), ?, 'Accounts Payable - Payment')",
        [$entryId, $amount]
    );

    // Credit: Cash/Bank
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '1001'), ?, 'Cash - Payment Made')",
        [$entryId, $amount]
    );
}
?>
