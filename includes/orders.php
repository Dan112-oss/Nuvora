<?php
/**
 * NOVURA MARKETPLACE - ORDER ENGINE
 * Path: includes/orders.php
 *
 * - orders_create():     turns the user's cart into an order inside ONE transaction
 *                        (rows locked, stock re-checked, prices re-read from the database).
 * - orders_cancel():     cancels an unpaid order and puts the stock back.
 * - orders_mark_paid():  idempotent payment confirmation (callback AND webhook both use it).
 * - paystack_*():        thin Paystack API client (cURL).
 *
 * Session-free on purpose: the Paystack webhook includes this file.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/cart.php';

/** Unpaid Paystack orders older than this are cancelled and their stock released. */
const ORDER_PAYMENT_WINDOW_MINUTES = 60;

/** Thrown with a message that is safe to show to the buyer. */
class OrderException extends RuntimeException
{
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

function orders_generate_number(): string
{
    return 'NVR-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/** Lock rows of a table and return them keyed by id. */
function orders_lock_rows(string $table, array $ids): array
{
    if (!in_array($table, ['products', 'product_variants'], true)) {
        throw new LogicException('Unsupported table for locking.');
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids);

    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows         = db_fetch_all("SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY id FOR UPDATE", $ids);

    $keyed = [];
    foreach ($rows as $row) {
        $keyed[(int) $row['id']] = $row;
    }

    return $keyed;
}

/* -------------------------------------------------------------------------
 * Create
 * ---------------------------------------------------------------------- */

/**
 * Create an order from the user's cart.
 *
 * $shipping keys: name, phone, address, city, state, notes (optional)
 *
 * @return array{order_id: int, order_number: string, total: float, reference: string, method: string}
 * @throws OrderException with a buyer-friendly message
 */
function orders_create(int $userId, array $shipping, string $method): array
{
    if (!in_array($method, ['paystack', 'cod'], true)) {
        throw new OrderException('Please choose a valid payment method.');
    }

    return db_transaction(static function () use ($userId, $shipping, $method): array {
        $cartId = cart_user_cart_id($userId);

        $items = $cartId === null ? [] : db_fetch_all(
            'SELECT product_id, variant_id, quantity FROM cart_items WHERE cart_id = :cart ORDER BY product_id, variant_id, id',
            ['cart' => $cartId]
        );

        if (!$items) {
            throw new OrderException('Your cart is empty.');
        }

        $products = orders_lock_rows('products', array_map(static fn (array $i): int => (int) $i['product_id'], $items));

        $variantIds = [];
        foreach ($items as $item) {
            if ($item['variant_id'] !== null) {
                $variantIds[] = (int) $item['variant_id'];
            }
        }
        $variants = orders_lock_rows('product_variants', $variantIds);

        // Vendors (commission rate + approval)
        $vendorIds = array_values(array_unique(array_map(static fn (array $p): int => (int) $p['vendor_id'], $products)));
        $vendors   = [];
        if ($vendorIds) {
            $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
            foreach (db_fetch_all("SELECT id, commission_rate, status FROM vendors WHERE id IN ({$placeholders})", $vendorIds) as $vendor) {
                $vendors[(int) $vendor['id']] = $vendor;
            }
        }

        // Products that REQUIRE a variant choice
        $needsVariant = [];
        if ($products) {
            $placeholders = implode(',', array_fill(0, count($products), '?'));
            foreach (db_fetch_all(
                "SELECT DISTINCT product_id FROM product_variants WHERE is_active = 1 AND product_id IN ({$placeholders})",
                array_keys($products)
            ) as $row) {
                $needsVariant[(int) $row['product_id']] = true;
            }
        }

        $lines     = [];
        $stockLeft = [];
        $subtotal  = 0.0;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = $item['variant_id'] !== null ? (int) $item['variant_id'] : null;
            $quantity  = (int) $item['quantity'];
            $product   = $products[$productId] ?? null;

            if ($product === null
                || $product['status'] !== 'active'
                || ($vendors[(int) $product['vendor_id']]['status'] ?? '') !== 'approved') {
                throw new OrderException('An item in your cart is no longer available. Please review your cart.');
            }

            $name    = (string) $product['name'];
            $variant = null;

            if ($variantId !== null) {
                $variant = $variants[$variantId] ?? null;

                if ($variant === null || (int) $variant['product_id'] !== $productId || (int) $variant['is_active'] !== 1) {
                    throw new OrderException('An option for "' . $name . '" is no longer available. Please review your cart.');
                }
            } elseif (!empty($needsVariant[$productId])) {
                throw new OrderException('Please choose an option for "' . $name . '" in your cart.');
            }

            $stockKey = $variantId !== null ? 'v' . $variantId : 'p' . $productId;
            $left     = $stockLeft[$stockKey] ?? ($variant !== null ? (int) $variant['stock_quantity'] : (int) $product['stock_quantity']);

            if ($quantity > $left) {
                throw new OrderException($left <= 0
                    ? '"' . $name . '" is now out of stock. Please update your cart.'
                    : 'Only ' . $left . ' of "' . $name . '" left in stock. Please update your cart.');
            }

            $stockLeft[$stockKey] = $left - $quantity;

            $unitPrice  = round(catalog_price($product)['current'] + (float) ($variant['price_adjustment'] ?? 0), 2);
            $lineTotal  = round($unitPrice * $quantity, 2);
            $rate       = (float) $vendors[(int) $product['vendor_id']]['commission_rate'];
            $commission = round($lineTotal * $rate / 100, 2);

            $lines[] = [
                'product_id'    => $productId,
                'variant_id'    => $variantId,
                'vendor_id'     => (int) $product['vendor_id'],
                'name'          => $name,
                'variant_label' => $variant !== null ? $variant['attribute_name'] . ': ' . $variant['attribute_value'] : null,
                'unit_price'    => $unitPrice,
                'quantity'      => $quantity,
                'line_total'    => $lineTotal,
                'commission'    => $commission,
                'earning'       => round($lineTotal - $commission, 2),
            ];

            $subtotal += $lineTotal;
        }

        $subtotal    = round($subtotal, 2);
        $shippingFee = cart_shipping_fee($subtotal);
        $total       = round($subtotal + $shippingFee, 2);

        // Insert the order (retry on the very unlikely order-number clash)
        $orderId     = 0;
        $orderNumber = '';

        for ($attempt = 0; $attempt < 3 && $orderId === 0; $attempt++) {
            $orderNumber = orders_generate_number();

            $orderId = db_insert(
                    'INSERT INTO orders
                        (order_number, user_id, status, payment_method, payment_status, subtotal, shipping_fee, discount, total, currency,
                         shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, notes)
                     VALUES
                        (:number, :user, :status, :method, :pay_status, :subtotal, :shipping_fee, 0, :total, :currency,
                         :s_name, :s_phone, :s_address, :s_city, :s_state, :notes)
                     ON CONFLICT (order_number) DO NOTHING',
                    [
                        'number'       => $orderNumber,
                        'user'         => $userId,
                        'status'       => 'pending',
                        'method'       => $method,
                        'pay_status'   => 'unpaid',
                        'subtotal'     => (string) $subtotal,
                        'shipping_fee' => (string) $shippingFee,
                        'total'        => (string) $total,
                        'currency'     => CURRENCY_CODE,
                        's_name'       => (string) $shipping['name'],
                        's_phone'      => (string) $shipping['phone'],
                        's_address'    => (string) $shipping['address'],
                        's_city'       => (string) $shipping['city'],
                        's_state'      => (string) $shipping['state'],
                        'notes'        => ($shipping['notes'] ?? '') !== '' ? (string) $shipping['notes'] : null,
                    ]
                );
        }

        if ($orderId === 0) {
            throw new RuntimeException('Could not generate a unique order number.');
        }

        foreach ($lines as $line) {
            db_insert(
                'INSERT INTO order_items
                    (order_id, product_id, variant_id, vendor_id, product_name, variant_label,
                     unit_price, quantity, line_total, commission_amount, vendor_earning, status)
                 VALUES
                    (:order, :product, :variant, :vendor, :name, :label,
                     :unit, :qty, :line, :commission, :earning, :status)',
                [
                    'order'      => $orderId,
                    'product'    => $line['product_id'],
                    'variant'    => $line['variant_id'],
                    'vendor'     => $line['vendor_id'],
                    'name'       => $line['name'],
                    'label'      => $line['variant_label'],
                    'unit'       => (string) $line['unit_price'],
                    'qty'        => $line['quantity'],
                    'line'       => (string) $line['line_total'],
                    'commission' => (string) $line['commission'],
                    'earning'    => (string) $line['earning'],
                    'status'     => 'pending',
                ]
            );

            // Reserve stock. The WHERE guard makes overselling impossible even under load.
            if ($line['variant_id'] !== null) {
                $reserved = db_execute(
                    'UPDATE product_variants SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :min_qty',
                    ['qty' => $line['quantity'], 'id' => $line['variant_id'], 'min_qty' => $line['quantity']]
                );

                if ($reserved === 0) {
                    throw new OrderException('"' . $line['name'] . '" just sold out. Please update your cart.');
                }

                db_execute(
                    'UPDATE products SET stock_quantity = GREATEST(stock_quantity - :qty, 0) WHERE id = :id',
                    ['qty' => $line['quantity'], 'id' => $line['product_id']]
                );
            } else {
                $reserved = db_execute(
                    'UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :min_qty',
                    ['qty' => $line['quantity'], 'id' => $line['product_id'], 'min_qty' => $line['quantity']]
                );

                if ($reserved === 0) {
                    throw new OrderException('"' . $line['name'] . '" just sold out. Please update your cart.');
                }
            }
        }

        $reference = $method === 'paystack'
            ? 'NVR-' . strtoupper(bin2hex(random_bytes(8)))
            : 'COD-' . $orderNumber;

        db_insert(
            'INSERT INTO payments (order_id, provider, reference, amount, currency, status)
             VALUES (:order, :provider, :reference, :amount, :currency, :status)',
            [
                'order'     => $orderId,
                'provider'  => $method,
                'reference' => $reference,
                'amount'    => (string) $total,
                'currency'  => CURRENCY_CODE,
                'status'    => 'pending',
            ]
        );

        db_execute('DELETE FROM cart_items WHERE cart_id = :cart', ['cart' => $cartId]);

        return [
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'total'        => $total,
            'reference'    => $reference,
            'method'       => $method,
        ];
    });
}

/* -------------------------------------------------------------------------
 * Cancel / restore
 * ---------------------------------------------------------------------- */

/**
 * Cancel an UNPAID, pending order and put its stock back.
 * Returns true if the order was cancelled by this call.
 */
function orders_cancel(int $orderId, string $reason = ''): bool
{
    return (bool) db_transaction(static function () use ($orderId, $reason): bool {
        $order = db_fetch_one(
            'SELECT id, status, payment_status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE',
            ['id' => $orderId]
        );

        if ($order === null || $order['status'] !== 'pending' || $order['payment_status'] === 'paid') {
            return false;
        }

        $items = db_fetch_all(
            'SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = :id',
            ['id' => $orderId]
        );

        foreach ($items as $item) {
            if ($item['variant_id'] !== null) {
                db_execute(
                    'UPDATE product_variants SET stock_quantity = stock_quantity + :qty WHERE id = :id',
                    ['qty' => (int) $item['quantity'], 'id' => (int) $item['variant_id']]
                );
            }

            if ($item['product_id'] !== null) {
                db_execute(
                    'UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :id',
                    ['qty' => (int) $item['quantity'], 'id' => (int) $item['product_id']]
                );
            }
        }

        db_execute("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = :id", ['id' => $orderId]);
        db_execute("UPDATE order_items SET status = 'cancelled' WHERE order_id = :id", ['id' => $orderId]);
        db_execute(
            "UPDATE payments SET status = 'failed', gateway_response = :reason WHERE order_id = :id AND status = 'pending'",
            ['id' => $orderId, 'reason' => $reason !== '' ? $reason : null]
        );

        return true;
    });
}

/** Put a cancelled order's items back into the buyer's cart. Returns lines restored. */
function orders_restore_cart(int $orderId): int
{
    $order = db_fetch_one('SELECT user_id FROM orders WHERE id = :id LIMIT 1', ['id' => $orderId]);

    if ($order === null) {
        return 0;
    }

    $items = db_fetch_all(
        'SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = :id',
        ['id' => $orderId]
    );

    return cart_restore_items((int) $order['user_id'], $items);
}

/** Cancel Paystack orders that were never paid, releasing their stock. Cheap; called from checkout. */
function orders_cleanup_stale(): void
{
    $stale = db_fetch_all(
        "SELECT id FROM orders
          WHERE status = 'pending' AND payment_method = 'paystack' AND payment_status = 'unpaid'
            AND created_at < (NOW() - INTERVAL '" . (int) ORDER_PAYMENT_WINDOW_MINUTES . " minutes')
          ORDER BY id LIMIT 20"
    );

    foreach ($stale as $row) {
        orders_cancel((int) $row['id'], 'Payment not completed in time');
    }
}

/* -------------------------------------------------------------------------
 * Payment confirmation (safe to call many times for the same reference)
 * ---------------------------------------------------------------------- */

/**
 * Record a successful payment.
 *
 * @return array{result: string, order_id: ?int}
 *         result: paid | already | mismatch | needs_refund | unknown
 */
function orders_mark_paid(string $reference, float $amountPaid, string $currency, array $gateway = []): array
{
    return db_transaction(static function () use ($reference, $amountPaid, $currency, $gateway): array {
        $payment = db_fetch_one('SELECT * FROM payments WHERE reference = :ref LIMIT 1 FOR UPDATE', ['ref' => $reference]);

        if ($payment === null) {
            return ['result' => 'unknown', 'order_id' => null];
        }

        $orderId = (int) $payment['order_id'];
        $order   = db_fetch_one('SELECT id, status, payment_status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE', ['id' => $orderId]);

        if ($order === null) {
            return ['result' => 'unknown', 'order_id' => null];
        }

        if ($payment['status'] === 'success') {
            return ['result' => 'already', 'order_id' => $orderId];
        }

        if (abs((float) $payment['amount'] - $amountPaid) > 0.01 || strtoupper($currency) !== strtoupper((string) $payment['currency'])) {
            error_log(sprintf(
                '[Novura] Payment mismatch for %s: expected %s %s, gateway reported %s %s',
                $reference, $payment['currency'], $payment['amount'], $currency, $amountPaid
            ));

            return ['result' => 'mismatch', 'order_id' => $orderId];
        }

        db_execute(
            "UPDATE payments SET status = 'success', paid_at = NOW(), gateway_response = :gateway WHERE id = :id",
            ['id' => (int) $payment['id'], 'gateway' => json_encode($gateway, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)]
        );

        if ($order['status'] === 'pending') {
            db_execute("UPDATE orders SET status = 'paid', payment_status = 'paid' WHERE id = :id", ['id' => $orderId]);

            return ['result' => 'paid', 'order_id' => $orderId];
        }

        // The order was cancelled (stock released) before this payment arrived.
        // Record the money as received and flag it for a manual refund.
        db_execute("UPDATE orders SET payment_status = 'paid' WHERE id = :id", ['id' => $orderId]);
        error_log('[Novura] Late payment on cancelled order ' . $orderId . ' (reference ' . $reference . '): refund required.');

        return ['result' => 'needs_refund', 'order_id' => $orderId];
    });
}

/* -------------------------------------------------------------------------
 * Paystack client
 * ---------------------------------------------------------------------- */

function paystack_enabled(): bool
{
    return PAYSTACK_SECRET_KEY !== '' && function_exists('curl_init');
}

/** Call the Paystack API. Throws RuntimeException on any failure. */
function paystack_request(string $method, string $path, ?array $payload = null): array
{
    $handle = curl_init(PAYSTACK_BASE_URL . $path);

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($payload !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $body   = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error  = curl_error($handle);

    if ($body === false) {
        throw new RuntimeException('Could not reach Paystack: ' . $error);
    }

    $data = json_decode((string) $body, true);

    if (!is_array($data)) {
        throw new RuntimeException('Paystack returned an unreadable response (HTTP ' . $status . ').');
    }

    if ($status >= 400 || empty($data['status'])) {
        throw new RuntimeException('Paystack error: ' . (string) ($data['message'] ?? 'HTTP ' . $status));
    }

    return $data;
}

/** Start a Paystack payment and return the hosted payment page URL. */
function paystack_initialize(array $order, string $email): string
{
    $response = paystack_request('POST', '/transaction/initialize', [
        'email'        => $email,
        'amount'       => (int) round(((float) $order['total']) * 100), // kobo
        'currency'     => CURRENCY_CODE,
        'reference'    => $order['reference'],
        'callback_url' => PAYSTACK_CALLBACK_URL,
        'metadata'     => [
            'order_id'     => $order['order_id'],
            'order_number' => $order['order_number'],
        ],
    ]);

    $url = (string) ($response['data']['authorization_url'] ?? '');

    if ($url === '') {
        throw new RuntimeException('Paystack did not return a payment link.');
    }

    return $url;
}

/** Ask Paystack for the real status of a transaction. Returns the "data" block. */
function paystack_verify(string $reference): array
{
    $response = paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));

    return is_array($response['data'] ?? null) ? $response['data'] : [];
}
