<?php
/**
 * ATIERA External API - Invoices Endpoint
 * Public API for invoice operations
 */

require_once '../../includes/database.php';
require_once '../../includes/api_auth.php';
require_once '../../includes/logger.php';
require_once '../../includes/coa_validation.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = Database::getInstance();
$apiAuth = APIAuth::getInstance();

// Authenticate API request
try {
    $client = $apiAuth->authenticate();
} catch (Exception $e) {
    // Authentication errors are handled in the authenticate method
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get invoices
            if (isset($_GET['id'])) {
                // Get single invoice
                getInvoice($db, $_GET['id']);
            } else {
                // Get all invoices with optional filters
                getInvoices($db);
            }
            break;

        case 'POST':
            // Create new invoice
            createInvoice($db, $client);
            break;

        case 'PUT':
            // Update invoice
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invoice ID required for updates'
                ]);
                exit;
            }
            updateInvoice($db, $_GET['id'], $client);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->error("External API error: " . $e->getMessage(), [
        'endpoint' => 'invoices',
        'method' => $method,
        'client_id' => $client['id']
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

/**
 * Get all invoices with filters
 */
function getInvoices($db) {
    $where = [];
    $params = [];

    // Filter by status
    if (isset($_GET['status'])) {
        $where[] = "i.status = ?";
        $params[] = $_GET['status'];
    }

    // Filter by customer
    if (isset($_GET['customer_id'])) {
        $where[] = "i.customer_id = ?";
        $params[] = $_GET['customer_id'];
    }

    // Filter by date range
    if (isset($_GET['date_from'])) {
        $where[] = "i.invoice_date >= ?";
        $params[] = $_GET['date_from'];
    }

    if (isset($_GET['date_to'])) {
        $where[] = "i.invoice_date <= ?";
        $params[] = $_GET['date_to'];
    }

    // Filter by amount range
    if (isset($_GET['min_amount'])) {
        $where[] = "i.total_amount >= ?";
        $params[] = $_GET['min_amount'];
    }

    if (isset($_GET['max_amount'])) {
        $where[] = "i.total_amount <= ?";
        $params[] = $_GET['max_amount'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Pagination
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $sql = "
        SELECT
            i.*,
            c.company_name as customer_name,
            c.customer_code,
            c.email as customer_email,
            u.full_name as created_by_name,
            COALESCE(p.total_paid, 0) as amount_paid,
            (i.total_amount - COALESCE(p.total_paid, 0)) as balance
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN (
            SELECT invoice_id, SUM(amount) as total_paid
            FROM payments_received
            GROUP BY invoice_id
        ) p ON i.id = p.invoice_id
        {$whereClause}
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $invoices = $db->select($sql, $params);

    // Get invoice items for each invoice
    foreach ($invoices as &$invoice) {
        $items = $db->select(
            "SELECT ii.*, coa.account_name
             FROM invoice_items ii
             LEFT JOIN chart_of_accounts coa ON ii.account_id = coa.id
             WHERE ii.invoice_id = ?
             ORDER BY ii.id ASC",
            [$invoice['id']]
        );
        $invoice['items'] = $items;
    }

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM invoices i {$whereClause}";
    $countResult = $db->select($countSql, array_slice($params, 0, -2)); // Remove limit and offset
    $totalCount = $countResult[0]['total'];

    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ]
    ]);
}

/**
 * Get single invoice
 */
function getInvoice($db, $invoiceId) {
    $invoice = $db->select(
        "SELECT
            i.*,
            c.company_name as customer_name,
            c.customer_code,
            c.email as customer_email,
            c.phone as customer_phone,
            c.address as customer_address,
            u.full_name as created_by_name,
            COALESCE(p.total_paid, 0) as amount_paid,
            (i.total_amount - COALESCE(p.total_paid, 0)) as balance
         FROM invoices i
         JOIN customers c ON i.customer_id = c.id
         LEFT JOIN users u ON i.created_by = u.id
         LEFT JOIN (
             SELECT invoice_id, SUM(amount) as total_paid
             FROM payments_received
             GROUP BY invoice_id
         ) p ON i.id = p.invoice_id
         WHERE i.id = ?",
        [$invoiceId]
    );

    if (empty($invoice)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Invoice not found'
        ]);
        return;
    }

    $invoice = $invoice[0];

    // Get invoice items
    $items = $db->select(
        "SELECT ii.*, coa.account_name
         FROM invoice_items ii
         LEFT JOIN chart_of_accounts coa ON ii.account_id = coa.id
         WHERE ii.invoice_id = ?
         ORDER BY ii.id ASC",
        [$invoiceId]
    );

    $invoice['items'] = $items;

    // Get payment history
    $payments = $db->select(
        "SELECT pr.*, pm.method_name, u.full_name as received_by_name
         FROM payments_received pr
         JOIN payment_methods pm ON pr.payment_method_id = pm.id
         LEFT JOIN users u ON pr.received_by = u.id
         WHERE pr.invoice_id = ?
         ORDER BY pr.payment_date DESC",
        [$invoiceId]
    );

    $invoice['payments'] = $payments;

    echo json_encode([
        'success' => true,
        'data' => $invoice
    ]);
}

/**
 * Create new invoice
 */
function createInvoice($db, $client) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON data'
        ]);
        return;
    }

    // Validate required fields
    $required = ['customer_id', 'invoice_date', 'due_date', 'items'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Missing required field: {$field}"
            ]);
            return;
        }
    }

    // Validate customer exists
    $customer = $db->select("SELECT id FROM customers WHERE id = ?", [$data['customer_id']]);
    if (empty($customer)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid customer ID'
        ]);
        return;
    }

    $db->beginTransaction();

    try {
        // Generate invoice number
        $stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE YEAR(created_at) = YEAR(CURDATE())");
        $count = $stmt->fetch()['count'] + 1;
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    // Calculate totals
    $subtotal = 0;
    $taxRate = $data['tax_rate'] ?? 12.00;
    $items = $data['items'];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invoice items are required'
        ]);
        return;
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
        return;
    }
    $invalidAccounts = findInvalidChartOfAccountsIds($db->getConnection(), $accountIds);
    if (!empty($invalidAccounts)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'One or more selected accounts are invalid or inactive.',
            'invalid_account_ids' => $invalidAccounts
        ]);
        return;
    }

    foreach ($items as $item) {
        if (!isset($item['description']) || !isset($item['unit_price'])) {
            throw new Exception('Each item must have description and unit_price');
        }
            $quantity = $item['quantity'] ?? 1;
            $subtotal += $quantity * $item['unit_price'];
        }

        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;

        // Create invoice
        $invoiceId = $db->insert(
            "INSERT INTO invoices (invoice_number, customer_id, invoice_date, due_date,
                                   subtotal, tax_rate, tax_amount, total_amount, balance,
                                   status, notes, created_by, created_via_api, api_client_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
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
                1, // System user for API
                $client['id']
            ]
        );

        // Create invoice items
        foreach ($items as $item) {
            $lineTotal = ($item['quantity'] ?? 1) * $item['unit_price'];
            $db->insert(
                "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, account_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $invoiceId,
                    $item['description'],
                    $item['quantity'] ?? 1,
                    $item['unit_price'],
                    $lineTotal,
                    $item['account_id'] ?? null
                ]
            );
        }

        // Auto-post journal entry if status is approved
        if (($data['status'] ?? 'draft') === 'approved') {
            postInvoiceJournalEntry($db, $invoiceId, $subtotal, $taxAmount);
        }

        $db->commit();

        // Log the action
        Logger::getInstance()->logUserAction(
            'Invoice created via API',
            'invoices',
            $invoiceId,
            null,
            array_merge($data, ['api_client_id' => $client['id']])
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
                'balance' => $totalAmount
            ]
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Update invoice
 */
function updateInvoice($db, $invoiceId, $client) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON data'
        ]);
        return;
    }

    // Check if invoice exists
    $existing = $db->select("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
    if (empty($existing)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Invoice not found'
        ]);
        return;
    }

    $db->beginTransaction();

    try {
        $fields = [];
        $params = [];

        // Update allowed fields
        $allowedFields = ['customer_id', 'invoice_date', 'due_date', 'status', 'notes'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        // Recalculate totals if items changed
        if (isset($data['items'])) {
            $subtotal = 0;
            $taxRate = $data['tax_rate'] ?? 12.00;
            $items = $data['items'];

            if (empty($items)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invoice items are required'
                ]);
                return;
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
                return;
            }
            $invalidAccounts = findInvalidChartOfAccountsIds($db->getConnection(), $accountIds);
            if (!empty($invalidAccounts)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'One or more selected accounts are invalid or inactive.',
                    'invalid_account_ids' => $invalidAccounts
                ]);
                return;
            }

            foreach ($items as $item) {
                $quantity = $item['quantity'] ?? 1;
                $subtotal += $quantity * $item['unit_price'];
            }

            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            $fields[] = "subtotal = ?"; $params[] = $subtotal;
            $fields[] = "tax_rate = ?"; $params[] = $taxRate;
            $fields[] = "tax_amount = ?"; $params[] = $taxAmount;
            $fields[] = "total_amount = ?"; $params[] = $totalAmount;
            $fields[] = "balance = ?"; $params[] = $totalAmount - $existing[0]['paid_amount'];

            // Update invoice items
            $db->execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
            foreach ($items as $item) {
                $lineTotal = ($item['quantity'] ?? 1) * $item['unit_price'];
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
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No valid fields to update'
            ]);
            return;
        }

        $params[] = $invoiceId;
        $sql = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ?";

        $affected = $db->execute($sql, $params);
        $db->commit();

        // Log the action
        Logger::getInstance()->logUserAction(
            'Invoice updated via API',
            'invoices',
            $invoiceId,
            $existing[0],
            array_merge($data, ['api_client_id' => $client['id']])
        );

        echo json_encode([
            'success' => $affected > 0
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Post journal entry for invoice
 */
function postInvoiceJournalEntry($db, $invoiceId, $subtotal, $taxAmount) {
    require_once __DIR__ . '/../../includes/journal_entry_number.php';
    $arAccountIdRow = $db->select("SELECT id FROM chart_of_accounts WHERE account_code = '1002' LIMIT 1");
    $arAccountId = $arAccountIdRow[0]['id'] ?? null;
    $entryNumber = generateJournalEntryNumber($db, $arAccountId, date('Y-m-d'));

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, posted_by, posted_at)
         VALUES (?, CURDATE(), ?, ?, ?, 'posted', ?, ?, NOW())",
        [
            $entryNumber,
            "Invoice $invoiceId - Customer billing",
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

    // Credit: Sales tax payable (if applicable)
    if ($taxAmount > 0) {
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, credit, description)
             VALUES (?, (SELECT id FROM chart_of_accounts WHERE account_code = '2108'), ?, 'Sales Tax Payable')",
            [$entryId, $taxAmount]
        );
    }
}
?>

