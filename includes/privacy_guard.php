<?php
/**
 * Privacy mode download/export guard.
 * Blocks downloads when privacy mode is hidden.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function privacyIsUnlocked(): bool
{
    return isset($_SESSION['privacy_unlocked']) && $_SESSION['privacy_unlocked'] === true;
}

function privacyIsVisible(): bool
{
    return isset($_SESSION['privacy_visible']) && $_SESSION['privacy_visible'] === true;
}

function requirePrivacyVisible(string $responseType = 'json'): void
{
    if (!privacyIsUnlocked() || !privacyIsVisible()) {
        http_response_code(403);
        if ($responseType === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Privacy mode is enabled. Disable it to export or download data.']);
        } else {
            echo 'Privacy mode is enabled. Disable it to export or download data.';
        }
        exit;
    }
}
