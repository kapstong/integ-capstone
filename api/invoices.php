<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/coa_validation.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$auth = new Auth();
ensure_api_auth($method, [
    'GET' => 'invoices.view',
    'PUT' => 'invoices.edit',
    'DELETE' => 'invoices.delete',
    'POST' => 'invoices.create',
    'PATCH' => 'invoices.edit',
]);

$userId = $_SESSION['user']['id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['aging'])) {
                // Generate aging report
                generateAgingReport($db);
            } elseif (isset($_GET['id'])) {
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

            if (empty($items)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invoice items are required']);
                exit;
            }

            $missingItems = [];
            $accountIds = [];
            foreach ($items as $index => $item) {
                $accountId = $item['account_id'] ?? null;
                if ($accountId === null || (is_string($accountId) && trim($accountId) === '')) {
                    $missingItems[] = $index + 1;
                } else {
                    $accountIds[] = $accountId;
                }
            }
            if (!empty($missingItems)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Each invoice item must have an account selected.',
                    'missing_items' => $missingItems
                ]);
                exit;
            }
            $invalidAccounts = findInvalidChartOfAccountsIds($db->getConnection(), $accountIds);
            if (!empty($invalidAccounts)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'One or more selected accounts are invalid or inactive.',
                    'invalid_account_ids' => $invalidAccounts
                ]);
                exit;
            }

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

                    if (empty($items)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invoice items are required']);
                        exit;
                    }

                    $missingItems = [];
                    $accountIds = [];
                    foreach ($items as $index => $item) {
                        $accountId = $item['account_id'] ?? null;
                        if ($accountId === null || (is_string($accountId) && trim($accountId) === '')) {
                            $missingItems[] = $index + 1;
                        } else {
                            $accountIds[] = $accountId;
                        }
                    }
                    if (!empty($missingItems)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Each invoice item must have an account selected.',
                            'missing_items' => $missingItems
                        ]);
                        exit;
                    }
                    $invalidAccounts = findInvalidChartOfAccountsIds($db->getConnection(), $accountIds);
                    if (!empty($invalidAccounts)) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'One or more selected accounts are invalid or inactive.',
                            'invalid_account_ids' => $invalidAccounts
                        ]);
                        exit;
                    }

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
    require_once __DIR__ . '/../includes/journal_entry_number.php';
    $arAccountIdRow = $db->select("SELECT id FROM chart_of_accounts WHERE account_code = '1002' LIMIT 1");
    $arAccountId = $arAccountIdRow[0]['id'] ?? null;
    $entryNumber = generateJournalEntryNumber($db, $arAccountId, date('Y-m-d'));

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

    // Credit: Revenue accounts from invoice items
    $items = $db->select(
        "SELECT account_id, line_total FROM invoice_items WHERE invoice_id = ?",
        [$invoiceId]
    );

    $fallbackRevenueId = $db->select("SELECT id FROM chart_of_accounts WHERE account_code = '4001' AND is_active = 1");
    if (empty($fallbackRevenueId)) {
        $fallbackRevenueId = $db->select("SELECT id FROM chart_of_accounts WHERE account_type = 'revenue' AND is_active = 1 ORDER BY account_code LIMIT 1");
    }
    $fallbackRevenueId = $fallbackRevenueId[0]['id'] ?? null;

    $revenueLines = [];
    if (!empty($items)) {
        $lineCount = count($items);
        $runningTotal = 0;

        foreach ($items as $index => $item) {
            $accountId = $item['account_id'] ?: $fallbackRevenueId;
            $lineAmount = round(floatval($item['line_total']), 2);

            if ($index === $lineCount - 1) {
                $lineAmount = $subtotal - $runningTotal;
            }

            $runningTotal += $lineAmount;
            $revenueLines[] = ['account_id' => $accountId, 'amount' => $lineAmount];
        }
    } else {
        $revenueLines[] = ['account_id' => $fallbackRevenueId, 'amount' => $subtotal];
    }

    foreach ($revenueLines as $line) {
        if (!$line['account_id'] || $line['amount'] <= 0) {
            continue;
        }
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
             VALUES (?, ?, ?, 'Revenue - Invoice')",
            [$entryId, $line['account_id'], $line['amount']]
        );
    }

    // Credit: Tax Payable (if applicable)
    if ($taxAmount > 0) {
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
             VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '2108'), ?, 'Sales Tax Payable')",
            [$entryId, $taxAmount]
        );
    }
}

/**
 * Generate aging report for accounts receivable
 */
function generateAgingReport($db) {
    try {
        // Query to get unpaid invoices with customer info and calculate days past due
        $query = "
            SELECT
                i.*,
                c.company_name as customer_name,
                c.customer_code,
                DATEDIFF(CURDATE(), i.due_date) as days_past_due,
                CASE
                    WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 'current'
                    WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN '1-30'
                    WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE '90+'
                END as aging_bucket
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            WHERE i.balance > 0.01
            AND i.status IN ('sent', 'overdue', 'draft')
            AND c.status = 'active'
            ORDER BY c.company_name, i.due_date
        ";

        $invoices = $db->select($query);

        // Group by customer and sum amounts per aging bucket
        $customerGroups = [];
        foreach ($invoices as $invoice) {
            $customerName = $invoice['customer_name'];

            if (!isset($customerGroups[$customerName])) {
                $customerGroups[$customerName] = [
                    'customer_name' => $customerName,
                    'customer_code' => $invoice['customer_code'],
                    'current' => 0,
                    'days30' => 0,
                    'days60' => 0,
                    'days90' => 0,
                    'legacy' => 0, // 90+ days
                    'total' => 0
                ];
            }

            $balance = (float)$invoice['balance'];

            // Add to appropriate aging bucket
            switch ($invoice['aging_bucket']) {
                case 'current':
                    $customerGroups[$customerName]['current'] += $balance;
                    break;
                case '1-30':
                    $customerGroups[$customerName]['days30'] += $balance;
                    break;
                case '31-60':
                    $customerGroups[$customerName]['days60'] += $balance;
                    break;
                case '61-90':
                    $customerGroups[$customerName]['days90'] += $balance;
                    break;
                case '90+':
                    $customerGroups[$customerName]['legacy'] += $balance;
                    break;
            }

            $customerGroups[$customerName]['total'] += $balance;
        }

        // Convert associative array to indexed array
        $agingData = array_values($customerGroups);
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
?>




