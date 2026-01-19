<?php
// Working Adjustments API with Database Persistence
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

        // Update journal entry - DISABLED UNTIL FIXED
        // updateAdjustmentJournalEntry($db, $id, $data);

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

        // Delete journal entries first
        $db->execute("DELETE FROM journal_entries WHERE reference = ?", [$id]);

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
    // Journal entry creation disabled for now to prevent errors
    // This functionality can be implemented later when GL integration is properly set up
    error_log("Journal entry creation skipped for adjustment $adjustmentId");
}

function updateAdjustmentJournalEntry($db, $adjustmentId, $data) {
    // Journal entry updates disabled for now to prevent errors
    error_log("Journal entry update skipped for adjustment $adjustmentId");
}
?>

