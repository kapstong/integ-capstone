<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();

api_send([
    'success' => true,
    'endpoint' => '/api/hotel/maintenance-costs',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'schema' => [
        'maintenance_request_id' => 'string',
        'cost_amount' => 'number',
        'date' => 'date',
        'expense_category' => 'string'
    ],
    'data' => []
]);
