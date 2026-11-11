<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(array $data, string $key): string
{
    return e($data[$key] ?? '');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function categories(): array
{
    return [
        'Rock',
        'Hip-Hop/R&B',
        'Gospel',
        'Regional/ Folk Fusion',
        'Pop',
        'Best Cover Song',
        'Song of the Year',
    ];
}

function collect_form_data(array $input): array
{
    $keys = [
        'category','artist_name','address','band_page',
        'contact_name','contact_number','song_title','songwriter',
        'sound_engineer','platform_audio_link','music_video_link',
        'lyrics','producer','video_director','recorded_at'
    ];

    $data = [];
    foreach ($keys as $key) {
        $value = trim((string)($input[$key] ?? ''));
        $data[$key] = preg_replace('/\r\n|\r|\n/', "\n", $value) ?? $value;
    }

    return $data;
}

function validate_submission(array $data): array
{
    $errors = [];

    $required = [
        'category' => 'Category is required.',
        'artist_name' => 'Official artist/band name is required.',
        'address' => 'Address is required.',
        'band_page' => 'Band page/website/social media URL is required.',
        'contact_name' => 'Contact name is required.',
        'contact_number' => 'Contact number is required.',
        'song_title' => 'Song title is required.',
        'songwriter' => 'Songwriter is required.',
        'sound_engineer' => 'Sound engineer is required.',
        'platform_audio_link' => 'Platform audio link is required.',
        'music_video_link' => 'Music video link is required.',
        'lyrics' => 'Lyrics are required.',
        'producer' => 'Producer is required.',
        'recorded_at' => 'Recorded At is required.',
    ];

    foreach ($required as $field => $message) {
        if (($data[$field] ?? '') === '') {
            $errors[$field] = $message;
        }
    }

    if (($data['category'] ?? '') !== '' && !in_array($data['category'], categories(), true)) {
        $errors['category'] = 'Invalid category selected.';
    }

    $lengths = [
        'artist_name' => 150,
        'address' => 500,
        'contact_name' => 150,
        'contact_number' => 20,
        'song_title' => 200,
        'songwriter' => 200,
        'sound_engineer' => 200,
        'producer' => 200,
        'video_director' => 200,
        'recorded_at' => 200,
        'lyrics' => 20000,
        'band_page' => 2048,
        'platform_audio_link' => 2048,
        'music_video_link' => 2048,
    ];

    foreach ($lengths as $field => $max) {
        if (isset($data[$field]) && mb_strlen($data[$field]) > $max) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " is too long.";
        }
    }

    foreach (['band_page','platform_audio_link','music_video_link'] as $field) {
        if (($data[$field] ?? '') !== '' && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
            $errors[$field] = 'Please provide a valid URL for ' . str_replace('_', ' ', $field) . '.';
        } elseif (($data[$field] ?? '') !== '') {
            $scheme = strtolower((string)parse_url($data[$field], PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                $errors[$field] = 'URL must begin with http:// or https://.';
            }
        }
    }

    if (($data['music_video_link'] ?? '') !== '' && !is_youtube_url($data['music_video_link'])) {
        $errors['music_video_link'] = 'Music Video Link must be a valid YouTube URL.';
    }

    if (($data['contact_number'] ?? '') !== '') {
        $digits = preg_replace('/\D+/', '', $data['contact_number']) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 13) {
            $errors['contact_number'] = 'Please provide a valid Indian contact number.';
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            // Common 0-prefixed Indian mobile format, accepted.
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            // Common +91 / 91 format, accepted.
        } elseif (strlen($digits) === 10 && !in_array($digits[0], ['6','7','8','9'], true)) {
            $errors['contact_number'] = 'Please provide a valid Indian mobile number.';
        }
    }

    return $errors;
}

function is_youtube_url(string $url): bool
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?? $host;

    return in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true);
}

function generate_submission_id(): string
{
    return 'MAN2026-' . strtoupper(bin2hex(random_bytes(3)));
}

function find_submission(PDO $pdo, string $submissionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT submission_id, artist_name, payment_status, razorpay_payment_id, payment_updated_at
         FROM submissions WHERE submission_id = :submission_id LIMIT 1'
    );
    $stmt->execute(['submission_id' => $submissionId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Duplicate-payment protection: true if this Razorpay payment_id has already
 * been recorded against a submission. Called before inserting a new
 * submission row so the same captured payment can never be attached to two
 * applications, even if session data were somehow replayed or tampered with.
 * (The database also enforces this at the schema level via a UNIQUE key on
 * submissions.razorpay_payment_id, present in the database export — this check
 * just gives a clean, specific error message instead of a raw SQL failure.)
 */
function is_payment_id_already_used(PDO $pdo, string $paymentId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM submissions WHERE razorpay_payment_id = :payment_id LIMIT 1');
    $stmt->execute(['payment_id' => $paymentId]);

    return (bool)$stmt->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| Persistent, session-independent rate limiting
|--------------------------------------------------------------------------
|
| Used for admin login attempts (includes/admin_auth.php, login.php) and
| Razorpay order creation (payment.php). Session-based counters can be
| trivially reset by an attacker just by discarding their session
| cookie/opening a new one, so this is backed by a small MySQL table
| instead. The table is created on first use, so no manual migration is
| required (database/migration_add_rate_limits_table.sql is provided too,
| for anyone who prefers to run it explicitly).
*/

function ensure_rate_limit_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS man_rate_limits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bucket VARCHAR(30) NOT NULL,
            subject_key VARCHAR(190) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_bucket_subject_time (bucket, subject_key, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function rate_limit_count(PDO $pdo, string $bucket, string $subjectKey, int $windowSeconds): int
{
    ensure_rate_limit_table($pdo);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM man_rate_limits
         WHERE bucket = :bucket AND subject_key = :subject_key
           AND created_at >= (NOW() - INTERVAL :window_seconds SECOND)'
    );
    $stmt->bindValue('bucket', $bucket);
    $stmt->bindValue('subject_key', $subjectKey);
    $stmt->bindValue('window_seconds', $windowSeconds, PDO::PARAM_INT);
    $stmt->execute();

    return (int)$stmt->fetchColumn();
}

function rate_limit_hit(PDO $pdo, string $bucket, string $subjectKey): void
{
    ensure_rate_limit_table($pdo);

    $stmt = $pdo->prepare('INSERT INTO man_rate_limits (bucket, subject_key) VALUES (:bucket, :subject_key)');
    $stmt->execute(['bucket' => $bucket, 'subject_key' => $subjectKey]);

    // Opportunistic cleanup (no cron needed on shared hosting) - only
    // runs on a small fraction of calls, since this table only grows on
    // failed logins / new payment orders, not on every page view.
    if (random_int(1, 20) === 1) {
        $pdo->exec('DELETE FROM man_rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}
