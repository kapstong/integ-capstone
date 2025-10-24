<?php
require_once 'config.php';
require_once 'includes/api_integrations.php';

$integrationManager = APIIntegrationManager::getInstance();
$hr4Config = $integrationManager->getIntegrationConfig('hr4');

echo "<h1>HR4 API Test Results</h1>";
echo "<pre>";
echo "HR4 Configuration:\n";
echo json_encode($hr4Config, JSON_PRETTY_PRINT) . "\n\n";

echo "==============================================\n";
echo "DIRECT API CALL:\n";
echo "==============================================\n";

// Direct API call test
$ch = curl_init($hr4Config['api_url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$headers = ['Content-Type: application/json'];
if (!empty($hr4Config['api_key'])) {
    $headers[] = 'Authorization: Bearer ' . base64_encode(hash('sha256', $hr4Config['api_key'], true));
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response:\n";
$decoded = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Decode Error: Response is not valid JSON\n";
    echo "*** RAW RESPONSE ***\n";
    echo $response;
    echo "\n*** END RAW RESPONSE ***\n";
} else {
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
}

echo "\n==============================================\n";
echo "PARSED PAYROLL DATA:\n";
echo "==============================================\n";

try {
    $payrollData = $integrationManager->executeIntegrationAction('hr4', 'getPayrollData', []);
    echo "Payroll Data:\n";
    echo json_encode($payrollData, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error fetching payroll data: " . $e->getMessage() . "\n";
}

echo "\n==============================================\n";
echo "IMPORT RESULT:\n";
echo "==============================================\n";

try {
    $importResult = $integrationManager->executeIntegrationAction('hr4', 'importPayroll', []);
    echo "Import Result:\n";
    echo json_encode($importResult, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error importing payroll: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
