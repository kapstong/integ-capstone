<?php
/**
 * ATIERA Financial Management System - Disbursements API Endpoint
 * Handles disbursement creation via API requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    // Only handle POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Check if user is authenticated
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    // Get POST data
    $disbursement_date = $_POST['disbursement_date'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? '';
    $reference_number = $_POST['reference_number'] ?? '';
    $payee = $_POST['payee'] ?? '';
    $purpose = $_POST['purpose'] ?? $_POST['description'] ?? '';
    $notes = $_POST['notes'] ?? '';

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

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System Error: ' . $e->getMessage()
    ]);
}
?>
