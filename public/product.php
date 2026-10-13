<?php
/**
 * NOVURA MARKETPLACE - PRODUCT PAGE
 * Path: public/product.php?slug=<product-slug>
 *
 * Variants: each variant row is ONE selectable option (e.g. "Size: XL" or
 * "Storage: 256GB") with its own price adjustment and stock.
 * The "Add to cart" button calls api/cart.php (built in the cart step).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';
require_once __DIR__ . '/../includes/product_card.php';

$slug    = input('slug');
$product = $slug !== '' ? catalog_product_by_slug($slug) : null;

if ($product === null) {
    abort(404, 'That product could not be found or is no longer available.');
}

$productId = (int) $product['id'];
catalog_register_view($productId);

$price     = catalog_price($product);
$images    = catalog_product_images($productId);
$variants  = catalog_product_variants($productId);
$reviews   = catalog_product_reviews($productId, 10);
$breakdown = catalog_rating_breakdown($productId);
$related   = catalog_fetch_products([
    'category_ids' => [(int) $product['category_id']],
    'exclude_id'   => $productId,
    'limit'        => 6,
    'sort'         => 'popular',
]);

$reviewCount  = (int) $product['review_count'];
$avgRating    = (float) $product['avg_rating'];
$hasVariants  = $variants !== [];

// Availability: variant stock when variants exist, otherwise product stock.
$totalStock = $product['stock_quantity'] !== null ? (int) $product['stock_quantity'] : 0;
if ($hasVariants) {
    $totalStock = array_sum(array_map(static fn (array $v): int => (int) $v['stock_quantity'], $variants));
}
$inStock = $totalStock > 0;

// Pre-select the first variant that is in stock.
$selectedVariantId = null;
foreach ($variants as $variant) {
    if ((int) $variant['stock_quantity'] > 0) {
        $selectedVariantId = (int) $variant['id'];
        break;
    }
}

$initialStock = $inStock ? $totalStock : 0;
if ($hasVariants && $selectedVariantId !== null) {
    foreach ($variants as $variant) {
        if ((int) $variant['id'] === $selectedVariantId) {
            $initialStock = (int) $variant['stock_quantity'];
        }
    }
}

// Gallery sources (placeholder when the vendor has not uploaded photos yet).
$gallery = [];
foreach ($images as $image) {
    $gallery[] = upload_url((string) $image['image_path']);
}
if (!$gallery) {
    $gallery[] = upload_url(null);
}

// Breadcrumbs: Home > category chain > product
$categoryRow = catalog_category_by_slug((string) $product['category_slug']);
$breadcrumbs = [['Home', url('public/index.php')]];
if ($categoryRow !== null) {
    foreach (catalog_category_ancestors($categoryRow) as $ancestor) {
        $breadcrumbs[] = [$ancestor['name'], url('public/category.php?slug=' . rawurlencode((string) $ancestor['slug']))];
    }
    $breadcrumbs[] = [$categoryRow['name'], url('public/category.php?slug=' . rawurlencode((string) $categoryRow['slug']))];
}
$breadcrumbs[] = [$product['name'], null];

// Group variants under their attribute name (Size, Colour, ...).
$variantGroups = [];
foreach ($variants as $variant) {
    $variantGroups[(string) $variant['attribute_name']][] = $variant;
}

// Structured data for search engines.
$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => $product['short_description'] ?: strip_tags((string) $product['description']),
    'sku'         => $product['sku'],
    'image'       => $gallery,
    'brand'       => $product['brand'] ? ['@type' => 'Brand', 'name' => $product['brand']] : null,
    'offers'      => [
        '@type'         => 'Offer',
        'url'           => product_url((string) $product['slug']),
        'priceCurrency' => CURRENCY_CODE,
        'price'         => number_format($price['current'], 2, '.', ''),
        'availability'  => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
];
if ($reviewCount > 0) {
    $jsonLd['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => number_format($avgRating, 1, '.', ''),
        'reviewCount' => $reviewCount,
    ];
}
$jsonLd = array_filter($jsonLd, static fn ($value): bool => $value !== null && $value !== '');

$pageTitle       = (string) $product['name'];
$metaDescription = (string) ($product['short_description'] ?: mb_substr(strip_tags((string) $product['description']), 0, 160));
$extraCss        = ['css/storefront.css'];
$extraScripts    = ['js/storefront.js'];
$bodyClass       = 'page-product';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <ol>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <li>
                    <?php if (!empty($crumb[1])): ?>
                        <a href="<?= e($crumb[1]) ?>"><?= e($crumb[0]) ?></a>
                    <?php else: ?>
                        <span aria-current="page"><?= e($crumb[0]) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <article class="pdp">
        <!-- ===== Gallery ===== -->
        <div class="pdp-gallery" data-gallery>
            <div class="pdp-main">
                <img src="<?= e($gallery[0]) ?>" alt="<?= e($product['name']) ?>" width="640" height="640" data-gallery-main decoding="async">
                <?php if ($price['discount'] > 0): ?>
                    <span class="card-flag card-flag-sale">-<?= (int) $price['discount'] ?>%</span>
                <?php endif; ?>
            </div>

            <?php if (count($gallery) > 1): ?>
                <div class="pdp-thumbs">
                    <?php foreach ($gallery as $i => $src): ?>
                        <button type="button" class="pdp-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-thumb
                                data-src="<?= e($src) ?>" aria-label="Show image <?= (int) ($i + 1) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
                            <img src="<?= e($src) ?>" alt="" width="72" height="72" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== Buy box ===== -->
        <div class="pdp-info">
            <h1 class="pdp-title"><?= e($product['name']) ?></h1>

            <p class="pdp-meta">
                <?php if (!empty($product['brand'])): ?>
                    <span>Brand: <strong><?= e($product['brand']) ?></strong></span>
                <?php endif; ?>
                <span>Sold by <a href="<?= e(url('public/shop.php?vendor=' . rawurlencode((string) $product['vendor_slug']))) ?>"><?= e($product['store_name']) ?></a></span>
                <?php if (!empty($product['sku'])): ?>
                    <span>SKU: <?= e($product['sku']) ?></span>
                <?php endif; ?>
            </p>

            <div class="pdp-rating">
                <?php if ($reviewCount > 0): ?>
                    <?= render_stars($avgRating, $reviewCount, false) ?>
                    <a href="#reviews"><?= (int) $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?></a>
                <?php else: ?>
                    <span class="text-muted">No reviews yet</span>
                <?php endif; ?>
            </div>

            <div class="pdp-price" data-price-box
                 data-base="<?= e(number_format($price['current'], 2, '.', '')) ?>"
                 data-old="<?= $price['old'] !== null ? e(number_format($price['old'], 2, '.', '')) : '' ?>"
                 data-symbol="<?= e(CURRENCY_SYMBOL) ?>">
                <span class="pdp-price-current" data-price-current><?= e(format_money($price['current'])) ?></span>
                <?php if ($price['old'] !== null): ?>
                    <span class="pdp-price-old" data-price-old><?= e(format_money($price['old'])) ?></span>
                    <span class="badge badge-sale">Save <?= (int) $price['discount'] ?>%</span>
                <?php endif; ?>
            </div>

            <?php if ($price['is_flash'] && $price['ends_at'] !== null): ?>
                <p class="pdp-flash">Flash sale ends in
                    <span class="countdown" data-countdown="<?= e(date('c', (int) strtotime($price['ends_at']))) ?>">soon</span>
                </p>
            <?php endif; ?>

            <p class="pdp-stock<?= $inStock ? '' : ' is-out' ?>" data-stock-text>
                <?php if (!$inStock): ?>
                    Out of stock
                <?php elseif ($initialStock <= 5): ?>
                    Only <?= (int) $initialStock ?> left in stock
                <?php else: ?>
                    In stock
                <?php endif; ?>
            </p>

            <?php if ($hasVariants): ?>
                <?php foreach ($variantGroups as $groupName => $options): ?>
                    <fieldset class="pdp-variants">
                        <legend><?= e($groupName) ?></legend>
                        <div class="pill-group">
                            <?php foreach ($options as $variant): ?>
                                <?php
                                $variantStock = (int) $variant['stock_quantity'];
                                $adjust       = (float) $variant['price_adjustment'];
                                ?>
                                <label class="pill<?= $variantStock <= 0 ? ' is-disabled' : '' ?>">
                                    <input type="radio" name="variant_id" value="<?= (int) $variant['id'] ?>"
                                           data-adjust="<?= e(number_format($adjust, 2, '.', '')) ?>"
                                           data-stock="<?= $variantStock ?>"
                                           <?= (int) $variant['id'] === $selectedVariantId ? 'checked' : '' ?>
                                           <?= $variantStock <= 0 ? 'disabled' : '' ?>>
                                    <span>
                                        <?= e($variant['attribute_value']) ?>
                                        <?php if ($adjust > 0): ?><small>+<?= e(format_money($adjust)) ?></small><?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="pdp-buy">
                <div class="qty" data-qty>
                    <button type="button" class="qty-btn" data-qty-minus aria-label="Decrease quantity">&minus;</button>
                    <input class="qty-input" type="number" value="1" min="1" max="<?= (int) min(20, max(1, $initialStock)) ?>"
                           inputmode="numeric" data-qty-input aria-label="Quantity" <?= $inStock ? '' : 'disabled' ?>>
                    <button type="button" class="qty-btn" data-qty-plus aria-label="Increase quantity">+</button>
                </div>

                <button type="button" class="btn btn-primary pdp-add" data-add-to-cart
                        data-product-id="<?= $productId ?>"
                        data-has-variants="<?= $hasVariants ? '1' : '0' ?>"
                        data-endpoint="<?= e(url('api/cart.php')) ?>"
                        <?= $inStock ? '' : 'disabled' ?>>
                    <?= icon('cart', 20) ?> <?= $inStock ? 'Add to cart' : 'Out of stock' ?>
                </button>
            </div>

            <ul class="pdp-notes">
                <li>Pay securely online or on delivery</li>
                <li>Ships from <?= e(trim(($product['vendor_city'] ?? '') . ', ' . ($product['vendor_state'] ?? ''), ', ')) ?: 'the seller' ?></li>
            </ul>
        </div>
    </article>

    <!-- ===== Description ===== -->
    <section class="pdp-section" id="description" aria-labelledby="desc-title">
        <h2 id="desc-title">Product details</h2>
        <?php if (!empty($product['description'])): ?>
            <div class="pdp-description"><?= nl2br(e($product['description'])) ?></div>
        <?php else: ?>
            <p class="text-muted"><?= e($product['short_description'] ?: 'No description has been added yet.') ?></p>
        <?php endif; ?>
    </section>

    <!-- ===== Reviews ===== -->
    <section class="pdp-section" id="reviews" aria-labelledby="reviews-title">
        <h2 id="reviews-title">Customer reviews</h2>

        <?php if ($reviewCount > 0): ?>
            <div class="reviews-layout">
                <div class="reviews-summary">
                    <p class="reviews-score"><?= e(number_format($avgRating, 1)) ?><span>/5</span></p>
                    <?= render_stars($avgRating, $reviewCount, false) ?>
                    <p class="text-muted"><?= (int) $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?></p>

                    <ul class="rating-bars">
                        <?php foreach ($breakdown as $stars => $count): ?>
                            <?php $percent = $reviewCount > 0 ? (int) round(($count / $reviewCount) * 100) : 0; ?>
                            <li>
                                <span><?= (int) $stars ?>&#9733;</span>
                                <span class="bar"><span class="bar-fill" style="width:<?= $percent ?>%"></span></span>
                                <span><?= (int) $count ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <ul class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <li class="review">
                            <div class="review-top">
                                <?= render_stars((float) $review['rating'], 0, false) ?>
                                <?php if (!empty($review['order_item_id'])): ?>
                                    <span class="badge">Verified purchase</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($review['title'])): ?>
                                <h3 class="review-title"><?= e($review['title']) ?></h3>
                            <?php endif; ?>
                            <?php if (!empty($review['comment'])): ?>
                                <p><?= nl2br(e($review['comment'])) ?></p>
                            <?php endif; ?>
                            <p class="review-by">
                                <?= e($review['first_name'] . ' ' . mb_substr((string) $review['last_name'], 0, 1) . '.') ?>
                                &middot; <?= e(date('j M Y', (int) strtotime((string) $review['created_at']))) ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <p class="text-muted">There are no reviews yet. Buyers can leave a review after their order is delivered.</p>
        <?php endif; ?>
    </section>

    <!-- ===== Related ===== -->
    <?php if ($related['items']): ?>
        <section class="home-section" aria-labelledby="related-title">
            <div class="section-head">
                <h2 id="related-title">You may also like</h2>
            </div>
            <div class="product-grid">
                <?php foreach ($related['items'] as $item): ?>
                    <?= product_card($item) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
