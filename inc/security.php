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

/**
 * Cloudflare's own address ranges, from cloudflare.com/ips-v4 and /ips-v6,
 * read on 2026-10-10. They change rarely; if a legitimate visitor ever starts
 * being counted as somebody else, re-read those two pages.
 */
const CLOUDFLARE_RANGES = [
    '173.245.48.0/20',  '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18',  '108.162.192.0/18','190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15',  '104.16.0.0/13',
    '104.24.0.0/14',    '172.64.0.0/13',   '131.0.72.0/22',
    '2400:cb00::/32',   '2606:4700::/32',  '2803:f800::/32',  '2405:b500::/32',
    '2405:8100::/32',   '2a06:98c0::/29',  '2c0f:f248::/32',
];

/** True when $ip falls inside $cidr. Handles both IPv4 and IPv6. */
function ip_in_range(string $ip, string $cidr): bool
{
    [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    $a = @inet_pton($ip);
    $b = @inet_pton((string) $net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;

    $bits  = (int) $bits;
    $whole = intdiv($bits, 8);
    $rest  = $bits % 8;

    if ($whole > 0 && strncmp($a, $b, $whole) !== 0) return false;
    if ($rest === 0) return true;
    if (strlen($a) <= $whole) return false;

    $mask = chr((0xFF << (8 - $rest)) & 0xFF);
    return (($a[$whole] & $mask) === ($b[$whole] & $mask));
}

/**
 * The address the request really came from.
 *
 * REMOTE_ADDR alone was wrong here, and wrong in the direction that matters.
 * This shop sits behind Cloudflare, so unless the host restores the original
 * address, REMOTE_ADDR is one of Cloudflare's own edge machines and every
 * visitor on earth shares a handful of them. That turns the login lockout from
 * a defence into a weapon: eight wrong passwords from anywhere and the owner
 * is shut out of their own shop for fifteen minutes, repeatable for ever. The
 * form rate limits had the same flaw, one counter for everybody.
 *
 * CF-Connecting-IP is trusted ONLY when the connection itself comes from a
 * Cloudflare range. Trusting it unconditionally would be worse than the bug:
 * anyone who finds the origin and skips the proxy could type whatever address
 * they liked and never be counted at all.
 *
 * If the host already restores the real address, REMOTE_ADDR is not a
 * Cloudflare range, the header is ignored, and nothing changes.
 */
function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '') return 'cli';

    $sent = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($sent === '' || !filter_var($sent, FILTER_VALIDATE_IP)) return $remote;

    foreach (CLOUDFLARE_RANGES as $range) {
        if (ip_in_range($remote, $range)) return $sent;
    }
    return $remote;
}

/** Who to count against — the caller, not the proxy in front of them. */
function client_key(): string
{
    return hash('sha256', client_ip());
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

/* ------------------------------------------------------------ the receipt */

const RECEIPT_COOKIE = 'argflex_receipt';

/**
 * Let THIS browser see the order it has just placed, and no other.
 *
 * The thank-you screen is reached at /checkout/?ok=REFERENCE, and a reference
 * is a date and six hex characters. Printing a customer's name, address and
 * telephone number against a URL of that shape would put them behind a guess —
 * a poor one, but the address bar is also copied into referrers, shared links
 * and shoulder-shots, and none of that is worth the convenience.
 *
 * So the details are shown only to a browser carrying this cookie, signed with
 * the site's own key so it cannot be written by hand. Everybody else still
 * gets the reference and the wording; they simply do not get the personal
 * data. Two hours is long enough to read a receipt and refresh it twice.
 */
function receipt_grant(string $reference): void
{
    if ($reference === '' || headers_sent()) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    setcookie(RECEIPT_COOKIE, $reference . '|' . hash_hmac('sha256', $reference, app_secret()), [
        'expires'  => time() + 7200,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
}

/** True when the browser asking is the one that placed this order. */
function receipt_allowed(string $reference): bool
{
    $raw = (string) ($_COOKIE[RECEIPT_COOKIE] ?? '');
    if ($reference === '' || $raw === '') return false;

    $at = strrpos($raw, '|');
    if ($at === false) return false;

    $ref = substr($raw, 0, $at);
    $sig = substr($raw, $at + 1);

    return hash_equals(hash_hmac('sha256', $ref, app_secret()), $sig)
        && hash_equals($ref, $reference);
}

/* ------------------------------------------------------- unwanted messages */

/**
 * How much a submitted message looks like link spam, and why.
 *
 * Turnstile is on this form and was passed — the message that prompted this
 * was two thousand words of casino copy with BBCode links, sent through a
 * solved captcha. That happens; a captcha proves something got past a
 * challenge, not that it has anything to say to a hose supplier.
 *
 * So this reads the message itself. Several weak signals rather than one
 * clever rule, because any single rule strict enough to catch spam is strict
 * enough to lose a real order. Scored against what a genuine enquiry to THIS
 * shop looks like — a paragraph about a bore size, a length and a price —
 * which is the only reason a threshold this low is safe.
 *
 * Nothing is deleted. A message over the line is filed as spam and kept where
 * the shop can look at it, because the cost of throwing away one real
 * customer is much higher than the cost of a row in a list.
 *
 * @return array{score:int, why:string[]}
 */
function spam_score(string $name, string $email, string $phone, string $message): array
{
    $why  = [];
    $add  = function (int $points, string $reason) use (&$why, &$score) {
        $score += $points;
        $why[]  = $reason;
    };
    $score = 0;

    $text  = $name . "\n" . $message;
    $lower = lower($text);

    /* BBCode. Nobody types [url=…] into an HTML form in 2026 — it is the
       signature of a bot written for forum software, and it is the single
       strongest signal here. */
    if (preg_match('~\[/?(?:url|link|b|img)\b~i', $text)) {
        $add(4, 'BBCode markup');
    }

    /* Links. One can be legitimate — a customer pointing at a drawing or a
       datasheet — so one is barely worth noticing and three is not an
       enquiry, it is an advertisement. */
    $links = preg_match_all('~https?://|\bwww\.[a-z0-9-]+\.[a-z]{2,}~i', $text);
    if ($links >= 3)      $add(4, $links . ' links');
    elseif ($links === 2) $add(2, 'two links');
    elseif ($links === 1) $add(1, 'a link');

    // Length. A real enquiry is a paragraph; this one ran to two thousand words.
    $len = mb_strlen($message);
    if ($len > 3000)      $add(2, 'very long (' . $len . ' characters)');
    elseif ($len > 1500)  $add(1, 'long (' . $len . ' characters)');

    /* Vocabulary that has no business reaching a hose supplier. Counted
       DISTINCT, and three are needed, so a customer who happens to write
       "bonus" or "credit" once is untouched. */
    $foreign = 0;
    foreach (['casino', 'gambling', 'betting', ' slots', 'wager', 'poker', 'jackpot',
              'bookmaker', 'crypto', 'bitcoin', 'forex', 'payday loan', 'backlink',
              'seo service', 'rank your site', 'viagra', 'escort', 'porn'] as $word) {
        if (str_contains($lower, $word)) $foreign++;
    }
    if ($foreign >= 3) $add(3, $foreign . ' words from another trade entirely');

    /* Ofcom reserves 07700 900000-900999 for drama and testing. They are
       never anybody's number, so one in a contact form was typed by something
       that needed a plausible-looking UK mobile. */
    if (preg_match('~(?:\+?44\s?|0)7700\s?9000?\d{2,3}~', preg_replace('~[^0-9+]~', '', $phone . $message))) {
        $add(3, 'a telephone number from the reserved test range');
    }

    /* And a message to a hose shop that never mentions anything a hose shop
       sells. On its own this means little — somebody may simply ask to speak
       to a person — so it is worth one point and never decides alone. */
    $ours = false;
    foreach (['hose', 'pipe', 'tube', 'clamp', 'coupling', 'fitting', 'bore', 'metre',
              'meter', 'diameter', 'price', 'quote', 'delivery', 'order', 'stock',
              'ducting', 'fuel', 'oil', 'water', 'air', 'gas'] as $word) {
        if (str_contains($lower, $word)) { $ours = true; break; }
    }
    /* A size counts as talking shop. "25 m of 16 mm" names no product and is
       unmistakably a customer — reading only for words missed it. */
    if (!$ours && preg_match('~\d+\s?(?:mm|m)\b~i', $message)) $ours = true;

    if (!$ours) $add(1, 'nothing about hose, price, delivery or a size');

    return ['score' => $score, 'why' => $why];
}

/** Over the line. Four is two independent signals, never one. */
function looks_like_spam(string $name, string $email, string $phone, string $message): bool
{
    return spam_score($name, $email, $phone, $message)['score'] >= 4;
}
