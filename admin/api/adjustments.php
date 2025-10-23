<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $logger);
            break;
        case 'POST':
            handlePost($db, $logger);
            break;
        case 'PUT':
            handlePut($db, $logger);
            break;
        case 'DELETE':
            handleDelete($db, $logger);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->log("API Error in adjustments.php: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null; // 'payable' or 'receivable'
        $vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
        $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

        if ($id) {
            // Get single adjustment
            $stmt = $db->prepare("
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
            ");
            $stmt->execute([$id]);
            $adjustment = $stmt->fetch(PDO::FETCH_ASSOC);

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

            $stmt = $db->prepare("
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
            ");
            $stmt->execute($params);
            $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($adjustments);
        }
    } catch (Exception $e) {
        $logger->log("Error in handleGet adjustments: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch adjustments']);
    }
}

function handlePost($db, $logger) {
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
        $isPayable = isset($data['vendor_id']) && !empty($data['vendor_id']);
        $isReceivable = isset($data['customer_id']) && !empty($data['customer_id']);

        if (!$isPayable && !$isReceivable) {
            http_response_code(400);
            echo json_encode(['error' => 'Either vendor_id or customer_id must be provided']);
            return;
        }

        if ($isPayable && $isReceivable) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot specify both vendor_id and customer_id']);
            return;
        }

        // Generate adjustment number
        $prefix = $isPayable ? 'ADJ-P-' : 'ADJ-R-';
        $stmt = $db->query("SELECT COUNT(*) as count FROM adjustments WHERE adjustment_type = '{$data['adjustment_type']}' AND vendor_id " . ($isPayable ? 'IS NOT NULL' : 'IS NULL') . " AND customer_id " . ($isReceivable ? 'IS NOT NULL' : 'IS NULL'));
        $count = $stmt->fetch()['count'] + 1;
        $adjustmentNumber = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

        $db->beginTransaction();

        // Insert adjustment
        $stmt = $db->prepare("
            INSERT INTO adjustments (
                adjustment_number, adjustment_type, vendor_id, customer_id,
                bill_id, invoice_id, amount, reason, adjustment_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
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

        $adjustmentId = $db->lastInsertId();

        // Create journal entry for the adjustment
        createAdjustmentJournalEntry($db, $adjustmentId, $data, $isPayable);

        $db->commit();

        $logger->log("Adjustment created: $adjustmentNumber", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Adjustment created successfully',
            'adjustment_id' => $adjustmentId,
            'adjustment_number' => $adjustmentNumber
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handlePost adjustments: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create adjustment']);
    }
}

function handlePut($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Adjustment ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        $db->beginTransaction();

        // Update adjustment
        $stmt = $db->prepare("
            UPDATE adjustments SET
                adjustment_type = ?,
                amount = ?,
                reason = ?,
                adjustment_date = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['adjustment_type'],
            $data['amount'],
            $data['reason'],
            $data['adjustment_date'],
            $id
        ]);

        // Update journal entry
        updateAdjustmentJournalEntry($db, $id, $data);

        $db->commit();

        $logger->log("Adjustment updated: $id", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Adjustment updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handlePut adjustments: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update adjustment']);
    }
}

function handleDelete($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Adjustment ID is required']);
            return;
        }

        $db->beginTransaction();

        // Delete journal entries first
        $stmt = $db->prepare("DELETE FROM journal_entries WHERE reference_type = 'adjustment' AND reference_id = ?");
        $stmt->execute([$id]);

        // Delete adjustment
        $stmt = $db->prepare("DELETE FROM adjustments WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();

        $logger->log("Adjustment deleted: $id", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Adjustment deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handleDelete adjustments: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete adjustment']);
    }
}

function createAdjustmentJournalEntry($db, $adjustmentId, $data, $isPayable) {
    // Get next journal entry number
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(entry_number, '-', -1) AS UNSIGNED)) as max_num FROM journal_entries WHERE entry_number LIKE 'JE-%'");
    $maxNum = $stmt->fetch()['max_num'] ?? 0;
    $entryNumber = 'JE-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

    $entryDate = $data['adjustment_date'];
    $description = "Adjustment: " . ucfirst(str_replace('_', ' ', $data['adjustment_type'])) . " - " . $data['reason'];

    // Determine account codes based on adjustment type and payable/receivable
    if ($isPayable) {
        // Accounts Payable adjustments
        switch ($data['adjustment_type']) {
            case 'credit_memo':
                // Reduce accounts payable (credit) and possibly expense account (debit)
                $debitAccount = 'EXPENSE-ADJ'; // Would need proper expense account
                $creditAccount = 'ACCOUNTS-PAYABLE';
                break;
            case 'debit_memo':
                // Increase accounts payable (debit) and possibly expense account (credit)
                $debitAccount = 'ACCOUNTS-PAYABLE';
                $creditAccount = 'EXPENSE-ADJ';
                break;
            case 'write_off':
                // Write off bad debt
                $debitAccount = 'BAD-DEBT-EXPENSE';
                $creditAccount = 'ACCOUNTS-PAYABLE';
                break;
            case 'discount':
                // Discount received
                $debitAccount = 'ACCOUNTS-PAYABLE';
                $creditAccount = 'DISCOUNT-RECEIVED';
                break;
            default:
                $debitAccount = 'ADJUSTMENT';
                $creditAccount = 'ACCOUNTS-PAYABLE';
        }
    } else {
        // Accounts Receivable adjustments
        switch ($data['adjustment_type']) {
            case 'credit_memo':
                // Reduce accounts receivable (debit) and possibly revenue account (credit)
                $debitAccount = 'ACCOUNTS-RECEIVABLE';
                $creditAccount = 'SALES-DISCOUNT';
                break;
            case 'debit_memo':
                // Increase accounts receivable (credit) and possibly revenue account (debit)
                $debitAccount = 'SALES-DISCOUNT';
                $creditAccount = 'ACCOUNTS-RECEIVABLE';
                break;
            case 'write_off':
                // Write off bad debt
                $debitAccount = 'BAD-DEBT-EXPENSE';
                $creditAccount = 'ACCOUNTS-RECEIVABLE';
                break;
            case 'discount':
                // Discount given
                $debitAccount = 'SALES-DISCOUNT';
                $creditAccount = 'ACCOUNTS-RECEIVABLE';
                break;
            default:
                $debitAccount = 'ACCOUNTS-RECEIVABLE';
                $creditAccount = 'ADJUSTMENT';
        }
    }

    // Get account IDs
    $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ?");
    $stmt->execute([$debitAccount]);
    $debitAccountId = $stmt->fetch()['id'] ?? null;

    $stmt->execute([$creditAccount]);
    $creditAccountId = $stmt->fetch()['id'] ?? null;

    if (!$debitAccountId || !$creditAccountId) {
        throw new Exception("Required accounts not found in chart of accounts");
    }

    // Insert journal entry header
    $stmt = $db->prepare("
        INSERT INTO journal_entries (
            entry_number, entry_date, description, reference_type, reference_id,
            total_debit, total_credit, created_by
        ) VALUES (?, ?, ?, 'adjustment', ?, ?, ?, ?)
    ");

    $stmt->execute([
        $entryNumber,
        $entryDate,
        $description,
        $adjustmentId,
        $data['amount'],
        $data['amount'],
        $_SESSION['user']['id'] ?? 1
    ]);

    $entryId = $db->lastInsertId();

    // Insert debit line
    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (
            journal_entry_id, account_id, debit_amount, credit_amount, description
        ) VALUES (?, ?, ?, 0, ?)
    ");
    $stmt->execute([$entryId, $debitAccountId, $data['amount'], $description]);

    // Insert credit line
    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (
            journal_entry_id, account_id, debit_amount, credit_amount, description
        ) VALUES (?, ?, 0, ?, ?)
    ");
    $stmt->execute([$entryId, $creditAccountId, $data['amount'], $description]);
}

function updateAdjustmentJournalEntry($db, $adjustmentId, $data) {
    // Find existing journal entry
    $stmt = $db->prepare("SELECT id FROM journal_entries WHERE reference_type = 'adjustment' AND reference_id = ?");
    $stmt->execute([$adjustmentId]);
    $entryId = $stmt->fetch()['id'] ?? null;

    if ($entryId) {
        // Update journal entry amounts
        $stmt = $db->prepare("
            UPDATE journal_entries SET
                total_debit = ?,
                total_credit = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$data['amount'], $data['amount'], $entryId]);

        // Update journal entry lines
        $stmt = $db->prepare("
            UPDATE journal_entry_lines SET
                debit_amount = CASE WHEN debit_amount > 0 THEN ? ELSE 0 END,
                credit_amount = CASE WHEN credit_amount > 0 THEN ? ELSE 0 END
            WHERE journal_entry_id = ?
        ");
        $stmt->execute([$data['amount'], $data['amount'], $entryId]);
    }
}
?>
