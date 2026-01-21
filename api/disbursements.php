<?php
// Disbursements API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start output buffering to catch any unwanted output
ob_start();

// Suppress any HTML output from errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set up error handler to catch and output errors as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $errstr]);
    ob_end_flush();
    exit(1);
}, E_ALL);

// Set up exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception: ' . $exception->getMessage()]);
    ob_end_flush();
    exit(1);
});

try {
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load required files: ' . $e->getMessage()]);
    ob_end_flush();
    exit(1);
}

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Session not found']);
    ob_end_flush();
    exit;
}

$db = Database::getInstance()->getConnection();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

function logAuditEntry($db, $action, $table, $recordId, $oldValues = null, $newValues = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_log (
                user_id, action, table_name, record_id, old_values, new_values,
                ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $_SESSION['user']['id'] ?? null,
            $action,
            $table,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log insert failed: " . $e->getMessage());
    }
}

function getActiveBudgetItem($db, $accountId) {
    $stmt = $db->prepare("
        SELECT bi.id, bi.budgeted_amount, bi.actual_amount
        FROM budget_items bi
        INNER JOIN budgets b ON bi.budget_id = b.id
        WHERE bi.account_id = ? AND b.status = 'active' AND YEAR(b.budget_year) = YEAR(CURDATE())
        LIMIT 1
    ");
    $stmt->execute([$accountId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function enforceBudgetLimit($db, $accountId, $amount) {
    $budget = getActiveBudgetItem($db, $accountId);
    if (!$budget) {
        return;
    }

    $remaining = floatval($budget['budgeted_amount']) - floatval($budget['actual_amount']);
    if ($amount > $remaining) {
        throw new Exception('Disbursement exceeds available budget for the selected account.');
    }
}

function applyBudgetActual($db, $accountId, $amount) {
    $budget = getActiveBudgetItem($db, $accountId);
    if (!$budget) {
        return;
    }

    $stmt = $db->prepare("
        UPDATE budget_items
        SET actual_amount = actual_amount + ?,
            variance = budgeted_amount - (actual_amount + ?),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$amount, $amount, $budget['id']]);
}

function processPayment($db, $data) {
    try {
        // Validate required fields
        $required = ['payee', 'amount', 'payment_method', 'payment_date'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        if (empty($data['bill_id']) && !empty($data['account_id'])) {
            enforceBudgetLimit($db, $data['account_id'], $data['amount']);
        }

        $db->beginTransaction();

        // Generate disbursement number
        $stmt = $db->query("SELECT COUNT(*) as count FROM disbursements WHERE DATE(disbursement_date) = CURDATE()");
        $count = $stmt->fetch()['count'] + 1;
        $disbursementNumber = 'DISB-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        // Insert disbursement
        $stmt = $db->prepare("
            INSERT INTO disbursements (
                disbursement_number, disbursement_date, payee, amount,
                payment_method, reference_number, purpose, account_id,
                approved_by, recorded_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $disbursementNumber,
            $data['payment_date'],
            $data['payee'],
            $data['amount'],
            $data['payment_method'],
            $data['reference_number'] ?? null,
            $data['description'] ?? 'Payment processed',
            $data['account_id'] ?? 1, // Default account ID (Cash)
            $_SESSION['user']['id'] ?? 1,
            $_SESSION['user']['id'] ?? 1,
            'paid' // Status for processed payment
        ]);

        $disbursementId = $db->lastInsertId();

        // Create journal entry for the disbursement
        createDisbursementJournalEntry($db, $disbursementId, $data);

        logAuditEntry($db, 'created', 'disbursements', $disbursementId, null, [
            'disbursement_number' => $disbursementNumber,
            'disbursement_date' => $data['payment_date'],
            'payee' => $data['payee'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? null
        ]);

        $db->commit();

        return [
            'success' => true,
            'message' => 'Payment processed successfully',
            'disbursement_id' => $disbursementId,
            'disbursement_number' => $disbursementNumber
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Voucher and Documentation API functions
function createVoucher($db, $data) {
    try {
        // Validate required fields
        $required = ['disbursement_id', 'voucher_type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        if (empty($data['bill_id']) && !empty($data['account_id'])) {
            enforceBudgetLimit($db, $data['account_id'], $data['amount']);
        }

        $db->beginTransaction();

        // Generate voucher number
        $stmt = $db->query("SELECT COUNT(*) as count FROM uploaded_files WHERE category = 'vouchers'");
        $count = $stmt->fetch()['count'] + 1;
        $voucherNumber = strtoupper($data['voucher_type']) . '-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Create voucher record in uploaded_files or a separate vouchers table
        // For now, we'll store voucher metadata in uploaded_files with category 'vouchers'

        $stmt = $db->prepare("
            INSERT INTO uploaded_files (
                original_name, file_name, file_path, file_size, mime_type, category,
                reference_id, reference_type, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, 'vouchers', ?, 'disbursement', ?)
        ");

        $originalName = $data['file_name'] ?? 'voucher_' . $voucherNumber . '.pdf';
        $fileName = $voucherNumber . '.pdf';
        $filePath = 'uploads/vouchers/' . $fileName;

        $stmt->execute([
            $originalName,
            $fileName,
            $filePath,
            $data['file_size'] ?? 0,
            $data['mime_type'] ?? 'application/pdf',
            $data['disbursement_id'],
            $_SESSION['user']['id'] ?? 1
        ]);

        $voucherId = $db->lastInsertId();

        // Update disbursement with voucher reference
        $stmt = $db->prepare("
            UPDATE disbursements SET
                voucher_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$voucherId, $data['disbursement_id']]);

        $db->commit();

        return [
            'success' => true,
            'message' => 'Voucher created successfully',
            'voucher_id' => $voucherId,
            'voucher_number' => $voucherNumber
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getVouchers($db, $disbursementId = null) {
    try {
        $where = $disbursementId ? "WHERE uf.reference_type = 'disbursement' AND uf.reference_id = ?" : "";
        $params = $disbursementId ? [$disbursementId] : [];

        $stmt = $db->prepare("
            SELECT uf.*,
                   d.disbursement_number,
                   u.full_name as uploaded_by_name
            FROM uploaded_files uf
            LEFT JOIN disbursements d ON uf.reference_id = d.id AND uf.reference_type = 'disbursement'
            LEFT JOIN users u ON uf.uploaded_by = u.id
            WHERE uf.category = 'vouchers' $where
            ORDER BY uf.uploaded_at DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Error fetching vouchers: " . $e->getMessage());
        return [];
    }
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'get_vouchers') {
                $disbursementId = isset($_GET['disbursement_id']) ? (int)$_GET['disbursement_id'] : null;
                $vouchers = getVouchers($db, $disbursementId);
                echo json_encode($vouchers);
                break;
            } else {
                handleGet($db);
                break;
            }
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['action'])) {
                switch ($input['action']) {
                    case 'process_payment':
                        $result = processPayment($db, $input);
                        break;
                    case 'create_voucher':
                        $result = createVoucher($db, $input);
                        break;
                    default:
                        $result = ['error' => 'Unknown action'];
                }
                echo json_encode($result);
            } else {
                handlePost($db);
            }
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
    }
} catch (Exception $e) {
    error_log("API Error in disbursements.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($db) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

        if ($id) {
            // Get single disbursement
            $stmt = $db->prepare("
                SELECT d.*,
                       u.username as recorded_by_name
                FROM disbursements d
                LEFT JOIN users u ON d.recorded_by = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$disbursement) {
                http_response_code(404);
                echo json_encode(['error' => 'Disbursement not found']);
                return;
            }

            echo json_encode($disbursement);
        } else {
            // Get all disbursements with optional filters
            $where = [];
            $params = [];

            if ($status) {
                $where[] = "d.status = ?";
                $params[] = $status;
            }

            if ($date_from) {
                $where[] = "d.disbursement_date >= ?";
                $params[] = $date_from;
            }

            if ($date_to) {
                $where[] = "d.disbursement_date <= ?";
                $params[] = $date_to;
            }

            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

            $stmt = $db->prepare("
                SELECT d.*,
                       u.username as recorded_by_name
                FROM disbursements d
                LEFT JOIN users u ON d.recorded_by = u.id
                $whereClause
                ORDER BY d.disbursement_date DESC, d.id DESC
            ");
            $stmt->execute($params);
            $disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($disbursements);
        }
    } catch (Exception $e) {
        error_log("Error in handleGet disbursements: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch disbursements']);
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
        $required = ['payee', 'amount', 'payment_method', 'disbursement_date'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                return;
            }
        }

        // Generate disbursement number
        $stmt = $db->query("SELECT COUNT(*) as count FROM disbursements WHERE DATE(disbursement_date) = CURDATE()");
        $count = $stmt->fetch()['count'] + 1;
        $disbursementNumber = 'DISB-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $db->beginTransaction();

        // Insert disbursement
        $stmt = $db->prepare("
            INSERT INTO disbursements (
                disbursement_number, disbursement_date, payee, amount,
                payment_method, reference_number, purpose, account_id,
                approved_by, recorded_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $disbursementNumber,
            $data['disbursement_date'],
            $data['payee'],
            $data['amount'],
            $data['payment_method'],
            $data['reference_number'] ?? null,
            $data['purpose'] ?? $data['notes'] ?? null,
            $data['account_id'] ?? 1, // Default account ID (Cash)
            $_SESSION['user']['id'] ?? 1, // approved_by
            $_SESSION['user']['id'] ?? 1,  // recorded_by
            'paid' // Status for created disbursement
        ]);

        $disbursementId = $db->lastInsertId();

        // Create journal entry for the disbursement
        createDisbursementJournalEntry($db, $disbursementId, $data);

        logAuditEntry($db, 'created', 'disbursements', $disbursementId, null, [
            'disbursement_number' => $disbursementNumber,
            'disbursement_date' => $data['disbursement_date'],
            'payee' => $data['payee'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? null
        ]);

        // Update bill status if bill_id is provided
        if (isset($data['bill_id']) && !empty($data['bill_id'])) {
            updateBillPaymentStatus($db, $data['bill_id'], $data['amount']);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Disbursement recorded successfully',
            'disbursement_id' => $disbursementId,
            'disbursement_number' => $disbursementNumber
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in handlePost disbursements: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create disbursement']);
    }
}

function handlePut($db) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Disbursement ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        $stmt = $db->prepare("SELECT * FROM disbursements WHERE id = ?");
        $stmt->execute([$id]);
        $oldRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        $db->beginTransaction();

        // Update disbursement
        $stmt = $db->prepare("
            UPDATE disbursements SET
                amount = ?,
                payment_method = ?,
                reference_number = ?,
                purpose = ?,
                disbursement_date = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['amount'],
            $data['payment_method'],
            $data['reference_number'] ?? null,
            $data['purpose'] ?? $data['notes'] ?? null,
            $data['disbursement_date'],
            $data['status'] ?? 'completed',
            $id
        ]);

        // Update journal entry
        updateDisbursementJournalEntry($db, $id, $data);

        logAuditEntry($db, 'updated', 'disbursements', $id, $oldRecord, [
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? null,
            'purpose' => $data['purpose'] ?? $data['notes'] ?? null,
            'disbursement_date' => $data['disbursement_date'],
            'status' => $data['status'] ?? 'paid'
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Disbursement updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in handlePut disbursements: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update disbursement']);
    }
}

function handleDelete($db) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Disbursement ID is required']);
            return;
        }

        $stmt = $db->prepare("SELECT * FROM disbursements WHERE id = ?");
        $stmt->execute([$id]);
        $oldRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        $db->beginTransaction();

        // Delete journal entry lines first to avoid foreign key constraint errors
        $stmt = $db->prepare("
            DELETE FROM journal_entry_lines
            WHERE journal_entry_id IN (
                SELECT id FROM journal_entries
                WHERE reference = ?
            )
        ");
        $stmt->execute(['DISB-' . $id]);

        // Delete journal entries first
        $stmt = $db->prepare("DELETE FROM journal_entries WHERE reference = ?");
        $stmt->execute(['DISB-' . $id]);

        // Delete disbursement
        $stmt = $db->prepare("DELETE FROM disbursements WHERE id = ?");
        $stmt->execute([$id]);

        logAuditEntry($db, 'deleted', 'disbursements', $id, $oldRecord, null);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Disbursement deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error in handleDelete disbursements: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete disbursement']);
    }
}

function createDisbursementJournalEntry($db, $disbursementId, $data) {
    // Get next journal entry number
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(entry_number, '-', -1) AS UNSIGNED)) as max_num FROM journal_entries WHERE entry_number LIKE 'JE-%'");
    $maxNum = $stmt->fetch()['max_num'] ?? 0;
    $entryNumber = 'JE-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

    $entryDate = $data['disbursement_date'] ?? $data['payment_date'];
    $description = "Disbursement: Payment to " . ($data['payee'] ?? 'vendor') . " - " . ($data['reference_number'] ?? 'N/A');

    // Use numeric account codes from chart_of_accounts
    $stmt = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ?");
    $stmt->execute(['1001']);
    $cashAccountId = $stmt->fetch()['id'] ?? 1;

    $isApPayment = !empty($data['bill_id']);
    if ($isApPayment) {
        $stmt->execute(['2001']);
        $debitAccountId = $stmt->fetch()['id'] ?? 2;
        $creditAccountId = $cashAccountId;
        $debitDescription = 'Accounts Payable - Disbursement';
        $creditDescription = 'Cash - Disbursement';
    } else {
        $debitAccountId = $data['account_id'] ?? null;

        if (!$debitAccountId) {
            $stmtAccount = $db->prepare("SELECT account_id FROM disbursements WHERE id = ?");
            $stmtAccount->execute([$disbursementId]);
            $debitAccountId = $stmtAccount->fetch()['account_id'] ?? null;
        }

        if (!$debitAccountId) {
            $fallbackExpense = $db->prepare("SELECT id FROM chart_of_accounts WHERE account_type = 'expense' ORDER BY account_code LIMIT 1");
            $fallbackExpense->execute();
            $debitAccountId = $fallbackExpense->fetch()['id'] ?? $cashAccountId;
        }

        $creditAccountId = $cashAccountId;
        $debitDescription = 'Expense - Disbursement';
        $creditDescription = 'Cash - Disbursement';
    }

    // Insert journal entry header
    $stmt = $db->prepare("
        INSERT INTO journal_entries (
            entry_number, entry_date, description, reference,
            total_debit, total_credit, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $entryNumber,
        $entryDate,
        $description,
        'DISB-' . $disbursementId,
        $data['amount'],
        $data['amount'],
        $_SESSION['user']['id'] ?? 1
    ]);

    $entryId = $db->lastInsertId();

    // Insert debit line (expense/AP)
    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (
            journal_entry_id, account_id, debit, credit, description
        ) VALUES (?, ?, ?, 0, ?)
    ");
    $stmt->execute([$entryId, $debitAccountId, $data['amount'], $debitDescription . ' - ' . $description]);
    if (!$isApPayment) {
        applyBudgetActual($db, $debitAccountId, $data['amount']);
    }

    // Insert credit line (cash)
    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (
            journal_entry_id, account_id, debit, credit, description
        ) VALUES (?, ?, 0, ?, ?)
    ");
    $stmt->execute([$entryId, $creditAccountId, $data['amount'], $creditDescription . ' - ' . $description]);
}

function updateDisbursementJournalEntry($db, $disbursementId, $data) {
    // Find existing journal entry by reference
    $stmt = $db->prepare("SELECT id FROM journal_entries WHERE reference = ?");
    $stmt->execute(['DISB-' . $disbursementId]);
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
                debit = CASE WHEN debit > 0 THEN ? ELSE 0 END,
                credit = CASE WHEN credit > 0 THEN ? ELSE 0 END
            WHERE journal_entry_id = ?
        ");
        $stmt->execute([$data['amount'], $data['amount'], $entryId]);
    }
}

function updateBillPaymentStatus($db, $billId, $paymentAmount) {
    // Get current bill balance
    $stmt = $db->prepare("SELECT balance FROM bills WHERE id = ?");
    $stmt->execute([$billId]);
    $currentBalance = $stmt->fetch()['balance'] ?? 0;

    // Calculate new balance
    $newBalance = max(0, $currentBalance - $paymentAmount);

    // Update bill balance and status
    $status = $newBalance <= 0 ? 'paid' : 'partial';
    $stmt = $db->prepare("
        UPDATE bills SET
            balance = ?,
            status = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$newBalance, $status, $billId]);
}

ob_end_flush();
?>
