<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_send(['success' => false, 'error' => 'Method not allowed'], 405);
}

list($dateFrom, $dateTo) = api_get_date_range();

api_send([
    'success' => true,
    'endpoint' => '/api/hotel/bookings',
    'filters' => [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ],
    'schema' => [
        'booking_id' => 'string',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'room_rate' => 'number',
        'total_amount' => 'number',
        'status' => 'string'
    ],
    'data' => []
]);
