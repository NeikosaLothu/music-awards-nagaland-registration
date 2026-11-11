-- Music Awards of Nagaland — Registration Portal
-- Fresh-install schema. Run this once against a new, empty database.
--
-- NOTE: the database/ folder (this file and the migrations below it) was
-- missing from the project zip that was reviewed, even though webhook.php
-- and includes/functions.php both assume tables that must exist
-- (payment_events, and a UNIQUE key on submissions.razorpay_payment_id).
-- This file was reconstructed from the exact columns/queries referenced
-- throughout the PHP code. If you already have a live database, do NOT
-- just run this blindly — compare it against what you actually have
-- deployed, and use the migration_*.sql files instead to bring an
-- existing database up to date (CREATE TABLE IF NOT EXISTS is a safe
-- no-op against a table that already exists, but it will NOT add a
-- missing UNIQUE KEY to a table that's already there).

CREATE TABLE IF NOT EXISTS submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id VARCHAR(32) NOT NULL,
    category VARCHAR(100) NOT NULL,
    artist_name VARCHAR(150) NOT NULL,
    address VARCHAR(500) NOT NULL,
    band_page VARCHAR(2048) NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    song_title VARCHAR(200) NOT NULL,
    songwriter VARCHAR(200) NOT NULL,
    sound_engineer VARCHAR(200) NOT NULL,
    platform_audio_link VARCHAR(2048) NOT NULL,
    music_video_link VARCHAR(2048) NOT NULL,
    lyrics TEXT NOT NULL,
    producer VARCHAR(200) NOT NULL,
    video_director VARCHAR(200) NULL,
    recorded_at VARCHAR(200) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    razorpay_order_id VARCHAR(64) NULL,
    razorpay_payment_id VARCHAR(64) NULL,
    payment_updated_at TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_submission_id (submission_id),
    -- Stops the same Razorpay payment from ever being attached to two
    -- submissions (defence in depth alongside the explicit
    -- is_payment_id_already_used() check in verify-payment.php/submit.php).
    UNIQUE KEY uq_razorpay_payment_id (razorpay_payment_id),
    KEY idx_category (category),
    KEY idx_artist_name (artist_name),
    KEY idx_created_at (created_at),
    KEY idx_payment_status (payment_status),
    KEY idx_razorpay_order_id (razorpay_order_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Judge/admin accounts (also self-created by setup-admin.php on first run).
CREATE TABLE IF NOT EXISTS man_admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(190) NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review workflow status per submission (separate from payment_status).
CREATE TABLE IF NOT EXISTS man_submission_status (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id VARCHAR(32) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Submitted',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submission_id (submission_id),
    KEY idx_status (status),
    CONSTRAINT fk_man_submission_status_admin FOREIGN KEY (updated_by)
        REFERENCES man_admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Razorpay webhook audit log (webhook.php). This is an independent safety
-- net / reconciliation log, not the source of truth for a submission - see
-- the comment at the top of webhook.php. The UNIQUE key on
-- razorpay_payment_id is what makes repeated webhook deliveries for the
-- same payment update the existing row (ON DUPLICATE KEY UPDATE) instead
-- of piling up duplicate rows, since Razorpay retries webhook deliveries.
CREATE TABLE IF NOT EXISTS payment_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    razorpay_payment_id VARCHAR(64) NOT NULL,
    razorpay_order_id VARCHAR(64) NULL,
    event VARCHAR(60) NOT NULL,
    amount INT UNSIGNED NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT '',
    notes TEXT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_razorpay_payment_id (razorpay_payment_id),
    KEY idx_razorpay_order_id (razorpay_order_id),
    KEY idx_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Persistent, session-independent rate limiting (admin login, order
-- creation). Also auto-created on first use by ensure_rate_limit_table()
-- in includes/functions.php, so this is here for completeness/consistency.
CREATE TABLE IF NOT EXISTS man_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(30) NOT NULL,
    subject_key VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bucket_subject_time (bucket, subject_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
