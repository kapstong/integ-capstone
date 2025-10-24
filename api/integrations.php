<?php
/**
 * ATIERA Financial Management System - Integrations API Endpoint
 * Handles external API integrations and actions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Require only the essential files for basic testing
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $action = $_GET['action'] ?? '';
    $integrationName = $_GET['integration_name'] ?? '';
    $actionName = $_GET['action_name'] ?? '';

    // Simplified HR3 claims response for testing
    if ($action === 'execute' && $integrationName === 'hr3' && $actionName === 'getApprovedClaims') {
        // Return sample HR3 claims data
        $sampleClaims = [
            [
                'claim_id' => 'CLM001',
                'employee_name' => 'John Doe',
                'employee_id' => 'EMP001',
                'amount' => 1500.00,
                'currency_code' => 'PHP',
                'description' => 'Transportation reimbursement',
                'status' => 'Approved',
                'claim_date' => '2025-10-01',
                'type' => 'Transportation'
            ],
            [
                'claim_id' => 'CLM002',
                'employee_name' => 'Jane Smith',
                'employee_id' => 'EMP002',
                'amount' => 800.00,
                'currency_code' => 'PHP',
                'description' => 'Meals during business trip',
                'status' => 'Approved',
                'claim_date' => '2025-10-02',
                'type' => 'Meals'
            ]
        ];

        echo json_encode([
            'success' => true,
            'result' => $sampleClaims,
            'message' => 'Sample HR3 claims data loaded successfully'
        ]);
        exit;
    }

    // Default response for other actions
    if ($action === 'execute') {
        echo json_encode([
            'success' => false,
            'error' => 'Integration action not implemented in simplified endpoint'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Action not supported in simplified endpoint'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System Error: ' . $e->getMessage()
    ]);
}
