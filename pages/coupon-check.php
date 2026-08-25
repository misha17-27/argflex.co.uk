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

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Send this as a POST.']);
    exit;
}

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
