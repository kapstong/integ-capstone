<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

$role = strtolower((string) ($_SESSION['user']['role'] ?? $_SESSION['user']['role_name'] ?? ''));
if (!in_array($role, ['admin', 'staff'], true)) {
    http_response_code(403);
    echo 'QR card printing is only available for staff and admin users.';
    exit;
}

$qrLoginUrl = '';
$errorMessage = '';
$user = $_SESSION['user'];

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $fetchedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($fetchedUser)) {
        $user = $fetchedUser;
    }

    $qrCodesPath = __DIR__ . '/includes/qr_codes.php';
    if (!file_exists($qrCodesPath)) {
        throw new Exception('QR module is not installed.');
    }
    require_once $qrCodesPath;

    $qrRecord = qr_login_get_active_code((int) $_SESSION['user']['id']);
    if (!$qrRecord) {
        $errorMessage = 'No active QR code found. Please generate one first from Profile Settings.';
    } else {
        $token = qr_login_decrypt_token($qrRecord['token_cipher'] ?? '', $qrRecord['token_iv'] ?? '');
        if (!$token) {
            $errorMessage = 'Unable to decode your current QR code. Please renew and try again.';
        } else {
            $qrLoginUrl = rtrim(Config::get('app.url'), '/') . '/qr-login.php?t=' . urlencode($token);
        }
    }
} catch (Throwable $e) {
    error_log('QR print page error: ' . $e->getMessage());
    $errorMessage = 'Failed to load QR card data.';
}

$firstName = trim((string) ($user['first_name'] ?? ''));
$lastName = trim((string) ($user['last_name'] ?? ''));
$fullName = trim((string) ($user['full_name'] ?? ''));
$username = (string) ($user['username'] ?? '');
$displayName = trim($firstName . ' ' . $lastName);
if ($displayName === '') {
    $displayName = $fullName !== '' ? $fullName : $username;
}

$displayRole = ucfirst((string) ($user['role'] ?? $role ?: 'staff'));
$displayDept = (string) ($user['department'] ?? 'Finance');
$issuedAt = date('M j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Card Print - ATIERA</title>
    <link rel="icon" type="image/png" href="logo2.png">
    <style>
        :root {
            --paper: #eef2f8;
            --ink: #0b1020;
            --brand-1: #0b1437;
            --brand-2: #1b2f73;
            --brand-3: #f0c34a;
            --accent: #7dd3fc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Trebuchet MS", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(1200px 700px at 15% 0%, #ffffff 0%, transparent 62%),
                radial-gradient(1000px 700px at 100% 100%, #dbe8ff 0%, transparent 70%),
                var(--paper);
            color: var(--ink);
            display: grid;
            place-items: center;
            padding: 2rem;
        }
        .stage {
            width: 100%;
            max-width: 760px;
            display: grid;
            justify-items: center;
        }
        .card {
            position: relative;
            border-radius: 18px;
            width: min(92vw, 720px);
            min-height: 420px;
            background: linear-gradient(145deg, var(--brand-1) 0%, var(--brand-2) 62%, #173682 100%);
            color: #fff;
            padding: 1.2rem 1.25rem;
            box-shadow: 0 26px 50px rgba(9, 16, 36, 0.35);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.12);
        }
        .card::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 88% 10%, rgba(255, 255, 255, 0.28), transparent 38%),
                linear-gradient(to bottom, transparent 60%, rgba(240, 195, 74, 0.12));
        }
        .card::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 50%;
            right: -90px;
            bottom: -110px;
            opacity: 0.45;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.1rem;
            position: relative;
            z-index: 1;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .header img {
            height: 38px;
            width: auto;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3));
        }
        .title {
            font-size: 0.84rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }
        .subtitle {
            opacity: 0.88;
            font-size: 0.76rem;
        }
        .badge {
            background: rgba(125, 211, 252, 0.15);
            border: 1px solid rgba(125, 211, 252, 0.45);
            color: #d9f4ff;
            padding: 0.36rem 0.62rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .body {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 230px 1fr;
            gap: 1.1rem;
            align-items: center;
        }
        .qr-shell {
            width: 230px;
            height: 230px;
            border-radius: 18px;
            background: #fff;
            padding: 12px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(15, 23, 42, 0.14);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.7), 0 10px 26px rgba(3, 7, 18, 0.28);
        }
        #qrCanvas, #qrFallback {
            width: 206px;
            height: 206px;
            display: block;
        }
        #qrFallback {
            display: none;
            border-radius: 8px;
        }
        .qr-missing {
            display: none;
            text-align: center;
            color: #991b1b;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.35;
            padding: 0.8rem;
        }
        .meta h1 {
            margin: 0 0 0.42rem;
            font-size: 1.85rem;
            line-height: 1.05;
            letter-spacing: 0.01em;
        }
        .meta .line {
            display: block;
            font-size: 0.95rem;
            opacity: 0.94;
            margin-bottom: 0.25rem;
        }
        .meta .label {
            opacity: 0.76;
            margin-right: 0.35rem;
        }
        .footer {
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px dashed rgba(255,255,255,0.28);
            display: flex;
            justify-content: space-between;
            gap: 0.9rem;
            align-items: center;
            position: relative;
            z-index: 1;
            font-size: 0.78rem;
            opacity: 0.92;
        }
        .helper {
            margin-top: 0.9rem;
            color: #334155;
            text-align: center;
            font-size: 0.85rem;
        }
        .controls {
            margin-top: 1rem;
            display: flex;
            gap: 0.6rem;
            justify-content: center;
        }
        .btn {
            border: 0;
            border-radius: 10px;
            padding: 0.62rem 1rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-primary {
            background: #0f172a;
            color: #fff;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }
        .notice {
            background: #fff;
            color: #7f1d1d;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 0.9rem 1rem;
            text-align: center;
        }
        @media (max-width: 760px) {
            .card {
                min-height: auto;
            }
            .body {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }
            .meta h1 {
                font-size: 1.5rem;
            }
            .footer {
                flex-direction: column;
                align-items: center;
            }
        }
        @media print {
            @page { margin: 8mm; size: auto; }
            body {
                background: #fff;
                padding: 0;
            }
            .stage {
                max-width: none;
            }
            .controls, .helper {
                display: none;
            }
            .card {
                width: 85.6mm;
                min-height: 54mm;
                border-radius: 4mm;
                padding: 3.2mm;
                box-shadow: none;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .header img {
                height: 6.5mm;
            }
            .title {
                font-size: 2.1mm;
            }
            .subtitle {
                font-size: 1.95mm;
            }
            .badge {
                font-size: 1.7mm;
                padding: 1.3mm 2.2mm;
            }
            .body {
                grid-template-columns: 27mm 1fr;
                gap: 2.5mm;
            }
            .qr-shell {
                width: 27mm;
                height: 27mm;
                padding: 1.2mm;
                border-radius: 2mm;
                box-shadow: none;
            }
            #qrCanvas, #qrFallback {
                width: 24.6mm;
                height: 24.6mm;
            }
            .meta h1 {
                font-size: 4.1mm;
                margin-bottom: 1.1mm;
            }
            .meta .line {
                font-size: 2.55mm;
                margin-bottom: 0.5mm;
            }
            .footer {
                margin-top: 2mm;
                padding-top: 1.7mm;
                font-size: 2mm;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/loading_screen.php'; ?>
    <div class="stage">
        <?php if ($errorMessage): ?>
            <div class="notice"><?php echo htmlspecialchars($errorMessage); ?></div>
            <div class="controls">
                <button class="btn btn-secondary" onclick="window.close()">Close</button>
            </div>
        <?php else: ?>
            <div class="card" id="printCard">
                <div class="header">
                    <div class="brand">
                        <img src="logo.png" alt="ATIERA">
                        <div>
                            <div class="title">ATIERA Fast Login</div>
                            <div class="subtitle">Financial Access Identity Card</div>
                        </div>
                    </div>
                    <div class="badge">Active</div>
                </div>
                <div class="body">
                    <div class="qr-shell">
                        <canvas id="qrCanvas" width="206" height="206"></canvas>
                        <img id="qrFallback" alt="QR Code Fallback">
                        <div id="qrMissing" class="qr-missing">QR code unavailable.</div>
                    </div>
                    <div class="meta">
                        <h1><?php echo htmlspecialchars($displayName ?: 'User'); ?></h1>
                        <span class="line"><span class="label">Role:</span><?php echo htmlspecialchars($displayRole); ?></span>
                        <span class="line"><span class="label">Department:</span><?php echo htmlspecialchars($displayDept); ?></span>
                        <span class="line"><span class="label">Username:</span><?php echo htmlspecialchars($username ?: 'N/A'); ?></span>
                    </div>
                </div>
                <div class="footer">
                    <span>Issued: <?php echo htmlspecialchars($issuedAt); ?></span>
                    <span>ATIERA Secure Entry</span>
                </div>
            </div>
            <div class="helper">If QR does not render instantly, wait a second before printing.</div>
            <div class="controls">
                <button class="btn btn-primary" onclick="window.print()">Print</button>
                <button class="btn btn-secondary" onclick="window.close()">Close</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            const qrLoginUrl = <?php echo json_encode($qrLoginUrl); ?>;
            const canvas = document.getElementById('qrCanvas');
            const fallbackImg = document.getElementById('qrFallback');
            const missing = document.getElementById('qrMissing');

            function autoPrint(delayMs) {
                window.setTimeout(function () { window.print(); }, delayMs);
            }

            function showMissing() {
                if (canvas) canvas.style.display = 'none';
                if (fallbackImg) fallbackImg.style.display = 'none';
                if (missing) missing.style.display = 'block';
            }

            function showFallbackImage() {
                if (!qrLoginUrl || !fallbackImg) {
                    showMissing();
                    return;
                }
                const fallbackUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&margin=8&format=png&data=' + encodeURIComponent(qrLoginUrl);
                fallbackImg.onload = function () {
                    if (canvas) canvas.style.display = 'none';
                    fallbackImg.style.display = 'block';
                    if (missing) missing.style.display = 'none';
                    autoPrint(250);
                };
                fallbackImg.onerror = showMissing;
                fallbackImg.src = fallbackUrl;
            }

            function renderWithLibrary() {
                if (!qrLoginUrl || !canvas || !window.QRCode) {
                    return false;
                }
                QRCode.toCanvas(canvas, qrLoginUrl, {
                    width: 206,
                    margin: 1,
                    color: { dark: '#111111', light: '#ffffff' }
                }, function (err) {
                    if (err) {
                        showFallbackImage();
                        return;
                    }
                    canvas.style.display = 'block';
                    if (fallbackImg) fallbackImg.style.display = 'none';
                    if (missing) missing.style.display = 'none';
                    autoPrint(220);
                });
                return true;
            }

            function loadScript(src, onOk, onErr) {
                const s = document.createElement('script');
                s.src = src;
                s.async = true;
                s.onload = onOk;
                s.onerror = onErr;
                document.head.appendChild(s);
            }

            if (!qrLoginUrl) {
                showMissing();
                return;
            }

            if (renderWithLibrary()) {
                return;
            }

            loadScript(
                'https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js',
                function () {
                    if (!renderWithLibrary()) {
                        showFallbackImage();
                    }
                },
                function () {
                    loadScript(
                        'https://unpkg.com/qrcode@1.5.4/build/qrcode.min.js',
                        function () {
                            if (!renderWithLibrary()) {
                                showFallbackImage();
                            }
                        },
                        showFallbackImage
                    );
                }
            );
        })();
    </script>
</body>
</html>
