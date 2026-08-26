<?php
/**
 * Give a name to links that wrap nothing but a picture with alt="".
 *
 * WordPress marked several imported photos decorative. That is fine for a
 * picture sitting in the text, but where the picture is the whole of a link
 * the link has nothing to announce, and a screen reader reads out the URL —
 * or nothing at all. Name it after what it points at.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$named = 0;

$fix = function (string $html) use (&$named): string {
    return preg_replace_callback(
        '~<a\s+href="/product/([^"/]+)/"[^>]*>\s*<img([^>]*)\salt=""~i',
        function ($m) use (&$named) {
            $p = find_product($m[1]) ?: find_product(rawurldecode($m[1]));
            if (!$p) return $m[0];
            $named++;
            return str_replace('alt=""', 'alt="' . e((string) $p['name']) . '"', $m[0]);
        },
        $html
    ) ?? $html;
};

$posts = all_posts();
foreach ($posts as $i => $p) {
    $now = $fix((string) $p['content']);
    if ($now !== $p['content']) $posts[$i]['content'] = $now;
}
if ($named) save_posts($posts);

$products = all_products(true);
foreach ($products as $i => $p) {
    foreach (['short', 'desc'] as $field) {
        $now = $fix((string) $p[$field]);
        if ($now !== $p[$field]) $products[$i][$field] = $now;
    }
}
if ($named) save_products($products);

echo "  {$named} picture-only link(s) given a name\n";
