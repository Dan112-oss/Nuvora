<?php
/**
 * NOVURA MARKETPLACE - LOGIN
 * Path: public/login.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_guest();

$error = null;
$email = '';

if (is_post()) {
    csrf_require();

    $email = strtolower(input('email'));

    // Password is read raw (never trimmed) and never echoed back.
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';

    $result = auth_attempt($email, $password);

    if ($result['ok']) {
        $user = auth_user();
        flash_set('success', 'Welcome back' . ($user !== null ? ', ' . $user['first_name'] : '') . '!');
        auth_redirect_after_login();
    }

    $error = $result['error'];
}

$pageTitle = 'Sign in';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-wrap">
        <div class="auth-card">
            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-sub">Sign in to your <?= e(APP_NAME) ?> account.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert"><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form action="<?= e(url('public/login.php')) ?>" method="post" data-once>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" type="email" id="email" name="email"
                           value="<?= e($email) ?>" maxlength="190" autocomplete="email" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" type="password" id="password" name="password"
                               maxlength="255" autocomplete="current-password" required>
                        <button type="button" class="input-toggle" data-toggle-password aria-label="Show password"><?= icon('eye', 18) ?></button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            </form>

            <p class="auth-switch">New to <?= e(APP_NAME) ?>? <a href="<?= e(url('public/register.php')) ?>">Create an account</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
