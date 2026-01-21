<?php
$email = 'catalan.jereckopaul@gmail.com';
$dept = 'FIN1';
$role = 'super_admin';
$exp = time() + 3600; // 1 hour

// Use the SHA2 secret stored in department_secrets
$secret = hash('sha256', 'fin1_secret_key_2026');

$payload = [
    'email' => $email,
    'dept'  => $dept,
    'role'  => $role,
    'exp'   => $exp
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $payloadJson, $secret);

$token = base64_encode(json_encode([
    'payload' => $payload,
    'signature' => $signature
], JSON_UNESCAPED_SLASHES));

echo $token . PHP_EOL;
