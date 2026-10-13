<?php
/**
 * NOVURA MARKETPLACE - ROOT ENTRY POINT
 * Path: index.php (project root)
 *
 * Visiting http://localhost/novura/ forwards to the storefront home page.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

header('Location: ' . BASE_URL . '/public/index.php', true, 302);
exit;
