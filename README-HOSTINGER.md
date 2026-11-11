# 18th Music Awards of Nagaland — Public Registration Portal

Standalone Core PHP + MySQL + Bootstrap 5 application for:

https://register.musicawardsofnagaland.in/submission-form

## 1. Database setup

1. Log in to Hostinger hPanel.
2. Open the MySQL Databases section.
3. Create a database and a **dedicated** database user for this app (do not use the MySQL root account, and do not leave the password empty).
4. Open phpMyAdmin for that database.
5. Import `database/schema.sql`. If you already have a live database from an earlier deployment (e.g. a separate `.sql` export you keep alongside this code), don't just re-import blindly — instead run the migration files below against it, and use `database/schema.sql` only as a reference for what the full, current structure should look like. It defines five tables: `submissions`, `man_admins`, `man_submission_status`, `payment_events` (written to by `webhook.php`), and `man_rate_limits` (persistent login/order rate limiting).
6. If upgrading an existing database, run once each, in order:
   - `database/migration_add_payment_events_table.sql` — needed for `webhook.php` to work at all.
   - `database/migration_add_payment_unique_constraint.sql` — stops the same Razorpay payment from ever being attached to two submissions. If this fails with a duplicate-key error, you have pre-existing duplicate `razorpay_payment_id` values to resolve first (the file explains how to check).
   - `database/migration_add_rate_limits_table.sql` — optional; the app also creates this table automatically the first time it's needed.
7. Provide the real connection details using **one** of:
   - Real server environment variables `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` (Hostinger hPanel → PHP Configuration, on plans that support it), or
   - Copy `config/secrets.local.example.php` to `config/secrets.local.php` and fill in the values there (recommended on shared hosting — see step 7a below), or
   - Edit the placeholder constants (`YOUR_HOSTINGER_DB_USER`, `YOUR_HOSTINGER_DB_PASSWORD`) directly in `config/database.php`.
8. Keep `DB_HOST` as `localhost` unless Hostinger gives you a different database host.

### 7a. `config/secrets.local.php` (recommended)

```
cp config/secrets.local.example.php config/secrets.local.php
```

Then fill in the real database, Razorpay, reCAPTCHA, and setup values in that file. It is never committed to version control (see `.gitignore`) and is not directly reachable over the web (`config/.htaccess` denies every `.php` file in that folder). `config/env.php` loads it automatically.

## 2. Upload

Upload the contents of this project to the document root/subdirectory mapped to:

`register.musicawardsofnagaland.in/submission-form`

Do not upload the project as a nested extra folder such as:

`/submission-form/music-awards-nagaland-registration/...`

unless your subdomain is configured for that extra directory.

## 3. PHP requirements

The application uses:
- PHP 8.x recommended
- PDO MySQL extension
- Sessions
- mbstring extension

No Composer, Node.js, Laravel, React, or build process is required.

## 4. Admin account setup

`setup-admin.php` is secret-gated and self-disabling:

1. Choose a long random value for `SETUP_ADMIN_SECRET` (e.g. `openssl rand -hex 32`) and set it via a real environment variable, or in `config/secrets.local.php`.
2. Visit `https://your-domain/submission-form/setup-admin.php?key=YOUR_SETUP_ADMIN_SECRET`.
3. Create the first admin/judge account.
4. The page automatically stops working the moment an admin account exists — it returns a generic 404 to anyone, key or no key, from then on. Deleting the file afterwards is no longer required for security, but is still fine to do for tidiness.
5. Sign in at `https://register.musicawardsofnagaland.in/submission-form/login.php` to reach the dashboard.

If `SETUP_ADMIN_SECRET` is never configured, the page always returns 404 and no admin account can be created — this is intentional (fail closed rather than leaving an open "create an admin" form on the public internet).

### Forgot your password?

`setup-admin.php` will **not** help — it 404s for everyone the moment any admin account already exists, by design. Use `reset-admin-password.php` instead:

1. Visit `https://your-domain/submission-form/reset-admin-password.php?key=YOUR_SETUP_ADMIN_SECRET` (it reuses `SETUP_ADMIN_SECRET` automatically, or set a separate `RESET_ADMIN_SECRET`).
2. Enter your username/email and a new password.
3. **Delete `reset-admin-password.php` from the server immediately after** — unlike `setup-admin.php`, it doesn't disable itself automatically, since it needs to keep working for future lockouts. Re-upload it only when you actually need it again.

## 5. Test

Open:

`https://register.musicawardsofnagaland.in/submission-form/`

Complete the form, solve the reCAPTCHA, pay the ₹250 fee (use Razorpay **Test Mode** first), preview, confirm, and verify that a row appears in MySQL with `payment_status = 'paid'`.

## 6. Landing-page integration

Do not modify the landing page design/code beyond changing the destination of existing registration buttons/links.

Use exactly:

`https://register.musicawardsofnagaland.in/submission-form`

## 7. Payment — ₹250 Razorpay Standard Checkout

The production flow is:

`Fill form → reCAPTCHA/validation → Pay ₹250 → Razorpay verification → Preview → Final Submit → MySQL record created`

The application is **not inserted into MySQL before payment**. The form is held in the PHP session while payment is processed. The final `submit.php` step re-checks the Razorpay payment (live, server-to-server) before creating the official submission row. A verified payment expires after 1 hour if final submission isn't completed, so a stale session can't be used to submit later.

### Razorpay setup

1. In Razorpay Dashboard, generate your **Live Key ID** and **Live Key Secret**.
2. Provide `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` via a real environment variable, `config/secrets.local.php`, or by editing the placeholders directly in `config/razorpay.php`.
3. Keep the Key Secret server-side. Do not put it in JavaScript or a public repository.
4. The registration fee is fixed in code at **₹250** (`25,000` paise).

This version uses Razorpay **Standard Checkout / Orders API** only. (An older, unused `rzp.io` Payment Link code path was removed in an earlier pass — it called two functions that were never actually defined anywhere in the project, and it contained a bug where a *missing* payment signature was treated as valid.)

### Webhook (safety net for live payments)

The form-based flow only records a payment if the visitor's browser stays connected the whole way through. `webhook.php` is a second, independent path: Razorpay calls it directly from their servers, so a payment still gets logged even if the visitor's tab closes right after paying. It writes to the `payment_events` audit table — it does **not** create submissions or replace `verify-payment.php`.

1. In Razorpay Dashboard, go to **Settings → Webhooks → Add New Webhook**.
2. Webhook URL: `https://register.musicawardsofnagaland.in/webhook.php`
3. Active events: at minimum `payment.captured` and `payment.failed`.
4. Set a **Secret** (any string you choose, different from your Key Secret) and provide the same value as `RAZORPAY_WEBHOOK_SECRET` via environment variable, `config/secrets.local.php`, or directly in `config/razorpay.php`.
5. Staff can use the `payment_events` table to reconcile a payment that seems to have gone missing from `submissions`.

### Duplicate-payment protection

The same `razorpay_payment_id` can never be used for two submissions — checked in application code (`is_payment_id_already_used()`, called from both `verify-payment.php` and `submit.php`) and enforced at the database level (a `UNIQUE` key on `submissions.razorpay_payment_id` — see `database/migration_add_payment_unique_constraint.sql` if upgrading an existing database), so it holds even under a race between two near-simultaneous requests.

### Test before going live

Use Razorpay Test Mode first with a test card to verify the complete flow end-to-end (including a deliberately failed/declined payment), then switch to Live Mode credentials. If you skip straight to Live Mode, at minimum do one real ₹250 self-payment all the way through — including confirming the webhook fires — before opening registration to the public.

## 8. Security notes

- SQL uses PDO prepared statements throughout (`PDO::ATTR_EMULATE_PREPARES` disabled), verified project-wide — no string-concatenated queries.
- Important validation is repeated server-side; the browser is never trusted.
- Output is HTML-escaped (`e()` / `admin_e()`), including in the admin dashboard.
- CAPTCHA is Google reCAPTCHA, verified server-side on every submission.
- CSRF tokens (constant-time compared) protect every state-changing request: registration, payment verification, final submission, admin login, admin status changes, admin logout, and first-run admin setup.
- Admin login is rate-limited per-account and per-IP using a persistent MySQL table (`man_rate_limits`), not the PHP session, so an attacker can't reset the counter just by dropping their session cookie.
- Payment verification independently re-fetches the payment from Razorpay's API (server-to-server) rather than trusting anything the browser reports about amount, currency, or status; a stale (>1hr) order or verified-payment session is rejected and must be redone.
- `webhook.php` independently logs captured/failed payments server-to-server (HMAC-SHA256 signature required, constant-time compared) as a safety net against interrupted browser sessions — it's an audit log, not a second way to create a submission.
- A unique database constraint on `razorpay_payment_id` prevents the same payment from ever being attached to two submissions, alongside an explicit application-level check.
- CSV exports neutralise spreadsheet formula injection (`csv_safe()`).
- Hidden honeypot catches basic bots.
- Submission IDs are unique (checked with a retry loop before insert).
- Double-submit protection disables the browser button and uses a server session flag, plus the database uniqueness check as a second layer.
- Technical exceptions are logged server-side (`error_log`) instead of displayed publicly; `display_errors` is off in production (see `.user.ini`).
- Admin sessions expire after 2 hours of inactivity in addition to the normal browser-session cookie.
- HTTPS is force-redirected in `.htaccess`, with HSTS enabled; session cookies are `Secure` + `HttpOnly` + `SameSite=Lax`.
- Admin passwords are hashed with `password_hash()`/`password_verify()` (auto-rehashed on login if the algorithm/cost changes).

## 9. Production configuration

Before public launch:
- Enable/force SSL for the domain in Hostinger hPanel first — `.htaccess` redirects HTTP→HTTPS, but the certificate itself has to be issued by Hostinger before that works.
- Confirm Hostinger PHP version and required extensions.
- Provide real database, Razorpay, and reCAPTCHA credentials (sections 1 / 7a / 7 above) — **rotate the Google reCAPTCHA secret key** in the Google reCAPTCHA admin console before going live if you have ever committed a real one to source control (treat any previously-committed secret as compromised).
- Use a MySQL user scoped to just this database — never `root`, never an empty password.
- Set `SETUP_ADMIN_SECRET` and create the admin account (section 4), before the site is publicly reachable if possible.
- Set up the Razorpay webhook (section 7) before going live, not after.
- Restrict access to phpMyAdmin using the hosting account controls.
- After deploying, open the browser console on the registration/payment pages and check for `Content-Security-Policy-Report-Only` violations. Once you're confident nothing legitimate (Razorpay Checkout, reCAPTCHA, fonts) is blocked, change that header's name in `.htaccess` to `Content-Security-Policy` to start enforcing it.
- Confirm these are **not** publicly downloadable (spot-check a few URLs directly): `config/*.php`, `config/secrets.local.php`, `database/*.sql`, `.user.ini`, `.htaccess`, `.gitignore`, `.git/` if present. `.htaccess` rules in this project already block all of these; this is just a final check.
- Test the registration flow on desktop and mobile.
- Take a database backup before major changes.
