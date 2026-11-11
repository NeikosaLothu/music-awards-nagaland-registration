-- Existing database upgrade for this project.
-- Prevents the same Razorpay payment ID from ever being attached to more
-- than one submission row (defence in depth against payment replay /
-- reuse, alongside the explicit is_payment_id_already_used() check
-- already in verify-payment.php and submit.php). Fresh installations get
-- this automatically from schema.sql.
--
-- If this fails with a duplicate-key error, you have pre-existing rows
-- that share a razorpay_payment_id - find and resolve those first:
--   SELECT razorpay_payment_id, COUNT(*) FROM submissions
--   WHERE razorpay_payment_id IS NOT NULL
--   GROUP BY razorpay_payment_id HAVING COUNT(*) > 1;

ALTER TABLE submissions
    ADD UNIQUE KEY uq_razorpay_payment_id (razorpay_payment_id);
