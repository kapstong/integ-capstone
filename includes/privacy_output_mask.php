<?php
/**
 * Server-side privacy masking for HTML output.
 */

function privacyShouldMaskOutput(): bool
{
    return empty($_SESSION['privacy_unlocked']) || empty($_SESSION['privacy_visible']);
}

function startPrivacyOutputMasking(): void
{
    if (!privacyShouldMaskOutput()) {
        return;
    }

    ob_start('privacyMaskHtmlOutput');
}

function privacyMaskHtmlOutput(string $html): string
{
    if (!privacyShouldMaskOutput()) {
        return $html;
    }

    $pattern = '/(<script\\b[^>]*>.*?<\\/script>|<style\\b[^>]*>.*?<\\/style>)/is';
    $parts = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }

    foreach ($parts as $index => $part) {
        if (preg_match('/^<(script|style)\\b/i', $part)) {
            continue;
        }
        $parts[$index] = privacyMaskCurrencies($part);
    }

    return implode('', $parts);
}

function privacyMaskCurrencies(string $text): string
{
    $currencyPattern = '/(\\(\\s*)?(PHP|&#8369;|&#x20b1;|\\x{20B1}|P|\\$)\\s*-?[\\d,]+(?:\\.\\d+)?(\\s*\\))?/iu';

    return preg_replace_callback($currencyPattern, function ($matches) {
        $leadingParen = $matches[1] ?? '';
        $currency = $matches[2] ?? '';
        $trailingParen = $matches[3] ?? '';
        $upperCurrency = strtoupper($currency);

        if ($upperCurrency === 'PHP') {
            $masked = 'PHP *********';
        } else {
            $masked = $currency . '*********';
        }

        return $leadingParen . $masked . $trailingParen;
    }, $text);
}
