<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';

// Logout changes session state, so it must be a CSRF-protected POST, not
// a plain GET link (a GET logout link can be triggered cross-site simply
// by loading an <img>/<a> pointing at it).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_check_csrf($_POST['csrf'] ?? null)) {
    header('Location: ' . admin_url(admin_is_logged_in() ? '/dashboard' : '/login.php'));
    exit;
}

admin_logout();
header('Location: ' . admin_url('/login.php'));
exit;
