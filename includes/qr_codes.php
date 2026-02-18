<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logger.php';

function qr_login_key() {
    $raw = Config::get('app.key', 'default-key-change-in-production');
    return hash('sha256', $raw, true);
}

function qr_login_encrypt_token($token) {
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($token, 'AES-256-CBC', qr_login_key(), OPENSSL_RAW_DATA, $iv);
    return [
        'cipher' => base64_encode($cipher),
        'iv' => base64_encode($iv)
    ];
}

function qr_login_decrypt_token($cipherB64, $ivB64) {
    if (!$cipherB64 || !$ivB64) {
        return null;
    }
    $cipher = base64_decode($cipherB64, true);
    $iv = base64_decode($ivB64, true);
    if ($cipher === false || $iv === false) {
        return null;
    }
    $token = openssl_decrypt($cipher, 'AES-256-CBC', qr_login_key(), OPENSSL_RAW_DATA, $iv);
    return $token ?: null;
}

function qr_login_get_active_code($userId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT *
        FROM user_qr_codes
        WHERE user_id = ? AND is_active = 1 AND revoked_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function qr_login_generate_code($userId, $rotate = false) {
    $db = Database::getInstance()->getConnection();
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $encrypted = qr_login_encrypt_token($token);

    if ($rotate) {
        $stmt = $db->prepare("
            UPDATE user_qr_codes
            SET is_active = 0, revoked_at = NOW()
            WHERE user_id = ? AND is_active = 1 AND revoked_at IS NULL
        ");
        $stmt->execute([$userId]);
    }

    $stmt = $db->prepare("
        INSERT INTO user_qr_codes (user_id, token_hash, token_cipher, token_iv, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$userId, $tokenHash, $encrypted['cipher'], $encrypted['iv']]);
    $recordId = $db->lastInsertId();

    $action = $rotate ? 'QR Code Renewed' : 'QR Code Generated';
    Logger::getInstance()->logUserAction($action, 'user_qr_codes', $recordId, null, [
        'user_id' => $userId
    ]);

    return [
        'token' => $token,
        'record_id' => $recordId
    ];
}

function qr_login_lookup_token($token) {
    $db = Database::getInstance()->getConnection();
    $tokenHash = hash('sha256', $token);
    $stmt = $db->prepare("
        SELECT qc.*, u.id AS user_id, u.role, u.status, u.username, u.full_name
        FROM user_qr_codes qc
        JOIN users u ON u.id = qc.user_id
        WHERE qc.token_hash = ? AND qc.is_active = 1 AND qc.revoked_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function qr_login_mark_used($qrId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE user_qr_codes
        SET last_used_at = NOW(), last_used_ip = ?, last_used_user_agent = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $qrId
    ]);
}
?>
