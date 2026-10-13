<?php
/**
 * NOVURA MARKETPLACE - SITE HEADER (opens <html>, <body> and <main>)
 *
 * Run all redirects/guards/POST handling BEFORE including this file.
 *
 * Optional variables a page can set before including:
 *   $pageTitle        string   e.g. 'Sign in'  ->  "Sign in | Novura"
 *   $metaDescription  string
 *   $bodyClass        string
 *   $extraCss         array    asset paths, e.g. ['css/shop.css']
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/* -------------------------------------------------------------------------
 * Header-only helpers (guarded so later steps can reuse or replace them)
 * ---------------------------------------------------------------------- */

if (!function_exists('icon')) {
    /** Inline SVG icon (Feather-style). */
    function icon(string $name, int $size = 20): string
    {
        static $paths = [
            'search'  => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'cart'    => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
            'user'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'menu'    => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
            'heart'   => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
            'chevron' => '<polyline points="6 9 12 15 18 9"/>',
            'x'       => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'eye'     => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        ];

        if (!isset($paths[$name])) {
            return '';
        }

        return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}

if (!function_exists('cart_item_count')) {
    /** Total quantity in the visitor's cart (never creates a guest session token). */
    function cart_item_count(): int
    {
        static $count = null;

        if ($count !== null) {
            return $count;
        }

        $count  = 0;
        $userId = auth_id();

        if ($userId !== null) {
            $count = (int) db_value(
                'SELECT COALESCE(SUM(ci.quantity), 0)
                   FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
                  WHERE c.user_id = :user_id',
                ['user_id' => $userId]
            );
        } elseif (!empty($_SESSION['_guest_token']) && is_string($_SESSION['_guest_token'])) {
            $count = (int) db_value(
                'SELECT COALESCE(SUM(ci.quantity), 0)
                   FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
                  WHERE c.session_id = :token',
                ['token' => $_SESSION['_guest_token']]
            );
        }

        return $count;
    }
}

/* -------------------------------------------------------------------------
 * Page data
 * ---------------------------------------------------------------------- */

$fullTitle = (isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '')
    ? $pageTitle . ' | ' . APP_NAME
    : APP_NAME . ' - Shop online with confidence';

$metaDescription = (isset($metaDescription) && is_string($metaDescription) && $metaDescription !== '')
    ? $metaDescription
    : APP_NAME . ' is a multi-vendor marketplace. Discover great deals from trusted sellers and get it delivered.';

$bodyClass   = (isset($bodyClass) && is_string($bodyClass)) ? $bodyClass : '';
$extraCss    = (isset($extraCss) && is_array($extraCss)) ? $extraCss : [];
$currentUser = auth_user();
$cartCount   = cart_item_count();

$navCategories = db_fetch_all(
    'SELECT name, slug FROM categories
      WHERE parent_id IS NULL AND is_active = 1
      ORDER BY sort_order, name LIMIT 12'
);

$cssFile = NOVURA_ROOT . '/assets/css/app.css';
$cssVer  = is_file($cssFile) ? (string) filemtime($cssFile) : '1';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-cache');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#0F1B3D">
    <?= csrf_meta() ?>

    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=<?= e($cssVer) ?>">
    <?php foreach ($extraCss as $cssPath): ?>
        <?php if (is_string($cssPath)): ?>
    <link rel="stylesheet" href="<?= e(asset($cssPath)) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<div class="topbar">
    <div class="container topbar-inner">
        <span class="topbar-note">
            <?php if (FREE_SHIPPING_THRESHOLD > 0): ?>
                Free delivery on orders over <?= e(format_money(FREE_SHIPPING_THRESHOLD)) ?>
            <?php else: ?>
                Welcome to <?= e(APP_NAME) ?>
            <?php endif; ?>
        </span>
        <nav class="topbar-links" aria-label="Quick links">
            <a href="<?= e(url('public/register.php?type=vendor')) ?>">Sell on <?= e(APP_NAME) ?></a>
            <a href="<?= e(url('public/help.php')) ?>">Help</a>
        </nav>
    </div>
</div>

<header class="site-header">
    <div class="container header-main">
        <button type="button" class="icon-btn nav-toggle" data-nav-toggle
                aria-label="Open categories" aria-expanded="false" aria-controls="category-nav">
            <?= icon('menu', 24) ?>
        </button>

        <a class="logo" href="<?= e(url('public/index.php')) ?>" aria-label="<?= e(APP_NAME) ?> home">
            <?= e(APP_NAME) ?><span class="logo-dot">.</span>
        </a>

        <form class="search" action="<?= e(url('public/search.php')) ?>" method="get" role="search">
            <label class="sr-only" for="site-search">Search products</label>
            <input id="site-search" class="search-input" type="search" name="q"
                   value="<?= e(input('q')) ?>" placeholder="Search products, brands and categories"
                   maxlength="100" autocomplete="off">
            <button type="submit" class="search-btn" aria-label="Search"><?= icon('search', 20) ?><span>Search</span></button>
        </form>

        <div class="header-actions">
            <div class="dropdown" data-dropdown>
                <button type="button" class="header-link" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                    <?php if ($currentUser !== null): ?>
                        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $currentUser['first_name'], 0, 1))) ?></span>
                        <span class="header-link-text">Hi, <?= e($currentUser['first_name']) ?></span>
                    <?php else: ?>
                        <?= icon('user', 22) ?>
                        <span class="header-link-text">Account</span>
                    <?php endif; ?>
                    <?= icon('chevron', 16) ?>
                </button>

                <div class="dropdown-menu" role="menu">
                    <?php if ($currentUser === null): ?>
                        <a class="btn btn-primary btn-block" href="<?= e(url('public/login.php')) ?>">Sign in</a>
                        <a class="dropdown-item" href="<?= e(url('public/register.php')) ?>">Create an account</a>
                    <?php else: ?>
                        <a class="dropdown-item" href="<?= e(url('public/account.php')) ?>">My account</a>
                        <a class="dropdown-item" href="<?= e(url('public/orders.php')) ?>">My orders</a>
                        <a class="dropdown-item" href="<?= e(url('public/wishlist.php')) ?>">Wishlist</a>
                        <?php if ($currentUser['role'] === 'vendor'): ?>
                            <a class="dropdown-item" href="<?= e(url('vendor/index.php')) ?>">Vendor dashboard</a>
                        <?php elseif ($currentUser['role'] === 'admin'): ?>
                            <a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>">Admin panel</a>
                        <?php endif; ?>
                        <form class="dropdown-form" action="<?= e(url('public/logout.php')) ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item dropdown-item-danger">Sign out</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <a class="header-link" href="<?= e(url('public/cart.php')) ?>" aria-label="Cart, <?= (int) $cartCount ?> items">
                <span class="cart-icon">
                    <?= icon('cart', 22) ?>
                    <span class="cart-badge" data-cart-count <?= $cartCount > 0 ? '' : 'hidden' ?>><?= (int) $cartCount ?></span>
                </span>
                <span class="header-link-text">Cart</span>
            </a>
        </div>
    </div>

    <nav class="category-nav" id="category-nav" aria-label="Categories">
        <div class="container category-nav-inner">
            <a href="<?= e(url('public/shop.php')) ?>" class="category-link category-link-all">All products</a>
            <?php foreach ($navCategories as $category): ?>
                <a class="category-link" href="<?= e(url('public/category.php?slug=' . rawurlencode((string) $category['slug']))) ?>">
                    <?= e($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>

<?php $flashMessages = flash_get(); ?>
<?php if ($flashMessages): ?>
    <div class="container flash-wrap" data-flash>
        <?php foreach ($flashMessages as $flash): ?>
            <?php
            $flashType = in_array($flash['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info';
            ?>
            <div class="alert alert-<?= e($flashType) ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>">
                <span><?= e($flash['message'] ?? '') ?></span>
                <button type="button" class="alert-close" data-dismiss aria-label="Dismiss"><?= icon('x', 16) ?></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main id="main" class="site-main">
