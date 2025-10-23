<?php
// Simple test first
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'PHP is working in API directory']);
    exit;
}

// Normal API code starts here
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start output buffering to catch any unwanted output
ob_start();

// Suppress any HTML output from errors
ini_set('display_errors', 0);
error_reporting(0);

// Clean any buffered output before including files
ob_clean();

require_once '../../includes/auth.php';
require_once '../../includes/database.php';
// require_once '../../includes/logger.php'; // Temporarily disabled to avoid potential issues

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Session not found']);
    exit;
}
?>

<?php
$db = null;

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("Database connection error in disbursements API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please check your configuration.']);
    exit;
}

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
                ORDER BY d.disbursement_date DESC, d.created_at DESC
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
        $required = ['vendor_id', 'amount', 'payment_method', 'disbursement_date'];
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

        // Get vendor name for payee
        $stmt = $db->prepare("SELECT company_name FROM vendors WHERE id = ?");
        $stmt->execute([$data['vendor_id']]);
        $vendor = $stmt->fetch();
        $payeeName = $vendor ? $vendor['company_name'] : 'Unknown Vendor';

        // Insert disbursement
        $stmt = $db->prepare("
            INSERT INTO disbursements (
                disbursement_number, disbursement_date, payee, amount,
                payment_method, reference_number, purpose, account_id,
                approved_by, recorded_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
        ");

        $stmt->execute([
            $disbursementNumber,
            $data['disbursement_date'],
            $payeeName,
            $data['amount'],
            $data['payment_method'],
            $data['reference_number'] ?? null,
            $data['notes'] ?? null,
            1, // Default account ID (Cash on Hand)
            $_SESSION['user']['id'] ?? 1, // approved_by
            $_SESSION['user']['id'] ?? 1  // recorded_by
        ]);

        $disbursementId = $db->lastInsertId();

        // Create journal entry for the disbursement
        createDisbursementJournalEntry($db, $disbursementId, $data);

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

        $db->beginTransaction();

        // Update disbursement
        $stmt = $db->prepare("
            UPDATE disbursements SET
                amount = ?,
                payment_method = ?,
                reference_number = ?,
                notes = ?,
                disbursement_date = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['amount'],
            $data['payment_method'],
            $data['reference_number'] ?? null,
            $data['notes'] ?? null,
            $data['disbursement_date'],
            $data['status'] ?? 'completed',
            $id
        ]);

        // Update journal entry
        updateDisbursementJournalEntry($db, $id, $data);

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

        $db->beginTransaction();

        // Delete journal entries first
        $stmt = $db->prepare("DELETE FROM journal_entries WHERE reference_type = 'disbursement' AND reference_id = ?");
        $stmt->execute([$id]);

        // Delete disbursement
        $stmt = $db->prepare("DELETE FROM disbursements WHERE id = ?");
        $stmt->execute([$id]);

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

    $entryDate = $data['disbursement_date'];
    $description = "Disbursement: Payment to vendor - " . ($data['reference_number'] ?? 'N/A');

    // Determine account codes based on payment method
    switch ($data['payment_method']) {
        case 'cash':
            $debitAccount = 'CASH-ON-HAND';
            break;
        case 'check':
            $debitAccount = 'CASH-IN-BANK';
            break;
        case 'bank_transfer':
            $debitAccount = 'CASH-IN-BANK';
            break;
        default:
            $debitAccount = 'CASH-IN-BANK';
    }

    $creditAccount = 'ACCOUNTS-PAYABLE'; // Vendor payment reduces accounts payable

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
        ) VALUES (?, ?, ?, 'disbursement', ?, ?, ?, ?)
    ");

    $stmt->execute([
        $entryNumber,
        $entryDate,
        $description,
        $disbursementId,
        $data['amount'],
        $data['amount'],
        $_SESSION['user']['id'] ?? 1
    ]);

    $entryId = $db->lastInsertId();

    // Insert debit line (cash/bank account)
    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (
            journal_entry_id, account_id, debit_amount, credit_amount, description
        ) VALUES (?, ?, ?, 0, ?)
    ");
    $stmt->execute([$entryId, $debitAccountId, $data['amount'], $description]);

    // Insert credit line (accounts payable)
    $stmt = $db->prepare("
        INSERT INTO journal_entry_lines (
            journal_entry_id, account_id, debit_amount, credit_amount, description
        ) VALUES (?, ?, 0, ?, ?)
    ");
    $stmt->execute([$entryId, $creditAccountId, $data['amount'], $description]);
}

function updateDisbursementJournalEntry($db, $disbursementId, $data) {
    // Find existing journal entry
    $stmt = $db->prepare("SELECT id FROM journal_entries WHERE reference_type = 'disbursement' AND reference_id = ?");
    $stmt->execute([$disbursementId]);
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
?>
