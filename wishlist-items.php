<?php
/**
 * Renders product cards for the slugs saved in the visitor's wishlist.
 * Fetched by assets/js/site.js on the wishlist page.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$slugs = array_filter(array_map('trim', explode(',', (string) ($_GET['slugs'] ?? ''))));
$slugs = array_slice($slugs, 0, 60);

foreach ($slugs as $slug) {
    $p = find_product($slug) ?? find_product(rawurldecode($slug));
    if ($p) {
        include ROOT_DIR . '/partials/product-card.php';
    }
}
