<?php
/**
 * What check_link_blocks.py needs to know about the catalogue, as JSON.
 *
 *     php .data/link_facts.php
 *
 * RAW where it can be, like product_facts.php and attribute_facts.php beside
 * it: each product's categories, family and bores, the sibling URLs its bore
 * picker links, and for each article what it refers to. The check decides in
 * Python what a block may and may not contain and how close two articles are,
 * and only then reads the pages. Nothing here calls related_products(),
 * related_posts() or product_guides() — that would be the code agreeing with
 * itself.
 *
 * `topics` is the one thing taken from the site's own working
 * (post_topics()): which hoses an article is about is the subject of
 * test_articles.php, and this check is about what is done with the answer.
 */
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';

$out = ['products' => [], 'posts' => []];

foreach (all_products() as $product) {
    $picker = [];
    foreach (family_options($product) as $bore) {
        if (!$bore['mine']) $picker[] = (string) $bore['url'];
    }
    $home = primary_category($product);

    $out['products'][(string) $product['slug']] = [
        'url'     => product_url($product),
        'name'    => (string) $product['name'],
        'cats'    => array_values((array) $product['cats']),
        'primary' => $home ? (string) $home['slug'] : '',
        'family'  => trim((string) (product_defaults($product)['family'] ?? '')),
        'bores'   => array_column(product_bores($product), 'name'),
        'picker'  => $picker,
    ];
}

foreach (all_posts() as $article) {
    $out['posts'][(string) $article['slug']] = [
        'url'    => post_url($article),
        'title'  => (string) $article['title'],
        'date'   => (string) ($article['date'] ?? ''),
        'topics' => post_topics($article),
    ];
}

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
