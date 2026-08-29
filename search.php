<?php
/**
 * What the header search box shows while you are still typing.
 *
 * The live site answers a keystroke with a list of products, and it is the
 * fastest route into a catalogue where people arrive knowing a bore size or a
 * standard rather than a product name. Pressing Enter still goes to /shop/?q=
 * and always will — this is a shortcut past that page, not a replacement for
 * it, so the search works with no JavaScript at all.
 *
 * Matching is the same haystack /shop/ uses, so the dropdown and the results
 * page can never disagree about what matches. The ordering is not the same:
 * here a hose whose NAME starts with what you typed comes first, because with
 * five rows to spend, "16mm" should not lead with something that merely
 * mentions 16 mm in its description.
 *
 * Read-only, so no token — there is nothing to forge. It is rate limited
 * anyway: it walks the catalogue on every call.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$q = trim((string) ($_GET['q'] ?? ''));

/** Answer and stop. */
function search_reply(array $data): never
{
    exit(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/* One character matches half the shop and tells nobody anything. */
if (mb_strlen($q) < 2) {
    search_reply(['q' => $q, 'total' => 0, 'items' => [], 'url' => '/shop/']);
}
if (mb_strlen($q) > 80) $q = mb_substr($q, 0, 80);

if (rate_limited('search', 240, 3600)) {
    http_response_code(429);
    search_reply(['q' => $q, 'total' => 0, 'items' => [], 'url' => '/shop/?q=' . rawurlencode($q)]);
}
rate_hit('search');

$needle = lower($q);
$hits   = array_values(array_filter(all_products(),
    fn($p) => str_contains(product_haystack($p), $needle)));

/* Best first. The middle band is what stops "8mm" leading with the 18 mm
   hose: both contain the string, but only one of them starts a word with it.
   usort is stable in PHP 8, so within a band the catalogue's own order
   survives. */
$edge = '/(?<![0-9a-z])' . preg_quote($needle, '/') . '/u';

usort($hits, function ($a, $b) use ($needle, $edge) {
    $rank = function (array $p) use ($needle, $edge): int {
        $name = lower($p['name']);
        if (str_starts_with($name, $needle))      return 0;
        if (preg_match($edge, $name))             return 1;
        if (str_contains($name, $needle))         return 2;
        if (preg_match($edge, product_haystack($p))) return 3;
        return 4;
    };
    return $rank($a) <=> $rank($b);
});

$total = count($hits);
$items = [];

foreach (array_slice($hits, 0, 6) as $p) {
    $items[] = [
        'name'  => $p['name'],
        'url'   => product_url($p),
        'image' => ($p['images'][0] ?? '') !== '' ? '/' . ltrim((string) $p['images'][0], '/') : '',
        'cat'   => product_cat_label($p),
        // The same label the card shows, range and all — "£3.08 – £148.92"
        'price' => $p['price_min'] > 0 ? price_label($p) : 'Price on request',
        'stock' => product_in_stock($p),
    ];
}

search_reply([
    'q'     => $q,
    'total' => $total,
    'items' => $items,
    'url'   => '/shop/?q=' . rawurlencode($q),
]);
