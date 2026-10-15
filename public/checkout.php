<?php
/**
 * NOVURA MARKETPLACE - CHECKOUT
 * Path: public/checkout.php
 *
 * Flow:  cart -> delivery details + payment method -> order created (stock reserved)
 *        -> Pay on delivery: order-success.php
 *        -> Paystack: hosted payment page -> payment-callback.php -> order-success.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/orders.php';

$user = require_login();

// Release stock held by abandoned Paystack orders before we look at availability.
orders_cleanup_stale();

$cart = cart_load();

if (!$cart['lines']) {
    flash_set('info', 'Your cart is empty.');
    redirect('public/cart.php');
}

if ($cart['unavailable'] > 0 || $cart['subtotal'] <= 0) {
    flash_set('warning', 'Please remove unavailable items from your cart before checking out.');
    redirect('public/cart.php');
}

$states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta',
    'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT (Abuja)', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina',
    'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers',
    'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
];

$paystackOn = paystack_enabled();
$errors     = [];
$formError  = null;

$values = [
    'name'    => trim($user['first_name'] . ' ' . $user['last_name']),
    'phone'   => (string) ($user['phone'] ?? ''),
    'address' => (string) ($user['address'] ?? ''),
    'city'    => (string) ($user['city'] ?? ''),
    'state'   => (string) ($user['state'] ?? ''),
    'notes'   => '',
    'method'  => $paystackOn ? 'paystack' : 'cod',
    'save'    => '1',
];

if (is_post()) {
    csrf_require();

    $values = [
        'name'    => input('name'),
        'phone'   => input('phone'),
        'address' => input('address'),
        'city'    => input('city'),
        'state'   => input('state'),
        'notes'   => input('notes'),
        'method'  => input('payment_method'),
        'save'    => isset($_POST['save_address']) ? '1' : '',
    ];

    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 160) {
        $errors['name'] = 'Enter the recipient\'s full name.';
    }

    if (!is_valid_phone($values['phone'])) {
        $errors['phone'] = 'Enter a valid phone number the courier can call.';
    }

    if (mb_strlen($values['address']) < 5 || mb_strlen($values['address']) > 255) {
        $errors['address'] = 'Enter a full delivery address (street, area, landmark).';
    }

    if (mb_strlen($values['city']) < 2 || mb_strlen($values['city']) > 100) {
        $errors['city'] = 'Enter your city or town.';
    }

    if (!in_array($values['state'], $states, true)) {
        $errors['state'] = 'Choose your state.';
    }

    if (mb_strlen($values['notes']) > 500) {
        $errors['notes'] = 'Delivery notes must be 500 characters or fewer.';
    }

    $allowedMethods = $paystackOn ? ['paystack', 'cod'] : ['cod'];
    if (!in_array($values['method'], $allowedMethods, true)) {
        $errors['method'] = 'Choose how you want to pay.';
    }

    if (!$errors) {
        try {
            $order = orders_create((int) $user['id'], [
                'name'    => $values['name'],
                'phone'   => $values['phone'],
                'address' => $values['address'],
                'city'    => $values['city'],
                'state'   => $values['state'],
                'notes'   => $values['notes'],
            ], $values['method']);
        } catch (OrderException $e) {
            flash_set('error', $e->getMessage());
            redirect('public/cart.php');
        }

        // Remember the address for next time (the order itself keeps its own copy).
        if ($values['save'] === '1') {
            db_execute(
                'UPDATE users SET phone = :phone, address = :address, city = :city, state = :state WHERE id = :id',
                [
                    'phone'   => $values['phone'],
                    'address' => $values['address'],
                    'city'    => $values['city'],
                    'state'   => $values['state'],
                    'id'      => (int) $user['id'],
                ]
            );
        }

        if ($order['method'] === 'cod') {
            flash_set('success', 'Order placed! Pay when it is delivered.');
            redirect('public/order-success.php?order=' . rawurlencode($order['order_number']));
        }

        // ----- Paystack -----
        try {
            $paymentUrl = paystack_initialize($order, (string) $user['email']);
            $host       = (string) parse_url($paymentUrl, PHP_URL_HOST);

            // Only ever send buyers to Paystack's own domains.
            if (parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https' || preg_match('/(^|\.)paystack\.(com|co)$/i', $host) !== 1) {
                throw new RuntimeException('Unexpected payment URL host: ' . $host);
            }

            header('Location: ' . str_replace(["\r", "\n"], '', $paymentUrl), true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('[Novura] Paystack initialize failed for order ' . $order['order_number'] . ': ' . $e->getMessage());

            orders_cancel($order['order_id'], 'Could not start payment');
            orders_restore_cart($order['order_id']);

            $formError = 'We could not start the payment. Your cart is safe, so please try again or choose pay on delivery.';
            $cart      = cart_load();
        }
    }
}

/** Error line under a field. */
function checkout_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="form-error">' . e($errors[$key]) . '</p>' : '';
}

function checkout_invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

$pageTitle = 'Checkout';
$extraCss  = ['css/storefront.css', 'css/checkout.css'];
$bodyClass = 'page-checkout';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1 class="page-title">Checkout</h1>

    <?php if ($formError !== null): ?>
        <div class="alert alert-error" role="alert"><span><?= e($formError) ?></span></div>
    <?php endif; ?>

    <?php if (!$cart['lines']): ?>
        <div class="empty-state">
            <h2>Your cart is empty</h2>
            <a class="btn btn-primary" href="<?= e(url('public/shop.php')) ?>">Continue shopping</a>
        </div>
    <?php else: ?>
        <form class="checkout-layout" action="<?= e(url('public/checkout.php')) ?>" method="post" data-once>
            <?= csrf_field() ?>

            <div class="checkout-main">
                <section class="panel" aria-labelledby="delivery-title">
                    <h2 id="delivery-title" class="panel-title">1. Delivery details</h2>

                    <div class="form-group">
                        <label class="form-label" for="name">Full name</label>
                        <input class="form-control<?= checkout_invalid($errors, 'name') ?>" type="text" id="name" name="name"
                               value="<?= e($values['name']) ?>" maxlength="160" autocomplete="name" required>
                        <?= checkout_error($errors, 'name') ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone number</label>
                        <input class="form-control<?= checkout_invalid($errors, 'phone') ?>" type="tel" id="phone" name="phone"
                               value="<?= e($values['phone']) ?>" maxlength="30" autocomplete="tel" required placeholder="e.g. 08012345678">
                        <?= checkout_error($errors, 'phone') ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="address">Delivery address</label>
                        <input class="form-control<?= checkout_invalid($errors, 'address') ?>" type="text" id="address" name="address"
                               value="<?= e($values['address']) ?>" maxlength="255" autocomplete="street-address" required
                               placeholder="House number, street, area, nearest landmark">
                        <?= checkout_error($errors, 'address') ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="city">City / town</label>
                            <input class="form-control<?= checkout_invalid($errors, 'city') ?>" type="text" id="city" name="city"
                                   value="<?= e($values['city']) ?>" maxlength="100" autocomplete="address-level2" required>
                            <?= checkout_error($errors, 'city') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="state">State</label>
                            <select class="form-control<?= checkout_invalid($errors, 'state') ?>" id="state" name="state" autocomplete="address-level1" required>
                                <option value="">Select state</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?= e($state) ?>" <?= $values['state'] === $state ? 'selected' : '' ?>><?= e($state) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= checkout_error($errors, 'state') ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notes">Delivery notes <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control<?= checkout_invalid($errors, 'notes') ?>" id="notes" name="notes" rows="2"
                                  maxlength="500" placeholder="Gate code, best time to call, etc."><?= e($values['notes']) ?></textarea>
                        <?= checkout_error($errors, 'notes') ?>
                    </div>

                    <label class="check">
                        <input type="checkbox" name="save_address" value="1" <?= $values['save'] === '1' ? 'checked' : '' ?>>
                        <span>Save these details for next time</span>
                    </label>
                </section>

                <section class="panel" aria-labelledby="payment-title">
                    <h2 id="payment-title" class="panel-title">2. Payment method</h2>

                    <div class="type-picker" role="radiogroup" aria-label="Payment method">
                        <?php if ($paystackOn): ?>
                            <label class="radio-card">
                                <input type="radio" name="payment_method" value="paystack" <?= $values['method'] === 'paystack' ? 'checked' : '' ?>>
                                <span class="radio-card-body"><strong>Pay online</strong><small>Card, bank transfer or USSD via Paystack</small></span>
                            </label>
                        <?php endif; ?>
                        <label class="radio-card">
                            <input type="radio" name="payment_method" value="cod" <?= ($values['method'] === 'cod' || !$paystackOn) ? 'checked' : '' ?>>
                            <span class="radio-card-body"><strong>Pay on delivery</strong><small>Pay cash or transfer when your order arrives</small></span>
                        </label>
                    </div>
                    <?= checkout_error($errors, 'method') ?>
                </section>
            </div>

            <aside class="summary" aria-label="Order summary">
                <h2 class="summary-title">Order summary</h2>

                <ul class="summary-items">
                    <?php foreach ($cart['lines'] as $line): ?>
                        <li>
                            <span class="summary-item-name">
                                <?= e($line['name']) ?>
                                <?php if ($line['variant_label'] !== null): ?><small><?= e($line['variant_label']) ?></small><?php endif; ?>
                                <small>Qty <?= (int) $line['quantity'] ?></small>
                            </span>
                            <span><?= e(format_money($line['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="summary-rows">
                    <div><dt>Subtotal</dt><dd><?= e(format_money($cart['subtotal'])) ?></dd></div>
                    <div><dt>Delivery</dt><dd><?= $cart['shipping'] > 0 ? e(format_money($cart['shipping'])) : 'Free' ?></dd></div>
                    <div class="summary-total"><dt>Total</dt><dd><?= e(format_money($cart['total'])) ?></dd></div>
                </dl>

                <button type="submit" class="btn btn-primary btn-block">Place order</button>
                <p class="summary-note"><a href="<?= e(url('public/cart.php')) ?>">&lsaquo; Edit cart</a></p>
            </aside>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
