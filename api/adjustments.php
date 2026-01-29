<?php
// Working Adjustments API with Database Persistence
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load required files: ' . $e->getMessage()]);
    exit(1);
}

$db = Database::getInstance();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Adjustments API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}



function handleGet($db) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null; // 'payable' or 'receivable'
        $vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
        $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

        if ($id) {
            // Get single adjustment
            $adjustment = $db->select("
                SELECT a.*,
                       CASE
                           WHEN a.adjustment_type IN ('credit_memo', 'debit_memo', 'write_off', 'discount') AND a.vendor_id IS NOT NULL THEN 'payable'
                           WHEN a.adjustment_type IN ('credit_memo', 'debit_memo', 'write_off', 'discount') AND a.customer_id IS NOT NULL THEN 'receivable'
                           ELSE 'unknown'
                       END as adjustment_category,
                       v.company_name as vendor_name,
                       c.company_name as customer_name,
                       b.bill_number,
                       i.invoice_number
                FROM adjustments a
                LEFT JOIN vendors v ON a.vendor_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN bills b ON a.bill_id = b.id
                LEFT JOIN invoices i ON a.invoice_id = i.id
                WHERE a.id = ?
            ", [$id]);
            $adjustment = $adjustment[0] ?? null;

            if (!$adjustment) {
                http_response_code(404);
                echo json_encode(['error' => 'Adjustment not found']);
                return;
            }

            echo json_encode($adjustment);
        } else {
            // Get all adjustments with optional filters
            $where = [];
            $params = [];

            if ($type === 'payable') {
                $where[] = "a.vendor_id IS NOT NULL";
            } elseif ($type === 'receivable') {
                $where[] = "a.customer_id IS NOT NULL";
            }

            if ($vendor_id) {
                $where[] = "a.vendor_id = ?";
                $params[] = $vendor_id;
            }

            if ($customer_id) {
                $where[] = "a.customer_id = ?";
                $params[] = $customer_id;
            }

            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

            $adjustments = $db->select("
                SELECT a.*,
                       CASE
                           WHEN a.adjustment_type IN ('credit_memo', 'debit_memo', 'write_off', 'discount') AND a.vendor_id IS NOT NULL THEN 'payable'
                           WHEN a.adjustment_type IN ('credit_memo', 'debit_memo', 'write_off', 'discount') AND a.customer_id IS NOT NULL THEN 'receivable'
                           ELSE 'unknown'
                       END as adjustment_category,
                       v.company_name as vendor_name,
                       c.company_name as customer_name,
                       b.bill_number,
                       i.invoice_number
                FROM adjustments a
                LEFT JOIN vendors v ON a.vendor_id = v.id
                LEFT JOIN customers c ON a.customer_id = c.id
                LEFT JOIN bills b ON a.bill_id = b.id
                LEFT JOIN invoices i ON a.invoice_id = i.id
                $whereClause
                ORDER BY a.adjustment_date DESC, a.created_at DESC
            ", $params);

            echo json_encode($adjustments);
        }
    } catch (Exception $e) {
        error_log("Error in handleGet adjustments: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch adjustments']);
    }
}

function handlePost($db) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        // Validate required fields
        $required = ['adjustment_type', 'amount', 'reason', 'adjustment_date'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                return;
            }
        }

        // Determine if this is payable or receivable adjustment
        $vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        $customerId = isset($data['customer_id']) ? (int)$data['customer_id'] : null;

        $isPayable = $vendorId > 0;
        $isReceivable = $customerId > 0;

        // Check for invalid combinations
        if ($isPayable && $isReceivable) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot specify both vendor_id and customer_id']);
            return;
        }

        if (!$isPayable && !$isReceivable) {
            http_response_code(400);
            echo json_encode(['error' => 'Either vendor_id or customer_id must be provided with a valid value']);
            return;
        }

        // Generate adjustment number
        $prefix = $isPayable ? 'ADJ-P-' : 'ADJ-R-';
        $countData = $db->select("SELECT COUNT(*) as count FROM adjustments WHERE adjustment_type = ? AND vendor_id " . ($isPayable ? 'IS NOT NULL' : 'IS NULL') . " AND customer_id " . ($isReceivable ? 'IS NOT NULL' : 'IS NULL'), [$data['adjustment_type']]);
        $count = $countData[0]['count'] + 1;
        $adjustmentNumber = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

        $db->beginTransaction();

        // Insert adjustment
        $adjustmentId = $db->insert("
            INSERT INTO adjustments (
                adjustment_number, adjustment_type, vendor_id, customer_id,
                bill_id, invoice_id, amount, reason, adjustment_date, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $adjustmentNumber,
            $data['adjustment_type'],
            $isPayable ? $data['vendor_id'] : null,
            $isReceivable ? $data['customer_id'] : null,
            $isPayable ? ($data['bill_id'] ?? null) : null,
            $isReceivable ? ($data['invoice_id'] ?? null) : null,
            $data['amount'],
            $data['reason'],
            $data['adjustment_date'],
            $_SESSION['user']['id'] ?? 1
        ]);

        applyAdjustmentToSource($db, $data, $isPayable, false);

        // Create journal entry for the adjustment
        createAdjustmentJournalEntry($db, $adjustmentId, $data, $isPayable);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Adjustment created successfully',
            'adjustment_id' => $adjustmentId,
            'adjustment_number' => $adjustmentNumber
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in handlePost adjustments: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create adjustment']);
    }
}

function handlePut($db) {
    error_log("PUT request received for adjustment ID: " . ($_GET['id'] ?? 'none'));
    error_log("Database connection status: " . ($db ? 'connected' : 'null'));

    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            error_log("No adjustment ID provided");
            http_response_code(400);
            echo json_encode(['error' => 'Adjustment ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Received data: " . json_encode($data));

        if (!$data) {
            error_log("Invalid JSON data received");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        if (!$db) {
            error_log("Database connection is null");
            http_response_code(500);
            echo json_encode(['error' => 'Database connection unavailable']);
            return;
        }

        $db->beginTransaction();

        // Get existing adjustment to reverse effects
        $existing = $db->select("SELECT * FROM adjustments WHERE id = ?", [$id]);
        if (empty($existing)) {
            throw new Exception('Adjustment not found');
        }
        $existing = $existing[0];
        $existingIsPayable = !empty($existing['vendor_id']);

        // Reverse previous impact
        applyAdjustmentToSource($db, $existing, $existingIsPayable, true);

        // Update adjustment
        $affected = $db->execute("
            UPDATE adjustments SET
                adjustment_type = ?,
                amount = ?,
                reason = ?,
                adjustment_date = ?
            WHERE id = ?
        ", [
            $data['adjustment_type'],
            $data['amount'],
            $data['reason'],
            $data['adjustment_date'],
            $id
        ]);

        if ($affected < 1) {
            throw new Exception('Failed to update adjustment');
        }

        $isPayable = !empty($existing['vendor_id']);
        $data['vendor_id'] = $existing['vendor_id'];
        $data['customer_id'] = $existing['customer_id'];
        $data['bill_id'] = $existing['bill_id'];
        $data['invoice_id'] = $existing['invoice_id'];

        applyAdjustmentToSource($db, $data, $isPayable, false);

        // Update journal entry
        updateAdjustmentJournalEntry($db, $id, $data, $isPayable);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Adjustment updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in handlePut adjustments: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update adjustment: ' . $e->getMessage()]);
    }
}

function handleDelete($db) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Adjustment ID is required']);
            return;
        }

        $db->beginTransaction();

        $existing = $db->select("SELECT * FROM adjustments WHERE id = ?", [$id]);
        if (empty($existing)) {
            throw new Exception('Adjustment not found or already deleted');
        }
        $existing = $existing[0];
        $isPayable = !empty($existing['vendor_id']);

        // Reverse adjustment effects
        applyAdjustmentToSource($db, $existing, $isPayable, true);

        // Delete journal entries first
        $db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id IN (SELECT id FROM journal_entries WHERE reference = ?)", ['ADJ-' . $id]);
        $db->execute("DELETE FROM journal_entries WHERE reference = ?", ['ADJ-' . $id]);

        // Delete adjustment
        $affected = $db->execute("DELETE FROM adjustments WHERE id = ?", [$id]);
        if ($affected < 1) {
            throw new Exception('Adjustment not found or already deleted');
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Adjustment deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in handleDelete adjustments: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete adjustment']);
    }
}

function createAdjustmentJournalEntry($db, $adjustmentId, $data, $isPayable) {
    $amount = floatval($data['amount']);
    if ($amount <= 0) {
        return;
    }

    $entryDate = $data['adjustment_date'] ?? date('Y-m-d');
    $description = "Adjustment {$data['adjustment_type']} #" . $adjustmentId;

    require_once __DIR__ . '/../includes/journal_entry_number.php';
    $entryNumber = generateJournalEntryNumber(
        $db,
        $isPayable ? getAccountIdByCode($db, '2001') : getAccountIdByCode($db, '1002'),
        $entryDate
    );

    $db->execute(
        "INSERT INTO journal_entries (entry_number, entry_date, description, reference, total_debit, total_credit, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 'posted', ?)",
        [
            $entryNumber,
            $entryDate,
            $description,
            'ADJ-' . $adjustmentId,
            $amount,
            $amount,
            $_SESSION['user']['id'] ?? 1
        ]
    );

    $entryId = $db->select("SELECT id FROM journal_entries WHERE reference = ?", ['ADJ-' . $adjustmentId])[0]['id'] ?? null;
    if (!$entryId) {
        return;
    }

    $lines = buildAdjustmentJournalLines($db, $data, $isPayable);
    foreach ($lines as $line) {
        $db->insert(
            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description)
             VALUES (?, ?, ?, ?, ?)",
            [$entryId, $line['account_id'], $line['debit'], $line['credit'], $line['description']]
        );
    }
}

function updateAdjustmentJournalEntry($db, $adjustmentId, $data, $isPayable) {
    $db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id IN (SELECT id FROM journal_entries WHERE reference = ?)", ['ADJ-' . $adjustmentId]);
    $db->execute("DELETE FROM journal_entries WHERE reference = ?", ['ADJ-' . $adjustmentId]);
    createAdjustmentJournalEntry($db, $adjustmentId, $data, $isPayable);
}

function getAccountIdByCode($db, $accountCode) {
    $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ? LIMIT 1");
    $stmt->execute([$accountCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['id'] ?? null;
}

function getExpenseLinesFromItems($db, $table, $parentColumn, $parentId, $amount, $fallbackAccountId) {
    $items = $db->select("SELECT account_id, line_total FROM {$table} WHERE {$parentColumn} = ?", [$parentId]);
    if (empty($items)) {
        return [['account_id' => $fallbackAccountId, 'amount' => $amount]];
    }

    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += floatval($item['line_total']);
    }

    if ($subtotal <= 0) {
        return [['account_id' => $fallbackAccountId, 'amount' => $amount]];
    }

    $factor = $amount / $subtotal;
    $lines = [];
    $runningTotal = 0;
    $count = count($items);

    foreach ($items as $index => $item) {
        $lineAmount = round(floatval($item['line_total']) * $factor, 2);
        if ($index === $count - 1) {
            $lineAmount = $amount - $runningTotal;
        }
        $runningTotal += $lineAmount;
        $lines[] = [
            'account_id' => $item['account_id'] ?: $fallbackAccountId,
            'amount' => $lineAmount
        ];
    }

    return $lines;
}

function buildAdjustmentJournalLines($db, $data, $isPayable) {
    $amount = floatval($data['amount']);
    $type = $data['adjustment_type'];
    $lines = [];

    $arAccountId = getAccountIdByCode($db, '1002') ?? 1;
    $apAccountId = getAccountIdByCode($db, '2001') ?? 1;
    $badDebtAccountId = getAccountIdByCode($db, '5409') ?? 1;
    $revenueFallbackId = getAccountIdByCode($db, '4001') ?? 1;
    $incomeFallbackId = getAccountIdByCode($db, '4309') ?? $revenueFallbackId;
    $expenseFallbackId = getAccountIdByCode($db, '5403') ?? 1;

    if ($isPayable) {
        $billId = $data['bill_id'] ?? null;
        $expenseLines = $billId ? getExpenseLinesFromItems($db, 'bill_items', 'bill_id', $billId, $amount, $expenseFallbackId)
            : [['account_id' => $expenseFallbackId, 'amount' => $amount]];

        switch ($type) {
            case 'debit_memo':
                foreach ($expenseLines as $line) {
                    $lines[] = [
                        'account_id' => $line['account_id'],
                        'debit' => $line['amount'],
                        'credit' => 0,
                        'description' => 'Payable Debit Memo'
                    ];
                }
                $lines[] = [
                    'account_id' => $apAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Accounts Payable - Debit Memo'
                ];
                break;
            case 'credit_memo':
            case 'discount':
                $lines[] = [
                    'account_id' => $apAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Accounts Payable - Credit Memo'
                ];
                foreach ($expenseLines as $line) {
                    $lines[] = [
                        'account_id' => $line['account_id'],
                        'debit' => 0,
                        'credit' => $line['amount'],
                        'description' => 'Expense Reversal'
                    ];
                }
                break;
            case 'write_off':
                $lines[] = [
                    'account_id' => $apAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Accounts Payable - Write Off'
                ];
                $lines[] = [
                    'account_id' => $incomeFallbackId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Payable Write Off Income'
                ];
                break;
        }
    } else {
        $invoiceId = $data['invoice_id'] ?? null;
        $revenueLines = $invoiceId ? getExpenseLinesFromItems($db, 'invoice_items', 'invoice_id', $invoiceId, $amount, $revenueFallbackId)
            : [['account_id' => $revenueFallbackId, 'amount' => $amount]];

        switch ($type) {
            case 'debit_memo':
                $lines[] = [
                    'account_id' => $arAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Accounts Receivable - Debit Memo'
                ];
                foreach ($revenueLines as $line) {
                    $lines[] = [
                        'account_id' => $line['account_id'],
                        'debit' => 0,
                        'credit' => $line['amount'],
                        'description' => 'Revenue - Debit Memo'
                    ];
                }
                break;
            case 'credit_memo':
                foreach ($revenueLines as $line) {
                    $lines[] = [
                        'account_id' => $line['account_id'],
                        'debit' => $line['amount'],
                        'credit' => 0,
                        'description' => 'Revenue Reversal'
                    ];
                }
                $lines[] = [
                    'account_id' => $arAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Accounts Receivable - Credit Memo'
                ];
                break;
            case 'discount':
            case 'write_off':
                $lines[] = [
                    'account_id' => $badDebtAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Discount/Write Off Expense'
                ];
                $lines[] = [
                    'account_id' => $arAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Accounts Receivable - Adjustment'
                ];
                break;
        }
    }

    return $lines;
}

function applyAdjustmentToSource($db, $data, $isPayable, $reverse) {
    $amount = floatval($data['amount']);
    if ($amount <= 0) {
        return;
    }
    $direction = $reverse ? -1 : 1;

    if ($isPayable) {
        $billId = $data['bill_id'] ?? null;
        if (!$billId) {
            return;
        }

        $delta = 0;
        if ($data['adjustment_type'] === 'debit_memo') {
            $delta = $amount;
        } else {
            $delta = -$amount;
        }
        $delta *= $direction;

        $db->execute("UPDATE bills SET balance = balance + ? WHERE id = ?", [$delta, $billId]);
        $bill = $db->select("SELECT balance, status FROM bills WHERE id = ?", [$billId]);
        if (!empty($bill)) {
            $balance = floatval($bill[0]['balance']);
            $status = $balance <= 0 ? 'paid' : ($bill[0]['status'] === 'paid' ? 'partial' : $bill[0]['status']);
            $db->execute("UPDATE bills SET status = ? WHERE id = ?", [$status, $billId]);
        }
    } else {
        $invoiceId = $data['invoice_id'] ?? null;
        if (!$invoiceId) {
            return;
        }

        $delta = 0;
        if ($data['adjustment_type'] === 'debit_memo') {
            $delta = $amount;
        } else {
            $delta = -$amount;
        }
        $delta *= $direction;

        $db->execute("UPDATE invoices SET balance = balance + ? WHERE id = ?", [$delta, $invoiceId]);
        $invoice = $db->select("SELECT balance, status FROM invoices WHERE id = ?", [$invoiceId]);
        if (!empty($invoice)) {
            $balance = floatval($invoice[0]['balance']);
            $status = $balance <= 0 ? 'paid' : ($invoice[0]['status'] === 'paid' ? 'sent' : $invoice[0]['status']);
            $db->execute("UPDATE invoices SET status = ? WHERE id = ?", [$status, $invoiceId]);
        }
    }
}
?>

