<?php
/**
 * Renders the cross-sells for whatever is in the basket.
 *
 * The basket lives in the visitor's browser, so the page asks for these once
 * it knows what is in it. Same shape as wishlist-items.php.
 *
 * Anything already in the basket is left out — offering someone a coupling
 * they have just added is noise, not a suggestion.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$inBasket = array_filter(array_map('trim', explode(',', (string) ($_GET['slugs'] ?? ''))));
$inBasket = array_slice($inBasket, 0, 60);

$wanted = [];
foreach ($inBasket as $slug) {
    $p = find_product($slug) ?? find_product(rawurldecode($slug));
    if (!$p) continue;
    foreach ((array) product_defaults($p)['crosssells'] as $other) {
        if (in_array($other, $inBasket, true)) continue;      // already buying it
        $wanted[$other] = true;
    }
}

$shown = 0;
foreach (array_keys($wanted) as $slug) {
    $p = find_product($slug);
    if (!$p || !product_in_stock($p)) continue;
    include ROOT_DIR . '/partials/product-card.php';
    if (++$shown >= 4) break;
}
