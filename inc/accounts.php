<?php
/**
 * Customer accounts.
 *
 * Deliberately separate from the admin's own login in admin/inc/auth.php:
 * different people, different powers, different cookie. Nothing here can
 * reach the admin panel, and a customer session on the public site cannot
 * become an admin one.
 *
 * Accounts live in storage/customers.php, which the server refuses to serve
 * and git ignores. Ordering never needs one — the checkout stays open to
 * anybody — an account only saves typing and shows what you have ordered.
 */
declare(strict_types=1);

function customers_file(): string
{
    return ROOT_DIR . '/storage/customers.php';
}

/**
 * Every account, read once per request.
 *
 * $fresh drops the cache. Without it, creating an account and then signing
 * that person in reads the copy from before they existed — which is exactly
 * what happened: registering appeared to work but quietly left them signed
 * out, and a password reset ended at the login form rejecting the password
 * it had just set.
 */
function all_customer_accounts(bool $fresh = false): array
{
    static $cache = null;
    if ($fresh) $cache = null;
    if ($cache === null) {
        $rows  = is_file(customers_file()) ? (require customers_file()) : [];
        $cache = is_array($rows) ? $rows : [];
    }
    return $cache;
}

function save_customer_accounts(array $rows): bool
{
    $ok = write_php_file(customers_file(), $rows,
        "Customer accounts. Passwords are hashed, never stored.\nWritten by the site; the server refuses to serve this file.");
    if ($ok) all_customer_accounts(true);      // what we just wrote is now the truth
    return $ok;
}

function find_account(string $email): ?array
{
    return all_customer_accounts()[lower(trim($email))] ?? null;
}

/**
 * Create an account. Returns an error string, or '' when it worked.
 *
 * The password rules are the shop's, not the customer's: ten characters is
 * the floor here as it is for the admin.
 */
function create_account(string $email, string $password, string $name): string
{
    $email = lower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'That email address does not look right.';
    if (strlen($password) < 10)                     return 'Use a password of at least ten characters.';
    if (find_account($email))                       return 'There is already an account on that address.';

    $rows = all_customer_accounts();
    $rows[$email] = [
        'email'      => $email,
        'name'       => clip(trim($name), 60),
        'password'   => password_hash($password, PASSWORD_DEFAULT),
        'created'    => date('c'),
        'address'    => '', 'city' => '', 'postcode' => '',
        'country'    => (string) setting('default_country'),
        'phone'      => '', 'company' => '',
        'reset'      => '', 'reset_at' => '',
    ];
    return save_customer_accounts($rows) ? '' : 'Could not save the account — try again.';
}

function account_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_name('argflex_customer');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',        // Lax, not Strict: they arrive from emails
    ]);
    session_start();
}

/**
 * Whoever is signed in, or null.
 *
 * A visitor with no session cookie is anonymous by definition, so this says
 * so without starting one. Handing every passer-by a cookie they never asked
 * for would be rude, would need explaining in a banner, and would stop a page
 * being cached whole.
 */
function current_customer(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE['argflex_customer'])) {
        return null;
    }
    account_session_start();
    $email = (string) ($_SESSION['customer'] ?? '');
    return $email !== '' ? find_account($email) : null;
}

/**
 * Try to sign in. Always compares a hash, even when there is no such
 * account, so a wrong address does not answer faster than a wrong password.
 */
function customer_login(string $email, string $password): bool
{
    $account = find_account($email);
    $hash    = $account['password'] ?? '$2y$12$argflexargflexargflexargflexargflexargflexargflexargflexargf';

    if (!password_verify($password, $hash) || !$account) return false;

    account_session_start();
    session_regenerate_id(true);
    $_SESSION['customer'] = $account['email'];
    return true;
}

function customer_logout(): void
{
    account_session_start();
    unset($_SESSION['customer']);
    session_regenerate_id(true);
}

/** Save a change to the signed-in customer's own record. */
function update_account(string $email, array $fields): bool
{
    $rows = all_customer_accounts();
    $key  = lower(trim($email));
    if (!isset($rows[$key])) return false;

    // only these are ever writable from the front end
    foreach (['name', 'company', 'phone', 'address', 'city', 'postcode', 'country',
              'password', 'reset', 'reset_at'] as $allowed) {
        if (array_key_exists($allowed, $fields)) $rows[$key][$allowed] = $fields[$allowed];
    }
    return save_customer_accounts($rows);
}

/** The orders belonging to an email address, newest first. */
function account_orders(string $email): array
{
    $want = lower(trim($email));
    return array_values(array_filter(all_orders(),
        fn($o) => lower((string) ($o['customer']['email'] ?? '')) === $want));
}

/** A one-shot token for resetting a forgotten password. */
function start_password_reset(string $email): string
{
    $account = find_account($email);
    if (!$account) return '';

    $token = bin2hex(random_bytes(20));
    update_account($account['email'], [
        'reset'    => password_hash($token, PASSWORD_DEFAULT),
        'reset_at' => date('c'),
    ]);
    return $token;
}

/** Is this token still good for this address? Good for one hour. */
function reset_token_valid(string $email, string $token): bool
{
    $account = find_account($email);
    if (!$account || ($account['reset'] ?? '') === '' || $token === '') return false;
    if (strtotime((string) $account['reset_at']) < time() - 3600) return false;
    return password_verify($token, $account['reset']);
}
