<?php
/**
 * NOVURA MARKETPLACE - REGISTER (buyers and sellers)
 * Path: public/register.php
 *
 * Sellers get a users row (role = vendor) plus a vendors row (status = pending).
 * Both are created in ONE transaction, so you never end up with half an account.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_guest();

/** Unique store slug: "Ada's Store" -> "adas-store", with a random suffix on collision. */
function register_vendor_slug(string $storeName): string
{
    $base = slugify($storeName);
    $slug = $base;

    for ($i = 0; $i < 5; $i++) {
        if (db_value('SELECT id FROM vendors WHERE slug = :slug LIMIT 1', ['slug' => $slug]) === null) {
            return $slug;
        }
        $slug = substr($base, 0, 100) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    return substr($base, 0, 100) . '-' . bin2hex(random_bytes(4));
}

$errors      = [];
$accountType = input('type') === 'vendor' ? 'vendor' : 'buyer';
$form        = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'phone'      => '',
    'store_name' => '',
];

if (is_post()) {
    csrf_require();

    // Honeypot: real users never see or fill this field.
    if (input('website') !== '') {
        redirect('public/index.php');
    }

    $accountType = input('account_type') === 'vendor' ? 'vendor' : 'buyer';

    $form = [
        'first_name' => input('first_name'),
        'last_name'  => input('last_name'),
        'email'      => strtolower(input('email')),
        'phone'      => input('phone'),
        'store_name' => input('store_name'),
    ];

    // Passwords are read raw (never trimmed) and never echoed back.
    $password        = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $passwordConfirm = (isset($_POST['password_confirm']) && is_string($_POST['password_confirm'])) ? $_POST['password_confirm'] : '';

    if ($accountType === 'vendor') {
        $storeLength = mb_strlen($form['store_name']);
        if ($storeLength < 3 || $storeLength > 150) {
            $errors['store_name'] = 'Store name must be between 3 and 150 characters.';
        }
    }

    if (!isset($_POST['terms'])) {
        $errors['terms'] = 'You must accept the terms to continue.';
    }

    if (!$errors) {
        $payload = $form + ['password' => $password, 'password_confirm' => $passwordConfirm];

        $result = db_transaction(static function () use ($payload, $accountType, $form): array {
            $res = auth_register($payload, $accountType);

            if ($res['ok'] && $accountType === 'vendor') {
                db_insert(
                    'INSERT INTO vendors (user_id, store_name, slug, phone, commission_rate, status)
                     VALUES (:user_id, :store_name, :slug, :phone, :commission, :status)',
                    [
                        'user_id'    => (int) $res['user_id'],
                        'store_name' => $form['store_name'],
                        'slug'       => register_vendor_slug($form['store_name']),
                        'phone'      => $form['phone'] !== '' ? $form['phone'] : null,
                        'commission' => DEFAULT_COMMISSION_RATE,
                        'status'     => 'pending',
                    ]
                );
            }

            return $res;
        });

        if ($result['ok']) {
            auth_login_user((int) $result['user_id']);

            if ($accountType === 'vendor') {
                flash_set('success', 'Your seller account is ready. Your store is awaiting approval, and you can set it up in the meantime.');
            } else {
                flash_set('success', 'Welcome to ' . APP_NAME . ', ' . $form['first_name'] . '!');
            }

            auth_redirect_after_login();
        }

        $errors = $result['errors'];
    }
}

/** Render an error line for a field. */
function register_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="form-error">' . e($errors[$key]) . '</p>' : '';
}

function register_invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

$pageTitle = 'Create your account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-wrap">
        <div class="auth-card auth-card-wide">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-sub">Shop millions of products or start selling on <?= e(APP_NAME) ?>.</p>

            <form action="<?= e(url('public/register.php')) ?>" method="post" data-once>
                <?= csrf_field() ?>

                <div class="type-picker" role="radiogroup" aria-label="Account type">
                    <label class="radio-card">
                        <input type="radio" name="account_type" value="buyer" <?= $accountType === 'buyer' ? 'checked' : '' ?>>
                        <span class="radio-card-body"><strong>I want to shop</strong><small>Buy from trusted sellers</small></span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="account_type" value="vendor" <?= $accountType === 'vendor' ? 'checked' : '' ?>>
                        <span class="radio-card-body"><strong>I want to sell</strong><small>Open your own store</small></span>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First name</label>
                        <input class="form-control<?= register_invalid($errors, 'first_name') ?>" type="text" id="first_name" name="first_name"
                               value="<?= e($form['first_name']) ?>" maxlength="80" autocomplete="given-name" required>
                        <?= register_error($errors, 'first_name') ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last name</label>
                        <input class="form-control<?= register_invalid($errors, 'last_name') ?>" type="text" id="last_name" name="last_name"
                               value="<?= e($form['last_name']) ?>" maxlength="80" autocomplete="family-name" required>
                        <?= register_error($errors, 'last_name') ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control<?= register_invalid($errors, 'email') ?>" type="email" id="email" name="email"
                           value="<?= e($form['email']) ?>" maxlength="190" autocomplete="email" required>
                    <?= register_error($errors, 'email') ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone number <span class="text-muted">(optional)</span></label>
                    <input class="form-control<?= register_invalid($errors, 'phone') ?>" type="tel" id="phone" name="phone"
                           value="<?= e($form['phone']) ?>" maxlength="30" autocomplete="tel" placeholder="e.g. 08012345678">
                    <?= register_error($errors, 'phone') ?>
                </div>

                <div class="form-group" data-vendor-only>
                    <label class="form-label" for="store_name">Store name <span class="text-muted">(sellers only)</span></label>
                    <input class="form-control<?= register_invalid($errors, 'store_name') ?>" type="text" id="store_name" name="store_name"
                           value="<?= e($form['store_name']) ?>" maxlength="150" data-required-vendor>
                    <?= register_error($errors, 'store_name') ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <input class="form-control<?= register_invalid($errors, 'password') ?>" type="password" id="password" name="password"
                               minlength="<?= (int) PASSWORD_MIN_LENGTH ?>" maxlength="72" autocomplete="new-password" required>
                        <button type="button" class="input-toggle" data-toggle-password aria-label="Show password"><?= icon('eye', 18) ?></button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <?= register_error($errors, 'password') ?>
                    <?php else: ?>
                        <p class="form-hint"><?= (int) PASSWORD_MIN_LENGTH ?>+ characters with at least one letter and one number.</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm password</label>
                    <div class="input-group">
                        <input class="form-control<?= register_invalid($errors, 'password_confirm') ?>" type="password" id="password_confirm" name="password_confirm"
                               maxlength="72" autocomplete="new-password" required>
                        <button type="button" class="input-toggle" data-toggle-password aria-label="Show password"><?= icon('eye', 18) ?></button>
                    </div>
                    <?= register_error($errors, 'password_confirm') ?>
                </div>

                <div class="form-group">
                    <label class="check">
                        <input type="checkbox" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
                        <span>I agree to the <a href="<?= e(url('public/terms.php')) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>
                        and <a href="<?= e(url('public/privacy.php')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                    </label>
                    <?= register_error($errors, 'terms') ?>
                </div>

                <div class="hp-field" aria-hidden="true">
                    <label for="website">Leave this field empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>

            <p class="auth-switch">Already have an account? <a href="<?= e(url('public/login.php')) ?>">Sign in</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
