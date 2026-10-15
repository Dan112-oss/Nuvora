<?php
/**
 * NOVURA MARKETPLACE - PAYSTACK WEBHOOK
 * Path: api/paystack-callback.php
 *
 * Set this URL as the Webhook URL in your Paystack dashboard
 * (Settings > API Keys & Webhooks):
 *     https://YOUR-DOMAIN/api/paystack-callback.php
 *
 * Why it exists: the buyer's browser may close before returning to the site.
 * Paystack calls this endpoint server-to-server, so the order still gets marked paid.
 *
 * Security: every request must carry a valid HMAC-SHA512 signature made with
 * your secret key. Requests without it are rejected. No session is started here.
 */

declare(strict_types=1);

define('NOVURA_API', true);

require_once __DIR__ . '/../includes/orders.php';

if (request_method() !== 'POST') {
    http_response_code(405);
    exit;
}

$rawBody   = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');

if (PAYSTACK_SECRET_KEY === '' || $signature === '') {
    http_response_code(401);
    exit;
}

if (!hash_equals(hash_hmac('sha512', $rawBody, PAYSTACK_SECRET_KEY), $signature)) {
    http_response_code(401);
    exit;
}

$event = json_decode($rawBody, true);

if (is_array($event) && ($event['event'] ?? '') === 'charge.success') {
    $data      = is_array($event['data'] ?? null) ? $event['data'] : [];
    $reference = is_string($data['reference'] ?? null) ? $data['reference'] : '';

    if ($reference !== '' && ($data['status'] ?? '') === 'success') {
        // Amount is in kobo; orders_mark_paid() compares it with what we stored.
        $result = orders_mark_paid(
            $reference,
            ((float) ($data['amount'] ?? 0)) / 100,
            (string) ($data['currency'] ?? ''),
            $data
        );

        if (in_array($result['result'], ['mismatch', 'needs_refund', 'unknown'], true)) {
            error_log('[Novura] Webhook result for ' . $reference . ': ' . $result['result']);
        }
    }
}

// Always answer 200 once the signature is valid, otherwise Paystack keeps retrying.
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';
