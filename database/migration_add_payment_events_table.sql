-- Existing database upgrade. Adds the table webhook.php writes to.
-- Safe/no-op if you already have this table (e.g. from schema.sql on a
-- fresh install).

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
