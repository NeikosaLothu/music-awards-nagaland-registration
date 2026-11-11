<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Minimal environment/config loader for shared hosting
|--------------------------------------------------------------------------
|
| Resolution order for env_value($key, $default):
|
|   1. A real environment variable - works wherever the host lets you set
|      PHP environment variables (Hostinger hPanel "PHP Configuration" on
|      supporting plans, or `SetEnv` in .htaccess on Apache/LiteSpeed).
|   2. config/secrets.local.php - a small, git-ignored file that returns
|      an associative array of overrides. This file is never committed,
|      and config/.htaccess already blocks direct web access to every
|      *.php file in this folder. This is the recommended approach on
|      Hostinger shared hosting, where PHP-level environment variables
|      are not always available.
|   3. The $default the caller supplies (the previous behaviour of this
|      project - editing the constants in config/*.php directly).
|
| See config/secrets.local.example.php for the file format.
*/

function env_value(string $key, string $default = ''): string
{
    static $localSecrets = null;

    if ($localSecrets === null) {
        $file = __DIR__ . '/secrets.local.php';
        $loaded = is_file($file) ? (require $file) : [];
        $localSecrets = is_array($loaded) ? $loaded : [];
    }

    $fromEnv = getenv($key);
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }

    if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    if (isset($localSecrets[$key]) && $localSecrets[$key] !== '') {
        return (string)$localSecrets[$key];
    }

    return $default;
}
