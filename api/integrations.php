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

        // HR3 API expects form-urlencoded data for PUT requests, not JSON
        $updateData = http_build_query([
            'claim_id' => $claimId,
            'status' => $newStatus, // Must be "Paid" to work with HR3 API
            'paid_by' => 'Financial System' // Optional field for HR3
        ]);

        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if (!empty($config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }

        // Add debugging information for PUT request
        $ch = curl_init($config['api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // HR3 requires PUT method
        curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData); // Form-encoded data
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Enable verbose logging for debugging
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verboseBuffer = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verboseBuffer);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        // Get verbose output
        rewind($verboseBuffer);
        $verbose = stream_get_contents($verboseBuffer);
        fclose($verboseBuffer);

        curl_close($ch);

        // Detailed logging for troubleshooting
        error_log("=== HR3 PUT REQUEST DEBUG ===");
        error_log("URL: {$config['api_url']}");
        error_log("Method: PUT");
        error_log("Headers: " . json_encode($headers));
        error_log("Data: {$updateData}");
        error_log("HTTP Code: {$httpCode}");
        error_log("Curl Error: {$curlError}");
        error_log("Response First 200 chars: " . substr($response, 0, 200));
        error_log("Verbose Log: " . substr($verbose, 0, 500)); // First 500 chars of verbose

        // Check if it's the claims list response (indicating HR3 not processing PUT correctly)
        $isClaimsList = false;
        if ($response && substr(trim($response), 0, 1) === '[') { // JSON array starts with [
            $possibleClaims = json_decode($response, true);
            if (is_array($possibleClaims) && !empty($possibleClaims) &&
                isset($possibleClaims[0]['claim_id'])) {
                $isClaimsList = true;
            }
        }

        // Check for 405 Method Not Allowed error (very specific configuration issue)
        if ($httpCode === 405) {
            echo json_encode([
                'success' => false,
                'error' => 'HR3 API returns HTTP 405 (Method Not Allowed) for PUT requests',
                'http_code' => $httpCode,
                'note' => 'HR3 web server is explicitly configured to BLOCK PUT requests. This is a server configuration issue.',
                'solution' => 'Configure Apache/nginx to allow PUT requests. Add the appropriate directives below.',
                'detailed_solution' => [
                    'apache_htaccess' => 'Add to HR3 .htaccess: <LimitExcept GET POST HEAD>deny from all</LimitExcept>',
                    'nginx_location' => 'Add to nginx config: location /api/claimsApi.php { limit_except GET POST PUT PATCH { deny all; } }',
                    'apache_vhost' => 'Add to Apache VirtualHost/VirtualDirectory: <Directory "/hr3/api/path"> AllowMethods GET POST PUT PATCH HEAD </Directory>'
                ]
            ]);
        } elseif ($isClaimsList) {
            // HR3 is returning claims list instead of processing PUT
            echo json_encode([
                'success' => false,
                'error' => 'HR3 API not processing PUT requests correctly - returning claims list instead of update response',
                'http_code' => $httpCode,
                'note' => 'HR3 server may not be configured properly for PUT requests. Disbursement created locally.',
                'debug_info' => [
                    'response_starts_with' => substr($response, 0, 50),
                    'is_json_array' => true,
                    'likely_claims_list' => true
                ]
            ]);
        } elseif (!$curlError && $httpCode === 200) {
            $result = json_decode($response, true);

            // HR3 API returns success message on successful update
            if ($result && isset($result['status']) && $result['status'] === 'success') {
                echo json_encode([
                    'success' => true,
                    'message' => 'HR3 claim status successfully updated to "' . $newStatus . '"',
                    'claim_id' => $claimId,
                    'new_status' => $newStatus,
                    'hr3_response' => $result
                ]);
            } else {
                // If HR3 API returns an error (like claim not found or wrong status)
                echo json_encode([
                    'success' => false,
                    'error' => 'HR3 API rejected the update: ' . ($result['error'] ?? 'Unknown error'),
                    'hr3_response' => $result,
                    'http_code' => $httpCode,
                    'note' => 'Check if claim exists and is in "Approved" status.'
                ]);
            }
        } else {
            // Network or HTTP error
            $errorMsg = $curlError ?: "HTTP $httpCode";
            echo json_encode([
                'success' => false,
                'error' => 'Failed to connect to HR3 API: ' . $errorMsg,
                'claim_id' => $claimId,
                'attempted_status' => $newStatus,
                'http_code' => $httpCode,
                'note' => 'Disbursement will still be created locally.'
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
