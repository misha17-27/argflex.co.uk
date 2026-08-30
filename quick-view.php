<?php
/**
 * One product, enough of it to decide without leaving the listing.
 *
 * The catalogue is thirty-odd hoses that look alike in a photograph and are
 * told apart by a standard, a bore range and a temperature. Opening each one
 * to read six lines and coming back is the slow way to compare them, so the
 * card offers a look: the picture, the price, what it is for, and the sizes
 * it comes in.
 *
 * Deliberately NOT the whole product page. Buying still happens on the page,
 * which is where the delivery estimate, the reviews and the full description
 * are — a popup that tries to be the page ends up a worse copy of it.
 *
 * Answers JSON. Read-only, so no token; rate limited because it walks the
 * catalogue and renders on every call.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once ROOT_DIR . '/inc/families.php';   // the sizes a model's siblings carry
require_once ROOT_DIR . '/inc/security.php';   // the counters

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

/** Answer and stop. */
function quick_reply(array $data): never
{
    exit(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

if (rate_limited('quickview', 300, 3600)) {
    http_response_code(429);
    quick_reply(['ok' => false, 'error' => 'Too many requests.']);
}
rate_hit('quickview');

$p = find_product(trim((string) ($_GET['slug'] ?? '')));
if (!$p) {
    http_response_code(404);
    quick_reply(['ok' => false, 'error' => 'No such product.']);
}

$p     = product_defaults($p);
$stock = stock_state($p);

/* At most four specification rows. The whole point is a glance; a popup that
   reproduces the full sheet is the product page with extra steps. */
$specs = [];
foreach (parse_specs((string) $p['short']) as $s) {
    if (count($specs) >= 4) break;
    $specs[] = ['label' => (string) $s['label'], 'value' => clip((string) $s['value'], 150)];
}

/* Sizes, whichever way this product holds them: its own attribute terms, and
   the rest of its family where it is one listing of several. */
$sizes = array_column(product_bores($p), 'name');
foreach (family_options($p) as $opt) {
    if (!in_array($opt['name'], $sizes, true)) $sizes[] = $opt['name'];
}
// By the number, so 3.2mm precedes 10mm — the product's own bore would
// otherwise lead the list wherever it happens to fall in the range.
usort($sizes, fn($a, $b) => bore_value($a) <=> bore_value($b));

$lengths = [];
foreach ((array) $p['attrs'] as $a) {
    if (stripos((string) $a['name'], 'length') === false) continue;
    $lengths = array_column((array) $a['terms'], 'name');
    break;
}

quick_reply([
    'ok'      => true,
    'slug'    => (string) $p['slug'],
    'name'    => (string) $p['name'],
    'url'     => product_url($p),
    'image'   => ($p['images'][0] ?? '') !== '' ? '/' . ltrim((string) $p['images'][0], '/') : '',
    'cat'     => product_cat_label($p),
    'price'   => $p['price_min'] > 0 ? price_label($p) : 'Price on request',
    'suffix'  => $p['price_min'] > 0 ? trim('Per metre · ' . price_suffix(), ' ·') : '',
    'stock'   => ['state' => (string) $stock['state'], 'label' => (string) $stock['label']],
    'specs'   => $specs,
    'sizes'   => array_values(array_slice($sizes, 0, 14)),
    'lengths' => array_values(array_slice($lengths, 0, 10)),
    // A single-price product can go straight in the basket from the popup.
    'buyable' => $p['price_min'] > 0 && !$p['variants'] && product_in_stock($p),
    'unit'    => (int) effective_min($p),
    'max'     => (int) stock_ceiling($p),
]);
