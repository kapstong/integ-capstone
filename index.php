<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/device_detector.php';
$auth = new Auth();

// Initialize variables
$info = '';
$error = '';

// Handle logout success message from session flash
if (isset($_SESSION['logout_success']) && $_SESSION['logout_success']) {
    regenerate_csrf_token(); // Ensure fresh CSRF token after logout
    $info = 'You have been logged out successfully.';
    unset($_SESSION['logout_success']); // Clear the flash message
}

// Handle session timeout message
if (isset($_GET['info'])) {
    if ($_GET['info'] === 'session_timeout') {
        $error = 'Your session expired due to inactivity. Please log in again.';
    } elseif ($_GET['info'] === '2fa_cancelled') {
        $info = '2FA verification cancelled. Please log in again.';
    }
}

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    $role = strtolower($user['role_name'] ?? '');
    if ($role === 'super_admin') {
        header('Location: superadmin/index.php');
    } elseif ($role === 'admin') {
        header('Location: admin/index.php');
    } elseif ($role === 'staff') {
        header('Location: staff/index.php');
    } else {
        header('Location: staff/index.php'); // Default to staff dashboard
    }
    exit();
}

$error = '';

$lockout = $auth->checkLockout();
$isLocked = $lockout['locked'];

if ($_POST) {
    if (!csrf_verify_request()) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptchaResponse = trim($_POST['g-recaptcha-response'] ?? '');
    $devicePayload = [
        'device_label' => trim($_POST['device_label'] ?? ''),
        'device_model' => trim($_POST['device_model'] ?? ''),
        'device_platform' => trim($_POST['device_platform'] ?? ''),
        'device_type' => trim($_POST['device_type'] ?? ''),
        'device_os' => trim($_POST['device_os'] ?? ''),
        'device_browser' => trim($_POST['device_browser'] ?? '')
    ];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $recaptchaOk = false;
        $recaptchaGraceUntil = $_SESSION['recaptcha_verified_until'] ?? 0;
        if (!empty($recaptchaGraceUntil) && time() < $recaptchaGraceUntil) {
            $recaptchaOk = true;
        }

        if (!$recaptchaOk) {
            if (empty($recaptchaResponse)) {
                $error = 'Captcha verification failed. Please try again.';
                goto login_end;
            }

            // Verify reCAPTCHA
            $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
            $verifyPayload = http_build_query([
                'secret' => RECAPTCHA_SECRET_KEY,
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $verifyContext = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $verifyPayload,
                    'timeout' => 5
                ]
            ]);
            $verifyResult = @file_get_contents($verifyUrl, false, $verifyContext);
            $verifyData = $verifyResult ? json_decode($verifyResult, true) : null;

            if (!$verifyData || empty($verifyData['success'])) {
                $error = 'Captcha verification failed. Please try again.';
                goto login_end;
            }

            $_SESSION['recaptcha_verified_until'] = time() + (30 * 60);
        }

        $result = $auth->login($username, $password);

        if ($result['success']) {
            // Check if user has 2FA enabled
            require_once 'includes/two_factor_auth.php';
            $twoFA = TwoFactorAuth::getInstance();

            if ($twoFA->is2FAEnabled($result['user']['id'])) {
                // Store pending 2FA verification in session
                $_SESSION['pending_2fa_user_id'] = $result['user']['id'];
                $_SESSION['pending_2fa_user'] = $result['user'];
                $_SESSION['pending_device'] = $devicePayload;
                $_SESSION['pending_login_method'] = 'password';

                // Clear the logged in session temporarily
                unset($_SESSION['user']);

                // Redirect to 2FA verification page
                header('Location: verify_2fa.php');
                exit();
            }

            // No 2FA required, proceed with normal login
            $deviceInfo = detect_device_info($_SERVER['HTTP_USER_AGENT'] ?? '');
            $deviceLabel = build_device_label($devicePayload, $_SERVER['HTTP_USER_AGENT'] ?? '');
            Logger::getInstance()->logUserAction(
                'User Login',
                'login_sessions',
                null,
                null,
                [
                    'login_method' => 'password',
                    'device_label' => $deviceLabel,
                    'device_type' => $devicePayload['device_type'] ?: $deviceInfo['device_type'],
                    'os' => $devicePayload['device_os'] ?: $deviceInfo['os'],
                    'browser' => $devicePayload['device_browser'] ?: $deviceInfo['browser'],
                    'platform' => $devicePayload['device_platform'] ?: null,
                    'model' => $devicePayload['device_model'] ?: null
                ]
            );

            // Route users based on their role
            $role = strtolower($result['user']['role_name'] ?? '');
            if ($role === 'super_admin') {
                $target = 'superadmin/index.php';
            } elseif ($role === 'admin') {
                $target = 'admin/index.php';
            } elseif ($role === 'staff') {
                $target = 'staff/index.php';
            } else {
                $target = 'staff/index.php'; // Default to staff dashboard
            }
            header('Location: ' . $target);
            exit();
        } else {
                         if (isset($result['lockout'])) {
                 $error = 'Account locked due to too many attempts. Please try again in ' . $result['lockout']['remaining'] . ' seconds.';
             } elseif (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $attemptsLeft = 5 - ($result['attempts'] ?? 0);
                $error = 'Invalid username or password. Attempts remaining: ' . $attemptsLeft;
            }
        }
    }
    }
}
login_end:
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>ATIERA — Secure Login</title>
<link rel="icon" href="logo2.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<link rel="stylesheet" href="responsive.css">

<style>
  :root{
    --blue-600:#1b2f73; --blue-700:#15265e; --blue-800:#0f1c49; --blue-a:#2342a6;
    --gold:#d4af37; --ink:#0f172a; --muted:#64748b;
    --ring:0 0 0 3px rgba(35,66,166,.28);
    --card-bg: rgba(255,255,255,.95); --card-border: rgba(226,232,240,.9);
    --wm-opa-light:.35; --wm-opa-dark:.55;
  }
  @media (prefers-color-scheme: dark){ :root{ --ink:#e5e7eb; --muted:#9ca3af; } }

  body{
    min-height:100svh; margin:0; color:var(--ink);
    background:
      radial-gradient(70% 60% at 8% 10%, rgba(255,255,255,.18) 0, transparent 60%),
      radial-gradient(40% 40% at 100% 0%, rgba(212,175,55,.08) 0, transparent 40%),
      linear-gradient(140deg, rgba(15,28,73,1) 50%, rgba(255,255,255,1) 50%);
  }
  html.dark body{
    background:
      radial-gradient(70% 60% at 8% 10%, rgba(212,175,55,.08) 0, transparent 60%),
      radial-gradient(40% 40% at 100% 0%, rgba(212,175,55,.12) 0, transparent 40%),
      linear-gradient(140deg, rgba(7,12,38,1) 50%, rgba(11,21,56,1) 50%);
    color:#e5e7eb;
  }

  .bg-watermark{ position:fixed; inset:0; z-index:-1; display:grid; place-items:center; pointer-events:none; }
  .bg-watermark img{
    width:min(820px,70vw); max-height:68vh; object-fit:contain; opacity:var(--wm-opa-light);
    filter: drop-shadow(0 0 26px rgba(255,255,255,.40)) drop-shadow(0 14px 34px rgba(0,0,0,.25));
    transition:opacity .25s ease, filter .25s ease, transform .6s ease;
  }
  html.dark .bg-watermark img{
    opacity:var(--wm-opa-dark);
    filter: drop-shadow(0 0 34px rgba(255,255,255,.55)) drop-shadow(0 16px 40px rgba(0,0,0,.30));
  }

  .reveal { opacity:0; transform:translateY(8px); animation:reveal .45s .05s both; }
  @keyframes reveal { to { opacity:1; transform:none; } }

  .card{
    background:linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
    -webkit-backdrop-filter: blur(16px); backdrop-filter: blur(16px);
    border:1px solid rgba(226,232,240,.9);
    border-radius:22px;
    box-shadow:0 22px 60px rgba(2,6,23,.20), inset 0 1px 0 rgba(255,255,255,.7);
  }
  html.dark .card{ background:rgba(17,24,39,.92); border-color:rgba(71,85,105,.55); box-shadow:0 16px 48px rgba(0,0,0,.5); }

  .card::before{
    content:''; position:absolute; inset:0; border-radius:22px; pointer-events:none;
    box-shadow:0 0 0 1px rgba(255,255,255,.5) inset;
  }

  .field{ position:relative; }
  .input{
    width:100%; border:1px solid #e5e7eb; border-radius:12px; background:#fff;
    padding:1rem 2.8rem 1rem .95rem; outline:none; color:#0f172a; transition:border-color .15s, box-shadow .15s, background .15s;
  }
  .input:focus{ border-color:var(--blue-a); box-shadow:var(--ring) }
  html.dark .input{ background:#0b1220; border-color:#243041; color:#e5e7eb; }
  .float-label{
    position:absolute; left:.9rem; top:50%; transform:translateY(-50%); padding:0 .25rem; color:#94a3b8;
    pointer-events:none; background:transparent; transition:all .15s ease;
  }
  .input:focus + .float-label,
  .input:not(:placeholder-shown) + .float-label{
    top:0; transform:translateY(-50%) scale(.92); color:var(--blue-a); background:#fff;
  }
  html.dark .input:focus + .float-label,
  html.dark .input:not(:placeholder-shown) + .float-label{ background:#0b1220; }
  .icon-right{ position:absolute; right:.6rem; top:50%; transform:translateY(-50%); color:#64748b; }
  html.dark .icon-right{ color:#94a3b8; }

  .card-header-pill{
    display:inline-flex; align-items:center; gap:.5rem; padding:.4rem .75rem;
    border-radius:999px; border:1px solid rgba(15,23,42,.12); background:rgba(248,250,252,.9);
    font-size:.75rem; font-weight:700; color:#1b2f73; letter-spacing:.04em;
  }

  .form-block{
    border:1px solid rgba(226,232,240,.9);
    border-radius:16px;
    padding:1rem 1rem 1.1rem;
    background:rgba(248,250,252,.6);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.8);
  }

  .recaptcha-wrap{
    padding:.25rem 0;
  }

  .card-ornament{
    position:absolute; right:16px; top:16px; width:46px; height:46px;
    border-radius:14px; background:linear-gradient(135deg, rgba(212,175,55,.2), rgba(27,47,115,.15));
    display:grid; place-items:center; box-shadow:inset 0 0 0 1px rgba(15,23,42,.08);
  }

  .btn{
    width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:linear-gradient(180deg, #1b2f73, #0f1c49);
    color:#fff; font-weight:800; border-radius:16px; padding:1rem 1.1rem; border:1px solid rgba(255,255,255,.08);
    transition:transform .08s ease, filter .15s ease, box-shadow .2s ease; box-shadow:0 10px 22px rgba(2,6,23,.22);
  }
  .btn:hover{ filter:saturate(1.08); box-shadow:0 12px 26px rgba(2,6,23,.26); }
  .btn:active{ transform:translateY(1px) scale(.99); }
  .btn[disabled]{ opacity:.85; cursor:not-allowed; }
  .btn-ghost{
    width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:transparent; color:var(--blue-700); font-weight:800; border-radius:16px; padding:.95rem 1.1rem;
    border:1px dashed rgba(27,47,115,.35); transition:transform .08s ease, background .15s ease, box-shadow .2s ease;
  }
  .btn-ghost:hover{ background:rgba(27,47,115,.06); box-shadow:0 10px 20px rgba(2,6,23,.12); }
  .btn-ghost.qr-side{
    width:74px;
    height:78px;
    padding:0;
    border-style:solid;
    border-radius:14px;
    display:grid;
    place-items:center;
  }
  .qr-icon{
    width:34px;
    height:34px;
    object-fit:contain;
  }

  .divider{
    display:flex; align-items:center; gap:.6rem; color:#94a3b8; font-size:.75rem; letter-spacing:.2em; text-transform:uppercase;
  }
  .divider::before, .divider::after{
    content:''; height:1px; background:rgba(148,163,184,.35); flex:1;
  }

  .qr-modal{
    position:fixed; inset:0; background:rgba(2,6,23,.55); display:none; align-items:center; justify-content:center; z-index:50;
    backdrop-filter: blur(4px);
  }
  .qr-modal.show{ display:flex; }
  .qr-panel{
    background:var(--card-bg); border-radius:18px; padding:1.25rem; width:min(520px,92vw);
    box-shadow:0 20px 50px rgba(2,6,23,.28);
  }
  .qr-panel h4{ margin:.2rem 0 .6rem; }
  #qrReader{ width:100%; }

  .alert{ border-radius:12px; padding:.65rem .8rem; font-size:.9rem; transition:opacity 0.5s ease }
  .alert-error{ border:1px solid #fecaca; background:#fef2f2; color:#b91c1c }
  .alert-info{ border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3 }
  html.dark .alert-error{ background:#3f1b1b; border-color:#7f1d1d; color:#fecaca }
  html.dark .alert-info{ background:#1e1b4b; border-color:#3730a3; color:#c7d2fe }

  .typing::after{ content:'|'; margin-left:2px; opacity:.6; animation: blink 1s steps(1) infinite; }
  @keyframes blink { 50%{opacity:0} }
</style>
</head>

<body class="grid md:grid-cols-2 gap-0 place-items-center p-6 md:p-10 min-h-screen">
  <?php include_once 'includes/loading_screen.php'; ?>

  <div class="bg-watermark" aria-hidden="true">
    <img src="logo.png" alt="ATIERA watermark" id="wm">
  </div>

  <section class="hidden md:flex w-full h-full items-center justify-center">
    <div class="max-w-lg text-white px-6 reveal">
      <img src="logo.png" alt="ATIERA" class="w-56 mb-6 drop-shadow-xl select-none" draggable="false">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
        ATIERA <span style="color:var(--gold)">HOTEL & RESTAURANT</span> Management
      </h1>
    </div>
  </section>

  <main class="w-full max-w-lg md:ml-28 md:mr-0 md:pr-4 self-center">
    <div id="card" class="card p-6 sm:p-8 reveal relative">
      <div class="card-ornament" aria-hidden="true">
        <img src="atieralogo2.png" alt="" style="width:26px;height:26px;object-fit:contain;">
      </div>
      <div class="flex items-center justify-between mb-4">
        <div class="md:hidden flex items-center gap-3">
          <img src="logo.png" alt="ATIERA" class="h-10 w-auto">
          <div>
            <div class="text-sm font-semibold leading-4">ATIERA Finance Suite</div>
            <div class="text-[10px] text-[color:var(--muted)]">Blue • White • <span class="font-medium" style="color:var(--gold)">Gold</span></div>
          </div>
        </div>
        <button id="modeBtn" class="px-3 py-2 rounded-lg border border-slate-200 text-sm hover:bg-white/60 dark:hover:bg-slate-800" aria-pressed="false" title="Toggle dark mode">🌓</button>

      </div>

      <h3 class="text-lg sm:text-xl font-semibold mb-1">Sign in</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Use your credentials to continue.</p>

      <?php if ($error || $info || (!$isLocked && isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0)): ?>
        <div class="alert <?php echo $error ? 'alert-error' : 'alert-info'; ?> mb-4" role="alert">
          <?php if ($error): ?>
            <?php echo htmlspecialchars($error); ?>
          <?php elseif ($info): ?>
            <?php echo htmlspecialchars($info); ?>
          <?php elseif (!$isLocked && isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0): ?>
            Login attempts: <?php echo $_SESSION['login_attempts']; ?>/5
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4" novalidate <?php echo $isLocked ? 'onsubmit="return false;"' : ''; ?>>
        <?php csrf_input(); ?>
        <input type="hidden" name="device_label" id="device_label">
        <input type="hidden" name="device_model" id="device_model">
        <input type="hidden" name="device_platform" id="device_platform">
        <input type="hidden" name="device_type" id="device_type">
        <input type="hidden" name="device_os" id="device_os">
        <input type="hidden" name="device_browser" id="device_browser">
        <div class="form-block space-y-4">
          <div class="field">
            <input id="username" name="username" type="text" autocomplete="username" class="input peer" placeholder=" " required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" <?php echo $isLocked ? 'disabled' : ''; ?>>
            <label for="username" class="float-label">Username</label>
            <span class="icon-right" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-5.33 0-8 2.67-8 5v1h16v-1c0-2.33-2.67-5-8-5Z" fill="currentColor"/></svg>
            </span>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
            </div>
            <div class="field">
              <input id="password" name="password" type="password" autocomplete="current-password" class="input peer" placeholder=" " required <?php echo $isLocked ? 'disabled' : ''; ?>>
              <label for="password" class="float-label">••••••••</label>
              <button type="button" id="togglePassword" class="icon-right" aria-label="Show password" title="Show password">
                <svg id="eyeOpen" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 5c-5 0-9 4.5-10 7 1 2.5 5 7 10 7s9-4.5 10-7c-1-2.5-5-7-10-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z" fill="currentColor"/></svg>
                <svg id="eyeClosed" width="20" height="20" viewBox="0 0 24 24" fill="none" style="display:none;"><path d="M3 5.27 4.28 4 20 19.72 18.73 21l-3.1-3.1A10.66 10.66 0 0 1 12 19c-5 0-9-4.5-10-7a17.3 17.3 0 0 1 4.22-5.6L3 5.27ZM12 7c5 0 9 4.5 10 7a17.5 17.5 0 0 1-3.2 4.4l-2.2-2.2a4 4 0 0 0-5.8-5.8L8.7 8.1A8.8 8.8 0 0 1 12 7Z" fill="currentColor"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="recaptcha-wrap">
          <div class="flex items-center gap-3 flex-wrap">
            <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
            <button type="button" id="qrScanBtn" class="btn-ghost qr-side" aria-label="Fast QR Login" title="Fast QR Login">
              <img src="qricon.png" alt="" class="qr-icon">
            </button>
          </div>
        </div>

        <button type="submit" class="btn" <?php echo $isLocked ? 'disabled' : ''; ?>>
          <span><?php echo $isLocked ? 'Account Locked' : 'Sign In'; ?></span>
        </button>

        <p class="text-xs text-center text-slate-500 dark:text-slate-400">© 2025 ATIERA</p>
      </form>
    </div>
  </main>

  <div id="qrModal" class="qr-modal" aria-hidden="true">
    <div class="qr-panel">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-lg font-semibold">Scan QR Code</h4>
        <button type="button" id="qrClose" class="px-3 py-1 rounded-lg border border-slate-200">Close</button>
      </div>
      <p class="text-sm text-slate-500 mb-3">Point your camera at your ATIERA Fast Login QR card.</p>
      <div id="qrReader"></div>
      <div id="qrStatus" class="text-sm text-slate-500 mt-3"></div>
    </div>
  </div>

<script>
  const $ = (s, r=document)=>r.querySelector(s);

  const modeBtn = $('#modeBtn');
  const wmImg = $('#wm');
  const togglePassword = $('#togglePassword');
  const passwordInput = $('#password');
  const eyeOpen = $('#eyeOpen');
  const eyeClosed = $('#eyeClosed');

  modeBtn.addEventListener('click', ()=>{
    const root = document.documentElement;
    const dark = root.classList.toggle('dark');
    modeBtn.setAttribute('aria-pressed', String(dark));
    wmImg.style.transform = 'scale(1.01)'; setTimeout(()=> wmImg.style.transform = '', 220);
  });

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
      const isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      togglePassword.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      togglePassword.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
      if (eyeOpen && eyeClosed) {
        eyeOpen.style.display = isHidden ? 'none' : 'inline';
        eyeClosed.style.display = isHidden ? 'inline' : 'none';
      }
    });
  }

  (function() {
    const form = document.querySelector('form');
    if (!form) return;

    const labelInput = document.getElementById('device_label');
    const modelInput = document.getElementById('device_model');
    const platformInput = document.getElementById('device_platform');
    const typeInput = document.getElementById('device_type');
    const osInput = document.getElementById('device_os');
    const browserInput = document.getElementById('device_browser');

    function detectFromUA(ua) {
      const lower = (ua || '').toLowerCase();
      let deviceType = 'Desktop';
      if (/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|mediapartners-google/.test(lower)) {
        deviceType = 'Bot';
      } else if (/ipad|tablet|android(?!.*mobile)/.test(lower)) {
        deviceType = 'Tablet';
      } else if (/mobile|iphone|ipod|android.*mobile|windows phone/.test(lower)) {
        deviceType = 'Mobile';
      }

      let os = 'Unknown';
      if (/windows nt 11\.0/.test(lower)) os = 'Windows 11';
      else if (/windows nt 10\.0/.test(lower)) os = 'Windows 10';
      else if (/windows nt 6\.3/.test(lower)) os = 'Windows 8.1';
      else if (/windows nt 6\.1/.test(lower)) os = 'Windows 7';
      else if (/mac os x/.test(lower)) os = 'macOS';
      else if (/android/.test(lower)) os = 'Android';
      else if (/iphone|ipad|ipod/.test(lower)) os = 'iOS';
      else if (/linux/.test(lower)) os = 'Linux';

      let browser = 'Unknown';
      if (/edg\//.test(lower)) browser = 'Edge';
      else if (/chrome\/|crios\//.test(lower)) browser = 'Chrome';
      else if (/firefox\//.test(lower)) browser = 'Firefox';
      else if (/safari\//.test(lower) && !/chrome\/|crios\/|edg\//.test(lower)) browser = 'Safari';

      let model = '';
      const androidMatch = ua && ua.match(/Android\s[\d.]+;\s([^;]+)\sBuild/i);
      if (androidMatch && androidMatch[1]) {
        model = androidMatch[1].trim();
      } else if (/iphone/.test(lower)) {
        model = 'iPhone';
      } else if (/ipad/.test(lower)) {
        model = 'iPad';
      }

      return { deviceType, os, browser, model };
    }

    async function fillDeviceFields() {
      const ua = navigator.userAgent || '';
      const uaData = navigator.userAgentData;
      let model = '';
      let platform = '';
      let deviceType = '';
      let os = '';
      let browser = '';

      if (uaData && uaData.getHighEntropyValues) {
        try {
          const hints = await uaData.getHighEntropyValues(['model', 'platform', 'platformVersion', 'fullVersionList']);
          model = hints.model || '';
          platform = hints.platform || '';
          if (hints.fullVersionList && hints.fullVersionList.length) {
            const preferred = hints.fullVersionList.find(item => item.brand && !/not.a.brand|not a brand/i.test(item.brand))
              || hints.fullVersionList[0];
            browser = preferred && preferred.brand ? preferred.brand.replace('Google ', '') : '';
          }
        } catch (e) {}
      }

      const fallback = detectFromUA(ua);
      deviceType = deviceType || fallback.deviceType;
      os = os || fallback.os;
      if (!browser || /not.a.brand|not a brand/i.test(browser)) {
        browser = fallback.browser;
      }
      model = model || fallback.model;
      platform = platform || navigator.platform || '';

      let label = '';
      if (model && platform) {
        label = `${model} (${platform})`;
      } else if (model) {
        label = model;
      } else if (deviceType && os) {
        label = `${deviceType} • ${os}`;
      } else {
        label = 'Unknown Device';
      }

      if (labelInput) labelInput.value = label;
      if (modelInput) modelInput.value = model;
      if (platformInput) platformInput.value = platform;
      if (typeInput) typeInput.value = deviceType;
      if (osInput) osInput.value = os;
      if (browserInput) browserInput.value = browser;
    }

    form.addEventListener('submit', async function(e) {
      if (form.dataset.deviceReady === '1') return;
      e.preventDefault();
      await fillDeviceFields();
      form.dataset.deviceReady = '1';
      form.submit();
    });
    fillDeviceFields();
  })();

  // Handle lockout countdown
  <?php if ($isLocked): ?>
  function updateLockoutCountdown() {
    const remaining = <?php echo $lockout['remaining']; ?>;
    if (remaining > 0) {
      const btn = document.querySelector('button[type="submit"]');
      const span = btn.querySelector('span');
      
      const countdown = setInterval(() => {
        const currentRemaining = Math.max(0, remaining - Math.floor((Date.now() - startTime) / 1000));
        if (currentRemaining <= 0) {
          clearInterval(countdown);
          location.reload();
          return;
        }
        span.textContent = `Locked (${currentRemaining}s)`;
      }, 1000);
      
      const startTime = Date.now();
    }
  }
  
  updateLockoutCountdown();
  <?php endif; ?>

  // Auto-hide logout message after 5 seconds
  <?php if (!empty($info) && strpos($info, 'logged out') !== false): ?>
  setTimeout(() => {
    const alertBox = document.querySelector('.alert');
    if (alertBox) {
      alertBox.style.transition = 'opacity 0.5s ease';
      alertBox.style.opacity = '0';
      setTimeout(() => {
        alertBox.remove();
      }, 500);
    }
  }, 5000);
  <?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
  const qrScanBtn = document.getElementById("qrScanBtn");
  const qrModal = document.getElementById("qrModal");
  const qrClose = document.getElementById("qrClose");
  const qrStatus = document.getElementById("qrStatus");
  let qrScanner = null;
  let qrHandled = false;

  function openQrModal() {
    if (!qrModal) return;
    qrModal.classList.add("show");
    qrModal.setAttribute("aria-hidden", "false");
    startQrScanner();
  }

  function closeQrModal() {
    if (!qrModal) return;
    qrModal.classList.remove("show");
    qrModal.setAttribute("aria-hidden", "true");
    stopQrScanner();
  }

  async function startQrScanner() {
    if (!window.Html5Qrcode) {
      if (qrStatus) qrStatus.textContent = "QR scanner is unavailable in this browser.";
      return;
    }
    qrHandled = false;
    try {
      qrScanner = new Html5Qrcode("qrReader");
      await qrScanner.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 240, height: 240 } },
        (decodedText) => handleQrResult(decodedText),
        () => {}
      );
    } catch (err) {
      if (qrStatus) qrStatus.textContent = "Unable to access the camera. Please allow permissions.";
    }
  }

  async function stopQrScanner() {
    if (qrScanner) {
      try {
        await qrScanner.stop();
      } catch (e) {}
      qrScanner.clear();
      qrScanner = null;
    }
  }

  function handleQrResult(decodedText) {
    if (qrHandled) return;
    qrHandled = true;
    const raw = (decodedText || "").trim();
    if (qrStatus) qrStatus.textContent = "QR code detected. Signing in...";
    stopQrScanner();

    function extractToken(value) {
      const text = (value || "").trim();
      if (!text) return "";

      // Raw hex token generated by qr_login_generate_code().
      if (/^[a-f0-9]{64}$/i.test(text)) {
        return text;
      }

      // Handle full/relative qr-login URLs.
      if (/qr-login\.php/i.test(text)) {
        try {
          const url = new URL(text, window.location.origin);
          const t = (url.searchParams.get("t") || "").trim();
          if (t) return t;
        } catch (e) {}

        const match = text.match(/[?&]t=([^&#\s]+)/i);
        if (match && match[1]) {
          try {
            return decodeURIComponent(match[1]).trim();
          } catch (e) {
            return match[1].trim();
          }
        }
      }

      return text;
    }

    const token = extractToken(raw);
    if (!token) {
      qrHandled = false;
      if (qrStatus) qrStatus.textContent = "Could not read QR token. Please try scanning again.";
      return;
    }

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "qr-login.php";
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "token";
    input.value = token;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }

  if (qrScanBtn) qrScanBtn.addEventListener("click", openQrModal);
  if (qrClose) qrClose.addEventListener("click", closeQrModal);
  if (qrModal) {
    qrModal.addEventListener("click", (e) => {
      if (e.target === qrModal) closeQrModal();
    });
  }
</script>
<script src="includes/tab_persistence.js?v=1"></script>
</body>
</html>






