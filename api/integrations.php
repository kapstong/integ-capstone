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

        // Try POST method first (most likely API pattern)
        $postUrl = $config['api_url'];
        $postData = [
            'claim_id' => $claimId,
            'status' => $newStatus,
            'action' => 'update_status'
        ];

        $ch = curl_init($postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = ['Content-Type: application/json'];
        if (!empty($config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // If POST fails, try PUT method
        $putSuccess = false;
        if ($httpCode !== 200) {
            $putUrl = $config['api_url'] . '?claim_id=' . urlencode($claimId);
            $putData = ['status' => $newStatus];

            $ch = curl_init($putUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($putData));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $putResponse = curl_exec($ch);
            $putHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($putHttpCode === 200) {
                $putSuccess = true;
                $response = $putResponse;
                $httpCode = $putHttpCode;
            }
        }

        // Check if either POST or PUT succeeded
        if ($httpCode === 200 || $putSuccess) {
            $result = json_decode($response, true);

            // Accept success if we get a valid response or if the API returns success
            if ($result && isset($result['success']) && $result['success'] === true) {
                echo json_encode([
                    'success' => true,
                    'message' => 'HR3 claim status updated to ' . $newStatus,
                    'claim_id' => $claimId,
                    'new_status' => $newStatus
                ]);
            } else {
                // Even if JSON doesn't have success=true, treat 200 response as success
                echo json_encode([
                    'success' => true,
                    'message' => 'HR3 claim status update attempted (response: ' . substr($response, 0, 100) . ')',
                    'claim_id' => $claimId,
                    'new_status' => $newStatus
                ]);
            }
        } else {
            // HR3 update failed, but this shouldn't break the local disbursement
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update HR3 claim status: HTTP ' . $httpCode .
                          ($curlError ? ' - ' . $curlError : '') .
                          ' (Disbursement still created locally)',
                'claim_id' => $claimId
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
