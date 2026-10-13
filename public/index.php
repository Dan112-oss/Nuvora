<?php
/**
 * NOVURA MARKETPLACE - HOME PAGE
 * Path: public/index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';
require_once __DIR__ . '/../includes/product_card.php';

/** Banner image URL if the file really exists, otherwise null (CSS gradient is used). */
function home_banner_image(?string $path): ?string
{
    if ($path === null || $path === '' || str_contains($path, '..')) {
        return null;
    }

    return is_file(UPLOAD_PATH . '/' . ltrim($path, '/')) ? upload_url($path) : null;
}

$bannerSql = "SELECT id, title, subtitle, image, link_url FROM banners
               WHERE is_active = 1 AND position = :position
                 AND (starts_at IS NULL OR starts_at <= NOW())
                 AND (ends_at IS NULL OR ends_at >= NOW())
               ORDER BY sort_order, id LIMIT :row_limit";

$heroBanners  = db_fetch_all($bannerSql, ['position' => 'hero', 'row_limit' => 6]);
$promoBanners = db_fetch_all($bannerSql, ['position' => 'promo', 'row_limit' => 2]);

$categoryMenu = catalog_category_menu();

$flashSale = catalog_fetch_products(['flash' => true, 'limit' => 6, 'sort' => 'popular']);
$featured  = catalog_fetch_products(['featured' => true, 'limit' => 12, 'sort' => 'popular']);
$newest    = catalog_fetch_products(['limit' => 12, 'sort' => 'newest']);

// Countdown targets the soonest flash sale that is ending.
$flashEnds = null;
foreach ($flashSale['items'] as $flashItem) {
    $info = catalog_price($flashItem);
    if ($info['ends_at'] !== null) {
        $timestamp = strtotime($info['ends_at']);
        if ($timestamp !== false && ($flashEnds === null || $timestamp < $flashEnds)) {
            $flashEnds = $timestamp;
        }
    }
}

$extraCss     = ['css/storefront.css'];
$extraScripts = ['js/storefront.js'];
$bodyClass    = 'page-home';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

    <!-- ===== Top area: category menu | hero slider | promos ===== -->
    <section class="home-top" aria-label="Featured">
        <aside class="home-cats" aria-label="Shop by category">
            <ul class="home-cats-list">
                <?php foreach ($categoryMenu as $top): ?>
                    <li class="home-cats-item">
                        <a class="home-cats-link" href="<?= e(url('public/category.php?slug=' . rawurlencode((string) $top['slug']))) ?>">
                            <?= e($top['name']) ?>
                        </a>
                        <?php if ($top['children']): ?>
                            <ul class="home-cats-fly">
                                <?php foreach ($top['children'] as $child): ?>
                                    <li>
                                        <a href="<?= e(url('public/category.php?slug=' . rawurlencode((string) $child['slug']))) ?>">
                                            <?= e($child['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!$categoryMenu): ?>
                    <li class="home-cats-item"><a class="home-cats-link" href="<?= e(url('public/shop.php')) ?>">All products</a></li>
                <?php endif; ?>
            </ul>
        </aside>

        <div class="hero" data-hero aria-roledescription="carousel" aria-label="Promotions">
            <?php if (!$heroBanners): ?>
                <?php
                $heroBanners = [[
                    'title'    => 'Welcome to ' . APP_NAME,
                    'subtitle' => 'Shop trusted sellers and get it delivered to your door.',
                    'image'    => '',
                    'link_url' => 'public/shop.php',
                ]];
                ?>
            <?php endif; ?>

            <?php foreach ($heroBanners as $index => $banner): ?>
                <?php $image = home_banner_image($banner['image'] ?? null); ?>
                <a class="hero-slide hero-slide-<?= (int) ($index % 3) ?><?= $index === 0 ? ' is-active' : '' ?>"
                   href="<?= e(url((string) ($banner['link_url'] ?: 'public/shop.php'))) ?>"
                   <?= $image !== null ? 'style="background-image:url(\'' . e($image) . '\')"' : '' ?>
                   aria-roledescription="slide" aria-label="<?= (int) ($index + 1) ?> of <?= count($heroBanners) ?>"
                   <?= $index === 0 ? '' : 'tabindex="-1" aria-hidden="true"' ?>>
                    <span class="hero-copy">
                        <span class="hero-title"><?= e($banner['title']) ?></span>
                        <?php if (!empty($banner['subtitle'])): ?>
                            <span class="hero-sub"><?= e($banner['subtitle']) ?></span>
                        <?php endif; ?>
                        <span class="btn btn-primary hero-cta">Shop now</span>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php if (count($heroBanners) > 1): ?>
                <button type="button" class="hero-nav hero-prev" data-hero-prev aria-label="Previous slide">&lsaquo;</button>
                <button type="button" class="hero-nav hero-next" data-hero-next aria-label="Next slide">&rsaquo;</button>
                <div class="hero-dots" data-hero-dots></div>
            <?php endif; ?>
        </div>

        <div class="home-promos">
            <?php foreach ($promoBanners as $pIndex => $promo): ?>
                <?php $promoImage = home_banner_image($promo['image'] ?? null); ?>
                <a class="promo-tile promo-tile-<?= (int) ($pIndex % 2) ?>"
                   href="<?= e(url((string) ($promo['link_url'] ?: 'public/shop.php'))) ?>"
                   <?= $promoImage !== null ? 'style="background-image:url(\'' . e($promoImage) . '\')"' : '' ?>>
                    <span class="promo-title"><?= e($promo['title']) ?></span>
                    <?php if (!empty($promo['subtitle'])): ?>
                        <span class="promo-sub"><?= e($promo['subtitle']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Trust strip ===== -->
    <section class="trust-strip" aria-label="Why shop with us">
        <div class="trust-item"><strong>Secure payments</strong><span>Pay online or on delivery</span></div>
        <div class="trust-item"><strong>Fast delivery</strong><span>Straight to your door</span></div>
        <div class="trust-item"><strong>Verified sellers</strong><span>Every store is reviewed</span></div>
        <div class="trust-item"><strong>Easy support</strong><span>We are here to help</span></div>
    </section>

    <!-- ===== Flash sales ===== -->
    <?php if ($flashSale['items']): ?>
        <section class="home-section section-flash" aria-labelledby="flash-title">
            <div class="section-head section-head-flash">
                <h2 id="flash-title">Flash sales</h2>
                <?php if ($flashEnds !== null): ?>
                    <span class="countdown" data-countdown="<?= e(date('c', $flashEnds)) ?>" aria-live="off">Ends soon</span>
                <?php endif; ?>
                <a class="section-link" href="<?= e(url('public/shop.php?flash=1')) ?>">See all</a>
            </div>
            <div class="product-grid">
                <?php foreach ($flashSale['items'] as $product): ?>
                    <?= product_card($product) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ===== Shop by category ===== -->
    <?php if ($categoryMenu): ?>
        <section class="home-section" aria-labelledby="cats-title">
            <div class="section-head">
                <h2 id="cats-title">Shop by category</h2>
            </div>
            <div class="category-tiles">
                <?php foreach ($categoryMenu as $top): ?>
                    <a class="category-tile" href="<?= e(url('public/category.php?slug=' . rawurlencode((string) $top['slug']))) ?>">
                        <span class="category-tile-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $top['name'], 0, 1))) ?></span>
                        <span class="category-tile-name"><?= e($top['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ===== Featured ===== -->
    <?php if ($featured['items']): ?>
        <section class="home-section" aria-labelledby="featured-title">
            <div class="section-head">
                <h2 id="featured-title">Featured picks</h2>
                <a class="section-link" href="<?= e(url('public/shop.php?sort=popular')) ?>">See all</a>
            </div>
            <div class="product-grid">
                <?php foreach ($featured['items'] as $product): ?>
                    <?= product_card($product) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ===== New arrivals ===== -->
    <?php if ($newest['items']): ?>
        <section class="home-section" aria-labelledby="new-title">
            <div class="section-head">
                <h2 id="new-title">New arrivals</h2>
                <a class="section-link" href="<?= e(url('public/shop.php?sort=newest')) ?>">See all</a>
            </div>
            <div class="product-grid">
                <?php foreach ($newest['items'] as $product): ?>
                    <?= product_card($product) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!$featured['items'] && !$newest['items']): ?>
        <div class="empty-state">
            <h2>The shelves are empty</h2>
            <p>No products are live yet. Import <code>database/seed.sql</code> for demo data, or ask a seller to list items.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
