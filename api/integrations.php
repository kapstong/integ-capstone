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

    // Connect to REAL HR3 API
    if ($action === 'execute' && $integrationName === 'hr3' && $actionName === 'getApprovedClaims') {
        // Get HR3 API configuration
        $configFile = '../config/integrations/hr3.json';
        $config = ['api_url' => 'https://hr3.atierahotelandrestaurant.com/api/claimsApi.php'];

        if (file_exists($configFile) && ($configData = json_decode(file_get_contents($configFile), true))) {
            $config = array_merge($config, $configData);
        }

        // Make HTTP request to HR3 API
        $ch = curl_init($config['api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS - remove in production
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For HTTPS - remove in production

        // Add headers if authentication is configured
        $headers = ['Content-Type: application/json'];
        if (!empty($config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to connect to HR3 API: ' . $curlError
            ]);
            exit;
        }

        if ($httpCode === 200) {
            $claims = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON response from HR3 API: ' . json_last_error_msg()
                ]);
                exit;
            }

            // Filter for only "Approved" status claims with amounts > 0
            $approvedClaims = [];
            if (is_array($claims)) {
                foreach ($claims as $claim) {
                    $amount = isset($claim['total_amount']) ? floatval($claim['total_amount']) : 0;
                    $status = $claim['status'] ?? '';

                    // Only include approved claims with positive amounts and not cancelled/rejected
                    if ($status === 'Approved' && $amount > 0 && !isset($claim['cancelled_at'])) {
                        $approvedClaims[] = [
                            'id' => $claim['claim_id'],
                            'claim_id' => $claim['claim_id'],
                            'employee_name' => $claim['employee_name'] ?? 'Unknown',
                            'employee_id' => $claim['employee_id'] ?? '',
                            'amount' => $amount,
                            'currency_code' => $claim['currency_code'] ?? 'PHP',
                            'description' => $claim['remarks'] ?? '',
                            'status' => $status,
                            'claim_date' => $claim['created_at'] ?? $claim['updated_at'] ?? '',
                            'reference_id' => $claim['reference_id'] ?? ''
                        ];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'result' => $approvedClaims,
                'message' => 'HR3 approved claims loaded successfully (' . count($approvedClaims) . ' claims found)'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'HR3 API returned HTTP ' . $httpCode . ': ' . $response
            ]);
        }
        exit;
    }

    // Update claim status back to HR3 (2-way sync)
    if ($action === 'execute' && $integrationName === 'hr3' && $actionName === 'updateClaimStatus') {
        $claimId = $_POST['claim_id'] ?? '';
        $newStatus = $_POST['status'] ?? 'Paid';

        if (!$claimId) {
            echo json_encode([
                'success' => false,
                'error' => 'claim_id is required'
            ]);
            exit;
        }

        // Get HR3 API configuration
        $configFile = '../config/integrations/hr3.json';
        $config = ['api_url' => 'https://hr3.atierahotelandrestaurant.com/api/claimsApi.php'];

        if (file_exists($configFile) && ($configData = json_decode(file_get_contents($configFile), true))) {
            $config = array_merge($config, $configData);
        }

        $headers = ['Content-Type: application/json'];
        if (!empty($config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }

        $success = false;
        $finalResponse = '';
        $finalHttpCode = 0;

        // Try multiple update patterns (most common API designs)

        // Pattern 1: POST to /claimsApi.php with claim_id parameter
        $apiUrls = [
            $config['api_url'],                                         // Main endpoint
            $config['api_url'] . '?claim_id=' . urlencode($claimId),   // Query param
            str_replace('/api/claimsApi.php', '/api/claims/' . urlencode($claimId), $config['api_url']), // REST style
        ];

        $methods = ['PATCH', 'PUT', 'POST']; // Try PATCH first (most RESTful for updates)

        foreach ($apiUrls as $apiUrl) {
            foreach ($methods as $method) {
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                // Send raw JSON payload
                $updateData = json_encode([
                    'claim_id' => $claimId,
                    'status' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'action' => 'update_status',
                    'processed_by' => 'financial_system'
                ]);

                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
                } elseif ($method === 'PUT') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
                } elseif ($method === 'PATCH') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
                }

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                // Log attempt for debugging
                error_log("HR3 Update Attempt - Method: {$method}, URL: {$apiUrl}, Code: {$httpCode}, Error: {$curlError}");

                // Check if this attempt succeeded (200 OK or 204 No Content)
                if (!$curlError && ($httpCode === 200 || $httpCode === 204 || $httpCode === 201)) {
                    $success = true;
                    $finalResponse = $response;
                    $finalHttpCode = $httpCode;
                    break 2; // Exit both loops
                }

                // Small delay between attempts
                usleep(100000); // 0.1 second
            }
        }

        // Return results
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'HR3 claim status successfully updated to "' . $newStatus . '"',
                'claim_id' => $claimId,
                'new_status' => $newStatus,
                'http_code' => $finalHttpCode,
                'response' => substr($finalResponse, 0, 200) // First 200 chars of response
            ]);
        } else {
            // HR3 update failed, but disbursement creation should still proceed
            echo json_encode([
                'success' => false,
                'error' => 'HR3 API update failed - claim status not synchronized. Disbursement will still be created locally.',
                'claim_id' => $claimId,
                'attempted_status' => $newStatus,
                'note' => 'Financial disbursement created successfully despite HR3 sync failure.'
            ]);
        }
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
