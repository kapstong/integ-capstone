<?php
$email = 'catalan.jereckopaul@gmail.com';
$dept = 'FIN1';
$role = 'super_admin';
$exp = null; // lifetime (omit exp)

// Use the exact secret stored in department_secrets (SHA2 hex string).
// Replace with the value from:
// SELECT secret_key FROM department_secrets WHERE department='FIN1' AND is_active=1 ORDER BY id DESC LIMIT 1;
$secret = 'fin1_secret_key_2026';

// If you want to use the raw secret instead, set $useRawSecret = true.
$useRawSecret = false;
if ($useRawSecret) {
    $secret = hash('sha256', $secret);
}

$payload = [
    'email' => $email,
    'dept'  => $dept,
    'role'  => $role
];
if ($exp !== null) {
    $payload['exp'] = $exp;
}

$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $payloadJson, $secret);

$token = base64_encode(json_encode([
    'payload' => $payload,
    'signature' => $signature
], JSON_UNESCAPED_SLASHES));

echo $token . PHP_EOL;
