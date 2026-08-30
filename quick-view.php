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
require_once ROOT_DIR . '/inc/gateways.php';   // which ways to pay are actually live
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

/* is_scalar first: ?slug[]=x makes $_GET['slug'] an ARRAY, and casting one to
   string emits a warning — which lands in the body ahead of the JSON, taking
   the absolute server path with it and leaving http_response_code() shouting
   about headers already sent. The endpoint then answers 200 with two HTML
   warnings and something no parser will read. */
$slug = is_scalar($_GET['slug'] ?? null) ? trim((string) $_GET['slug']) : '';
$p    = $slug !== '' ? find_product($slug) : null;
if (!$p) {
    http_response_code(404);
    quick_reply(['ok' => false, 'error' => 'No such product.']);
}

$p     = product_defaults($p);
$stock = stock_state($p);

/* At most four specification rows. The whole point is a glance; a popup that
   reproduces the full sheet is the product page with extra steps. Cut on a
   word with an ellipsis, so a value that goes on is visibly unfinished rather
   than looking like the whole of a shorter one. */
$specs = [];
foreach (parse_specs((string) $p['short']) as $s) {
    if (count($specs) >= 4) break;
    $value = trim((string) $s['value']);
    if (mb_strlen($value) > 150) {
        $cut   = mb_substr($value, 0, 150);
        $space = mb_strrpos($cut, ' ');
        $value = rtrim($space !== false && $space > 90 ? mb_substr($cut, 0, $space) : $cut, " ,;:.") . '…';
    }
    $specs[] = ['label' => (string) $s['label'], 'value' => $value];
}

/* The real variations, the same map the product page builds. The popup is a
   picker now, not a summary: somebody who can see the bore and the length
   ought to be able to choose them and buy, rather than be sent to another
   page to repeat a decision they have already made. */
$variants = [];
foreach ((array) $p['variants'] as $v) {
    $variants[(string) $v['key']] = [
        'price' => (int) variant_price($v, $p),
        'was'   => variant_price($v, $p) < (int) $v['price'] ? (int) $v['price'] : 0,
        'label' => (string) $v['label'],
        'image' => ($v['image'] ?? '') !== '' ? '/' . ltrim((string) $v['image'], '/') : '',
    ];
}

/* The axes to draw, in the order the product lists them, and only the ones a
   variation actually keys on. */
$axes = [];
foreach ((array) $p['attrs'] as $a) {
    if (empty($a['variation']) || !$a['terms']) continue;
    $axes[] = [
        'name'  => (string) $a['name'],
        'terms' => array_values(array_map(
            fn($t) => ['name' => (string) $t['name'], 'slug' => (string) $t['slug']],
            (array) $a['terms'])),
    ];
}

/* Where this model is sold as several listings, the bores of the whole family.
   A sibling's bore reopens the popup on that sibling rather than closing it —
   the customer is comparing, and being thrown back to the grid to click again
   is the thing this popup exists to avoid. */
$family = [];
foreach (family_options($p) as $opt) {
    $family[] = [
        'name' => (string) $opt['name'],
        'slug' => (string) $opt['slug'],
        'mine' => (bool) $opt['mine'],
        'to'   => $opt['mine'] ? '' : basename(rtrim((string) $opt['url'], '/')),
    ];
}
$familyAxis = $family ? family_axis($p) : '';

/* $axes is sent WHOLE, family axis included. Filtering it here would be wrong
   the moment a family member's bore is also a real variation axis: the axis
   would vanish from the list the browser joins into a variation key, and every
   combination would match nothing. The browser merges the two rows instead —
   the same thing pages/product.php does. */

quick_reply([
    'ok'       => true,
    'slug'     => (string) $p['slug'],
    'name'     => (string) $p['name'],
    'url'      => product_url($p),
    'image'    => ($p['images'][0] ?? '') !== '' ? '/' . ltrim((string) $p['images'][0], '/') : '',
    'cat'      => product_cat_label($p),
    'price'    => $p['price_min'] > 0 ? price_label($p) : 'Price on request',
    'suffix'   => $p['price_min'] > 0 ? trim('Per metre · ' . price_suffix(), ' ·') : '',
    'stock'    => ['state' => (string) $stock['state'], 'label' => (string) $stock['label']],
    'specs'    => $specs,

    'axes'     => $axes,
    'variants' => $variants,
    'opensOn'  => (array) ($p['default_attrs'] ?? []),
    'family'   => $family,
    'axisName' => $familyAxis,

    /* Ways to pay that the shop can ACTUALLY take today. Only real gateways
       get a button of their own: "Pay by proforma invoice" is not a payment,
       it is a request for one, and a button promising otherwise would be a
       lie the customer only discovers at the end. With no keys configured
       this is empty and the popup shows the checkout link alone; the moment
       Stripe or PayPal is switched on, its button appears here on its own. */
    'pay'      => array_values(array_map(
        fn($m) => ['id' => (string) $m['id'], 'title' => (string) $m['title']],
        array_filter(usable_payment_methods(),
            fn($m) => in_array($m['id'], ['stripe', 'ppcp'], true) && gateway_ready($m['id'])))),

    // A product with no variations at all is one price and goes straight in.
    'simple'   => !$p['variants'] && $p['price_min'] > 0,
    'unit'     => (int) effective_min($p),
    'max'      => (int) stock_ceiling($p),
    'inStock'  => product_in_stock($p),
]);
