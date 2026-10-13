<?php
/**
 * NOVURA MARKETPLACE - PRODUCT LISTING VIEW (partial)
 * Path: includes/product_listing.php
 *
 * Included by shop.php, category.php and search.php AFTER the header.
 * Required variables:
 *   $listing  array  title, path, params, sort_options, breadcrumbs,
 *                    sidebar_title, sidebar_categories, active_slug,
 *                    subtitle (optional), empty_message (optional)
 *   $filters  array  from catalog_read_filters()
 *   $result   array  from catalog_fetch_products()
 */

declare(strict_types=1);

require_once __DIR__ . '/product_card.php';

$listPath     = (string) ($listing['path'] ?? 'public/shop.php');
$baseParams   = is_array($listing['params'] ?? null) ? $listing['params'] : [];
$sortOptions  = is_array($listing['sort_options'] ?? null) ? $listing['sort_options'] : catalog_sort_options();
$breadcrumbs  = is_array($listing['breadcrumbs'] ?? null) ? $listing['breadcrumbs'] : [];
$sidebarCats  = is_array($listing['sidebar_categories'] ?? null) ? $listing['sidebar_categories'] : [];
$activeSlug   = (string) ($listing['active_slug'] ?? '');
$minValue     = $filters['min'] !== null ? catalog_num((float) $filters['min']) : '';
$maxValue     = $filters['max'] !== null ? catalog_num((float) $filters['max']) : '';
$hasFilters   = $filters['min'] !== null || $filters['max'] !== null;
$clearUrl     = catalog_url($listPath, $baseParams + ['sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null]);

/** Print hidden inputs for the parameters that must survive a form submit. */
$hiddenFields = static function (array $params, array $skip = []): string {
    $html = '';
    foreach ($params as $name => $value) {
        if (in_array($name, $skip, true) || $value === null || $value === '' || is_array($value)) {
            continue;
        }
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }

    return $html;
};

$paginationParams = $baseParams + [
    'sort' => $filters['sort'],
    'min'  => $minValue,
    'max'  => $maxValue,
];
?>
<div class="container">
    <?php if ($breadcrumbs): ?>
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
    <?php endif; ?>

    <div class="listing-layout">
        <aside class="filters" id="filters">
            <?php if ($sidebarCats): ?>
                <h2 class="filters-title"><?= e($listing['sidebar_title'] ?? 'Categories') ?></h2>
                <ul class="filter-list">
                    <?php foreach ($sidebarCats as $sideCategory): ?>
                        <li>
                            <a class="filter-link<?= $activeSlug === $sideCategory['slug'] ? ' is-active' : '' ?>"
                               href="<?= e(url('public/category.php?slug=' . rawurlencode((string) $sideCategory['slug']))) ?>">
                                <?= e($sideCategory['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form class="price-filter" action="<?= e(url($listPath)) ?>" method="get">
                <?= $hiddenFields($baseParams) ?>
                <?php if ($filters['sort'] !== 'newest'): ?>
                    <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
                <?php endif; ?>

                <h2 class="filters-title">Price (<?= e(CURRENCY_SYMBOL) ?>)</h2>
                <div class="price-inputs">
                    <label class="sr-only" for="min-price">Minimum price</label>
                    <input class="form-control" type="number" id="min-price" name="min" min="0" step="any"
                           inputmode="decimal" placeholder="Min" value="<?= e($minValue) ?>">
                    <span aria-hidden="true">&ndash;</span>
                    <label class="sr-only" for="max-price">Maximum price</label>
                    <input class="form-control" type="number" id="max-price" name="max" min="0" step="any"
                           inputmode="decimal" placeholder="Max" value="<?= e($maxValue) ?>">
                </div>
                <button type="submit" class="btn btn-dark btn-block btn-sm mt-1">Apply</button>
                <?php if ($hasFilters): ?>
                    <a class="filters-clear" href="<?= e($clearUrl) ?>">Clear price filter</a>
                <?php endif; ?>
            </form>
        </aside>

        <section class="listing-main" aria-labelledby="listing-title">
            <div class="listing-head">
                <div>
                    <h1 class="listing-title" id="listing-title"><?= e($listing['title'] ?? 'Products') ?></h1>
                    <?php if (!empty($listing['subtitle'])): ?>
                        <p class="listing-sub"><?= e($listing['subtitle']) ?></p>
                    <?php endif; ?>
                    <p class="listing-count">
                        <?= (int) $result['total'] ?> <?= (int) $result['total'] === 1 ? 'product' : 'products' ?>
                    </p>
                </div>

                <div class="listing-tools">
                    <button type="button" class="btn btn-outline btn-sm filters-toggle" data-filters-toggle
                            aria-expanded="false" aria-controls="filters">Filters</button>

                    <form class="sort-form" action="<?= e(url($listPath)) ?>" method="get">
                        <?= $hiddenFields($baseParams) ?>
                        <?= $hiddenFields(['min' => $minValue, 'max' => $maxValue]) ?>
                        <label class="sr-only" for="sort">Sort by</label>
                        <select class="form-control sort-select" id="sort" name="sort" data-autosubmit>
                            <?php foreach ($sortOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-dark btn-sm">Sort</button></noscript>
                    </form>
                </div>
            </div>

            <?php if ($result['items']): ?>
                <div class="product-grid">
                    <?php foreach ($result['items'] as $product): ?>
                        <?= product_card($product) ?>
                    <?php endforeach; ?>
                </div>

                <?= catalog_pagination((int) $result['page'], (int) $result['pages'], $listPath, $paginationParams) ?>
            <?php else: ?>
                <div class="empty-state">
                    <h2>No products found</h2>
                    <p><?= e($listing['empty_message'] ?? 'Try changing your filters or browse another category.') ?></p>
                    <a class="btn btn-primary" href="<?= e(url('public/shop.php')) ?>">Browse all products</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
