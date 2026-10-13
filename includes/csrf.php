<?php
/**
 * NOVURA MARKETPLACE - CSRF PROTECTION
 *
 * In every HTML form:      <?= csrf_field() ?>
 * In <head> (for fetch):   <?= csrf_meta() ?>   then send header "X-CSRF-Token"
 * At top of POST handlers: csrf_require();
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Per-session CSRF token (created on first use). */
function csrf_token(): string
{
    ensure_session();

    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/** Hidden input for HTML forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Meta tag so JavaScript can read the token for fetch/AJAX calls. */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . e(csrf_token()) . '">';
}

/** Constant-time token comparison. */
function csrf_verify(?string $token): bool
{
    ensure_session();

    $expected = $_SESSION['_csrf'] ?? null;

    return is_string($expected)
        && $expected !== ''
        && is_string($token)
        && hash_equals($expected, $token);
}

/**
 * Enforce CSRF on state-changing requests (POST, PUT, PATCH, DELETE).
 * Safe methods (GET, HEAD, OPTIONS) pass through untouched.
 * Accepts the token from the "_csrf" form field or the "X-CSRF-Token" header.
 */
function csrf_require(): void
{
    if (in_array(request_method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!csrf_verify(is_string($token) ? $token : null)) {
        abort(419);
    }
}
