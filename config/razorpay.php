<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/*
 * Razorpay Standard Checkout configuration.
 *
 * Prefer setting these as real server environment variables, or via
 * config/secrets.local.php (see config/secrets.local.example.php).
 * Editing the placeholders below directly still works too.
 */
define('RAZORPAY_KEY_ID', env_value('RAZORPAY_KEY_ID', 'YOUR_RAZORPAY_KEY_ID'));
define('RAZORPAY_KEY_SECRET', env_value('RAZORPAY_KEY_SECRET', 'YOUR_RAZORPAY_KEY_SECRET'));
const RAZORPAY_AMOUNT_PAISE = 25000;
const RAZORPAY_CURRENCY = 'INR';
const RAZORPAY_DESCRIPTION = 'Music Awards of Nagaland 2026 - Registration Fee';

/*
 * Webhook secret — set this in Razorpay Dashboard > Settings > Webhooks when
 * you create the webhook pointing at webhook.php, then provide the SAME
 * value here (env var / secrets file / placeholder below). This is a
 * DIFFERENT value from RAZORPAY_KEY_SECRET above; it exists only to prove
 * that a webhook request genuinely came from Razorpay.
 */
define('RAZORPAY_WEBHOOK_SECRET', env_value('RAZORPAY_WEBHOOK_SECRET', 'YOUR_RAZORPAY_WEBHOOK_SECRET'));

function razorpay_is_configured(): bool
{
    return RAZORPAY_KEY_ID !== '' && RAZORPAY_KEY_SECRET !== ''
        && RAZORPAY_KEY_ID !== 'YOUR_RAZORPAY_KEY_ID'
        && RAZORPAY_KEY_SECRET !== 'YOUR_RAZORPAY_KEY_SECRET';
}

function razorpay_webhook_is_configured(): bool
{
    return RAZORPAY_WEBHOOK_SECRET !== '' && RAZORPAY_WEBHOOK_SECRET !== 'YOUR_RAZORPAY_WEBHOOK_SECRET';
}

function razorpay_verify_webhook_signature(string $rawBody, string $signature): bool
{
    if (!razorpay_webhook_is_configured() || $signature === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, RAZORPAY_WEBHOOK_SECRET);

    return hash_equals($expected, $signature);
}

function razorpay_api_request(string $method, string $endpoint, ?array $payload = null): array
{
    if (!razorpay_is_configured()) throw new RuntimeException('Razorpay API credentials are not configured.');
    $ch = curl_init('https://api.razorpay.com/v1/' . ltrim($endpoint, '/'));
    if ($ch === false) throw new RuntimeException('Could not initialise Razorpay connection.');
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];
    if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($response === false || $curlError !== '') throw new RuntimeException('Unable to connect to Razorpay.');
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) throw new RuntimeException('Invalid response received from Razorpay.');
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException((string)($decoded['error']['description'] ?? 'Razorpay request failed.'));
    }
    return $decoded;
}

function razorpay_create_order(string $receipt): array
{
    return razorpay_api_request('POST', 'orders', [
        'amount' => RAZORPAY_AMOUNT_PAISE,
        'currency' => RAZORPAY_CURRENCY,
        'receipt' => $receipt,
        'notes' => ['application' => 'Music Awards of Nagaland 2026'],
    ]);
}

function razorpay_fetch_payment(string $paymentId): array
{
    return razorpay_api_request('GET', 'payments/' . rawurlencode($paymentId));
}

function razorpay_verify_checkout_signature(string $orderId, string $paymentId, string $signature): bool
{
    if (!razorpay_is_configured()) return false;
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    return hash_equals($expected, $signature);
}
