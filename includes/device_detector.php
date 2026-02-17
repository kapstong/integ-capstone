<?php
function detect_device_info($userAgent) {
    $ua = strtolower($userAgent ?? '');

    $deviceType = 'Desktop';
    if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|mediapartners-google/', $ua)) {
        $deviceType = 'Bot';
    } elseif (preg_match('/ipad|tablet|android(?!.*mobile)/', $ua)) {
        $deviceType = 'Tablet';
    } elseif (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone/', $ua)) {
        $deviceType = 'Mobile';
    }

    $os = 'Unknown';
    if (preg_match('/windows nt 11\.0/', $ua)) {
        $os = 'Windows 11';
    } elseif (preg_match('/windows nt 10\.0/', $ua)) {
        $os = 'Windows 10';
    } elseif (preg_match('/windows nt 6\.3/', $ua)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/windows nt 6\.1/', $ua)) {
        $os = 'Windows 7';
    } elseif (preg_match('/mac os x/', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/android/', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/iphone|ipad|ipod/', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/linux/', $ua)) {
        $os = 'Linux';
    }

    $browser = 'Unknown';
    if (preg_match('/edg\//', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/chrome\/|crios\//', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/firefox\//', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/safari\//', $ua) && !preg_match('/chrome\/|crios\/|edg\//', $ua)) {
        $browser = 'Safari';
    }

    return [
        'device_type' => $deviceType,
        'os' => $os,
        'browser' => $browser
    ];
}

