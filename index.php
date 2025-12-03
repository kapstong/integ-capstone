<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
$auth = new Auth();

// Initialize variables
$info = '';
$error = '';

// Handle logout first, before any other logic
if (isset($_GET['logout'])) {
    $auth->logout();
    $info = 'You have been logged out successfully.';
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
    if ($role === 'admin') {
        header('Location: admin/index.php');
    } elseif ($role === 'staff') {
        header('Location: user/index.php');
    } else {
        header('Location: user/index.php'); // Default to staff dashboard
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
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $result = $auth->login($username, $password);

        if ($result['success']) {
            // Check if user has 2FA enabled
            require_once 'includes/two_factor_auth.php';
            $twoFA = TwoFactorAuth::getInstance();

            if ($twoFA->is2FAEnabled($result['user']['id'])) {
                // Store pending 2FA verification in session
                $_SESSION['pending_2fa_user_id'] = $result['user']['id'];
                $_SESSION['pending_2fa_user'] = $result['user'];

                // Clear the logged in session temporarily
                unset($_SESSION['user']);

                // Redirect to 2FA verification page
                header('Location: verify_2fa.php');
                exit();
            }

            // No 2FA required, proceed with normal login
            // Route users based on their role
            $role = strtolower($result['user']['role_name'] ?? '');
            if ($role === 'admin') {
                $target = 'admin/index.php';
            } elseif ($role === 'staff') {
                $target = 'user/index.php';
            } else {
                $target = 'user/index.php'; // Default to staff dashboard
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
    background:var(--card-bg); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
    border:1px solid var(--card-border); border-radius:18px; box-shadow:0 16px 48px rgba(2,6,23,.18);
  }
  html.dark .card{ background:rgba(17,24,39,.92); border-color:rgba(71,85,105,.55); box-shadow:0 16px 48px rgba(0,0,0,.5); }

  .field{ position:relative; }
  .input{
    width:100%; border:1px solid #e5e7eb; border-radius:12px; background:#fff;
    padding:1rem 2.6rem 1rem .95rem; outline:none; color:#0f172a; transition:border-color .15s, box-shadow .15s, background .15s;
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

  .btn{
    width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:linear-gradient(180deg, var(--blue-600), var(--blue-800));
    color:#fff; font-weight:800; border-radius:14px; padding:.95rem 1rem; border:1px solid rgba(255,255,255,.06);
    transition:transform .08s ease, filter .15s ease, box-shadow .2s ease; box-shadow:0 8px 18px rgba(2,6,23,.18);
  }
  .btn:hover{ filter:saturate(1.08); box-shadow:0 12px 26px rgba(2,6,23,.26); }
  .btn:active{ transform:translateY(1px) scale(.99); }
  .btn[disabled]{ opacity:.85; cursor:not-allowed; }

  .alert{ border-radius:12px; padding:.65rem .8rem; font-size:.9rem; transition:opacity 0.5s ease }
  .alert-error{ border:1px solid #fecaca; background:#fef2f2; color:#b91c1c }
  .alert-info{ border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3 }
  html.dark .alert-error{ background:#3f1b1b; border-color:#7f1d1d; color:#fecaca }
  html.dark .alert-info{ background:#1e1b4b; border-color:#3730a3; color:#c7d2fe }

  .typing::after{ content:'|'; margin-left:2px; opacity:.6; animation: blink 1s steps(1) infinite; }
  @keyframes blink { 50%{opacity:0} }
</style>
</head>

<body class="grid md:grid-cols-2 gap-0 place-items-center p-6 md:p-10">

  <div class="bg-watermark" aria-hidden="true">
    <img src="logo.png" alt="ATIERA watermark" id="wm">
  </div>

  <section class="hidden md:flex w-full h-full items-center justify-center">
    <div class="max-w-lg text-white px-6 reveal">
      <img src="logo.png" alt="ATIERA" class="w-56 mb-6 drop-shadow-xl select-none" draggable="false">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
        ATIERA <span style="color:var(--gold)">HOTEL & RESTAURANT</span> Management
      </h1>
      <p class="mt-4 text-white/90 text-lg">Secure • Fast • Intuitive</p>
    </div>
  </section>

  <main class="w-full max-w-md md:ml-auto">
    <div id="card" class="card p-6 sm:p-8 reveal">
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
          </div>
        </div>

        <button type="submit" class="btn" <?php echo $isLocked ? 'disabled' : ''; ?>>
          <span><?php echo $isLocked ? 'Account Locked' : 'Sign In'; ?></span>
        </button>

        <p class="text-xs text-center text-slate-500 dark:text-slate-400">© 2025 ATIERA BSIT 4101 CLUSTER 1</p>
      </form>
    </div>
  </main>

<script>
  const $ = (s, r=document)=>r.querySelector(s);

  const modeBtn = $('#modeBtn');
  const wmImg = $('#wm');

  modeBtn.addEventListener('click', ()=>{
    const root = document.documentElement;
    const dark = root.classList.toggle('dark');
    modeBtn.setAttribute('aria-pressed', String(dark));
    wmImg.style.transform = 'scale(1.01)'; setTimeout(()=> wmImg.style.transform = '', 220);
  });

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
  <?php if (isset($_GET['logout'])): ?>
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

</body>
</html>
