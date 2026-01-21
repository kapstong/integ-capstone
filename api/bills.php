<?php
// Force JSON output and disable HTML errors for API responses
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set up error handler to catch and output errors as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $errstr]);
    exit(1);
}, E_ALL);

// Set up exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception: ' . $exception->getMessage()]);
    exit(1);
});

try {
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';

    // Session is already started in auth.php

    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error', 'details' => $e->getMessage()]);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user']['id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                if ($_GET['action'] === 'aging') {
                    // Generate aging report
                    generateAgingReport($db);
                } elseif ($_GET['action'] === 'next_number') {
                    // Get next bill number
                    $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE YEAR(created_at) = YEAR(CURDATE())");
                    $count = $stmt->fetch()['count'] + 1;
                    $nextNumber = 'BILL-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                    echo json_encode(['success' => true, 'next_number' => $nextNumber]);
                }
            } elseif (isset($_GET['id'])) {
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
                // Build WHERE clause for filtering
                $whereConditions = [];
                $params = [];

                // Status filtering
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $statusList = explode(',', $_GET['status']);
                    $placeholders = str_repeat('?,', count($statusList) - 1) . '?';
                    $whereConditions[] = "b.status IN ($placeholders)";
                    $params = array_merge($params, $statusList);
                }

                // Vendor ID filtering
                if (isset($_GET['vendor_id']) && !empty($_GET['vendor_id'])) {
                    $whereConditions[] = "b.vendor_id = ?";
                    $params[] = $_GET['vendor_id'];
                }

                // Date range filtering
                if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                    $whereConditions[] = "b.bill_date >= ?";
                    $params[] = $_GET['date_from'];
                }

                if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                    $whereConditions[] = "b.bill_date <= ?";
                    $params[] = $_GET['date_to'];
                }

                // Build the SQL query
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : '';

                $bills = $db->select(
                    "SELECT b.*, v.company_name as vendor_name, v.vendor_code
                     FROM bills b
                     JOIN vendors v ON b.vendor_id = v.id
                     $whereClause
                     ORDER BY b.created_at DESC",
                    $params
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

            // Use provided bill number or generate new one
            $billNumber = $data['bill_number'] ?? null;
            if (empty($billNumber)) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE YEAR(created_at) = YEAR(CURDATE())");
                $count = $stmt->fetch()['count'] + 1;
                $billNumber = 'BILL-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // Calculate totals - handle simple amount field from frontend
            $taxRate = $data['tax_rate'] ?? 12.00;

            if (isset($data['amount'])) {
                // Simple bill creation from frontend - use amount directly
                $totalAmount = (float)$data['amount'];
                $subtotal = $totalAmount / (1 + ($taxRate / 100));
                $taxAmount = $totalAmount - $subtotal;
                $items = []; // No items for simple bills
            } else {
                // Detailed bill creation with items
                $subtotal = 0;
                $items = $data['items'] ?? [];

                foreach ($items as $item) {
                    $subtotal += ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                }

                $taxAmount = $subtotal * ($taxRate / 100);
                $totalAmount = $subtotal + $taxAmount;
            }

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
                if (isset($data['amount'])) {
                    // Update total_amount directly (frontend sends simple amount field)
                    $fields[] = "total_amount = ?";
                    $params[] = $data['amount'];
                }
                if (isset($data['description'])) {
                    // Update notes from description
                    $fields[] = "notes = ?";
                    $params[] = $data['description'];
                }
                if (isset($data['bill_number'])) {
                    // Update bill number
                    $fields[] = "bill_number = ?";
                    $params[] = $data['bill_number'];
                }
                if (isset($data['status'])) {
                    $fields[] = "status = ?";
                    $params[] = $data['status'];
                }
                if (isset($data['approved_by'])) {
                    $fields[] = "approved_by = ?";
                    $params[] = $data['approved_by'];
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
    exit;
}

/**
 * Generate aging report
 */
function generateAgingReport($db) {
    try {
        $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;

        // Query to get unpaid bills with vendor info and calculate days past due
        $query = "
            SELECT
                b.*,
                v.company_name as vendor_name,
                v.vendor_code,
                DATEDIFF(CURDATE(), b.due_date) as days_past_due,
                CASE
                    WHEN DATEDIFF(CURDATE(), b.due_date) <= 0 THEN 'current'
                    WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 1 AND 30 THEN '1-30'
                    WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN DATEDIFF(CURDATE(), b.due_date) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE '90+'
                END as aging_bucket
            FROM bills b
            JOIN vendors v ON b.vendor_id = v.id
            WHERE b.balance > 0.01
            AND b.status IN ('approved', 'overdue', 'draft')
            AND v.status = 'active'
            ORDER BY v.company_name, b.due_date
        ";

        $bills = $db->select($query);

        $agingData = [];
        foreach ($bills as $bill) {
            $agingData[] = [
                'bill_number' => $bill['bill_number'],
                'vendor_name' => $bill['vendor_name'],
                'vendor_code' => $bill['vendor_code'],
                'bill_date' => $bill['bill_date'],
                'due_date' => $bill['due_date'],
                'total_amount' => (float)$bill['total_amount'],
                'balance' => (float)$bill['balance'],
                'days_past_due' => (int)$bill['days_past_due'],
                'aging_bucket' => $bill['aging_bucket'],
                'status' => $bill['status']
            ];
        }

        echo json_encode($agingData);

    } catch (Exception $e) {
        Logger::getInstance()->logDatabaseError('Aging Report Generation', $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to generate aging report',
            'message' => $e->getMessage()
        ]);
    }
}

function getActiveBudgetItem($db, $accountId) {
    $budget = $db->select(
        "SELECT bi.id, bi.budgeted_amount, bi.actual_amount
         FROM budget_items bi
         INNER JOIN budgets b ON bi.budget_id = b.id
         WHERE bi.account_id = ? AND b.status = 'active' AND YEAR(b.budget_year) = YEAR(CURDATE())
         LIMIT 1",
        [$accountId]
    );

    return $budget[0] ?? null;
}

function enforceBudgetLimit($db, $accountId, $amount) {
    $budget = getActiveBudgetItem($db, $accountId);
    if (!$budget) {
        return;
    }

    $remaining = floatval($budget['budgeted_amount']) - floatval($budget['actual_amount']);
    if ($amount > $remaining) {
        throw new Exception('Bill exceeds available budget for the selected account.');
    }
}

function applyBudgetActual($db, $accountId, $amount) {
    $budget = getActiveBudgetItem($db, $accountId);
    if (!$budget) {
        return;
    }

    $db->execute(
        "UPDATE budget_items
         SET actual_amount = actual_amount + ?,
             variance = budgeted_amount - (actual_amount + ?),
             updated_at = CURRENT_TIMESTAMP
         WHERE id = ?",
        [$amount, $amount, $budget['id']]
    );
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

    $totalAmount = $subtotal + $taxAmount;
    $items = $db->select(
        "SELECT account_id, line_total FROM bill_items WHERE bill_id = ?",
        [$billId]
    );

    // Fallback expense account (Office Supplies) if bill items are missing accounts
    $fallbackExpenseId = $db->select("SELECT id FROM chart_of_accounts WHERE account_code = '5403'");
    if (empty($fallbackExpenseId)) {
        $fallbackExpenseId = $db->select("SELECT id FROM chart_of_accounts WHERE account_type = 'expense' ORDER BY account_code LIMIT 1");
    }
    $fallbackExpenseId = $fallbackExpenseId[0]['id'] ?? 1;

    $expenseLines = [];

    if (!empty($items)) {
        $factor = $subtotal > 0 ? ($totalAmount / $subtotal) : 1;
        $lineCount = count($items);
        $runningTotal = 0;

        foreach ($items as $index => $item) {
            $accountId = $item['account_id'] ?: $fallbackExpenseId;
            $lineAmount = round(floatval($item['line_total']) * $factor, 2);

            // Adjust last line for rounding differences
            if ($index === $lineCount - 1) {
                $lineAmount = $totalAmount - $runningTotal;
            }

            $runningTotal += $lineAmount;
            $expenseLines[] = ['account_id' => $accountId, 'amount' => $lineAmount];
        }
    } else {
        $expenseLines[] = ['account_id' => $fallbackExpenseId, 'amount' => $totalAmount];
    }

    // Budget checks (by account)
    $budgetTotals = [];
    foreach ($expenseLines as $line) {
        $budgetTotals[$line['account_id']] = ($budgetTotals[$line['account_id']] ?? 0) + $line['amount'];
    }
    foreach ($budgetTotals as $accountId => $amount) {
        enforceBudgetLimit($db, $accountId, $amount);
    }

    // Insert expense lines and apply budget actuals
    foreach ($expenseLines as $line) {
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, description)
             VALUES (?, ?, ?, 'Expense - Bill')",
            [$entryId, $line['account_id'], $line['amount']]
        );

        applyBudgetActual($db, $line['account_id'], $line['amount']);
    }

    // Credit: Accounts Payable
    $db->insert(
        "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
         VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '2001'), ?, 'Accounts Payable - Bill')",
        [$entryId, $totalAmount]
    );
}

