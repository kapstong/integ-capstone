<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();

api_send([
    'success' => true,
    'endpoint' => '/api/hotel/payments',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'schema' => [
        'payment_id' => 'string',
        'invoice_id' => 'string',
        'amount_paid' => 'number',
        'payment_method' => 'string',
        'payment_date' => 'date'
    ],
    'data' => []
]);
