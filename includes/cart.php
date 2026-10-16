<?php
/**
 * NOVURA MARKETPLACE - CART LOGIC
 * Path: includes/cart.php
 *
 * - Guests keep a cart keyed by guest_token(); logged-in users have a cart keyed by user id.
 * - The guest cart is merged into the user's cart the first time the cart is touched after login.
 * - Prices are NEVER stored in the cart; they are read live from the catalog.
 * - Stock is checked on every change (variant stock when a variant exists, else product stock).
 *
 * Pages must require includes/auth.php BEFORE this file and BEFORE includes/header.php,
 * so the cart badge in the header uses the merge-aware count below.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/catalog.php';

/** Maximum units of one product/variant in a cart. */
const CART_MAX_LINE_QTY = 20;

/* -------------------------------------------------------------------------
 * Finding / creating carts
 * ---------------------------------------------------------------------- */

/** Insert a cart row; if a parallel request created it first, return that one. */
function cart_insert(?int $userId, ?string $token): int
{
    $id = db_insert(
        'INSERT INTO carts (user_id, session_id) VALUES (:user_id, :token) ON CONFLICT DO NOTHING',
        ['user_id' => $userId, 'token' => $userId === null ? $token : null]
    );

    if ($id > 0) {
        return $id;
    }

    // A parallel request created the cart a moment earlier: use that one.
    $existing = $userId !== null
        ? db_value('SELECT id FROM carts WHERE user_id = :id LIMIT 1', ['id' => $userId])
        : db_value('SELECT id FROM carts WHERE session_id = :id LIMIT 1', ['id' => $token]);

    if ($existing === null) {
        throw new RuntimeException('Could not create a cart.');
    }

    return (int) $existing;
}

/** The cart id of a specific user (no session needed). */
function cart_user_cart_id(int $userId, bool $create = false): ?int
{
    $id = db_value('SELECT id FROM carts WHERE user_id = :id LIMIT 1', ['id' => $userId]);

    if ($id !== null) {
        return (int) $id;
    }

    return $create ? cart_insert($userId, null) : null;
}

/**
 * Cart id for the current visitor (user cart if signed in, otherwise guest cart).
 * Returns null when no cart exists yet and $create is false.
 */
function cart_find(bool $create = false): ?int
{
    if (!function_exists('auth_id')) {
        require_once __DIR__ . '/auth.php';
    }

    $userId = auth_id();

    if ($userId !== null) {
        cart_merge_guest($userId);

        return cart_user_cart_id($userId, $create);
    }

    ensure_session();

    $token = (!empty($_SESSION['_guest_token']) && is_string($_SESSION['_guest_token']))
        ? $_SESSION['_guest_token']
        : ($create ? guest_token() : null);

    if ($token === null) {
        return null;
    }

    $id = db_value('SELECT id FROM carts WHERE session_id = :token LIMIT 1', ['token' => $token]);

    if ($id !== null) {
        return (int) $id;
    }

    return $create ? cart_insert(null, $token) : null;
}

/** Merge the visitor's guest cart into their user cart (once per login). */
function cart_merge_guest(int $userId): void
{
    ensure_session();

    if (($_SESSION['_cart_merged'] ?? null) === $userId) {
        return;
    }

    $token = $_SESSION['_guest_token'] ?? null;

    if (is_string($token) && $token !== '') {
        $guestId = db_value('SELECT id FROM carts WHERE session_id = :token LIMIT 1', ['token' => $token]);

        if ($guestId !== null) {
            db_transaction(static function () use ($guestId, $userId): void {
                $userCartId = db_value('SELECT id FROM carts WHERE user_id = :id LIMIT 1 FOR UPDATE', ['id' => $userId]);

                if ($userCartId === null) {
                    // No user cart yet: the guest cart simply becomes the user's cart.
                    db_execute('UPDATE carts SET user_id = :user_id, session_id = NULL WHERE id = :id', [
                        'user_id' => $userId,
                        'id'      => (int) $guestId,
                    ]);

                    return;
                }

                $items = db_fetch_all(
                    'SELECT product_id, variant_id, quantity FROM cart_items WHERE cart_id = :id',
                    ['id' => (int) $guestId]
                );

                foreach ($items as $item) {
                    cart_put_item(
                        (int) $userCartId,
                        (int) $item['product_id'],
                        $item['variant_id'] !== null ? (int) $item['variant_id'] : null,
                        (int) $item['quantity']
                    );
                }

                db_execute('DELETE FROM carts WHERE id = :id', ['id' => (int) $guestId]);
            });
        }
    }

    $_SESSION['_cart_merged'] = $userId;
}

/* -------------------------------------------------------------------------
 * Stock-aware item operations
 * ---------------------------------------------------------------------- */

/**
 * Can this product (and variant) be bought, and how many are in stock?
 *
 * @return array{ok: bool, stock: int, message: string}
 */
function cart_stock_info(int $productId, ?int $variantId): array
{
    $product = db_fetch_one(
        "SELECT p.id, p.stock_quantity
           FROM products p JOIN vendors v ON v.id = p.vendor_id
          WHERE p.id = :id AND p.status = 'active' AND v.status = 'approved' LIMIT 1",
        ['id' => $productId]
    );

    if ($product === null) {
        return ['ok' => false, 'stock' => 0, 'message' => 'This product is no longer available.'];
    }

    $variantCount = (int) db_value(
        'SELECT COUNT(*) FROM product_variants WHERE product_id = :id AND is_active = 1',
        ['id' => $productId]
    );

    if ($variantCount > 0) {
        if ($variantId === null) {
            return ['ok' => false, 'stock' => 0, 'message' => 'Please choose an option first.'];
        }

        $variant = db_fetch_one(
            'SELECT stock_quantity FROM product_variants WHERE id = :id AND product_id = :product AND is_active = 1 LIMIT 1',
            ['id' => $variantId, 'product' => $productId]
        );

        if ($variant === null) {
            return ['ok' => false, 'stock' => 0, 'message' => 'That option is not available.'];
        }

        $stock = (int) $variant['stock_quantity'];
    } else {
        if ($variantId !== null) {
            return ['ok' => false, 'stock' => 0, 'message' => 'That option is not available.'];
        }

        $stock = (int) $product['stock_quantity'];
    }

    if ($stock <= 0) {
        return ['ok' => false, 'stock' => 0, 'message' => 'Sorry, this item is out of stock.'];
    }

    return ['ok' => true, 'stock' => $stock, 'message' => ''];
}

/**
 * Add quantity to a cart (additive), capped by stock and CART_MAX_LINE_QTY.
 *
 * @return array{ok: bool, message: string, quantity: int, added: int}
 */
function cart_put_item(int $cartId, int $productId, ?int $variantId, int $quantity): array
{
    $info = cart_stock_info($productId, $variantId);

    if (!$info['ok']) {
        return ['ok' => false, 'message' => $info['message'], 'quantity' => 0, 'added' => 0];
    }

    $existing = $variantId === null
        ? db_fetch_one(
            'SELECT id, quantity FROM cart_items WHERE cart_id = :cart AND product_id = :product AND variant_id IS NULL LIMIT 1',
            ['cart' => $cartId, 'product' => $productId]
        )
        : db_fetch_one(
            'SELECT id, quantity FROM cart_items WHERE cart_id = :cart AND product_id = :product AND variant_id = :variant LIMIT 1',
            ['cart' => $cartId, 'product' => $productId, 'variant' => $variantId]
        );

    $current = $existing !== null ? (int) $existing['quantity'] : 0;
    $limit   = min($info['stock'], CART_MAX_LINE_QTY);
    $newQty  = min($limit, $current + max(1, $quantity));

    if ($newQty <= $current) {
        return [
            'ok'       => false,
            'message'  => 'You already have the maximum available quantity (' . $current . ') in your cart.',
            'quantity' => $current,
            'added'    => 0,
        ];
    }

    if ($existing !== null) {
        db_execute('UPDATE cart_items SET quantity = :qty WHERE id = :id', ['qty' => $newQty, 'id' => (int) $existing['id']]);
    } else {
        db_insert(
            'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (:cart, :product, :variant, :qty)',
            ['cart' => $cartId, 'product' => $productId, 'variant' => $variantId, 'qty' => $newQty]
        );
    }

    db_execute('UPDATE carts SET updated_at = NOW() WHERE id = :id', ['id' => $cartId]);

    return ['ok' => true, 'message' => '', 'quantity' => $newQty, 'added' => $newQty - $current];
}

/** Add a product to the visitor's cart. @return array{ok: bool, message: string} */
function cart_add(int $productId, ?int $variantId, int $quantity): array
{
    $quantity = max(1, min(CART_MAX_LINE_QTY, $quantity));

    // Validate first so a bad request never creates an empty cart.
    $info = cart_stock_info($productId, $variantId);
    if (!$info['ok']) {
        return ['ok' => false, 'message' => $info['message']];
    }

    $cartId = cart_find(true);
    if ($cartId === null) {
        return ['ok' => false, 'message' => 'Could not open your cart. Please try again.'];
    }

    $result = cart_put_item($cartId, $productId, $variantId, $quantity);

    if (!$result['ok']) {
        return ['ok' => false, 'message' => $result['message']];
    }

    $message = $result['added'] < $quantity
        ? 'Added ' . $result['added'] . ' to your cart (maximum available reached).'
        : 'Added to your cart.';

    return ['ok' => true, 'message' => $message];
}

/** Set the quantity of a cart line (0 removes it). @return array{ok: bool, message: string} */
function cart_update(int $itemId, int $quantity): array
{
    $cartId = cart_find(false);

    $item = $cartId === null ? null : db_fetch_one(
        'SELECT id, product_id, variant_id FROM cart_items WHERE id = :id AND cart_id = :cart LIMIT 1',
        ['id' => $itemId, 'cart' => $cartId]
    );

    if ($item === null) {
        return ['ok' => false, 'message' => 'That item is not in your cart.'];
    }

    if ($quantity <= 0) {
        return cart_remove($itemId);
    }

    $info = cart_stock_info((int) $item['product_id'], $item['variant_id'] !== null ? (int) $item['variant_id'] : null);

    if (!$info['ok']) {
        return ['ok' => false, 'message' => $info['message']];
    }

    $newQty = min($quantity, $info['stock'], CART_MAX_LINE_QTY);

    db_execute('UPDATE cart_items SET quantity = :qty WHERE id = :id', ['qty' => $newQty, 'id' => $itemId]);

    return [
        'ok'      => true,
        'message' => $newQty < $quantity ? 'Quantity adjusted to the maximum available (' . $newQty . ').' : 'Cart updated.',
    ];
}

/** Remove a cart line. @return array{ok: bool, message: string} */
function cart_remove(int $itemId): array
{
    $cartId = cart_find(false);

    if ($cartId === null) {
        return ['ok' => false, 'message' => 'That item is not in your cart.'];
    }

    $removed = db_execute('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart', ['id' => $itemId, 'cart' => $cartId]);

    return $removed > 0
        ? ['ok' => true, 'message' => 'Item removed from your cart.']
        : ['ok' => false, 'message' => 'That item is not in your cart.'];
}

/**
 * Put items back into a user's cart (used when a payment fails or is abandoned).
 * Works without a session. Returns how many lines were restored.
 *
 * @param array<int,array{product_id: mixed, variant_id: mixed, quantity: mixed}> $items
 */
function cart_restore_items(int $userId, array $items): int
{
    if (!$items) {
        return 0;
    }

    $cartId   = cart_user_cart_id($userId, true);
    $restored = 0;

    if ($cartId === null) {
        return 0;
    }

    foreach ($items as $item) {
        if ($item['product_id'] === null) {
            continue;
        }

        $result = cart_put_item(
            $cartId,
            (int) $item['product_id'],
            $item['variant_id'] !== null ? (int) $item['variant_id'] : null,
            (int) $item['quantity']
        );

        if ($result['ok']) {
            $restored++;
        }
    }

    return $restored;
}

/* -------------------------------------------------------------------------
 * Totals and loading
 * ---------------------------------------------------------------------- */

/** Delivery fee for a subtotal (free above the configured threshold). */
function cart_shipping_fee(float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }

    if (FREE_SHIPPING_THRESHOLD > 0 && $subtotal >= FREE_SHIPPING_THRESHOLD) {
        return 0.0;
    }

    return (float) FLAT_SHIPPING_FEE;
}

/**
 * Load the visitor's cart with live prices and stock.
 * Quantities above available stock are lowered automatically (and reported in "notices").
 *
 * @return array{cart_id: ?int, lines: array, notices: array, count: int, subtotal: float,
 *               shipping: float, total: float, unavailable: int, free_shipping_remaining: float}
 */
function cart_load(): array
{
    $empty = [
        'cart_id' => null, 'lines' => [], 'notices' => [], 'count' => 0, 'subtotal' => 0.0,
        'shipping' => 0.0, 'total' => 0.0, 'unavailable' => 0, 'free_shipping_remaining' => 0.0,
    ];

    $cartId = cart_find(false);
    if ($cartId === null) {
        return $empty;
    }

    $rows = db_fetch_all(
        'SELECT ci.id AS item_id, ci.quantity, ci.variant_id,
                p.id AS product_id, p.name, p.slug, p.price, p.compare_price, p.stock_quantity,
                p.is_flash_sale, p.flash_sale_price, p.flash_sale_ends_at, p.status AS product_status,
                v.id AS vendor_id, v.store_name, v.status AS vendor_status,
                pv.attribute_name, pv.attribute_value, pv.price_adjustment,
                pv.stock_quantity AS variant_stock, pv.is_active AS variant_active,
                (SELECT pi.image_path FROM product_images pi
                  WHERE pi.product_id = p.id
                  ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image
           FROM cart_items ci
           JOIN products p ON p.id = ci.product_id
           JOIN vendors v  ON v.id = p.vendor_id
           LEFT JOIN product_variants pv ON pv.id = ci.variant_id
          WHERE ci.cart_id = :cart
          ORDER BY ci.id',
        ['cart' => $cartId]
    );

    $cart            = $empty;
    $cart['cart_id'] = $cartId;

    foreach ($rows as $row) {
        $hasVariant = $row['variant_id'] !== null;
        $stock      = $hasVariant ? (int) $row['variant_stock'] : (int) $row['stock_quantity'];

        $available = $row['product_status'] === 'active'
            && $row['vendor_status'] === 'approved'
            && (!$hasVariant || (int) $row['variant_active'] === 1)
            && $stock > 0;

        $quantity = (int) $row['quantity'];
        $maxQty   = $available ? min($stock, CART_MAX_LINE_QTY) : 0;

        if ($available && $quantity > $maxQty) {
            db_execute('UPDATE cart_items SET quantity = :qty WHERE id = :id', ['qty' => $maxQty, 'id' => (int) $row['item_id']]);
            $cart['notices'][] = 'The quantity of "' . $row['name'] . '" was lowered to ' . $maxQty . ' because of limited stock.';
            $quantity = $maxQty;
        }

        $price     = catalog_price($row);
        $adjust    = $hasVariant ? (float) $row['price_adjustment'] : 0.0;
        $unitPrice = $price['current'] + $adjust;

        if (!$available) {
            $cart['unavailable']++;
        }

        $cart['lines'][] = [
            'item_id'       => (int) $row['item_id'],
            'product_id'    => (int) $row['product_id'],
            'variant_id'    => $hasVariant ? (int) $row['variant_id'] : null,
            'name'          => (string) $row['name'],
            'slug'          => (string) $row['slug'],
            'variant_label' => $hasVariant ? $row['attribute_name'] . ': ' . $row['attribute_value'] : null,
            'image'         => $row['image'],
            'store_name'    => (string) $row['store_name'],
            'unit_price'    => $unitPrice,
            'old_price'     => $price['old'] !== null ? $price['old'] + $adjust : null,
            'quantity'      => $quantity,
            'line_total'    => $available ? round($unitPrice * $quantity, 2) : 0.0,
            'stock'         => $stock,
            'max_qty'       => $maxQty,
            'available'     => $available,
        ];

        $cart['count'] += $quantity;

        if ($available) {
            $cart['subtotal'] += round($unitPrice * $quantity, 2);
        }
    }

    $cart['subtotal'] = round($cart['subtotal'], 2);
    $cart['shipping'] = cart_shipping_fee($cart['subtotal']);
    $cart['total']    = round($cart['subtotal'] + $cart['shipping'], 2);

    if (FREE_SHIPPING_THRESHOLD > 0 && $cart['subtotal'] > 0 && $cart['subtotal'] < FREE_SHIPPING_THRESHOLD) {
        $cart['free_shipping_remaining'] = round(FREE_SHIPPING_THRESHOLD - $cart['subtotal'], 2);
    }

    return $cart;
}

/* -------------------------------------------------------------------------
 * Header badge (replaces the simpler fallback in includes/header.php)
 * ---------------------------------------------------------------------- */

if (!function_exists('cart_item_count')) {
    /** Total quantity in the visitor's cart (merges a guest cart after login). */
    function cart_item_count(): int
    {
        $cartId = cart_find(false);

        if ($cartId === null) {
            return 0;
        }

        return (int) db_value('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = :cart', ['cart' => $cartId]);
    }
}
