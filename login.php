<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';

if (admin_is_logged_in()) {
    header('Location: ' . admin_url('/dashboard'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Rate limiting is backed by MySQL (man_rate_limits), not the PHP
    // session - a session-based counter can be reset by an attacker just
    // by discarding their session cookie and starting a new one, which
    // defeats the whole point. This is keyed on both the attempted
    // account and the client IP, so neither alone can be brute-forced.
    $pdo = db();
    $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $identityKey = 'id:' . strtolower($identity);
    $ipKey = 'ip:' . $clientIp;
    $identityAttempts = $identity !== '' ? rate_limit_count($pdo, 'admin_login', $identityKey, 900) : 0;
    $ipAttempts = rate_limit_count($pdo, 'admin_login', $ipKey, 900);

    if ($identityAttempts >= 8 || $ipAttempts >= 20) {
        $error = 'Too many unsuccessful attempts. Please wait 15 minutes and try again.';
    } elseif (!admin_check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please refresh and try again.';
    } elseif ($identity === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } elseif (admin_login($identity, $password)) {
        $next = (string)($_SESSION['after_login'] ?? '/dashboard');
        unset($_SESSION['after_login']);
        header('Location: ' . admin_url(str_starts_with($next, '/') ? $next : '/dashboard'));
        exit;
    } else {
        if ($identity !== '') {
            rate_limit_hit($pdo, 'admin_login', $identityKey);
        }
        rate_limit_hit($pdo, 'admin_login', $ipKey);
        $error = 'Invalid username/email or password.';
    }
}

$csrf = admin_csrf();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Judge / Admin Login · Music Awards of Nagaland</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= admin_url('/assets/css/admin.css') ?>">
</head><body class="login-body">
<div class="login-bg"></div>
<div class="login-page">
  <section class="login-card">
    <div class="login-brand">
      <div class="login-mark"><?= admin_icon('trophy') ?></div>
      <div>
        <div class="login-brand-name">Music Awards of Nagaland</div>
        <div class="login-year">18th Edition · 2026</div>
      </div>
    </div>
    <div class="gold-rule"></div>
    <div class="portal-label">Private portal</div>
    <h1>Judge &amp; Admin<br>Access</h1>
    <p class="login-intro">Sign in to review, score and manage music award submissions.</p>
    <?php if ($error): ?><div class="login-error"><?= admin_e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on" novalidate>
      <input type="hidden" name="csrf" value="<?= admin_e($csrf) ?>">
      <label class="field-label" for="identity">Username or email</label>
      <div class="field-group">
        <span class="icon-affix"><?= admin_icon('user') ?></span>
        <input class="admin-input" id="identity" name="identity" type="text" autocomplete="username" required autofocus>
      </div>
      <label class="field-label" for="password">Password</label>
      <div class="field-group password-wrap">
        <span class="icon-affix"><?= admin_icon('lock') ?></span>
        <input class="admin-input" id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="button" class="password-toggle" data-target="password">Show</button>
      </div>
      <button class="login-button" type="submit">Sign in <?= admin_icon('arrow-right') ?></button>
    </form>
    <div class="login-note"><?= admin_icon('lock') ?> Authorized access only</div>
  </section>
</div>
<script>
document.querySelector('.password-toggle')?.addEventListener('click', function(){const i=document.getElementById(this.dataset.target); i.type=i.type==='password'?'text':'password'; this.textContent=i.type==='password'?'Show':'Hide';});
</script>
</body></html>
