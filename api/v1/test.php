<?php
/**
 * ATIERA External API Test Endpoint
 * Simple test endpoint to verify API functionality
 */

require_once '../../includes/database.php';
require_once '../../includes/api_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$apiAuth = APIAuth::getInstance();

// Authenticate API request
try {
    $client = $apiAuth->authenticate();
} catch (Exception $e) {
    // Authentication errors are handled in the authenticate method
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return API status and client information
    echo json_encode([
        'success' => true,
        'message' => 'ATIERA External API is working correctly',
        'timestamp' => date('c'),
        'client' => [
            'id' => $client['id'],
            'name' => $client['name'],
            'created_at' => $client['created_at']
        ],
        'endpoints' => [
            'GET /api/v1/test' => 'API status check',
            'GET /api/v1/invoices' => 'List/get invoices',
            'POST /api/v1/invoices' => 'Create invoice',
            'PUT /api/v1/invoices' => 'Update invoice',
            'GET /api/v1/customers' => 'List/get customers',
            'GET /api/v1/vendors' => 'List/get vendors'
        ],
        'documentation' => '/admin/api_docs.php'
    ]);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET to test API connectivity.'
    ]);
}
?>
