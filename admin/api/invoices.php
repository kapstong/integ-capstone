<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';
require_once '../../includes/notifications.php';

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
            if (isset($_GET['id'])) {
                // Get single invoice with items
                $invoice = $db->select(
                    "SELECT i.*, c.company_name as customer_name, c.customer_code,
                            u.full_name as created_by_name
                     FROM invoices i
                     JOIN customers c ON i.customer_id = c.id
                     LEFT JOIN users u ON i.created_by = u.id
                     WHERE i.id = ?",
                    [$_GET['id']]
                );

                if (empty($invoice)) {
                    echo json_encode(['error' => 'Invoice not found']);
                    exit;
                }

                $invoice = $invoice[0];

                // Get invoice items
                $items = $db->select(
                    "SELECT ii.*, coa.account_name
                     FROM invoice_items ii
                     LEFT JOIN chart_of_accounts coa ON ii.account_id = coa.id
                     WHERE ii.invoice_id = ?
                     ORDER BY ii.id ASC",
                    [$_GET['id']]
                );

                $invoice['items'] = $items;
                echo json_encode($invoice);
            } else {
                // Get all invoices with customer info
                $invoices = $db->select(
                    "SELECT i.*, c.company_name as customer_name, c.customer_code
                     FROM invoices i
                     JOIN customers c ON i.customer_id = c.id
                     ORDER BY i.created_at DESC"
                );
                echo json_encode($invoices);
            }
            break;

        case 'POST':
            // Create new invoice
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                $data = $_POST;
            }

            // Validate required fields
            if (empty($data['customer_id']) || empty($data['invoice_date']) || empty($data['due_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Generate invoice number
            $stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE YEAR(created_at) = YEAR(CURDATE())");
            $count = $stmt->fetch()['count'] + 1;
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Calculate totals
            $subtotal = 0;
            $taxRate = $data['tax_rate'] ?? 12.00;
            $items = $data['items'] ?? [];

            foreach ($items as $item) {
                $subtotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
            }

            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            $db->beginTransaction();

            try {
                $invoiceId = $db->insert(
                    "INSERT INTO invoices (invoice_number, customer_id, invoice_date, due_date,
                                         subtotal, tax_rate, tax_amount, total_amount, balance,
                                         status, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $invoiceNumber,
                        $data['customer_id'],
                        $data['invoice_date'],
                        $data['due_date'],
                        $subtotal,
                        $taxRate,
                        $taxAmount,
                        $totalAmount,
                        $totalAmount, // Initial balance equals total
                        $data['status'] ?? 'draft',
                        $data['notes'] ?? null,
                        $userId
                    ]
                );

                // Insert invoice items
                foreach ($items as $item) {
                    $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                    $db->insert(
                        "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, account_id)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $invoiceId,
                            $item['description'] ?? '',
                            $item['quantity'] ?? 1,
                            $item['unit_price'] ?? 0,
                            $lineTotal,
                            $item['account_id'] ?? null
                        ]
                    );
                }

                // Auto-post journal entry if status is sent
                if (($data['status'] ?? 'draft') === 'sent') {
                    postInvoiceJournalEntry($db, $invoiceId, $subtotal, $taxAmount, $data['customer_id']);
                }

                $db->commit();

                // Send notification if invoice status is sent
                if (($data['status'] ?? 'draft') === 'sent') {
                    NotificationManager::getInstance()->sendInvoiceNotification($invoiceId, 'created');
                }

                // Log the action
                Logger::getInstance()->logUserAction('Created invoice', 'invoices', $invoiceId, null, $data);

                echo json_encode([
                    'success' => true,
                    'id' => $invoiceId,
                    'invoice_number' => $invoiceNumber
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'PUT':
            // Update invoice
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invoice ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            // Get old values for audit
            $oldInvoice = $db->select("SELECT * FROM invoices WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldInvoice[0] ?? null;

            $db->beginTransaction();

            try {
                $fields = [];
                $params = [];

                if (isset($data['customer_id'])) {
                    $fields[] = "customer_id = ?";
                    $params[] = $data['customer_id'];
                }
                if (isset($data['invoice_date'])) {
                    $fields[] = "invoice_date = ?";
                    $params[] = $data['invoice_date'];
                }
                if (isset($data['due_date'])) {
                    $fields[] = "due_date = ?";
                    $params[] = $data['due_date'];
                }
                if (isset($data['status'])) {
                    $fields[] = "status = ?";
                    $params[] = $data['status'];
                }
                if (isset($data['notes'])) {
                    $fields[] = "notes = ?";
                    $params[] = $data['notes'];
                }

                // Recalculate totals if items changed
                if (isset($data['items'])) {
                    $subtotal = 0;
                    $taxRate = $data['tax_rate'] ?? 12.00;
                    $items = $data['items'];

                    foreach ($items as $item) {
                        $subtotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                    }

                    $taxAmount = $subtotal * ($taxRate / 100);
                    $totalAmount = $subtotal + $taxAmount;

                    $fields[] = "subtotal = ?"; $params[] = $subtotal;
                    $fields[] = "tax_rate = ?"; $params[] = $taxRate;
                    $fields[] = "tax_amount = ?"; $params[] = $taxAmount;
                    $fields[] = "total_amount = ?"; $params[] = $totalAmount;
                    $fields[] = "balance = ?"; $params[] = $totalAmount - ($oldValues['paid_amount'] ?? 0);

                    // Update invoice items
                    $db->execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$_GET['id']]);
                    foreach ($items as $item) {
                        $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                        $db->insert(
                            "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, account_id)
                             VALUES (?, ?, ?, ?, ?, ?)",
                            [
                                $_GET['id'],
                                $item['description'] ?? '',
                                $item['quantity'] ?? 1,
                                $item['unit_price'] ?? 0,
                                $lineTotal,
                                $item['account_id'] ?? null
                            ]
                        );
                    }
                }

                if (empty($fields)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No fields to update']);
                    exit;
                }

                $params[] = $_GET['id'];
                $sql = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ?";

                $affected = $db->execute($sql, $params);
                $db->commit();

                // Log the action
                Logger::getInstance()->logUserAction('Updated invoice', 'invoices', $_GET['id'], $oldValues, $data);

                echo json_encode(['success' => $affected > 0]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'DELETE':
            // Delete invoice
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invoice ID required']);
                exit;
            }

            // Check if invoice has payments
            $stmt = $db->query("SELECT COUNT(*) as count FROM payments_received WHERE invoice_id = ?", [$_GET['id']]);
            if ($stmt->fetch()['count'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete invoice with existing payments']);
                exit;
            }

            // Get old values for audit
            $oldInvoice = $db->select("SELECT * FROM invoices WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldInvoice[0] ?? null;

            $db->beginTransaction();

            try {
                // Delete invoice items first
                $db->execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$_GET['id']]);

                // Delete invoice
                $affected = $db->execute("DELETE FROM invoices WHERE id = ?", [$_GET['id']]);

                $db->commit();

                // Log the action
                Logger::getInstance()->logUserAction('Deleted invoice', 'invoices', $_GET['id'], $oldValues, null);

                echo json_encode(['success' => $affected > 0]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Invoice API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

/**
 * Post journal entry for invoice
 */
function postInvoiceJournalEntry($db, $invoiceId, $subtotal, $taxAmount, $customerId) {
    // Generate journal entry number
    $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, posted_by, posted_at)
         VALUES (?, CURDATE(), ?, ?, ?, 'posted', ?, ?, NOW())",
        [
            $entryNumber,
            "Invoice $invoiceId - Customer sales",
            $subtotal + $taxAmount,
            $subtotal + $taxAmount,
            1, // System user
            1  // System user
        ]
    );

    // Debit: Accounts Receivable
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '1002'), ?, 'Accounts Receivable - Invoice')",
        [$entryId, $subtotal + $taxAmount]
    );

    // Credit: Sales Revenue
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '4001'), ?, 'Sales Revenue')",
        [$entryId, $subtotal]
    );

    // Credit: Tax Payable (if applicable)
    if ($taxAmount > 0) {
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
             VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '2001'), ?, 'Tax Payable')",
            [$entryId, $taxAmount]
        );
    }
}
?>
