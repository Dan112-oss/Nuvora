<?php
/**
 * NOVURA MARKETPLACE - CART API (JSON)
 * Path: api/cart.php
 *
 * POST (JSON body, header X-CSRF-Token required):
 *   {"action":"add","product_id":5,"variant_id":12,"quantity":2}
 *   {"action":"update","item_id":9,"quantity":3}      (0 removes the line)
 *   {"action":"remove","item_id":9}
 * GET:
 *   ?action=summary
 *
 * Every response: {"success": bool, "message": string, "cart_count": int, ...}
 */

declare(strict_types=1);

define('NOVURA_API', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

header('Cache-Control: no-store');

$method = request_method();

if (!in_array($method, ['GET', 'POST'], true)) {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

/** Cart numbers returned with every response so the page can update itself. */
function cart_api_snapshot(): array
{
    $cart = cart_load();

    return [
        'cart_count'         => $cart['count'],
        'subtotal'           => $cart['subtotal'],
        'subtotal_formatted' => format_money($cart['subtotal']),
        'shipping_formatted' => format_money($cart['shipping']),
        'total_formatted'    => format_money($cart['total']),
    ];
}

if ($method === 'GET') {
    if (input('action', 'summary') !== 'summary') {
        json_response(['success' => false, 'message' => 'Unknown action.'], 400);
    }

    json_response(['success' => true, 'message' => ''] + cart_api_snapshot());
}

// ----- POST -----
csrf_require();

$body = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($body)) {
    json_response(['success' => false, 'message' => 'Invalid request.'], 400);
}

$toInt = static function (mixed $value): ?int {
    return (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) ? (int) $value : null;
};

$action = is_string($body['action'] ?? null) ? $body['action'] : '';

switch ($action) {
    case 'add':
        $productId = $toInt($body['product_id'] ?? null);
        $variantId = isset($body['variant_id']) && $body['variant_id'] !== null ? $toInt($body['variant_id']) : null;
        $quantity  = $toInt($body['quantity'] ?? 1) ?? 1;

        if ($productId === null || $productId < 1 || (isset($body['variant_id']) && $body['variant_id'] !== null && $variantId === null)) {
            json_response(['success' => false, 'message' => 'Invalid product.'], 422);
        }

        $result = cart_add($productId, $variantId, $quantity);
        break;

    case 'update':
        $itemId   = $toInt($body['item_id'] ?? null);
        $quantity = $toInt($body['quantity'] ?? null);

        if ($itemId === null || $quantity === null) {
            json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $result = cart_update($itemId, $quantity);
        break;

    case 'remove':
        $itemId = $toInt($body['item_id'] ?? null);

        if ($itemId === null) {
            json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $result = cart_remove($itemId);
        break;

    default:
        json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}

json_response([
    'success' => $result['ok'],
    'message' => $result['message'],
] + cart_api_snapshot(), $result['ok'] ? 200 : 422);
