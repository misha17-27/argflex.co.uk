<?php
/**
 * Admin authentication: sessions, password hashing, CSRF tokens and a
 * throttle on failed logins. Accounts live in storage/users.php, which the
 * web server denies and git ignores.
 */
declare(strict_types=1);

const USERS_FILE    = ROOT_DIR . '/storage/users.php';
const ATTEMPTS_FILE = ROOT_DIR . '/storage/login-attempts.json';
const MAX_ATTEMPTS  = 8;
const LOCKOUT_SECS  = 900;   // 15 minutes

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_name('argflex_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Strict',
    ]);
    session_start();

    // rotate the id periodically to blunt fixation
    if (!isset($_SESSION['started'])) {
        $_SESSION['started'] = time();
    } elseif (time() - $_SESSION['started'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['started'] = time();
    }
}

function users(): array
{
    return is_file(USERS_FILE) ? (array) (require USERS_FILE) : [];
}

function has_users(): bool
{
    return users() !== [];
}

const ROLES = ['admin' => 'Administrator', 'editor' => 'Editor'];

/** Sections only an administrator may open. */
const ADMIN_ONLY = ['users', 'security', 'mail', 'settings'];

function create_user(string $email, string $password, string $name = '', string $role = 'admin'): bool
{
    $list  = users();
    $key   = strtolower(trim($email));
    $exists = $list[$key] ?? null;

    $list[$key] = [
        'email'   => $key,
        'name'    => $name !== '' ? $name : $email,
        'role'    => isset(ROLES[$role]) ? $role : 'editor',
        'hash'    => $password !== ''
                     ? password_hash($password, PASSWORD_DEFAULT)
                     : (string) ($exists['hash'] ?? ''),
        'created' => $exists['created'] ?? date('c'),
    ];
    return write_php_file(USERS_FILE, $list, 'Admin accounts. Never commit this file.');
}

function delete_user(string $email): bool
{
    $list = users();
    $key  = strtolower(trim($email));
    // never leave the panel without an administrator
    $admins = array_filter($list, fn($u) => ($u['role'] ?? 'admin') === 'admin');
    if (!isset($list[$key]) || (count($admins) <= 1 && isset($admins[$key]))) return false;
    unset($list[$key]);
    return write_php_file(USERS_FILE, $list, 'Admin accounts. Never commit this file.');
}

function is_admin(): bool
{
    return (current_user()['role'] ?? 'admin') === 'admin';
}

/** Stop an editor opening an administrator-only section. */
function require_admin(string $section): void
{
    if (in_array($section, ADMIN_ONLY, true) && !is_admin()) {
        flash('That section needs an administrator account.', 'bad');
        header('Location: /admin/');
        exit;
    }
}

/* ------------------------------------------------------------- throttling */

function attempts(): array
{
    return is_file(ATTEMPTS_FILE)
        ? (array) json_decode((string) file_get_contents(ATTEMPTS_FILE), true)
        : [];
}

function attempt_key(): string
{
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function is_locked_out(): int
{
    $entry = attempts()[attempt_key()] ?? null;
    if (!$entry || ($entry['count'] ?? 0) < MAX_ATTEMPTS) return 0;
    $left = LOCKOUT_SECS - (time() - (int) ($entry['at'] ?? 0));
    return $left > 0 ? $left : 0;
}

function record_attempt(bool $ok): void
{
    $all = attempts();
    $key = attempt_key();
    if ($ok) {
        unset($all[$key]);
    } else {
        $entry = $all[$key] ?? ['count' => 0, 'at' => 0];
        if (time() - (int) $entry['at'] > LOCKOUT_SECS) $entry['count'] = 0;
        $entry['count']++;
        $entry['at'] = time();
        $all[$key] = $entry;
    }
    @file_put_contents(ATTEMPTS_FILE, json_encode($all), LOCK_EX);
}

/* ------------------------------------------------------------------ login */

function attempt_login(string $email, string $password): bool
{
    $user = users()[strtolower(trim($email))] ?? null;
    // always run a hash comparison so a missing account is not faster than a wrong password
    $hash = $user['hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidiu';
    $ok   = password_verify($password, $hash) && $user !== null;

    record_attempt($ok);
    if (!$ok) return false;

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'email' => $user['email'],
        'name'  => $user['name'],
        'role'  => $user['role'] ?? 'admin',
    ];
    $_SESSION['started'] = time();
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /admin/login');
        exit;
    }
}

/* ------------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Every POST must carry a matching token or it is refused outright. */
function check_csrf(): void
{
    $sent = (string) ($_POST['_token'] ?? '');
    if ($sent === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Session expired — go back, reload the page and try again.');
    }
}

/* ------------------------------------------------------------------ flash */

function flash(string $message = null, string $kind = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
