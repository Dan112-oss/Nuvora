<?php
/**
 * NOVURA MARKETPLACE - LOGOUT
 * Path: public/logout.php
 *
 * Only accepts POST with a valid CSRF token (the "Sign out" button in the header
 * is a small form). This stops other sites from signing visitors out via a link or image.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (!is_post()) {
    redirect('public/index.php');
}

csrf_require();

auth_logout();
flash_set('success', 'You have been signed out.');

redirect('public/index.php');
