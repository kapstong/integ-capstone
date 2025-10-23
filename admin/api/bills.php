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
            if (isset($_GET['id'])) {
                // Get single bill with items
                $bill = $db->select(
                    "SELECT b.*, v.company_name as vendor_name, v.vendor_code,
                            u.full_name as created_by_name
                     FROM bills b
                     JOIN vendors v ON b.vendor_id = v.id
                     LEFT JOIN users u ON b.created_by = u.id
                     WHERE b.id = ?",
                    [$_GET['id']]
                );

                if (empty($bill)) {
                    echo json_encode(['error' => 'Bill not found']);
                    exit;
                }

                $bill = $bill[0];

                // Get bill items
                $items = $db->select(
                    "SELECT bi.*, coa.account_name
                     FROM bill_items bi
                     LEFT JOIN chart_of_accounts coa ON bi.account_id = coa.id
                     WHERE bi.bill_id = ?
                     ORDER BY bi.id ASC",
                    [$_GET['id']]
                );

                $bill['items'] = $items;
                echo json_encode($bill);
            } else {
                // Get all bills with vendor info
                $bills = $db->select(
                    "SELECT b.*, v.company_name as vendor_name, v.vendor_code
                     FROM bills b
                     JOIN vendors v ON b.vendor_id = v.id
                     ORDER BY b.created_at DESC"
                );
                echo json_encode($bills);
            }
            break;

        case 'POST':
            // Create new bill
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                $data = $_POST;
            }

            // Validate required fields
            if (empty($data['vendor_id']) || empty($data['bill_date']) || empty($data['due_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Generate bill number
            $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE YEAR(created_at) = YEAR(CURDATE())");
            $count = $stmt->fetch()['count'] + 1;
            $billNumber = 'BILL-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

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
                $billId = $db->insert(
                    "INSERT INTO bills (bill_number, vendor_id, bill_date, due_date,
                                       subtotal, tax_rate, tax_amount, total_amount, balance,
                                       status, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $billNumber,
                        $data['vendor_id'],
                        $data['bill_date'],
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

                // Insert bill items
                foreach ($items as $item) {
                    $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                    $db->insert(
                        "INSERT INTO bill_items (bill_id, description, quantity, unit_price, line_total, account_id)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $billId,
                            $item['description'] ?? '',
                            $item['quantity'] ?? 1,
                            $item['unit_price'] ?? 0,
                            $lineTotal,
                            $item['account_id'] ?? null
                        ]
                    );
                }

                // Auto-post journal entry if status is approved
                if (($data['status'] ?? 'draft') === 'approved') {
                    postBillJournalEntry($db, $billId, $subtotal, $taxAmount);
                }

                $db->commit();

                // Log the action
                Logger::getInstance()->logUserAction('Created bill', 'bills', $billId, null, $data);

                echo json_encode([
                    'success' => true,
                    'id' => $billId,
                    'bill_number' => $billNumber
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'PUT':
            // Update bill
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bill ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            // Get old values for audit
            $oldBill = $db->select("SELECT * FROM bills WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldBill[0] ?? null;

            $db->beginTransaction();

            try {
                $fields = [];
                $params = [];

                if (isset($data['vendor_id'])) {
                    $fields[] = "vendor_id = ?";
                    $params[] = $data['vendor_id'];
                }
                if (isset($data['bill_date'])) {
                    $fields[] = "bill_date = ?";
                    $params[] = $data['bill_date'];
                }
                if (isset($data['due_date'])) {
                    $fields[] = "due_date = ?";
                    $params[] = $data['due_date'];
                }
                if (isset($data['status'])) {
                    $fields[] = "status = ?";
                    $params[] = $data['status'];
                }
                if (isset($data['approved_by'])) {
                    $fields[] = "approved_by = ?";
                    $params[] = $data['approved_by'];
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

                    // Update bill items
                    $db->execute("DELETE FROM bill_items WHERE bill_id = ?", [$_GET['id']]);
                    foreach ($items as $item) {
                        $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                        $db->insert(
                            "INSERT INTO bill_items (bill_id, description, quantity, unit_price, line_total, account_id)
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
                $sql = "UPDATE bills SET " . implode(', ', $fields) . " WHERE id = ?";

                $affected = $db->execute($sql, $params);
                $db->commit();

                // Log the action
                Logger::getInstance()->logUserAction('Updated bill', 'bills', $_GET['id'], $oldValues, $data);

                echo json_encode(['success' => $affected > 0]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'DELETE':
            // Delete bill
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bill ID required']);
                exit;
            }

            // Check if bill has payments
            $stmt = $db->query("SELECT COUNT(*) as count FROM payments_made WHERE bill_id = ?", [$_GET['id']]);
            if ($stmt->fetch()['count'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete bill with existing payments']);
                exit;
            }

            // Get old values for audit
            $oldBill = $db->select("SELECT * FROM bills WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldBill[0] ?? null;

            $db->beginTransaction();

            try {
                // Delete bill items first
                $db->execute("DELETE FROM bill_items WHERE bill_id = ?", [$_GET['id']]);

                // Delete bill
                $affected = $db->execute("DELETE FROM bills WHERE id = ?", [$_GET['id']]);

                $db->commit();

                // Log the action
                Logger::getInstance()->logUserAction('Deleted bill', 'bills', $_GET['id'], $oldValues, null);

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
    Logger::getInstance()->logDatabaseError('Bill API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

/**
 * Post journal entry for bill
 */
function postBillJournalEntry($db, $billId, $subtotal, $taxAmount) {
    // Generate journal entry number
    $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
    $count = $stmt->fetch()['count'] + 1;
    $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, posted_by, posted_at)
         VALUES (?, CURDATE(), ?, ?, ?, 'posted', ?, ?, NOW())",
        [
            $entryNumber,
            "Bill $billId - Vendor purchase",
            $subtotal + $taxAmount,
            $subtotal + $taxAmount,
            1, // System user
            1  // System user
        ]
    );

    // Debit: Expense accounts (or appropriate expense account)
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '5001'), ?, 'Cost of Goods Sold - Bill')",
        [$entryId, $subtotal]
    );

    // Debit: Tax expense (if applicable)
    if ($taxAmount > 0) {
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
             VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '5002'), ?, 'Tax Expense')",
            [$entryId, $taxAmount]
        );
    }

    // Credit: Accounts Payable
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '2001'), ?, 'Accounts Payable - Bill')",
        [$entryId, $subtotal + $taxAmount]
    );
}
