<?php
/**
 * NOVURA MARKETPLACE - CART PAGE
 * Path: public/cart.php
 *
 * Works without JavaScript: the quantity inputs and Remove buttons live in one
 * form that posts back to this page (post/redirect/get).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

if (is_post()) {
    csrf_require();

    $removeId = input_int('remove', 0);

    if ($removeId > 0) {
        $result = cart_remove($removeId);
        flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    } else {
        $quantities = (isset($_POST['qty']) && is_array($_POST['qty'])) ? $_POST['qty'] : [];
        $problems   = [];

        foreach ($quantities as $itemId => $qty) {
            if (!is_scalar($qty) || preg_match('/^\d+$/', (string) $qty) !== 1) {
                continue;
            }

            $result = cart_update((int) $itemId, (int) $qty);

            if (!$result['ok']) {
                $problems[] = $result['message'];
            } elseif (str_starts_with($result['message'], 'Quantity adjusted')) {
                $problems[] = $result['message'];
            }
        }

        if ($problems) {
            flash_set('warning', implode(' ', array_unique($problems)));
        } else {
            flash_set('success', 'Your cart has been updated.');
        }
    }

    redirect('public/cart.php');
}

$cart = cart_load();

$pageTitle    = 'Your cart';
$extraCss     = ['css/storefront.css', 'css/checkout.css'];
$bodyClass    = 'page-cart';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="page-title">Your cart <?php if ($cart['count'] > 0): ?><span class="page-title-count">(<?= (int) $cart['count'] ?> <?= $cart['count'] === 1 ? 'item' : 'items' ?>)</span><?php endif; ?></h1>

    <?php foreach ($cart['notices'] as $notice): ?>
        <div class="alert alert-warning" role="status"><span><?= e($notice) ?></span></div>
    <?php endforeach; ?>

    <?php if (!$cart['lines']): ?>
        <div class="empty-state">
            <h2>Your cart is empty</h2>
            <p>Browse our products and add something you like.</p>
            <a class="btn btn-primary" href="<?= e(url('public/shop.php')) ?>">Start shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <form class="cart-lines" action="<?= e(url('public/cart.php')) ?>" method="post">
                <?= csrf_field() ?>

                <?php /* Default button: pressing Enter in a quantity box must UPDATE, never hit the first Remove button. */ ?>
                <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Update cart</button>

                <ul class="cart-list">
                    <?php foreach ($cart['lines'] as $line): ?>
                        <?php $href = product_url($line['slug']); ?>
                        <li class="cart-line<?= $line['available'] ? '' : ' is-unavailable' ?>">
                            <a class="cart-line-media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                                <img src="<?= e(upload_url($line['image'])) ?>" alt="" width="96" height="96" loading="lazy">
                            </a>

                            <div class="cart-line-info">
                                <a class="cart-line-name" href="<?= e($href) ?>"><?= e($line['name']) ?></a>
                                <?php if ($line['variant_label'] !== null): ?>
                                    <p class="cart-line-variant"><?= e($line['variant_label']) ?></p>
                                <?php endif; ?>
                                <p class="cart-line-vendor">Sold by <?= e($line['store_name']) ?></p>

                                <?php if (!$line['available']): ?>
                                    <p class="cart-line-warning">No longer available. Please remove it to continue.</p>
                                <?php elseif ($line['stock'] <= 5): ?>
                                    <p class="cart-line-warning">Only <?= (int) $line['stock'] ?> left in stock</p>
                                <?php endif; ?>

                                <button type="submit" name="remove" value="<?= (int) $line['item_id'] ?>" class="link-button">Remove</button>
                            </div>

                            <div class="cart-line-qty">
                                <?php if ($line['available']): ?>
                                    <label class="sr-only" for="qty-<?= (int) $line['item_id'] ?>">Quantity for <?= e($line['name']) ?></label>
                                    <input class="form-control cart-qty-input" type="number" id="qty-<?= (int) $line['item_id'] ?>"
                                           name="qty[<?= (int) $line['item_id'] ?>]" value="<?= (int) $line['quantity'] ?>"
                                           min="0" max="<?= (int) $line['max_qty'] ?>" inputmode="numeric">
                                <?php endif; ?>
                            </div>

                            <div class="cart-line-price">
                                <?php if ($line['available']): ?>
                                    <strong><?= e(format_money($line['line_total'])) ?></strong>
                                    <?php if ($line['quantity'] > 1): ?>
                                        <small><?= e(format_money($line['unit_price'])) ?> each</small>
                                    <?php endif; ?>
                                    <?php if ($line['old_price'] !== null): ?>
                                        <small class="cart-old"><?= e(format_money($line['old_price'])) ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="cart-actions">
                    <a class="btn btn-outline" href="<?= e(url('public/shop.php')) ?>">Continue shopping</a>
                    <button type="submit" class="btn btn-dark">Update cart</button>
                </div>
            </form>

            <aside class="summary" aria-label="Order summary">
                <h2 class="summary-title">Order summary</h2>

                <dl class="summary-rows">
                    <div><dt>Subtotal</dt><dd><?= e(format_money($cart['subtotal'])) ?></dd></div>
                    <div>
                        <dt>Delivery</dt>
                        <dd><?= $cart['shipping'] > 0 ? e(format_money($cart['shipping'])) : 'Free' ?></dd>
                    </div>
                    <div class="summary-total"><dt>Total</dt><dd><?= e(format_money($cart['total'])) ?></dd></div>
                </dl>

                <?php if ($cart['free_shipping_remaining'] > 0): ?>
                    <p class="summary-note">Add <strong><?= e(format_money($cart['free_shipping_remaining'])) ?></strong> more for free delivery.</p>
                <?php endif; ?>

                <?php if ($cart['unavailable'] > 0): ?>
                    <p class="summary-note summary-note-warn">Remove unavailable items to continue.</p>
                    <span class="btn btn-primary btn-block is-disabled" aria-disabled="true">Proceed to checkout</span>
                <?php elseif ($cart['subtotal'] <= 0): ?>
                    <span class="btn btn-primary btn-block is-disabled" aria-disabled="true">Proceed to checkout</span>
                <?php else: ?>
                    <a class="btn btn-primary btn-block" href="<?= e(url('public/checkout.php')) ?>">Proceed to checkout</a>
                    <?php if (!auth_check()): ?>
                        <p class="summary-note">You will be asked to sign in or create an account. Your cart is kept.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
