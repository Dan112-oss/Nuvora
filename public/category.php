<?php
/**
 * NOVURA MARKETPLACE - CATEGORY PAGE
 * Path: public/category.php?slug=<category-slug>
 *
 * Shows products from the category AND all of its sub-categories.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';

$slug     = input('slug');
$category = $slug !== '' ? catalog_category_by_slug($slug) : null;

if ($category === null) {
    abort(404, 'That category could not be found.');
}

$categoryId = (int) $category['id'];
$filters    = catalog_read_filters();
$ancestors  = catalog_category_ancestors($category);

$result = catalog_fetch_products([
    'category_ids' => catalog_descendant_ids($categoryId),
    'min_price'    => $filters['min'],
    'max_price'    => $filters['max'],
    'sort'         => $filters['sort'],
    'page'         => $filters['page'],
]);

// Sidebar: sub-categories if there are any, otherwise the sibling categories.
$children = catalog_child_categories($categoryId);
if ($children) {
    $sidebarTitle      = 'In ' . $category['name'];
    $sidebarCategories = $children;
} elseif ($category['parent_id'] !== null) {
    $parent            = $ancestors ? end($ancestors) : null;
    $sidebarTitle      = $parent !== null ? 'More in ' . $parent['name'] : 'Categories';
    $sidebarCategories = catalog_child_categories((int) $category['parent_id']);
} else {
    $sidebarTitle      = 'Categories';
    $sidebarCategories = catalog_top_categories();
}

$breadcrumbs = [['Home', url('public/index.php')]];
foreach ($ancestors as $ancestor) {
    $breadcrumbs[] = [$ancestor['name'], url('public/category.php?slug=' . rawurlencode((string) $ancestor['slug']))];
}
$breadcrumbs[] = [$category['name'], null];

$listing = [
    'title'              => $category['name'],
    'path'               => 'public/category.php',
    'params'             => ['slug' => $category['slug']],
    'sort_options'       => catalog_sort_options(),
    'breadcrumbs'        => $breadcrumbs,
    'sidebar_title'      => $sidebarTitle,
    'sidebar_categories' => $sidebarCategories,
    'active_slug'        => (string) $category['slug'],
    'empty_message'      => 'There are no products in this category yet.',
];

$pageTitle       = $category['name'];
$metaDescription = 'Shop ' . $category['name'] . ' on ' . APP_NAME . '. Compare prices and buy from trusted sellers.';
$extraCss        = ['css/storefront.css'];
$extraScripts    = ['js/storefront.js'];

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/product_listing.php';
require_once __DIR__ . '/../includes/footer.php';
