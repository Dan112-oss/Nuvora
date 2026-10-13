<?php
/**
 * NOVURA MARKETPLACE - SITE FOOTER (closes <main>, <body> and <html>)
 *
 * Optional variable a page can set before including:
 *   $extraScripts  array  asset paths, e.g. ['js/shop.js']
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$extraScripts = (isset($extraScripts) && is_array($extraScripts)) ? $extraScripts : [];

$jsFile = NOVURA_ROOT . '/assets/js/app.js';
$jsVer  = is_file($jsFile) ? (string) filemtime($jsFile) : '1';
?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-col footer-brand">
            <a class="logo logo-light" href="<?= e(url('public/index.php')) ?>"><?= e(APP_NAME) ?><span class="logo-dot">.</span></a>
            <p>Shop from trusted sellers, all in one place. Great prices, secure payment and delivery to your door.</p>
        </div>

        <div class="footer-col">
            <h2 class="footer-title">Shop</h2>
            <ul class="footer-list">
                <li><a href="<?= e(url('public/shop.php')) ?>">All products</a></li>
                <li><a href="<?= e(url('public/deals.php')) ?>">Flash sales</a></li>
                <li><a href="<?= e(url('public/cart.php')) ?>">Your cart</a></li>
                <li><a href="<?= e(url('public/orders.php')) ?>">Track an order</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h2 class="footer-title">Sell</h2>
            <ul class="footer-list">
                <li><a href="<?= e(url('public/register.php?type=vendor')) ?>">Sell on <?= e(APP_NAME) ?></a></li>
                <li><a href="<?= e(url('vendor/index.php')) ?>">Vendor dashboard</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h2 class="footer-title">Help</h2>
            <ul class="footer-list">
                <li><a href="<?= e(url('public/help.php')) ?>">Help center</a></li>
                <li><a href="<?= e(url('public/terms.php')) ?>">Terms &amp; conditions</a></li>
                <li><a href="<?= e(url('public/privacy.php')) ?>">Privacy policy</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <span>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?>. All rights reserved.</span>
            <span>Payments in <?= e(CURRENCY_CODE) ?> &middot; Secure checkout</span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>?v=<?= e($jsVer) ?>" defer></script>
<?php foreach ($extraScripts as $scriptPath): ?>
    <?php if (is_string($scriptPath)): ?>
<script src="<?= e(asset($scriptPath)) ?>" defer></script>
    <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
