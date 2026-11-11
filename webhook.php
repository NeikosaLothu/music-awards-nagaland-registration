<?php

declare(strict_types=1);

/*
 * RAZORPAY WEBHOOK — SAFETY NET
 * ------------------------------
 * Configure this in Razorpay Dashboard > Settings > Webhooks:
 *   URL:    https://register.musicawardsofnagaland.in/webhook.php
 *   Events: payment.captured, payment.failed
 *   Secret: any value you choose — paste the SAME value into
 *           RAZORPAY_WEBHOOK_SECRET in config/razorpay.php
 *
 * WHY THIS EXISTS
 * The normal flow (payment.php -> verify-payment.php -> submit.php) only
 * records anything if the visitor's browser stays connected the whole way
 * through. If Razorpay captures money but their tab closes, their phone
 * loses signal, etc. right after paying, nothing in the database reflects
 * that. This endpoint is called directly by Razorpay's servers, not the
 * visitor's browser, so it still logs the event even then. It is an
 * independent audit log for staff to reconcile — it does NOT create
 * submissions or replace the existing verification.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$rawBody = (string)file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

if (!razorpay_verify_webhook_signature($rawBody, $signature)) {
    error_log('[Music Awards Registration / Webhook] Signature verification failed or webhook secret not configured.');
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$event = (string)($payload['event'] ?? '');
$payment = $payload['payload']['payment']['entity'] ?? null;

// Only payment events matter here; acknowledge anything else quietly so
// Razorpay doesn't retry events we're not interested in.
if (!is_array($payment)) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$paymentId = (string)($payment['id'] ?? '');
if ($paymentId === '') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$orderId = (string)($payment['order_id'] ?? '');
$amount = (int)($payment['amount'] ?? 0);
$currency = (string)($payment['currency'] ?? '');
$status = (string)($payment['status'] ?? '');
$notes = json_encode($payment['notes'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}';

try {
    $pdo = db();
    // Razorpay retries webhook deliveries, so the same event for the same
    // payment can arrive more than once — this must stay safe to re-run.
    $stmt = $pdo->prepare(
        'INSERT INTO payment_events
            (razorpay_payment_id, razorpay_order_id, event, amount, currency, status, notes, received_at)
         VALUES (:payment_id, :order_id, :event, :amount, :currency, :status, :notes, NOW())
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            received_at = NOW()'
    );
    $stmt->execute([
        'payment_id' => $paymentId,
        'order_id' => $orderId ?: null,
        'event' => $event,
        'amount' => $amount,
        'currency' => $currency,
        'status' => $status,
        'notes' => $notes,
    ]);
} catch (Throwable $e) {
    error_log('[Music Awards Registration / Webhook] ' . $e->getMessage());
    // 500 so Razorpay retries — a transient DB hiccup shouldn't silently
    // drop the one record this endpoint exists to protect.
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
