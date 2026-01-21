<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "connections.php";
require_once '../includes/permissions.php';
session_start();

if (!isset($_GET['token'])) die("Token missing");

// Normalize token for URL/base64 variants.
$token = rawurldecode($_GET['token']);
// If upstream mistakenly concatenates another token param, keep the first token.
if (strpos($token, 'token=') !== false) {
    $parts = preg_split('/[?&]token=/', $token);
    $token = $parts[0];
}
$token = str_replace(' ', '+', $token);

// decode token
$decoded = base64_decode($token, true);
if (!$decoded) {
    // Support base64url tokens (-_ instead of +/).
    $token = strtr($token, '-_', '+/');
    $padding = strlen($token) % 4;
    if ($padding) {
        $token .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($token, true);
}
if (!$decoded) die("Invalid token");

$data = json_decode($decoded, true);
if (!$data || !isset($data['payload'], $data['signature'])) {
    die("Invalid token structure");
}

$signature = $data['signature'];

/**
 * NORMALIZE PAYLOAD
 * Accept both array and JSON-string payloads
 */
if (is_array($data['payload'])) {
    // payload came as array (your current case)
    $payload     = $data['payload'];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
} elseif (is_string($data['payload'])) {
    // payload came as JSON string (future-safe)
    $payloadJson = $data['payload'];
    $payload     = json_decode($payloadJson, true);
} else {
    die("Invalid payload format");
}

if (!$payload) die("Invalid payload");

// fetch FIN1 secret
$stmt = $conn->prepare("
    SELECT secret_key 
    FROM department_secrets 
    WHERE department = ? AND is_active = 1 
    ORDER BY id DESC LIMIT 1
");
$stmt->execute(['FIN1']);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) die("Secret not found");

$secret = $res['secret_key'];

// verify signature (CRITICAL)
$check = hash_hmac("sha256", $payloadJson, $secret);
if (!hash_equals($check, $signature)) {
    die("Invalid or tampered token");
}

// expiry check
if ($payload['exp'] < time()) {
    die("Token expired");
}

// department validation
if ($payload['dept'] !== 'FIN1') {
    die("Invalid department access");
}

// create normal session (match login flow)
session_regenerate_id(true);

// load user for session population
if (empty($payload['email'])) {
    die("Email missing");
}

$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, u.full_name, u.role,
           u.department, u.phone, u.status
    FROM users u
    WHERE u.email = ? AND u.status = 'active'
    LIMIT 1
");
$stmt->execute([$payload['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found or inactive");
}

$_SESSION['user'] = [
    'id' => $user['id'],
    'username' => $user['username'],
    'role_name' => $user['role'],
    'name' => $user['full_name'],
    'email' => $user['email'],
    'department' => $user['department'] ?? '',
    'phone' => $user['phone'] ?? ''
];

$permManager = PermissionManager::getInstance();
$permManager->loadUserPermissions($user['id']);
$_SESSION['user']['permissions'] = $permManager->getUserPermissions();
$_SESSION['user']['roles'] = $permManager->getUserRoles();

$stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->execute([$user['id']]);

header("Location: index.php");
exit;
