<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS man_admins (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(80) NOT NULL, email VARCHAR(190) NULL, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(120) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uq_username(username), UNIQUE KEY uq_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$count = (int)$pdo->query('SELECT COUNT(*) FROM man_admins')->fetchColumn();

/*
 * This page must stop working the instant an admin account exists, and
 * it must not sit open on the public internet before the deployer has
 * chosen an installation secret. Both cases return the same generic 404
 * below, so a random visitor cannot distinguish "setup already done"
 * from "not configured yet" from "wrong key" - the page is simply gone
 * as far as they can tell.
 *
 * Set SETUP_ADMIN_SECRET as a real server environment variable, or in
 * config/secrets.local.php (see config/secrets.local.example.php), then
 * open this file with ?key=that-value to create the first admin account.
 */
$setupSecret = env_value('SETUP_ADMIN_SECRET', '');

if ($count > 0 || $setupSecret === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$providedKey = (string)($_POST['setup_key'] ?? $_GET['key'] ?? '');
if (!hash_equals($setupSecret, $providedKey)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session token. Refresh the page.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['display_name'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) {
            $error = 'Username must be 3–80 characters and contain only letters, numbers, dot, underscore or hyphen.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address or leave it blank.';
        } elseif ($name === '') {
            $error = 'Display name is required.';
        } elseif (strlen($pass) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif ($pass !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $st = $pdo->prepare('INSERT INTO man_admins(username,email,password_hash,display_name) VALUES(:u,:e,:p,:n)');
            $st->execute([
                'u' => $username,
                'e' => $email ?: null,
                'p' => password_hash($pass, PASSWORD_DEFAULT),
                'n' => $name,
            ]);
            $success = 'Admin account created. This setup page is now disabled. Delete setup-admin.php from the server, then open /login.';
            $count = 1;
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Setup · Music Awards of Nagaland</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= admin_url('/assets/css/admin.css') ?>"></head><body class="login-body"><div class="login-bg"></div><div class="login-page"><section class="login-card"><div class="login-brand"><div class="login-mark"><?= admin_icon('trophy') ?></div><div><div class="login-brand-name">Music Awards of Nagaland</div><div class="login-year">18th Edition · 2026</div></div></div><div class="gold-rule"></div><div class="portal-label">One-time setup</div><h1>Create admin account</h1><p class="login-intro">Use this page once. Delete <strong>setup-admin.php</strong> immediately after creating the account.</p><?php if($error): ?><div class="login-error"><?=admin_e($error)?></div><?php endif; ?><?php if($success): ?><div class="login-success"><?=admin_e($success)?></div><?php endif; ?><?php if($count===0): ?><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="setup_key" value="<?=admin_e($providedKey)?>"><label class="field-label">Username</label><input class="admin-input" name="username" required><label class="field-label">Email <span class="optional">optional</span></label><input class="admin-input" type="email" name="email"><label class="field-label">Display name</label><input class="admin-input" name="display_name" required><label class="field-label">Password</label><input class="admin-input" type="password" name="password" minlength="10" required><label class="field-label">Confirm password</label><input class="admin-input" type="password" name="confirm_password" minlength="10" required><button class="login-button" type="submit">Create account <?= admin_icon('arrow-right') ?></button></form><?php endif; ?></section></div></body></html>
