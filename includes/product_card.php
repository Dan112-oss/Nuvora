<?php
/**
 * NOVURA MARKETPLACE - PRODUCT CARD
 * Path: includes/product_card.php
 *
 * Usage:  echo product_card($row);
 * $row comes from catalog_fetch_products() (needs name, slug, price, image, etc.).
 */

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

function product_card(array $product): string
{
    $price      = catalog_price($product);
    $href       = product_url((string) $product['slug']);
    $stock      = (int) ($product['stock_quantity'] ?? 0);
    $outOfStock = $stock <= 0;
    $reviews    = (int) ($product['review_count'] ?? 0);

    ob_start();
    ?>
    <article class="product-card">
        <a class="product-card-media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
            <img class="product-card-img" src="<?= e(upload_url($product['image'] ?? null)) ?>"
                 alt="" width="300" height="300" loading="lazy" decoding="async">

            <?php if ($outOfStock): ?>
                <span class="card-flag card-flag-out">Out of stock</span>
            <?php elseif ($price['discount'] > 0): ?>
                <span class="card-flag card-flag-sale">-<?= (int) $price['discount'] ?>%</span>
            <?php endif; ?>
        </a>

        <div class="product-card-body">
            <a class="product-card-name" href="<?= e($href) ?>"><?= e($product['name']) ?></a>

            <div class="product-card-price">
                <?= e(format_money($price['current'])) ?>
                <?php if ($price['old'] !== null): ?>
                    <span class="product-card-old"><?= e(format_money($price['old'])) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($reviews > 0): ?>
                <?= render_stars((float) $product['avg_rating'], $reviews) ?>
            <?php endif; ?>

            <?php if (!$outOfStock && $stock <= 5): ?>
                <p class="product-card-low"><?= $stock === 1 ? 'Only 1 left' : 'Only ' . $stock . ' left' ?></p>
            <?php endif; ?>

            <?php if (!empty($product['store_name'])): ?>
                <p class="product-card-vendor"><?= e($product['store_name']) ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}
