<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/*
|--------------------------------------------------------------------------
| Google reCAPTCHA configuration
|--------------------------------------------------------------------------
|
| Local development:
|   localhost
|
| Production:
|   register.musicawardsofnagaland.in
|
| The site key is public (it is already embedded in the page HTML), so it
| is fine to keep a working default here. The SECRET key must never be
| committed to source - set it as a real server environment variable, or
| via config/secrets.local.php (see config/secrets.local.example.php).
|
*/

define('RECAPTCHA_SITE_KEY', env_value('RECAPTCHA_SITE_KEY', '6LdiTrstAAAAAB2p_hCUeiNcNg75RApq85T5excT'));
define('RECAPTCHA_SECRET_KEY', env_value('RECAPTCHA_SECRET_KEY', ''));