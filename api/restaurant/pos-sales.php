<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();

api_send([
    'success' => true,
    'endpoint' => '/api/restaurant/pos-sales',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'schema' => [
        'order_id' => 'string',
        'total_order_amount' => 'number',
        'order_date' => 'date',
        'payment_status' => 'string'
    ],
    'data' => []
]);
