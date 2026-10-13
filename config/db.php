<?php
/**
 * NOVURA MARKETPLACE - DATABASE LAYER (PDO)
 *
 * Usage:
 *   require_once __DIR__ . '/db.php';
 *   $product = db_fetch_one('SELECT * FROM products WHERE id = :id', ['id' => 5]);
 *
 * For JSON endpoints, define('NOVURA_API', true) BEFORE requiring this file so
 * connection failures return JSON instead of an HTML page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Shared PDO connection (created once per request).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '%s'",
                DB_TIMEZONE
            ),
        ]);
    } catch (PDOException $e) {
        // Log the real reason; never expose credentials or host details to visitors.
        error_log('[Novura] Database connection failed: ' . $e->getMessage());
        db_fail();
    }

    return $pdo;
}

/**
 * Stop the request with a safe 500 response.
 */
function db_fail(): never
{
    http_response_code(500);

    if (defined('NOVURA_API') && NOVURA_API) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Service temporarily unavailable. Please try again shortly.',
        ]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ' - Temporarily unavailable</title></head>'
            . '<body style="font-family:Arial,sans-serif;text-align:center;padding:4rem 1rem;">'
            . '<h1>We&rsquo;ll be right back</h1>'
            . '<p>' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8')
            . ' is temporarily unavailable. Please try again in a few minutes.</p>'
            . '</body></html>';
    }

    exit;
}

/**
 * Prepare and run a query. Supports named (:id) and positional (?) params.
 * Value types are bound correctly (important for LIMIT/OFFSET integers).
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $type = match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
        $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
    }

    $stmt->execute();

    return $stmt;
}

/** First row as an associative array, or null when nothing matches. */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/** All rows as a list of associative arrays. */
function db_fetch_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Single value from the first column of the first row, or null. */
function db_value(string $sql, array $params = []): mixed
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}

/** Run INSERT/UPDATE/DELETE and return the number of affected rows. */
function db_execute(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/** Run an INSERT and return the new auto-increment id. */
function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);

    return (int) db()->lastInsertId();
}

/**
 * Run a callback inside a transaction (commit on success, rollback on any error).
 * Safe to nest: an inner call simply joins the outer transaction.
 */
function db_transaction(callable $callback): mixed
{
    $pdo = db();

    if ($pdo->inTransaction()) {
        return $callback($pdo);
    }

    $pdo->beginTransaction();

    try {
        $result = $callback($pdo);
        $pdo->commit();

        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
