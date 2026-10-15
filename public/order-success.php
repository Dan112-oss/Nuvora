<?php
/**
 * NOVURA MARKETPLACE - ORDER CONFIRMATION
 * Path: public/order-success.php?order=<order-number>
 *
 * Only the buyer who placed the order can open it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

$user = require_login();

$orderNumber = input('order');

$order = $orderNumber !== '' ? db_fetch_one(
    'SELECT * FROM orders WHERE order_number = :number AND user_id = :user LIMIT 1',
    ['number' => $orderNumber, 'user' => (int) $user['id']]
) : null;

if ($order === null) {
    abort(404, 'We could not find that order.');
}

$items = db_fetch_all(
    'SELECT oi.product_name, oi.variant_label, oi.unit_price, oi.quantity, oi.line_total,
            p.slug,
            (SELECT pi.image_path FROM product_images pi
              WHERE pi.product_id = oi.product_id
              ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image
       FROM order_items oi
       LEFT JOIN products p ON p.id = oi.product_id
      WHERE oi.order_id = :order
      ORDER BY oi.id',
    ['order' => (int) $order['id']]
);

$payment = db_fetch_one(
    'SELECT reference, status FROM payments WHERE order_id = :order ORDER BY id DESC LIMIT 1',
    ['order' => (int) $order['id']]
);

// Headline and explanation depend on how the order is paid.
$method = (string) $order['payment_method'];

if ($order['status'] === 'cancelled') {
    $tone     = 'error';
    $headline = 'This order was cancelled';
    $message  = $order['payment_status'] === 'paid'
        ? 'Your payment arrived after the order expired. Please contact support to get a refund.'
        : 'No money was taken. You can add the items to your cart again whenever you like.';
} elseif ($order['payment_status'] === 'paid') {
    $tone     = 'success';
    $headline = 'Payment received. Thank you!';
    $message  = 'The sellers are preparing your order for delivery.';
} elseif ($method === 'cod') {
    $tone     = 'success';
    $headline = 'Order placed!';
    $message  = 'Pay ' . format_money((float) $order['total']) . ' when your order is delivered. The seller will confirm it shortly.';
} else {
    $tone     = 'info';
    $headline = 'Waiting for your payment';
    $message  = 'We have not received confirmation from Paystack yet. If you have already paid, this can take a minute.';
}

$awaitingPayment = $order['status'] === 'pending' && $method === 'paystack' && $order['payment_status'] !== 'paid';

$pageTitle = 'Order ' . $order['order_number'];
$extraCss  = ['css/storefront.css', 'css/checkout.css'];
$bodyClass = 'page-order-success';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="success-hero success-hero-<?= e($tone) ?>">
        <h1><?= e($headline) ?></h1>
        <p><?= e($message) ?></p>
        <p class="success-order">Order number: <strong><?= e($order['order_number']) ?></strong></p>

        <?php if ($awaitingPayment && $payment !== null): ?>
            <a class="btn btn-primary" href="<?= e(url('public/payment-callback.php?reference=' . rawurlencode((string) $payment['reference']))) ?>">Check payment status</a>
        <?php endif; ?>
    </div>

    <div class="success-layout">
        <section class="panel" aria-labelledby="items-title">
            <h2 id="items-title" class="panel-title">Items in this order</h2>

            <ul class="cart-list">
                <?php foreach ($items as $item): ?>
                    <li class="cart-line cart-line-compact">
                        <div class="cart-line-media">
                            <img src="<?= e(upload_url($item['image'])) ?>" alt="" width="72" height="72" loading="lazy">
                        </div>
                        <div class="cart-line-info">
                            <?php if (!empty($item['slug'])): ?>
                                <a class="cart-line-name" href="<?= e(product_url((string) $item['slug'])) ?>"><?= e($item['product_name']) ?></a>
                            <?php else: ?>
                                <span class="cart-line-name"><?= e($item['product_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['variant_label'])): ?>
                                <p class="cart-line-variant"><?= e($item['variant_label']) ?></p>
                            <?php endif; ?>
                            <p class="cart-line-vendor"><?= (int) $item['quantity'] ?> &times; <?= e(format_money($item['unit_price'])) ?></p>
                        </div>
                        <div class="cart-line-price"><strong><?= e(format_money($item['line_total'])) ?></strong></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <aside class="summary" aria-label="Order details">
            <h2 class="summary-title">Order details</h2>

            <dl class="summary-rows">
                <div><dt>Subtotal</dt><dd><?= e(format_money($order['subtotal'])) ?></dd></div>
                <div><dt>Delivery</dt><dd><?= (float) $order['shipping_fee'] > 0 ? e(format_money($order['shipping_fee'])) : 'Free' ?></dd></div>
                <?php if ((float) $order['discount'] > 0): ?>
                    <div><dt>Discount</dt><dd>-<?= e(format_money($order['discount'])) ?></dd></div>
                <?php endif; ?>
                <div class="summary-total"><dt>Total</dt><dd><?= e(format_money($order['total'])) ?></dd></div>
            </dl>

            <h3 class="summary-sub">Payment</h3>
            <p class="summary-text">
                <?= $method === 'cod' ? 'Pay on delivery' : 'Paystack (online)' ?>
                &middot; <?= e(ucfirst((string) $order['payment_status'])) ?>
            </p>

            <h3 class="summary-sub">Delivering to</h3>
            <p class="summary-text">
                <?= e($order['shipping_name']) ?><br>
                <?= e($order['shipping_address']) ?><br>
                <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?><br>
                <?= e($order['shipping_phone']) ?>
            </p>

            <a class="btn btn-primary btn-block" href="<?= e(url('public/shop.php')) ?>">Continue shopping</a>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
