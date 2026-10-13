<?php
/**
 * NOVURA MARKETPLACE - SEARCH
 * Path: public/search.php?q=<words>
 *
 * Every word must appear in the product name, brand or short description.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';

$query   = mb_substr(input('q'), 0, 100);
$filters = catalog_read_filters(true);

$extraCss     = ['css/storefront.css'];
$extraScripts = ['js/storefront.js'];

// No search words: show a friendly prompt instead of listing everything.
if (catalog_search_terms($query) === []) {
    $pageTitle = 'Search';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container">
        <div class="empty-state">
            <h1>What are you looking for?</h1>
            <p>Type a product, brand or category in the search box above.</p>
            <a class="btn btn-primary" href="<?= e(url('public/shop.php')) ?>">Browse all products</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$result = catalog_fetch_products([
    'q'         => $query,
    'min_price' => $filters['min'],
    'max_price' => $filters['max'],
    'sort'      => $filters['sort'],
    'page'      => $filters['page'],
]);

$listing = [
    'title'              => 'Results for "' . $query . '"',
    'path'               => 'public/search.php',
    'params'             => ['q' => $query],
    'sort_options'       => catalog_sort_options(true),
    'breadcrumbs'        => [['Home', url('public/index.php')], ['Search', null]],
    'sidebar_title'      => 'Browse categories',
    'sidebar_categories' => catalog_top_categories(),
    'active_slug'        => '',
    'empty_message'      => 'Check the spelling, try fewer words, or browse a category.',
];

$pageTitle       = 'Search: ' . $query;
$metaDescription = 'Search results for ' . $query . ' on ' . APP_NAME . '.';

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/product_listing.php';
require_once __DIR__ . '/../includes/footer.php';
