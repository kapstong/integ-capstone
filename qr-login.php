<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/qr_codes.php';
require_once 'includes/device_detector.php';
require_once 'includes/two_factor_auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
} else {
    $token = trim($_GET['t'] ?? '');
}

$error = '';
if ($token === '') {
    $error = 'Missing QR token.';
} else {
    $qr = qr_login_lookup_token($token);
    if (!$qr) {
        $error = 'Invalid or expired QR token.';
    } elseif (($qr['status'] ?? '') !== 'active') {
        $error = 'User account is not active.';
    } elseif (strtolower($qr['role'] ?? '') === 'super_admin') {
        $error = 'QR login is not available for this role.';
    }
}

if (!$error && $qr) {
    $auth = new Auth();
    $result = $auth->loginByUserId($qr['user_id']);

    if ($result['success']) {
        qr_login_mark_used($qr['id']);

        $deviceInfo = detect_device_info($_SERVER['HTTP_USER_AGENT'] ?? '');
        $devicePayload = [
            'device_label' => 'QR Login',
            'device_type' => $deviceInfo['device_type'],
            'device_os' => $deviceInfo['os'],
            'device_browser' => $deviceInfo['browser'],
            'device_platform' => null,
            'device_model' => null
        ];

        $twoFA = TwoFactorAuth::getInstance();
        if ($twoFA->is2FAEnabled($qr['user_id'])) {
            $_SESSION['pending_2fa_user_id'] = $qr['user_id'];
            $_SESSION['pending_2fa_user'] = $result['user'];
            $_SESSION['pending_device'] = $devicePayload;
            $_SESSION['pending_login_method'] = 'qr';
            unset($_SESSION['user']);
            header('Location: verify_2fa.php');
            exit();
        }

        Logger::getInstance()->logUserAction(
            'QR Login',
            'login_sessions',
            null,
            null,
            [
                'login_method' => 'qr',
                'device_label' => $devicePayload['device_label'],
                'device_type' => $devicePayload['device_type'],
                'os' => $devicePayload['device_os'],
                'browser' => $devicePayload['device_browser']
            ]
        );

        $role = strtolower($result['user']['role_name'] ?? '');
        if ($role === 'super_admin') {
            $target = 'superadmin/index.php';
        } elseif ($role === 'admin') {
            $target = 'admin/index.php';
        } else {
            $target = 'staff/index.php';
        }
        header('Location: ' . $target);
        exit();
    } else {
        $error = $result['error'] ?? 'Login failed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Login - ATIERA</title>
    <link rel="icon" href="logo2.png">
    <link rel="stylesheet" href="responsive.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            background: linear-gradient(140deg, #0f1c49 50%, #ffffff 50%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #0f172a;
        }
        .card {
            background: rgba(255,255,255,0.95);
            border-radius: 18px;
            padding: 2rem;
            box-shadow: 0 16px 40px rgba(2,6,23,.18);
            max-width: 520px;
            width: 90%;
            text-align: center;
        }
        .card h2 { margin: 0 0 .5rem; }
        .card p { color: #64748b; }
        .btn {
            display: inline-block;
            margin-top: 1rem;
            padding: .75rem 1.5rem;
            border-radius: 10px;
            background: #1b2f73;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="logo.png" alt="ATIERA" style="height:60px;margin-bottom:1rem;">
        <h2>QR Login Failed</h2>
        <p><?php echo htmlspecialchars($error ?: 'Unable to complete QR login.'); ?></p>
        <a class="btn" href="index.php">Back to Login</a>
    </div>
</body>
</html>
