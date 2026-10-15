<?php
/**
 * NOVURA MARKETPLACE - PAYMENT RETURN PAGE
 * Path: public/payment-callback.php?reference=...
 *
 * Paystack sends the buyer back here (PAYSTACK_CALLBACK_URL). We never trust the
 * URL: the real status and amount are fetched from Paystack's API before anything
 * is marked paid. The webhook (api/paystack-callback.php) does the same job in case
 * the buyer never returns.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/orders.php';

$reference = input('reference') !== '' ? input('reference') : input('trxref');

if (preg_match('/^[A-Za-z0-9._=\-]{6,100}$/', $reference) !== 1) {
    abort(404, 'We could not find that payment.');
}

$payment = db_fetch_one(
    "SELECT p.id, p.order_id, p.status AS payment_status, o.order_number
       FROM payments p JOIN orders o ON o.id = p.order_id
      WHERE p.reference = :ref AND p.provider = 'paystack' LIMIT 1",
    ['ref' => $reference]
);

if ($payment === null) {
    abort(404, 'We could not find that payment.');
}

$orderId   = (int) $payment['order_id'];
$successTo = 'public/order-success.php?order=' . rawurlencode((string) $payment['order_number']);

// Already confirmed (for example by the webhook): nothing more to verify.
if ($payment['payment_status'] === 'success') {
    redirect($successTo);
}

try {
    $transaction = paystack_verify($reference);
} catch (Throwable $e) {
    error_log('[Novura] Paystack verify failed for ' . $reference . ': ' . $e->getMessage());
    flash_set('info', 'We could not confirm your payment yet. If you were charged, your order will update shortly.');
    redirect($successTo);
}

$status = (string) ($transaction['status'] ?? '');

if ($status === 'success') {
    $result = orders_mark_paid(
        $reference,
        ((float) ($transaction['amount'] ?? 0)) / 100,
        (string) ($transaction['currency'] ?? ''),
        $transaction
    );

    switch ($result['result']) {
        case 'paid':
        case 'already':
            flash_set('success', 'Payment received. Thank you for your order!');
            break;

        case 'needs_refund':
            flash_set('error', 'Your payment arrived after the order expired. Please contact support with order ' . $payment['order_number'] . ' for a refund.');
            break;

        default:
            // mismatch / unknown: never mark paid; support must look at it.
            flash_set('error', 'We could not match this payment to your order. Please contact support with order ' . $payment['order_number'] . '.');
    }

    redirect($successTo);
}

if (in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
    if (orders_cancel($orderId, 'Paystack status: ' . $status)) {
        $restored = orders_restore_cart($orderId);

        flash_set(
            'warning',
            $restored > 0
                ? 'Your payment was not completed, so no money was taken. Your items are back in your cart.'
                : 'Your payment was not completed, so no money was taken.'
        );
    } else {
        flash_set('info', 'This order has already been updated.');
    }

    redirect('public/cart.php');
}

// "pending", "ongoing", "processing", "queued": Paystack has not finished yet.
flash_set('info', 'Your payment is still being processed. This page will show the result once it is confirmed.');
redirect($successTo);
