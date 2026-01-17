<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

$configFile = __DIR__ . '/../../config/integrations/hr3.json';
$config = ['api_url' => 'https://hr3.atierahotelandrestaurant.com/api/claimsApi.php'];

if (file_exists($configFile)) {
    $configData = json_decode(file_get_contents($configFile), true);
    if (is_array($configData)) {
        $config = array_merge($config, $configData);
    }
}

$apiUrl = $config['api_url'] ?? 'https://hr3.atierahotelandrestaurant.com/api/claimsApi.php';
$externalParams = $_GET;
unset($externalParams['api_key']);

if (!empty($externalParams)) {
    $separator = strpos($apiUrl, '?') === false ? '?' : '&';
    $apiUrl .= $separator . http_build_query($externalParams);
}

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

if ($curlError) {
    api_send(['success' => false, 'error' => 'Connection error: ' . $curlError], 502);
}

if ($httpCode < 200 || $httpCode >= 300) {
    api_send(['success' => false, 'error' => 'Upstream returned HTTP ' . $httpCode], 502);
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    api_send(['success' => false, 'error' => 'Invalid JSON from upstream'], 502);
}

api_send([
    'success' => true,
    'endpoint' => '/api/hr/claims',
    'source' => $apiUrl,
    'data' => $data
]);
