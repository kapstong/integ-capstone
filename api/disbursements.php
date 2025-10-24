<?php
/**
 * ATIERA Financial Management System - Disbursements API Endpoint
 * Handles disbursement CRUD operations via API requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../includes/auth.php';
require_once '../includes/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // Check if user is authenticated
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    // Handle different HTTP methods
    switch ($method) {
        case 'GET':
            // List disbursements or get single disbursement
            $id = $_GET['id'] ?? null;

            if ($id) {
                // Get single disbursement
                $stmt = $db->prepare("SELECT * FROM disbursements WHERE id = ?");
                $stmt->execute([$id]);
                $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$disbursement) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Disbursement not found']);
                    exit;
                }

                echo json_encode($disbursement);
            } else {
                // List all disbursements with filters
                $whereConditions = [];
                $params = [];

                if (!empty($_GET['status'])) {
                    $whereConditions[] = "status = ?";
                    $params[] = $_GET['status'];
                }

                if (!empty($_GET['date_from'])) {
                    $whereConditions[] = "disbursement_date >= ?";
                    $params[] = $_GET['date_from'];
                }

                if (!empty($_GET['date_to'])) {
                    $whereConditions[] = "disbursement_date <= ?";
                    $params[] = $_GET['date_to'];
                }

                $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";
                $orderClause = "ORDER BY disbursement_date DESC, created_at DESC";

                $stmt = $db->prepare("SELECT * FROM disbursements $whereClause $orderClause");
                $stmt->execute($params);
                $disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($disbursements);
            }
            break;

        case 'POST':
            // Create new disbursement
            $input = json_decode(file_get_contents('php://input'), true);

            // Handle both JSON and form-encoded data
            if (!$input) {
                $input = $_POST;
            }

            $disbursement_date = $input['disbursement_date'] ?? '';
            $amount = floatval($input['amount'] ?? 0);
            $payment_method = $input['payment_method'] ?? '';
            $reference_number = $input['reference_number'] ?? '';
            $payee = $input['payee'] ?? '';
            $purpose = $input['purpose'] ?? $input['description'] ?? '';
            $notes = $input['notes'] ?? '';

            // Validate required fields
            if (!$disbursement_date || !$amount || !$payment_method || !$payee || !$purpose) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Generate disbursement number (unique identifier)
            $disbursement_number = 'DISB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Get default account_id (Cash account)
            $account_id = 1; // Default to account '1001' (Cash)

            // Insert disbursement record
            $stmt = $db->prepare("
                INSERT INTO disbursements (
                    disbursement_number,
                    disbursement_date,
                    payee,
                    amount,
                    payment_method,
                    reference_number,
                    purpose,
                    account_id,
                    status,
                    recorded_by,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, NOW(), NOW())
            ");

            $result = $stmt->execute([
                $disbursement_number,
                $disbursement_date,
                $payee,
                $amount,
                $payment_method,
                $reference_number ?: null,
                $purpose,
                $account_id,
                $_SESSION['user']['id']
            ]);

            if ($result) {
                // Get the auto-generated ID
                $disbursement_id = $db->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'disbursement_id' => $disbursement_id,
                    'disbursement_number' => $disbursement_number,
                    'amount' => $amount,
                    'payee' => $payee,
                    'message' => 'Disbursement created successfully'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create disbursement']);
            }
            break;

        case 'PUT':
            // Update existing disbursement
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Disbursement ID required']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            // Build update query dynamically
            $updateFields = [];
            $params = [];

            $allowedFields = ['payee', 'amount', 'payment_method', 'reference_number', 'purpose', 'disbursement_date'];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            // Always update the updated_at timestamp
            $updateFields[] = "updated_at = NOW()";

            if (empty($updateFields)) {
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            // Check if disbursement exists
            $stmt = $db->prepare("SELECT id FROM disbursements WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Disbursement not found']);
                exit;
            }

            $params[] = $id; // Add ID for WHERE clause
            $stmt = $db->prepare("UPDATE disbursements SET " . implode(", ", $updateFields) . " WHERE id = ?");

            if ($stmt->execute($params)) {
                echo json_encode(['success' => true, 'message' => 'Disbursement updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update disbursement']);
            }
            break;

        case 'DELETE':
            // Delete disbursement
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Disbursement ID required']);
                exit;
            }

            // Check if disbursement exists and get its details for audit trail
            $stmt = $db->prepare("SELECT disbursement_number FROM disbursements WHERE id = ?");
            $stmt->execute([$id]);
            $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$disbursement) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Disbursement not found']);
                exit;
            }

            // Delete the disbursement
            $stmt = $db->prepare("DELETE FROM disbursements WHERE id = ?");
            if ($stmt->execute([$id])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Disbursement deleted successfully',
                    'disbursement_number' => $disbursement['disbursement_number']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete disbursement']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System Error: ' . $e->getMessage()
    ]);
}
?>
