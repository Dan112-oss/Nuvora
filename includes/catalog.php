<?php
/**
 * NOVURA MARKETPLACE - CATALOG DATA LAYER
 * Path: includes/catalog.php
 *
 * Shared by the home page, shop, category, search and product pages.
 * Only ACTIVE products from APPROVED vendors are ever returned.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** SQL for the price a buyer pays right now (flash price while the sale is live). */
const CATALOG_PRICE_SQL = 'CASE WHEN p.is_flash_sale = 1 AND p.flash_sale_price IS NOT NULL AND (p.flash_sale_ends_at IS NULL OR p.flash_sale_ends_at > NOW()) THEN p.flash_sale_price ELSE p.price END';

/** SQL condition: product is in a live flash sale. */
const CATALOG_FLASH_SQL = '(p.is_flash_sale = 1 AND p.flash_sale_price IS NOT NULL AND (p.flash_sale_ends_at IS NULL OR p.flash_sale_ends_at > NOW()))';

/* -------------------------------------------------------------------------
 * Pricing and formatting
 * ---------------------------------------------------------------------- */

/**
 * Effective pricing for a product row.
 *
 * @return array{current: float, old: ?float, discount: int, is_flash: bool, ends_at: ?string}
 */
function catalog_price(array $product): array
{
    $price   = (float) ($product['price'] ?? 0);
    $compare = (float) ($product['compare_price'] ?? 0);
    $ends    = $product['flash_sale_ends_at'] ?? null;
    $isFlash = false;

    if (!empty($product['is_flash_sale']) && ($product['flash_sale_price'] ?? null) !== null) {
        $isFlash = ($ends === null) || (strtotime((string) $ends) > time());
    }

    $current = $isFlash ? (float) $product['flash_sale_price'] : $price;
    $old     = $isFlash ? max($price, $compare) : $compare;

    if ($old <= $current) {
        $old = 0.0;
    }

    return [
        'current'  => $current,
        'old'      => $old > 0 ? $old : null,
        'discount' => $old > 0 ? (int) round((1 - $current / $old) * 100) : 0,
        'is_flash' => $isFlash,
        'ends_at'  => $isFlash && $ends !== null ? (string) $ends : null,
    ];
}

/** Number without trailing zeros: 1000.50 -> "1000.5", 2000.00 -> "2000". */
function catalog_num(float $value): string
{
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

/** Star rating markup. The fill is pure CSS via --rating. */
function render_stars(float $rating, int $count = 0, bool $showCount = true): string
{
    $rating  = max(0.0, min(5.0, $rating));
    $percent = number_format(($rating / 5) * 100, 2, '.', '');

    $html = '<span class="rating">'
        . '<span class="stars" style="--rating:' . $percent . '%" role="img" aria-label="'
        . e(number_format($rating, 1) . ' out of 5 stars') . '"></span>';

    if ($showCount && $count > 0) {
        $html .= '<span class="rating-count">(' . (int) $count . ')</span>';
    }

    return $html . '</span>';
}

/* -------------------------------------------------------------------------
 * URLs and pagination
 * ---------------------------------------------------------------------- */

/** Build a URL with a clean query string (empty values are dropped). */
function catalog_url(string $path, array $params = []): string
{
    $params = array_filter($params, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    $query  = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return url($path) . ($query !== '' ? '?' . $query : '');
}

function product_url(string $slug): string
{
    return url('public/product.php?slug=' . rawurlencode($slug));
}

/** Previous / numbered / next pagination links. */
function catalog_pagination(int $page, int $pages, string $path, array $params): string
{
    if ($pages <= 1) {
        return '';
    }

    $link = static function (int $target) use ($path, $params): string {
        $query = $params;
        $query['page'] = $target > 1 ? $target : null;

        return e(catalog_url($path, $query));
    };

    $html = '<nav class="pagination" aria-label="Pagination"><ul>';

    if ($page > 1) {
        $html .= '<li><a class="page-link" href="' . $link($page - 1) . '" rel="prev">&lsaquo; Prev</a></li>';
    }

    $window = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
            $window[] = $i;
        }
    }

    $previous = 0;
    foreach ($window as $number) {
        if ($number - $previous > 1) {
            $html .= '<li><span class="page-gap">&hellip;</span></li>';
        }

        if ($number === $page) {
            $html .= '<li><span class="page-link is-current" aria-current="page">' . $number . '</span></li>';
        } else {
            $html .= '<li><a class="page-link" href="' . $link($number) . '">' . $number . '</a></li>';
        }

        $previous = $number;
    }

    if ($page < $pages) {
        $html .= '<li><a class="page-link" href="' . $link($page + 1) . '" rel="next">Next &rsaquo;</a></li>';
    }

    return $html . '</ul></nav>';
}

/* -------------------------------------------------------------------------
 * Listing filters and sorting
 * ---------------------------------------------------------------------- */

/** @return array<string,string> sort key => label */
function catalog_sort_options(bool $withRelevance = false): array
{
    $options = $withRelevance ? ['relevance' => 'Best match'] : [];

    return $options + [
        'newest'     => 'Newest',
        'popular'    => 'Most popular',
        'rating'     => 'Top rated',
        'price_asc'  => 'Price: low to high',
        'price_desc' => 'Price: high to low',
    ];
}

function catalog_order_sql(string $sort): string
{
    return match ($sort) {
        'price_asc'  => CATALOG_PRICE_SQL . ' ASC, p.id DESC',
        'price_desc' => CATALOG_PRICE_SQL . ' DESC, p.id DESC',
        'popular'    => 'p.view_count DESC, p.id DESC',
        'rating'     => 'p.avg_rating DESC, p.review_count DESC, p.id DESC',
        default      => 'p.created_at DESC, p.id DESC',
    };
}

/**
 * Read and validate listing filters from the query string.
 *
 * @return array{min: ?float, max: ?float, sort: string, page: int}
 */
function catalog_read_filters(bool $withRelevance = false): array
{
    $money = static function (string $key): ?float {
        $value = str_replace(',', '', input($key));

        return preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) === 1 ? (float) $value : null;
    };

    $min = $money('min');
    $max = $money('max');

    if ($min !== null && $max !== null && $min > $max) {
        [$min, $max] = [$max, $min];
    }

    $options = catalog_sort_options($withRelevance);
    $default = $withRelevance ? 'relevance' : 'newest';
    $sort    = input('sort', $default);

    if (!isset($options[$sort])) {
        $sort = $default;
    }

    return [
        'min'  => $min,
        'max'  => $max,
        'sort' => $sort,
        'page' => max(1, min(10000, input_int('page', 1))),
    ];
}

/** Split a search phrase into at most 5 terms. */
function catalog_search_terms(string $query): array
{
    $query = trim((string) preg_replace('/\s+/u', ' ', $query));

    if ($query === '') {
        return [];
    }

    $terms = explode(' ', mb_substr($query, 0, 100));

    return array_slice(array_values(array_filter($terms, static fn (string $t): bool => $t !== '')), 0, 5);
}

/* -------------------------------------------------------------------------
 * Product queries
 * ---------------------------------------------------------------------- */

/**
 * Fetch products for a listing.
 *
 * Options: category_ids (int[]), vendor_id, exclude_id, q, min_price, max_price,
 *          featured, flash, in_stock, sort, page, per_page, limit (no pagination).
 *
 * @return array{items: array, total: int, page: int, pages: int, per_page: int}
 */
function catalog_fetch_products(array $opts = []): array
{
    $where  = ["p.status = 'active'", "v.status = 'approved'"];
    $params = [];

    $categoryIds = $opts['category_ids'] ?? null;
    if (is_array($categoryIds) && $categoryIds) {
        $placeholders = [];
        foreach (array_values($categoryIds) as $i => $categoryId) {
            $placeholders[]      = ':cat' . $i;
            $params['cat' . $i] = (int) $categoryId;
        }
        $where[] = 'p.category_id IN (' . implode(',', $placeholders) . ')';
    }

    if (!empty($opts['vendor_id'])) {
        $where[]             = 'p.vendor_id = :vendor_id';
        $params['vendor_id'] = (int) $opts['vendor_id'];
    }

    if (!empty($opts['exclude_id'])) {
        $where[]              = 'p.id <> :exclude_id';
        $params['exclude_id'] = (int) $opts['exclude_id'];
    }

    if (!empty($opts['featured'])) {
        $where[] = 'p.is_featured = 1';
    }

    if (!empty($opts['flash'])) {
        $where[] = CATALOG_FLASH_SQL;
    }

    if (!empty($opts['in_stock'])) {
        $where[] = 'p.stock_quantity > 0';
    }

    if (isset($opts['min_price']) && $opts['min_price'] !== null) {
        $where[]             = CATALOG_PRICE_SQL . ' >= :min_price';
        $params['min_price'] = (string) (float) $opts['min_price'];
    }

    if (isset($opts['max_price']) && $opts['max_price'] !== null) {
        $where[]             = CATALOG_PRICE_SQL . ' <= :max_price';
        $params['max_price'] = (string) (float) $opts['max_price'];
    }

    // Every word must match name, brand or short description.
    // (Each placeholder is unique: native prepared statements cannot reuse names.)
    $terms = catalog_search_terms((string) ($opts['q'] ?? ''));
    foreach ($terms as $i => $term) {
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $where[] = "(p.name ILIKE :t{$i}a OR p.brand ILIKE :t{$i}b OR p.short_description ILIKE :t{$i}c)";
        $params["t{$i}a"] = $like;
        $params["t{$i}b"] = $like;
        $params["t{$i}c"] = $like;
    }

    $sort        = (string) ($opts['sort'] ?? 'newest');
    $orderParams = [];

    if ($sort === 'relevance' && $terms) {
        $phrase                  = addcslashes(implode(' ', $terms), '%_\\');
        $orderSql                = '(p.name ILIKE :rel_start) DESC, (p.name ILIKE :rel_any) DESC, p.view_count DESC, p.id DESC';
        $orderParams['rel_start'] = $phrase . '%';
        $orderParams['rel_any']   = '%' . $phrase . '%';
    } else {
        $orderSql = catalog_order_sql($sort === 'relevance' ? 'popular' : $sort);
    }

    $whereSql = implode(' AND ', $where);
    $from     = 'FROM products p JOIN vendors v ON v.id = p.vendor_id WHERE ' . $whereSql;

    $limit = isset($opts['limit']) ? max(1, min(60, (int) $opts['limit'])) : null;

    if ($limit !== null) {
        $perPage = $limit;
        $page    = 1;
        $total   = null;
        $pages   = 1;
    } else {
        $perPage = max(1, (int) ($opts['per_page'] ?? ITEMS_PER_PAGE));
        $total   = (int) db_value('SELECT COUNT(*) ' . $from, $params);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min((int) ($opts['page'] ?? 1), $pages));
    }

    $sql = 'SELECT p.id, p.name, p.slug, p.brand, p.price, p.compare_price, p.stock_quantity,
                   p.is_flash_sale, p.flash_sale_price, p.flash_sale_ends_at, p.is_featured,
                   p.avg_rating, p.review_count,
                   v.store_name, v.slug AS vendor_slug,
                   (SELECT pi.image_path FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image '
         . $from . ' ORDER BY ' . $orderSql . ' LIMIT :row_limit OFFSET :row_offset';

    $listParams = $params + $orderParams + [
        'row_limit'  => $perPage,
        'row_offset' => ($page - 1) * $perPage,
    ];

    $items = db_fetch_all($sql, $listParams);

    return [
        'items'    => $items,
        'total'    => $total ?? count($items),
        'page'     => $page,
        'pages'    => $pages,
        'per_page' => $perPage,
    ];
}

/** One product by slug (active product, approved vendor) or null. */
function catalog_product_by_slug(string $slug): ?array
{
    return db_fetch_one(
        "SELECT p.*, v.store_name, v.slug AS vendor_slug, v.city AS vendor_city, v.state AS vendor_state,
                c.name AS category_name, c.slug AS category_slug
           FROM products p
           JOIN vendors v    ON v.id = p.vendor_id
           JOIN categories c ON c.id = p.category_id
          WHERE p.slug = :slug AND p.status = 'active' AND v.status = 'approved'
          LIMIT 1",
        ['slug' => $slug]
    );
}

function catalog_product_images(int $productId): array
{
    return db_fetch_all(
        'SELECT id, image_path, is_primary FROM product_images
          WHERE product_id = :id ORDER BY is_primary DESC, sort_order, id',
        ['id' => $productId]
    );
}

function catalog_product_variants(int $productId): array
{
    return db_fetch_all(
        'SELECT id, attribute_name, attribute_value, price_adjustment, stock_quantity
           FROM product_variants WHERE product_id = :id AND is_active = 1 ORDER BY id',
        ['id' => $productId]
    );
}

function catalog_product_reviews(int $productId, int $limit = 10): array
{
    return db_fetch_all(
        "SELECT r.rating, r.title, r.comment, r.created_at, r.order_item_id,
                u.first_name, u.last_name
           FROM reviews r JOIN users u ON u.id = r.user_id
          WHERE r.product_id = :id AND r.status = 'approved'
          ORDER BY r.created_at DESC, r.id DESC LIMIT :row_limit",
        ['id' => $productId, 'row_limit' => $limit]
    );
}

/** @return array<int,int> star (1-5) => number of approved reviews */
function catalog_rating_breakdown(int $productId): array
{
    $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

    $rows = db_fetch_all(
        "SELECT rating, COUNT(*) AS total FROM reviews
          WHERE product_id = :id AND status = 'approved' GROUP BY rating",
        ['id' => $productId]
    );

    foreach ($rows as $row) {
        $breakdown[(int) $row['rating']] = (int) $row['total'];
    }

    return $breakdown;
}

/** Count a product view once per visitor session. */
function catalog_register_view(int $productId): void
{
    ensure_session();

    $viewed = is_array($_SESSION['_viewed'] ?? null) ? $_SESSION['_viewed'] : [];

    if (isset($viewed[$productId])) {
        return;
    }

    db_execute('UPDATE products SET view_count = view_count + 1 WHERE id = :id', ['id' => $productId]);

    $viewed[$productId] = time();
    $_SESSION['_viewed'] = array_slice($viewed, -100, null, true);
}

/* -------------------------------------------------------------------------
 * Categories
 * ---------------------------------------------------------------------- */

function catalog_top_categories(): array
{
    return db_fetch_all(
        'SELECT id, name, slug FROM categories
          WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order, name'
    );
}

/** Top-level categories, each with a "children" list (for menus). */
function catalog_category_menu(): array
{
    static $menu = null;

    if ($menu !== null) {
        return $menu;
    }

    $menu = catalog_top_categories();

    if (!$menu) {
        return $menu;
    }

    $children = db_fetch_all(
        'SELECT parent_id, name, slug FROM categories
          WHERE parent_id IS NOT NULL AND is_active = 1 ORDER BY sort_order, name'
    );

    $byParent = [];
    foreach ($children as $child) {
        $byParent[(int) $child['parent_id']][] = $child;
    }

    foreach ($menu as $index => $top) {
        $menu[$index]['children'] = $byParent[(int) $top['id']] ?? [];
    }

    return $menu;
}

function catalog_category_by_slug(string $slug): ?array
{
    return db_fetch_one(
        'SELECT id, parent_id, name, slug FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1',
        ['slug' => $slug]
    );
}

function catalog_child_categories(int $parentId): array
{
    return db_fetch_all(
        'SELECT id, name, slug FROM categories
          WHERE parent_id = :id AND is_active = 1 ORDER BY sort_order, name',
        ['id' => $parentId]
    );
}

/** Ancestors from the top-level category down to the direct parent. */
function catalog_category_ancestors(array $category): array
{
    $chain    = [];
    $parentId = $category['parent_id'] !== null ? (int) $category['parent_id'] : null;

    for ($depth = 0; $parentId !== null && $depth < 5; $depth++) {
        $parent = db_fetch_one(
            'SELECT id, parent_id, name, slug FROM categories WHERE id = :id LIMIT 1',
            ['id' => $parentId]
        );

        if ($parent === null) {
            break;
        }

        array_unshift($chain, $parent);
        $parentId = $parent['parent_id'] !== null ? (int) $parent['parent_id'] : null;
    }

    return $chain;
}

/** The category plus every active descendant id (up to 4 levels). */
function catalog_descendant_ids(int $categoryId): array
{
    $ids      = [$categoryId];
    $frontier = [$categoryId];

    for ($depth = 0; $depth < 4 && $frontier; $depth++) {
        $placeholders = implode(',', array_fill(0, count($frontier), '?'));
        $rows = db_fetch_all(
            "SELECT id FROM categories WHERE is_active = 1 AND parent_id IN ($placeholders)",
            $frontier
        );

        $frontier = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $ids      = array_merge($ids, $frontier);
    }

    return array_values(array_unique($ids));
}

/** Approved vendor by slug, or null. */
function catalog_vendor_by_slug(string $slug): ?array
{
    return db_fetch_one(
        "SELECT id, store_name, slug, description, city, state FROM vendors
          WHERE slug = :slug AND status = 'approved' LIMIT 1",
        ['slug' => $slug]
    );
}
