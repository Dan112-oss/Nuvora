<?php
/**
 * NOVURA MARKETPLACE - SHARED HELPERS
 *
 * Output escaping, URLs, safe redirects, flash messages, old form input,
 * JSON responses, error pages, security headers and a global exception handler.
 * Depends only on config/config.php (no database access here).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/* -------------------------------------------------------------------------
 * Session
 * ---------------------------------------------------------------------- */

/** Start the session once (cookie settings come from config.php). */
function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Stable per-visitor token for guest carts.
 * Use this (NOT session_id()) as carts.session_id, because the session id
 * is regenerated on login and would orphan the guest cart.
 */
function guest_token(): string
{
    ensure_session();

    if (empty($_SESSION['_guest_token']) || !is_string($_SESSION['_guest_token'])) {
        $_SESSION['_guest_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_guest_token'];
}

/* -------------------------------------------------------------------------
 * Output and URLs
 * ---------------------------------------------------------------------- */

/** Escape any value for safe HTML output. */
function e(mixed $value): string
{
    if ($value === null || is_array($value) || is_object($value)) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute URL for an app path, e.g. url('public/shop.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL for a static asset, e.g. asset('css/app.css'). */
function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/** URL for an uploaded file stored under /uploads. */
function upload_url(?string $path): string
{
    if ($path === null || $path === '') {
        return asset('img/placeholder.png');
    }

    return UPLOAD_URL . '/' . ltrim($path, '/');
}

/** Format a money amount, e.g. ₦12,500 or ₦12,500.50. */
function format_money(float|int|string|null $amount, bool $withSymbol = true): string
{
    $value    = (float) ($amount ?? 0);
    $decimals = (abs($value - round($value)) < 0.005) ? 0 : 2;
    $text     = number_format($value, $decimals);

    return $withSymbol ? CURRENCY_SYMBOL . $text : $text;
}

/** Turn text into a URL-safe slug. */
function slugify(string $text): string
{
    $text = trim($text);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');

    return $text !== '' ? substr($text, 0, 120) : 'item';
}

/* -------------------------------------------------------------------------
 * Redirects
 * ---------------------------------------------------------------------- */

/**
 * Redirect to an app path ('public/login.php') or an absolute URL on THIS site.
 * Absolute URLs pointing to other hosts are rejected (open-redirect protection).
 */
function redirect(string $to, int $status = 302): never
{
    $to = str_replace(["\r", "\n"], '', $to);

    if (preg_match('#^https?://#i', $to)) {
        $target = parse_url($to);
        $base   = parse_url(BASE_URL);

        $sameHost = strcasecmp((string) ($target['host'] ?? ''), (string) ($base['host'] ?? '')) === 0
            && ($target['port'] ?? null) === ($base['port'] ?? null);

        if (!$sameHost) {
            $to = url('');
        }
    } else {
        $to = url($to);
    }

    header('Location: ' . $to, true, $status);
    exit;
}

/* -------------------------------------------------------------------------
 * Request helpers
 * ---------------------------------------------------------------------- */

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function is_post(): bool
{
    return request_method() === 'POST';
}

/** True for JSON/AJAX requests or scripts that define('NOVURA_API', true). */
function is_api_request(): bool
{
    if (defined('NOVURA_API') && NOVURA_API) {
        return true;
    }

    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr    = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    return $xhr || str_contains($accept, 'application/json');
}

/** Trimmed string input from POST, then GET. Arrays are ignored. */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;

    if (!is_scalar($value)) {
        return $default;
    }

    return trim(str_replace("\0", '', (string) $value));
}

/** Integer input (POST then GET). */
function input_int(string $key, int $default = 0): int
{
    $value = input($key, '');

    return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $default;
}

/** Client IP. Uses REMOTE_ADDR only; forwarded headers are spoofable. */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* -------------------------------------------------------------------------
 * Validation helpers
 * ---------------------------------------------------------------------- */

function is_valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Loose international phone check: optional +, 7-15 digits (spaces/dashes allowed). */
function is_valid_phone(string $phone): bool
{
    $digits = preg_replace('/[\s\-()]/', '', $phone);

    return preg_match('/^\+?\d{7,15}$/', (string) $digits) === 1;
}

/* -------------------------------------------------------------------------
 * Flash messages and old input
 * ---------------------------------------------------------------------- */

/** Queue a one-time message. $type: success | error | warning | info */
function flash_set(string $type, string $message): void
{
    ensure_session();
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Return all queued messages and clear them. */
function flash_get(): array
{
    ensure_session();
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

/** Remember submitted form values for the next request (passwords are never stored). */
function old_flash(array $data): void
{
    ensure_session();

    $safe = [];
    foreach ($data as $key => $value) {
        if (is_string($key) && is_scalar($value) && stripos($key, 'password') === false) {
            $safe[$key] = (string) $value;
        }
    }

    $_SESSION['_old'] = $safe;
}

/** Previously submitted value. Wrap in e() when printing: e(old('email')). */
function old(string $key, string $default = ''): string
{
    static $bag = null;

    if ($bag === null) {
        ensure_session();
        $bag = is_array($_SESSION['_old'] ?? null) ? $_SESSION['_old'] : [];
        unset($_SESSION['_old']);
    }

    return isset($bag[$key]) ? (string) $bag[$key] : $default;
}

/* -------------------------------------------------------------------------
 * Responses and errors
 * ---------------------------------------------------------------------- */

/** Send a JSON response and stop. */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/** Stop with an error page (or JSON for API requests). */
function abort(int $code = 500, ?string $message = null): never
{
    $titles = [
        403 => 'Access denied',
        404 => 'Page not found',
        419 => 'Session expired',
        429 => 'Too many requests',
        500 => 'Something went wrong',
    ];

    $defaults = [
        403 => 'You do not have permission to view this page.',
        404 => 'The page you are looking for does not exist.',
        419 => 'Your session expired. Please refresh the page and try again.',
        429 => 'Too many requests. Please wait a moment and try again.',
        500 => 'An unexpected error occurred. Please try again shortly.',
    ];

    $title   = $titles[$code] ?? 'Error';
    $message = $message ?? ($defaults[$code] ?? 'An unexpected error occurred.');

    if (is_api_request()) {
        json_response(['success' => false, 'message' => $message], $code);
    }

    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' - ' . e(APP_NAME) . '</title></head>'
        . '<body style="font-family:Arial,sans-serif;text-align:center;padding:4rem 1rem;color:#222;">'
        . '<h1 style="margin-bottom:.5rem;">' . e((string) $code) . '</h1>'
        . '<h2 style="margin-top:0;">' . e($title) . '</h2>'
        . '<p>' . e($message) . '</p>'
        . '<p><a href="' . e(url('')) . '">Back to ' . e(APP_NAME) . '</a></p>'
        . '</body></html>';

    exit;
}

/* -------------------------------------------------------------------------
 * Security headers
 * ---------------------------------------------------------------------- */

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    if ($isHttps && APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* -------------------------------------------------------------------------
 * Global exception handler + boot
 * ---------------------------------------------------------------------- */

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf(
        '[Novura] Uncaught %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    // Visitors never see internals in production.
    abort(500, APP_DEBUG ? get_class($e) . ': ' . $e->getMessage() : null);
});

if (PHP_SAPI !== 'cli') {
    send_security_headers();
}
