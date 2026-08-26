<?php
/**
 * What a basket costs to deliver, answered by the server.
 *
 * The cart lives in the browser, so the cart and checkout pages used to work
 * delivery out in JavaScript from a copy of the rules. That was tolerable
 * while the rule was "cheapest method over a threshold". It is not tolerable
 * now: carriage depends on the metres in the basket, one basket can split
 * into two consignments charged separately, and four rules decide which of
 * eight rates each consignment may use. Keeping a second copy of that in
 * site.js would guarantee the two drift, and the one that is wrong would be
 * the one the customer sees.
 *
 * So there is one implementation, in inc/shipping.php, and the browser asks
 * it. Prices are re-derived here from the catalogue — the request says only
 * which product, which option and how many.
 *
 * Fetched by assets/js/site.js on the cart and checkout pages.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$raw   = (string) file_get_contents('php://input');
$body  = json_decode($raw, true);
if (!is_array($body)) $body = [];

$lines   = is_array($body['cart'] ?? null) ? $body['cart'] : [];
$country = strtoupper(trim((string) ($body['country'] ?? '')));
if (!isset(COUNTRIES[$country])) $country = (string) setting('default_country');

$picked = [];
foreach ((array) ($body['ship'] ?? []) as $i => $rateId) $picked[(int) $i] = (int) $rateId;

$code     = trim((string) ($body['coupon'] ?? ''));
$items    = price_basket_lines(array_slice($lines, 0, 200));
$subtotal = array_sum(array_column($items, 'line'));

$coupon   = $code !== '' ? coupon_apply($code, $items, $subtotal) : ['ok' => false];
$discount = !empty($coupon['ok']) ? (int) $coupon['discount'] : 0;

$quote = shipping_quote($items, $country, $picked);
$free  = !empty($coupon['ok']) && !empty($coupon['free_shipping']);
$ship  = $free ? 0 : (int) $quote['cost'];

// VAT on the goods only — see the note in pages/checkout.php.
$tax  = tax_for($country);
$base = $subtotal - $discount + (tax_on_shipping() ? $ship : 0);
$vat  = (int) round($base * $tax['rate'] / 100);

/* Only what the page needs to draw: the consignments, their headings, and
   the rates each may be sent by. */
$packages = [];
foreach ($quote['packages'] as $i => $pkg) {
    $packages[] = [
        'name'   => $pkg['name'],
        'weight' => $pkg['weight'],
        'chosen' => (int) ($pkg['chosen']['id'] ?? ($pkg['rates'][0]['id'] ?? 0)),
        'rates'  => array_map(fn($r) => [
            'id'    => (int) $r['id'],
            'title' => (string) $r['title'],
            'cost'  => (int) $r['cost'],
        ], $pkg['rates']),
    ];
}

echo json_encode([
    'deliverable' => (bool) $quote['deliverable'],
    'why'         => (string) $quote['why'],
    'packages'    => $packages,
    'subtotal'    => $subtotal,
    'discount'    => $discount,
    'shipping'    => $ship,
    'free'        => $free,
    'vat'         => $vat,
    'total'       => $subtotal - $discount + $ship + $vat,
    'count'       => count($items),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
