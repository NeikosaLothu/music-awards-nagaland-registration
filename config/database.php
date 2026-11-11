<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/*
 * HOSTINGER DATABASE CONFIGURATION
 * --------------------------------
 * The values below are placeholders. Provide real credentials one of
 * two ways (see config/secrets.local.example.php):
 *
 *   1. Set DB_HOST / DB_NAME / DB_USER / DB_PASS as real server
 *      environment variables, or
 *   2. Copy config/secrets.local.example.php to config/secrets.local.php
 *      and fill them in there.
 *
 * Do NOT put these credentials in JavaScript or HTML, and do not commit
 * real credentials into this file.
 *
 * Use a dedicated MySQL user created for this application (Hostinger
 * hPanel > MySQL Databases) with access to only this database - never
 * the MySQL root account, and never an empty password.
 */

define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_NAME', env_value('DB_NAME', 'music_awards_nagaland'));
define('DB_USER', env_value('DB_USER', 'YOUR_HOSTINGER_DB_USER'));
define('DB_PASS', env_value('DB_PASS', 'YOUR_HOSTINGER_DB_PASSWORD'));
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_USER === 'YOUR_HOSTINGER_DB_USER' || DB_PASS === 'YOUR_HOSTINGER_DB_PASSWORD') {
        error_log('[Music Awards Registration] Database credentials have not been configured yet.');
        http_response_code(500);
        die('Service temporarily unavailable. Please try again later.');
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
