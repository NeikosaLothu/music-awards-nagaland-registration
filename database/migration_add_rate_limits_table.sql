-- Existing database upgrade for this project.
-- Adds persistent, session-independent rate limiting for admin login
-- attempts (login.php) and Razorpay order creation (payment.php). A
-- session-based counter can be reset by an attacker just by discarding
-- their session cookie, which is what this replaces.
--
-- The application also creates this table automatically on first use
-- (see ensure_rate_limit_table() in includes/functions.php), so running
-- this manually is optional - it's kept here for consistency with the
-- rest of database/ and for anyone who prefers to provision it ahead of
-- time.

CREATE TABLE IF NOT EXISTS man_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(30) NOT NULL,
    subject_key VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bucket_subject_time (bucket, subject_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
