<?php
/**
 * NOVURA MARKETPLACE - SHOP (ALL PRODUCTS)
 * Path: public/shop.php
 *
 * Optional query parameters:
 *   flash=1          only live flash sales
 *   vendor=<slug>    only one store's products
 *   sort, min, max, page
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';

$filters = catalog_read_filters();
$flash   = input('flash') === '1';
$vendor  = null;
$params  = [];

$vendorSlug = input('vendor');
if ($vendorSlug !== '') {
    $vendor = catalog_vendor_by_slug($vendorSlug);

    if ($vendor === null) {
        abort(404, 'That store could not be found.');
    }

    $params['vendor'] = $vendor['slug'];
}

if ($flash) {
    $params['flash'] = '1';
}

$result = catalog_fetch_products([
    'flash'      => $flash,
    'vendor_id'  => $vendor['id'] ?? null,
    'min_price'  => $filters['min'],
    'max_price'  => $filters['max'],
    'sort'       => $filters['sort'],
    'page'       => $filters['page'],
]);

if ($vendor !== null) {
    $title    = $vendor['store_name'];
    $subtitle = $vendor['description'] ?: null;
} elseif ($flash) {
    $title    = 'Flash sales';
    $subtitle = 'Limited-time prices. When the timer ends, so does the deal.';
} else {
    $title    = 'All products';
    $subtitle = null;
}

$breadcrumbs = [['Home', url('public/index.php')]];
if ($vendor !== null) {
    $breadcrumbs[] = ['Stores', null];
}
$breadcrumbs[] = [$title, null];

$listing = [
    'title'              => $title,
    'subtitle'           => $subtitle,
    'path'               => 'public/shop.php',
    'params'             => $params,
    'sort_options'       => catalog_sort_options(),
    'breadcrumbs'        => $breadcrumbs,
    'sidebar_title'      => 'Categories',
    'sidebar_categories' => catalog_top_categories(),
    'active_slug'        => '',
    'empty_message'      => $flash ? 'There are no live flash sales right now. Check back soon.' : null,
];

$pageTitle       = $title;
$metaDescription = 'Browse ' . strtolower($title) . ' on ' . APP_NAME . '. Great prices from trusted sellers.';
$extraCss        = ['css/storefront.css'];
$extraScripts    = ['js/storefront.js'];

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/product_listing.php';
require_once __DIR__ . '/../includes/footer.php';
