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
        // can it be sent today, as stored: in stock and priced
        'live'    => product_in_stock($product) && (int) ($product['price_min'] ?? 0) > 0,
    ];
}

/* THE ARTICLES THE SHOP HAS AND THIS REPOSITORY DOES NOT.
   Five articles on the live site name SAE J30 R6 in their titles; data/ here
   holds one of them, so no page on this machine can show how those five share
   thirteen listings between them — and the first attempt at sharing them out
   (a hash of the slug) was reported as a spread on the strength of that one
   article, when on the live titles two of four landed on the 22 mm. These are
   those titles, with the slugs the shop gave them. Each is put through
   post_products() with the five as the articles it shares with, and through
   the article template itself, and the check holds what comes back to rules
   it works out from the raw catalogue above. */
$fixture = [
    ['slug' => 'oil-resistant-hose-sae-j30-r6-12-7mm-designed-for-fuel-and-oil-applications', 'date' => '2026-01-01',
     'title' => 'Oil-Resistant Hose (SAE J30 R6 – 12.7mm): Designed for Fuel and Oil Applications'],
    ['slug' => 'sae-j30-r6-or-r10-which-fuel-hose-goes-in-the-tank', 'date' => '2026-09-01',
     'title' => 'SAE J30 R6 vs R10: Which Fuel Hose Goes In the Tank'],
    ['slug' => 'sae-j30-r6-sizes-bore-guide', 'date' => '2026-09-08',
     'title' => 'SAE J30 R6 Sizes: Every Bore from 3.2 mm to 25 mm'],
    ['slug' => 'diesel-transfer-hose-tank-bowser-drum', 'date' => '2026-09-15',
     'title' => 'Diesel Transfer Hose: SAE J30 R6 or Oil Delivery Hose'],
    ['slug' => 'small-engine-motorcycle-fuel-line-replacement', 'date' => '2026-09-15',
     'title' => 'Small Engine Fuel Line: SAE J30 R6 or DIN 73379 B'],
];
foreach ($fixture as $at => $written) {
    $fixture[$at] += ['content' => '<p>SAE J30 R6 hose.</p>', 'excerpt' => 'SAE J30 R6 hose.', 'image' => ''];
}

/* The page as the template writes it. In a function of its own: the template
   is included, an include shares the scope it is included from, and it sets
   $shown, $post, $all and a dozen more. (And not called $page: set_page()
   keeps the page in a global of that name.) */
$renderArticle = function (array $post): string {
    ob_start();
    include ROOT_DIR . '/pages/post.php';
    return (string) ob_get_clean();
};

$out['fixture'] = [];
foreach ($fixture as $written) {
    $dealt  = post_products($written, 3, $fixture);
    $served = $renderArticle($written);
    $list   = preg_match('~<p[^>]*\bid="every-size"[^>]*>(.*?)</p>~s', $served, $found) ? $found[1] : '';
    preg_match_all('~href="([^"]+)"[^>]*>([^<]*)</a>~', $list, $anchors, PREG_SET_ORDER);

    $out['fixture'][(string) $written['slug']] = [
        'title' => (string) $written['title'],
        'date'  => (string) $written['date'],
        'shown' => array_map(fn($card) => (string) $card['slug'], $dealt),
        'sizes' => array_map(fn($a) => ['url' => html_entity_decode($a[1]), 'label' => html_entity_decode(trim($a[2]))], $anchors),
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
