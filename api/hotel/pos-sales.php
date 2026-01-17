<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();

api_send([
    'success' => true,
    'endpoint' => '/api/hotel/pos-sales',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'schema' => [
        'transaction_id' => 'string',
        'items' => 'array',
        'quantity' => 'number',
        'total_amount' => 'number',
        'transaction_date' => 'date'
    ],
    'data' => []
]);
