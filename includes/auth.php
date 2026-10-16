<?php
/**
 * NOVURA MARKETPLACE - AUTHENTICATION AND ROLE GUARDS
 *
 * Typical page:
 *   require_once __DIR__ . '/../includes/auth.php';
 *   require_role('admin');                       // admin/ pages
 *   require_role('vendor');                      // vendor/ pages
 *   require_login();                             // buyer account pages
 *   require_guest();                             // login/register pages
 *
 * Only the user id is kept in the session. Role and status are re-read from
 * the database once per request, so suspending a user takes effect immediately.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

const AUTH_MAX_ATTEMPTS = 5;     // failed logins allowed per window
const AUTH_LOCK_SECONDS = 900;   // 15-minute window / lockout

/* -------------------------------------------------------------------------
 * Boot: start session + idle timeout
 * ---------------------------------------------------------------------- */

function auth_boot(): void
{
    ensure_session();

    if (isset($_SESSION['user_id'])) {
        $last = (int) ($_SESSION['_last_activity'] ?? time());

        if (time() - $last > SESSION_LIFETIME) {
            auth_logout();
            flash_set('info', 'You were signed out because of inactivity.');
        }
    }

    $_SESSION['_last_activity'] = time();
}

/* -------------------------------------------------------------------------
 * Current user
 * ---------------------------------------------------------------------- */

/**
 * Fresh user row for the signed-in user (cached per request) or null.
 * Pass true to force a reload after login/logout.
 */
function auth_user(bool $refresh = false): ?array
{
    static $cache = ['loaded' => false, 'user' => null];

    if ($refresh) {
        $cache = ['loaded' => false, 'user' => null];
    }

    if ($cache['loaded']) {
        return $cache['user'];
    }

    $cache['loaded'] = true;
    ensure_session();

    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    $row = db_fetch_one(
        'SELECT id, role, first_name, last_name, email, phone, avatar, address, city, state, status
           FROM users WHERE id = :id LIMIT 1',
        ['id' => $id]
    );

    if ($row === null || $row['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    $cache['user'] = $row;

    return $row;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

function auth_id(): ?int
{
    $user = auth_user();

    return $user === null ? null : (int) $user['id'];
}

function auth_role(): ?string
{
    $user = auth_user();

    return $user === null ? null : (string) $user['role'];
}

/** True when the signed-in user has one of the given roles. */
function auth_has_role(string|array $roles): bool
{
    $role = auth_role();

    return $role !== null && in_array($role, (array) $roles, true);
}

function auth_name(): string
{
    $user = auth_user();

    return $user === null ? '' : trim($user['first_name'] . ' ' . $user['last_name']);
}

/** Vendor store row for the signed-in vendor, or null. */
function auth_vendor(): ?array
{
    static $cache = [];

    $userId = auth_id();
    if ($userId === null || auth_role() !== 'vendor') {
        return null;
    }

    if (!array_key_exists($userId, $cache)) {
        $cache[$userId] = db_fetch_one('SELECT * FROM vendors WHERE user_id = :id LIMIT 1', ['id' => $userId]);
    }

    return $cache[$userId];
}

/** Default landing page for a role. */
function auth_home_url(?string $role = null): string
{
    return match ($role ?? auth_role()) {
        'admin'  => 'admin/index.php',
        'vendor' => 'vendor/index.php',
        default  => 'public/account.php',
    };
}

/* -------------------------------------------------------------------------
 * Register
 * ---------------------------------------------------------------------- */

/** Returns an error message for a bad password, or null when acceptable. */
function auth_validate_password(string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }

    if (strlen($password) > 72) {
        return 'Password must be 72 characters or fewer.';
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }

    return null;
}

/**
 * Create an account. Admins can never be created here (only 'buyer' or 'vendor').
 * Expected keys: first_name, last_name, email, phone (optional), password, password_confirm.
 *
 * @return array{ok: bool, user_id: ?int, errors: array<string,string>}
 */
function auth_register(array $data, string $role = 'buyer'): array
{
    $role      = in_array($role, ['buyer', 'vendor'], true) ? $role : 'buyer';
    $errors    = [];
    $namePattern = '/^[\p{L}\p{M}\s\'\.\-]+$/u';

    $first    = trim((string) ($data['first_name'] ?? ''));
    $last     = trim((string) ($data['last_name'] ?? ''));
    $email    = strtolower(trim((string) ($data['email'] ?? '')));
    $phone    = trim((string) ($data['phone'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $confirm  = (string) ($data['password_confirm'] ?? '');

    if ($first === '' || mb_strlen($first) > 80 || !preg_match($namePattern, $first)) {
        $errors['first_name'] = 'Enter a valid first name.';
    }

    if ($last === '' || mb_strlen($last) > 80 || !preg_match($namePattern, $last)) {
        $errors['last_name'] = 'Enter a valid last name.';
    }

    if (!is_valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($phone !== '' && !is_valid_phone($phone)) {
        $errors['phone'] = 'Enter a valid phone number.';
    }

    $passwordError = auth_validate_password($password);
    if ($passwordError !== null) {
        $errors['password'] = $passwordError;
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (!isset($errors['email'])
        && db_value('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $email]) !== null) {
        $errors['email'] = 'An account with this email already exists.';
    }

    if ($errors) {
        return ['ok' => false, 'user_id' => null, 'errors' => $errors];
    }

    // ON CONFLICT keeps this safe when two sign-ups use the same email at the same moment.
    $userId = db_insert(
        'INSERT INTO users (role, first_name, last_name, email, phone, password_hash)
         VALUES (:role, :first, :last, :email, :phone, :hash)
         ON CONFLICT (email) DO NOTHING',
        [
            'role'  => $role,
            'first' => $first,
            'last'  => $last,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'hash'  => password_hash($password, PASSWORD_DEFAULT),
        ]
    );

    if ($userId === 0) {
        return ['ok' => false, 'user_id' => null, 'errors' => ['email' => 'An account with this email already exists.']];
    }

    return ['ok' => true, 'user_id' => $userId, 'errors' => []];
}

/* -------------------------------------------------------------------------
 * Login / logout
 * ---------------------------------------------------------------------- */

/** Seconds left on a login lockout (0 = not locked). */
function auth_throttle_remaining(): int
{
    ensure_session();

    $state = $_SESSION['_login_fail'] ?? null;
    if (!is_array($state)) {
        return 0;
    }

    $elapsed = time() - (int) ($state['first'] ?? 0);

    if ($elapsed >= AUTH_LOCK_SECONDS) {
        unset($_SESSION['_login_fail']);
        return 0;
    }

    return ((int) ($state['count'] ?? 0) >= AUTH_MAX_ATTEMPTS) ? AUTH_LOCK_SECONDS - $elapsed : 0;
}

function auth_throttle_hit(): void
{
    ensure_session();

    $state = $_SESSION['_login_fail'] ?? null;

    if (!is_array($state) || (time() - (int) ($state['first'] ?? 0)) >= AUTH_LOCK_SECONDS) {
        $state = ['count' => 0, 'first' => time()];
    }

    $state['count'] = (int) $state['count'] + 1;
    $_SESSION['_login_fail'] = $state;
}

/** Hash used to keep timing equal when an email does not exist. */
function auth_dummy_hash(): string
{
    static $hash = null;

    return $hash ??= password_hash('novura-timing-guard', PASSWORD_DEFAULT);
}

/**
 * Verify credentials and sign the user in.
 *
 * @return array{ok: bool, error: ?string}
 */
function auth_attempt(string $email, string $password): array
{
    $wait = auth_throttle_remaining();
    if ($wait > 0) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again in ' . (int) ceil($wait / 60) . ' minute(s).'];
    }

    $email = strtolower(trim($email));
    $user  = null;

    if ($email !== '' && strlen($email) <= 190 && strlen($password) <= 255) {
        $user = db_fetch_one(
            'SELECT id, password_hash, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
    }

    // Always run a hash comparison so response time does not reveal valid emails.
    $valid = password_verify($password, $user['password_hash'] ?? auth_dummy_hash());

    if ($user === null || !$valid) {
        auth_throttle_hit();
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'This account has been suspended. Please contact support.'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_execute('UPDATE users SET password_hash = :hash WHERE id = :id', [
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id'   => (int) $user['id'],
        ]);
    }

    auth_login_user((int) $user['id']);

    return ['ok' => true, 'error' => null];
}

/** Sign a user in (also used right after registration). Regenerates the session id. */
function auth_login_user(int $userId): void
{
    ensure_session();
    session_regenerate_id(true);

    $_SESSION['user_id']        = $userId;
    $_SESSION['_last_activity'] = time();
    unset($_SESSION['_login_fail']);

    db_execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $userId]);

    auth_user(true);
}

/** Sign out: wipes session data and issues a fresh session id. */
function auth_logout(): void
{
    ensure_session();

    $_SESSION = [];
    session_regenerate_id(true);

    auth_user(true);
}

/* -------------------------------------------------------------------------
 * Route guards
 * ---------------------------------------------------------------------- */

/** Full URL of the current request on this site (used to return after login). */
function auth_current_url(): string
{
    $base   = parse_url(BASE_URL);
    $origin = ($base['scheme'] ?? 'http') . '://' . ($base['host'] ?? 'localhost')
        . (isset($base['port']) ? ':' . $base['port'] : '');

    return $origin . (string) ($_SERVER['REQUEST_URI'] ?? '/');
}

/** Require a signed-in user. Redirects to login (or returns 401 JSON). */
function require_login(): array
{
    $user = auth_user();
    if ($user !== null) {
        return $user;
    }

    if (is_api_request()) {
        json_response(['success' => false, 'message' => 'Please sign in to continue.'], 401);
    }

    if (request_method() === 'GET') {
        $_SESSION['_intended'] = auth_current_url();
    }

    flash_set('info', 'Please sign in to continue.');
    redirect('public/login.php');
}

/** Require a signed-in user with one of the given roles, otherwise 403. */
function require_role(string|array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], (array) $roles, true)) {
        abort(403);
    }

    return $user;
}

/** For login/register pages: send signed-in users to their dashboard. */
function require_guest(): void
{
    if (auth_check()) {
        redirect(auth_home_url());
    }
}

/** After a successful login: return to the page they wanted, or their dashboard. */
function auth_redirect_after_login(): never
{
    $intended = $_SESSION['_intended'] ?? null;
    unset($_SESSION['_intended']);

    redirect(is_string($intended) && $intended !== '' ? $intended : auth_home_url());
}

/* -------------------------------------------------------------------------
 * Boot on include
 * ---------------------------------------------------------------------- */
auth_boot();
