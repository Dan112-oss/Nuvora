<?php
/**
 * NOVURA MARKETPLACE - APPLICATION CONFIG
 * Requires PHP 8.1+
 *
 * Override any value WITHOUT editing this file by either:
 *   1) creating config/config.local.php (keep it out of version control), using define('DB_PASS', '...');
 *   2) setting environment variables prefixed with NOVURA_ (e.g. NOVURA_DB_PASS).
 * Order of priority: config.local.php > environment variable > default below.
 */

declare(strict_types=1);

if (defined('NOVURA_CONFIG_LOADED')) {
    return;
}
define('NOVURA_CONFIG_LOADED', true);

/* -------------------------------------------------------------------------
 * Helpers (local to this file)
 * ---------------------------------------------------------------------- */
if (!function_exists('novura_env')) {
    function novura_env(string $key, mixed $default = null): mixed
    {
        $value = getenv('NOVURA_' . $key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

if (!function_exists('novura_default')) {
    function novura_default(string $name, mixed $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

/* -------------------------------------------------------------------------
 * Local overrides (secrets live here on the server)
 * ---------------------------------------------------------------------- */
define('NOVURA_ROOT', dirname(__DIR__));

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

/* -------------------------------------------------------------------------
 * Application
 * ---------------------------------------------------------------------- */
novura_default('APP_NAME', 'Novura');
novura_default('APP_ENV', (string) novura_env('APP_ENV', 'development')); // development | production
novura_default('APP_DEBUG', APP_ENV !== 'production');
novura_default('BASE_URL', rtrim((string) novura_env('BASE_URL', 'http://localhost/novura'), '/'));
novura_default('TIMEZONE', 'Africa/Lagos');
novura_default('DB_TIMEZONE', '+01:00'); // keep in sync with TIMEZONE (Lagos = UTC+1, no DST)

/* -------------------------------------------------------------------------
 * Database
 * ---------------------------------------------------------------------- */
novura_default('DB_HOST', (string) novura_env('DB_HOST', '127.0.0.1'));
novura_default('DB_PORT', (int) novura_env('DB_PORT', 3306));
novura_default('DB_NAME', (string) novura_env('DB_NAME', 'novura'));
novura_default('DB_USER', (string) novura_env('DB_USER', 'root'));
novura_default('DB_PASS', (string) novura_env('DB_PASS', ''));
novura_default('DB_CHARSET', 'utf8mb4');

/* -------------------------------------------------------------------------
 * Money and shop rules
 * ---------------------------------------------------------------------- */
novura_default('CURRENCY_CODE', 'NGN');
novura_default('CURRENCY_SYMBOL', '₦');
novura_default('DEFAULT_COMMISSION_RATE', 10.00);     // percent taken from each vendor sale
novura_default('FLAT_SHIPPING_FEE', 1500.00);
novura_default('FREE_SHIPPING_THRESHOLD', 50000.00);  // set 0 to disable free shipping
novura_default('ITEMS_PER_PAGE', 24);

/* -------------------------------------------------------------------------
 * Payments (Paystack) - never commit real keys
 * ---------------------------------------------------------------------- */
novura_default('PAYSTACK_PUBLIC_KEY', (string) novura_env('PAYSTACK_PUBLIC_KEY', ''));
novura_default('PAYSTACK_SECRET_KEY', (string) novura_env('PAYSTACK_SECRET_KEY', ''));
novura_default('PAYSTACK_BASE_URL', 'https://api.paystack.co');
novura_default('PAYSTACK_CALLBACK_URL', BASE_URL . '/public/payment-callback.php');

/* -------------------------------------------------------------------------
 * Uploads
 * ---------------------------------------------------------------------- */
novura_default('UPLOAD_PATH', NOVURA_ROOT . '/uploads');
novura_default('UPLOAD_URL', BASE_URL . '/uploads');
novura_default('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
novura_default('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

/* -------------------------------------------------------------------------
 * Security and sessions
 * ---------------------------------------------------------------------- */
novura_default('SESSION_NAME', 'novura_session');
novura_default('SESSION_LIFETIME', 7200); // seconds
novura_default('PASSWORD_MIN_LENGTH', 8);

/* -------------------------------------------------------------------------
 * Runtime setup
 * ---------------------------------------------------------------------- */
date_default_timezone_set(TIMEZONE);
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

$logDir = NOVURA_ROOT . '/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    session_name(SESSION_NAME);
}
