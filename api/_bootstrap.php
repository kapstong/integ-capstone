<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/api_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$apiClient = APIAuth::getInstance()->authenticate();
$db = Database::getInstance()->getConnection();

function api_send($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function api_date($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d', $timestamp);
}

function api_get_date_range() {
    $dateFrom = api_date($_GET['date_from'] ?? null);
    $dateTo = api_date($_GET['date_to'] ?? null);
    return [$dateFrom, $dateTo];
}

