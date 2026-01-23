<?php
/**
 * Currency formatting helper that respects server-side privacy masking.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/privacy_output_mask.php';

function format_currency($amount, $symbol = '\u20B1', $decimals = 2)
{
    // Normalize amount
    $amt = is_numeric($amount) ? (float)$amount : 0.0;

    // When privacy masking is active, return masked value
    if (function_exists('privacyShouldMaskOutput') && privacyShouldMaskOutput()) {
        // Use 'PHP' label for PHP word matches or currency symbol otherwise
        $currencyLabel = $symbol === 'PHP' ? 'PHP' : $symbol;
        return $currencyLabel . ' ' . str_repeat('*', 8);
    }

    // Format number with thousands separator
    $formatted = number_format($amt, $decimals, '.', ',');

    // If symbol is the unicode peso, render it before the amount
    if ($symbol === '\u20B1' || $symbol === '&#8369;' || $symbol === '₱') {
        return '₱' . $formatted;
    }

    return $symbol . $formatted;
}
