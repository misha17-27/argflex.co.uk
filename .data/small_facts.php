<?php
/**
 * What check_small_defects.py needs to know, as JSON.
 *
 *     php .data/small_facts.php
 *
 * RAW, like the other *_facts.php files beside it: the addresses the
 * catalogue stores, the social slots exactly as Settings holds them, the
 * ticker words before anything escapes them, and which pages have copy saved.
 * The check works out in Python what a page should therefore say.
 *
 * `cases` is the exception, and a deliberate one. Four small functions decide
 * things the pages on this machine cannot show — the repository's data has
 * its og:image values repaired and its social slots hold placeholders, so no
 * local page carries a dead picture or a real profile. Each is called here
 * with fixed inputs and the check holds the answers it expects.
 */
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require_once ROOT_DIR . '/inc/commerce.php';

$out = ['products' => [], 'categories' => [], 'posts' => [], 'social' => [], 'cases' => []];

foreach (all_products() as $product) {
    $out['products'][] = [
        'url'      => product_url($product),
        'modified' => (string) ($product['modified'] ?? ''),
    ];
}
foreach (all_categories() as $category) {
    $out['categories'][] = [
        'url'    => category_url($category),
        'slug'   => (string) $category['slug'],
        'parent' => (string) $category['parent'],
    ];
}
foreach (all_posts() as $article) {
    $out['posts'][] = ['url' => post_url($article), 'date' => (string) ($article['date'] ?? '')];
}

for ($slot = 1; $slot <= 4; $slot++) {
    $out['social'][] = [
        'name' => (string) setting('soc' . $slot . '_name'),
        'url'  => (string) setting('soc' . $slot . '_url'),
    ];
}

$pagesFile = ROOT_DIR . '/data/pages.php';
$saved     = is_file($pagesFile) ? (array) require $pagesFile : [];
$out['saved_pages'] = array_keys(array_filter($saved));
$out['ticker_saved'] = (string) ($saved['/']['ticker'] ?? '');
$out['hero_tags_saved'] = (string) ($saved['/']['hero_tags'] ?? '');

$size = fn(string $file) => array_slice((array) @getimagesize(ROOT_DIR . '/' . $file), 0, 2);
$out['logo']      = $size('assets/img/site/logo.png');
$out['logo_chat'] = $size('assets/img/site/logo-chat.webp');

$out['cases'] = [
    'own_image_url' => [
        'dead-wordpress' => own_image_url('https://argflex.co.uk/wp-content/uploads/2023/12/dsc07924-edit.jpg'),
        'absolute'       => own_image_url('https://argflex.co.uk/assets/img/site/hero-1.webp'),
        'www'            => own_image_url('https://www.argflex.co.uk/assets/img/site/hero-1.webp'),
        'versioned'      => own_image_url('https://argflex.co.uk/assets/img/site/hero-1.webp?v=abc123'),
        'relative'       => own_image_url('assets/img/site/hero-1.webp'),
        'rooted'         => own_image_url('/assets/img/site/hero-1.webp'),
        'foreign'        => own_image_url('https://example.com/assets/img/site/hero-1.webp'),
        'climbing'       => own_image_url('/assets/../index.php'),
        'directory'      => own_image_url('/assets/img/site/'),
        'blank'          => own_image_url(''),
        'null'           => own_image_url(null),
    ],
    'is_profile_url' => [
        'https://www.facebook.com/'             => is_profile_url('https://www.facebook.com/'),
        'https://www.instagram.com'             => is_profile_url('https://www.instagram.com'),
        'https://www.instagram.com//'           => is_profile_url('https://www.instagram.com//'),
        'https://www.facebook.com/example.page' => is_profile_url('https://www.facebook.com/example.page'),
        'https://www.facebook.com/profile.php?id=1' => is_profile_url('https://www.facebook.com/profile.php?id=1'),
        'https://example.com/?id=1'             => is_profile_url('https://example.com/?id=1'),
        'https://wa.me/440000000000'            => is_profile_url('https://wa.me/440000000000'),
        'facebook.com/example'                  => is_profile_url('facebook.com/example'),
        'javascript:alert(1)'                   => is_profile_url('javascript:alert(1)'),
        ''                                      => is_profile_url(''),
    ],
    'files_day' => [
        'missing' => files_day('no/such/file.php'),
        'robots'  => files_day('robots.txt'),
        'newest'  => files_day('robots.txt', 'index.php', 'no/such/file.php'),
    ],
    'page_lines' => [
        // nothing is saved under this path, so these are the fallbacks
        'default'       => page_lines('/no-such-page/', 'ticker', ['Fuel & oil', '<b>']),
        'blank-default' => page_lines('/no-such-page/', 'ticker', []),
    ],
];

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
