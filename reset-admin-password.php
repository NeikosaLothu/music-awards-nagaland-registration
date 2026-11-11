<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';

$pdo = db();

/*
 * One-time admin password reset. Mirrors setup-admin.php's security model:
 * gated behind a secret only you know, rate-limited, and safe to leave in
 * place only briefly. Delete this file as soon as you're back in.
 *
 * Reuses SETUP_ADMIN_SECRET if you already set one (env var or
 * config/secrets.local.php) so this works immediately with no extra
 * configuration; set RESET_ADMIN_SECRET instead if you'd rather use a
 * different value.
 */
$resetSecret = env_value('RESET_ADMIN_SECRET', env_value('SETUP_ADMIN_SECRET', ''));

if ($resetSecret === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$providedKey = (string)($_POST['reset_key'] ?? $_GET['key'] ?? '');
if (!hash_equals($resetSecret, $providedKey)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

// Same persistent, IP-based throttle used on the real login form - this
// page changes a password, so it deserves the same protection even
// though the secret key already gates it.
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (rate_limit_count($pdo, 'reset_password', 'ip:' . $clientIp, 900) >= 8) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Too many attempts. Wait 15 minutes and try again.';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session token. Refresh the page.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($username === '') {
            $error = 'Enter the username or email of the account to reset.';
        } elseif (strlen($pass) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif ($pass !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM man_admins WHERE username = :u OR email = :u LIMIT 1');
            $stmt->execute(['u' => $username]);
            $adminId = $stmt->fetchColumn();
            rate_limit_hit($pdo, 'reset_password', 'ip:' . $clientIp);

            if (!$adminId) {
                $error = 'No admin account matches that username or email.';
            } else {
                $update = $pdo->prepare('UPDATE man_admins SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
                $update->execute(['hash' => password_hash($pass, PASSWORD_DEFAULT), 'id' => (int)$adminId]);
                $success = 'Password updated. Delete reset-admin-password.php from the server now, then sign in.';
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Admin Password · Music Awards of Nagaland</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= admin_url('/assets/css/admin.css') ?>"></head>
<body class="login-body"><div class="login-bg"></div><div class="login-page"><section class="login-card">
<div class="login-brand"><div class="login-mark"><?= admin_icon('lock') ?></div><div><div class="login-brand-name">Music Awards of Nagaland</div><div class="login-year">18th Edition · 2026</div></div></div>
<div class="gold-rule"></div>
<div class="portal-label">One-time reset</div>
<h1>Reset admin password</h1>
<p class="login-intro">Use this once to regain access, then delete this file.</p>
<?php if ($error): ?><div class="login-error"><?= admin_e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="login-success"><?= admin_e($success) ?></div><?php endif; ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf()) ?>">
  <input type="hidden" name="reset_key" value="<?= admin_e($providedKey) ?>">
  <label class="field-label" for="username">Username or email</label>
  <div class="field-group"><span class="icon-affix"><?= admin_icon('user') ?></span><input class="admin-input" id="username" name="username" required autofocus></div>
  <label class="field-label" for="password">New password</label>
  <div class="field-group password-wrap"><span class="icon-affix"><?= admin_icon('lock') ?></span><input class="admin-input" id="password" name="password" type="password" minlength="10" required><button type="button" class="password-toggle" data-target="password">Show</button></div>
  <label class="field-label" for="confirm_password">Confirm new password</label>
  <div class="field-group password-wrap"><span class="icon-affix"><?= admin_icon('lock') ?></span><input class="admin-input" id="confirm_password" name="confirm_password" type="password" minlength="10" required><button type="button" class="password-toggle" data-target="confirm_password">Show</button></div>
  <button class="login-button" type="submit">Reset password <?= admin_icon('arrow-right') ?></button>
</form>
</section></div>
<script>
document.querySelectorAll('.password-toggle').forEach(function(btn){btn.addEventListener('click', function(){const i=document.getElementById(this.dataset.target); i.type=i.type==='password'?'text':'password'; this.textContent=i.type==='password'?'Show':'Hide';});});
</script>
</body></html>
