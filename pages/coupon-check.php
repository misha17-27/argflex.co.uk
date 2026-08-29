<?php
/**
 * Checks a discount code against the basket and answers with JSON.
 *
 * The basket lives in the browser, so it is re-priced from the catalogue
 * here before the code is judged — otherwise a tampered basket could earn a
 * bigger discount than the goods are worth. Nothing is stored; the checkout
 * runs the same check again before an order is written.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/security.php';   // the form token and the counters

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Send this as a POST.']);
    exit;
}

/* Answered before the token is looked at, deliberately. With codes switched
   off no page carries a coupon token at all, so a token check would refuse
   this with "the form went stale" — which is not what happened and would
   send whoever is reading it looking for a bug that is not there. The cart
   page already shows whether codes are being taken, so saying so leaks
   nothing. */
if (!coupons_enabled()) {
    echo json_encode(['ok' => false, 'error' => 'Discount codes are not being accepted at the moment.',
                      'code' => '', 'title' => '', 'discount' => 0, 'free_shipping' => false]);
    exit;
}

require_form_token('coupon', 'json');

/* This endpoint answers "is that a real code?" and it answers instantly, so
   left open it is a way to read the whole coupon list one guess at a time.
   Sixty tries an hour is far more than a customer with a code in an email
   needs, and far fewer than a dictionary run wants. */
if (rate_limited('coupon', 60, 3600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many codes tried. Wait a few minutes.']);
    exit;
}
rate_hit('coupon');

$lines = json_decode((string) ($_POST['cart'] ?? '[]'), true);
$items = price_basket_lines(is_array($lines) ? $lines : []);
$net   = array_sum(array_column($items, 'line'));

$result = coupon_apply((string) ($_POST['code'] ?? ''), $items, $net);

echo json_encode([
    'ok'            => (bool) $result['ok'],
    'error'         => (string) $result['error'],
    'code'          => (string) $result['code'],
    'title'         => (string) $result['title'],
    'discount'      => (int) $result['discount'],
    'free_shipping' => (bool) $result['free_shipping'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
