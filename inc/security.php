<?php
/**
 * What every form on the public side is defended by.
 *
 * The admin has had a token and a lockout since it was written. The shop's
 * own forms had neither: a honeypot and a Turnstile widget stop a robot
 * filling them in, and stop nothing at all when another site posts to them
 * with a real person's cookies attached. That is the hole this closes.
 *
 * Nothing here needs a session. Making every passer-by carry one to look at
 * a hose would cost a cookie banner and the ability to cache a page whole,
 * so the token is signed rather than stored, and the counter is a file.
 */
declare(strict_types=1);

const FORM_COOKIE   = 'argflex_form';
const RATE_FILE     = ROOT_DIR . '/storage/rate-limits.json';
const SECRET_FILE   = ROOT_DIR . '/storage/app.key';

/* -------------------------------------------------------------- the secret */

/**
 * The key everything here is signed with, made once and kept out of git.
 *
 * If it cannot be written — a read-only deploy, the wrong owner on storage —
 * the shop still has to work, so this falls back to a key derived from
 * something already secret and already on disk. That is weaker, because it
 * changes when the admin password does, but a form that refuses everybody is
 * worse than one signed with a key that occasionally rolls.
 */
function app_secret(): string
{
    static $key = null;
    if ($key !== null) return $key;

    if (is_file(SECRET_FILE)) {
        $stored = trim((string) @file_get_contents(SECRET_FILE));
        if (strlen($stored) >= 32) return $key = $stored;
    }

    $made = bin2hex(random_bytes(32));
    if (@file_put_contents(SECRET_FILE, $made, LOCK_EX) !== false) {
        @chmod(SECRET_FILE, 0600);
        return $key = $made;
    }

    return $key = hash('sha256', 'argflex|' . (is_file(ROOT_DIR . '/storage/users.php')
        ? (string) @filemtime(ROOT_DIR . '/storage/users.php') : '')
        . '|' . __DIR__);
}

/* ---------------------------------------------------------------- the token */

/**
 * The visitor's half of the token, in a cookie of its own.
 *
 * Deliberately not the session: the checkout is open to people who never sign
 * in, and they need a token too.
 */
function form_seed(): string
{
    static $seed = null;
    if ($seed !== null) return $seed;

    $have = (string) ($_COOKIE[FORM_COOKIE] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $have)) return $seed = $have;

    $seed = bin2hex(random_bytes(16));
    if (!headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie(FORM_COOKIE, $seed, [
            'expires'  => 0,          // dies with the browser
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE[FORM_COOKIE] = $seed;    // so two forms on one page agree
    return $seed;
}

/**
 * A token for one particular thing.
 *
 * Naming the action means the token minted for the enquiry form cannot be
 * replayed against "change my password" — the two are signed differently.
 */
function form_token(string $action): string
{
    return hash_hmac('sha256', $action . '|' . form_seed(), app_secret());
}

function form_field(string $action): string
{
    return '<input type="hidden" name="_form" value="' . e(form_token($action)) . '">';
}

/**
 * True when this request carries the right token for this action.
 *
 * $sent is for the endpoints that read a JSON body rather than a form, where
 * $_POST is empty — payment.php is one. They pass what the body held.
 */
function form_token_ok(string $action, ?string $sent = null): bool
{
    $sent ??= (string) ($_POST['_form'] ?? '');
    $seed   = (string) ($_COOKIE[FORM_COOKIE] ?? '');
    if ($sent === '' || !preg_match('/^[a-f0-9]{32}$/', $seed)) return false;

    return hash_equals(hash_hmac('sha256', $action . '|' . $seed, app_secret()), $sent);
}

/**
 * Refuse a POST that has no valid token, in the way that suits the caller.
 *
 * $html is for a page that renders its own errors; it returns a sentence to
 * show instead of dying. An endpoint that answers JSON gets a 419 and stops.
 */
function require_form_token(string $action, string $mode = 'text'): string
{
    if (form_token_ok($action)) return '';

    $why = ($_COOKIE[FORM_COOKIE] ?? '') === ''
        ? 'Your browser did not keep our cookie, so we could not check this form came from us. '
          . 'Allow cookies for this site and try again.'
        : 'That form had gone stale. Reload the page and send it once more.';

    if ($mode === 'return') return $why;

    http_response_code(419);
    if ($mode === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => $why]));
    }
    exit($why);
}

/* ------------------------------------------------------------ the counters */

/** Who to count against. REMOTE_ADDR only: any header can be typed by hand. */
function client_key(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
}

/**
 * How many attempts are remembered per caller per bucket.
 *
 * This is the ceiling on any limit that can ever fire. It was 40, hard-coded
 * in rate_hit(), while three callers asked for 60, 240 and 300 — so the
 * counter could never reach them and the coupon endpoint, the search and the
 * quick view were all effectively unlimited while looking protected. Both
 * functions read it now, and rate_limited() clamps to it rather than quietly
 * asking for something the store cannot prove.
 */
const RATE_KEEP = 600;

/**
 * True when this caller has done $what more than $max times in $window
 * seconds. Counting the attempt is the caller's job — see rate_hit() — so a
 * check before the work and a count after it stay separate.
 */
function rate_limited(string $what, int $max, int $window): bool
{
    $max = max(1, min($max, RATE_KEEP));

    $all   = rate_all();
    $entry = $all[$what . ':' . client_key()] ?? null;
    if (!$entry) return false;

    $hits = array_filter((array) $entry, fn($t) => (int) $t > time() - $window);
    return count($hits) >= $max;
}

/** Count one attempt. */
function rate_hit(string $what, int $window = 3600): void
{
    $all = rate_all();
    $key = $what . ':' . client_key();

    $mine   = array_values(array_filter((array) ($all[$key] ?? []), fn($t) => (int) $t > time() - $window));
    $mine[] = time();
    $all[$key] = array_slice($mine, -RATE_KEEP);

    /* Drop anything nobody will ask about again, so the file cannot grow
       without bound on a busy day. */
    $cutoff = time() - 86400;
    foreach ($all as $k => $times) {
        $kept = array_values(array_filter((array) $times, fn($t) => (int) $t > $cutoff));
        if ($kept) $all[$k] = $kept; else unset($all[$k]);
    }

    @file_put_contents(RATE_FILE, json_encode($all), LOCK_EX);
}

/** Forget this caller's attempts — for when they finally get it right. */
function rate_clear(string $what): void
{
    $all = rate_all();
    unset($all[$what . ':' . client_key()]);
    @file_put_contents(RATE_FILE, json_encode($all), LOCK_EX);
}

function rate_all(): array
{
    if (!is_file(RATE_FILE)) return [];
    return (array) json_decode((string) @file_get_contents(RATE_FILE), true);
}

/* ------------------------------------------------------------- the details */

/**
 * A single line of text, safe to put in a mail header.
 *
 * A newline in a Reply-To is how an open relay is made out of a contact
 * form: everything after it is read as another header. This drops them.
 */
function header_safe(string $value, int $max = 200): string
{
    $value = preg_replace('/[\r\n\t\0\x0B]+/', ' ', $value) ?? '';
    return clip(trim($value), $max);
}

/**
 * True when a posted address is worth sending to.
 *
 * Deliberately stricter than filter_var alone, which is happy with an
 * address holding a newline once it has been decoded elsewhere.
 */
function usable_email(string $email): bool
{
    $email = trim($email);
    return $email !== ''
        && strlen($email) <= 190
        && $email === header_safe($email, 190)
        && (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}
