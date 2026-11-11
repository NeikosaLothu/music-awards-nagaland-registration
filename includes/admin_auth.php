<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

admin_session_start();

function admin_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
 * Neutralise CSV/spreadsheet formula injection. Excel, Sheets, and
 * LibreOffice all treat a cell that starts with =, +, -, or @ as a
 * formula when the file is opened - which is how a submitted "song
 * title" or "address" could otherwise run a formula (or, in older
 * Excel versions, a DDE command) on whoever opens the export.
 *
 * Prefixing a leading apostrophe is the standard, minimally-invasive
 * fix: spreadsheet software shows the value as plain text starting
 * from the first real character and drops the apostrophe from the
 * display, so normal values are unaffected. The one visible side
 * effect is contact numbers that start with "+" (e.g. +919876543210)
 * also get neutralised, which is an accepted, standard trade-off for
 * this protection.
 */
function csv_safe(mixed $value): string
{
    $value = (string)$value;

    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }

    return $value;
}

function admin_csrf(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['admin_csrf'];
}

function admin_check_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['admin_csrf']) && hash_equals((string)$_SESSION['admin_csrf'], $token);
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['man_admin_id']);
}

function admin_base_url(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (basename($dir) === 'dashboard') {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }
    return $dir === '/' ? '' : $dir;
}

function admin_url(string $path = ''): string
{
    return admin_base_url() . ($path !== '' && $path[0] !== '/' ? '/' . $path : $path);
}

// Idle admin sessions are force-logged-out after this many seconds.
const ADMIN_SESSION_IDLE_LIMIT = 7200; // 2 hours

function require_admin(): void
{
    if (!admin_is_logged_in()) {
        header('Location: ' . admin_url('/login.php'));
        exit;
    }

    $lastActivity = (int)($_SESSION['man_admin_last_activity'] ?? $_SESSION['man_admin_login_at'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > ADMIN_SESSION_IDLE_LIMIT) {
        admin_logout();
        header('Location: ' . admin_url('/login.php'));
        exit;
    }

    $_SESSION['man_admin_last_activity'] = time();
}

function admin_user(): array
{
    return [
        'id' => $_SESSION['man_admin_id'] ?? null,
        'username' => $_SESSION['man_admin_username'] ?? '',
        'display_name' => $_SESSION['man_admin_display_name'] ?? 'Judge / Admin',
    ];
}

function admin_login(string $identity, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, display_name FROM man_admins WHERE username = :username OR email = :email LIMIT 1');
    $identity = trim($identity);
    $stmt->execute(['username' => $identity, 'email' => $identity]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['man_admin_id'] = (int)$admin['id'];
    $_SESSION['man_admin_username'] = (string)$admin['username'];
    $_SESSION['man_admin_display_name'] = (string)$admin['display_name'];
    $_SESSION['man_admin_login_at'] = time();
    admin_csrf();

    if (password_needs_rehash((string)$admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE man_admins SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $update->execute(['hash' => $newHash, 'id' => (int)$admin['id']]);
    }

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function admin_status(PDO $pdo, string $submissionId): string
{
    $stmt = $pdo->prepare('SELECT status FROM man_submission_status WHERE submission_id = :id LIMIT 1');
    $stmt->execute(['id' => $submissionId]);
    $status = $stmt->fetchColumn();
    return $status ?: 'Submitted';
}

function admin_statuses(): array
{
    return ['Submitted', 'Under Review', 'Reviewed', 'Approved', 'Rejected'];
}

function ensure_submission_status(PDO $pdo, string $submissionId): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO man_submission_status (submission_id, status) VALUES (:id, \'Submitted\')');
    $stmt->execute(['id' => $submissionId]);
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        'Reviewed' => 'status-reviewed',
        'Under Review' => 'status-review',
        default => 'status-submitted',
    };
}

/*
 * Small inline-SVG icon set for the admin UI (Feather-style: 24x24,
 * stroke-based, currentColor). Centralised here so every page draws
 * icons the same way. $name is always a fixed string literal from our
 * own templates, never user input, so this intentionally returns raw
 * markup rather than something to pass through admin_e().
 */
function admin_icon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'tag' => '<path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.35-4.35a2 2 0 0 0 0-2.83Z"/><circle cx="7.5" cy="7.5" r="1.25" fill="currentColor" stroke="none"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'music' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'video' => '<rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 9 5-3v12l-5-3"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>',
        'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'external-link' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'trophy' => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4a1 1 0 0 0-1 1 5 5 0 0 0 4 4.9"/><path d="M17 5h3a1 1 0 0 1 1 1 5 5 0 0 1-4 4.9"/>',
        'mic' => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'menu' => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
        'close' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6.34 6.34l1.41 1.41M16.25 16.25l1.41 1.41M6.34 17.66l1.41-1.41M16.25 7.75l1.41-1.41" stroke-linecap="round"/>',
    ];

    $inner = $paths[$name] ?? '';
    return '<svg class="icon icon-' . $name . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
