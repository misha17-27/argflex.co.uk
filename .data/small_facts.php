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

/* Addresses that redirect for a reason of their own, for the check of the
   final slash: each must still take ONE hop when asked for without it. The
   Settings table as stored, and every size that only one product carries. */
$out['redirects'] = (array) setting('redirects');
$out['lone_sizes'] = [];
foreach (all_attributes() as $axis) {
    foreach ((array) ($axis['terms'] ?? []) as $axisTerm) {
        $lone = attribute_term_lone_product((string) $axis['slug'], (string) $axisTerm['slug']);
        if ($lone) {
            $out['lone_sizes'][attribute_term_url((string) $axis['slug'], (string) $axisTerm['slug'])]
                = product_url($lone);
        }
    }
}

/* RAW: the two register numbers and the address as Settings -> General holds
   them. Both numbers are empty in this copy, which is why `cases` below asks
   the builder what a filled one would add. */
$out['company_number'] = (string) setting('company_number');
$out['vat_number']     = (string) setting('vat_number');
$out['address']        = (string) setting('address');

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
    /* The note beside a price, as a shopkeeper might have stored it. The
       repository's own stored copy is one of these, the live shop's is
       another, and no page here can show more than one. */
    'price_note_wording' => array_combine(
        $notes = ['Exl vat', 'Exl. VAT', 'excl vat', 'EXCL. VAT', 'ex vat', 'Ex. VAT', 'exc VAT',
                  'Excluding VAT', 'exclusive of VAT', ' exl  vat ', 'ex VAT', 'exclude Tax',
                  '+ VAT', 'incl. VAT', 'excl. VAT and delivery', 'trade price', ''],
        array_map('price_note_wording', $notes)
    ),
    /* A stored description whose range has one end, and three that must pass
       through as they are. */
    'single_length_wording' => array_combine(
        $lines = ['From £0.88/m ex VAT, cut 1-1 m. -15°C to +60°C.',
                  'From £37.95 ex VAT, 25-25 m coils.',
                  'From £1.10/m ex VAT, cut 1-50 m. -30°C to +70°C.',
                  'From £37.95 ex VAT, 25-50 m coils.',
                  'Bore 8–12 mm. To SAE J30 R6.'],
        array_map('tidy_description', $lines)
    ),
    /* What the Organization block gains from the two numbers. Made-up ones,
       handed to the builder: nothing is written to data/ or storage/. */
    'organization_registration' => [
        'none'    => organization_registration('', ''),
        'blank'   => organization_registration("  ", "\t "),
        'company' => organization_registration(' 01234567 ', ''),
        'vat'     => organization_registration('', ' GB123456789 '),
        'both'    => organization_registration('01234567', 'GB123456789'),
    ],
    /* The whole block, not only what the numbers add: `as_set` is what every
       page should carry (the check compares each page's block with it), and
       `both` is the same builder handed made-up numbers, so the check can
       see them INSIDE the Organization array. Looking for the call in the
       source of inc/config.php passed just as well when its result was
       thrown away. */
    'organization_block' => [
        'as_set' => organization_block((string) setting('company_number'), (string) setting('vat_number')),
        'both'   => organization_block('01234567', 'GB123456789'),
    ],
    'product_image_alt' => [
        'card'  => product_image_alt(['name' => 'Acetylene hose'], 'assets/img/products/acetylene-hose-spec.svg'),
        'photo' => product_image_alt(['name' => 'Acetylene hose'], 'assets/img/products/acetylene-hose.webp'),
    ],
    'product_description_html' => [
        'empty'   => product_description_html(['name' => 'Fuel "R6" hose',
            'desc' => '<p><img src="/assets/img/content/screenshot-2022-06-21-at-12-25-12-1.webp" alt="" width="10" height="5" /></p>']),
        'missing' => product_description_html(['name' => 'Fuel hose',
            'desc' => '<p><img src="/assets/img/content/asadd-1.webp" width="10"></p>']),
        'kept'    => product_description_html(['name' => 'Fuel hose',
            'desc' => '<p><img src="/assets/img/content/screenshot-1.webp" alt="Acetylene hose" /></p>']),
        'none'    => product_description_html(['name' => 'Fuel hose', 'desc' => '<p>No picture.</p>']),
        /* Tags as an owner might type them on the live catalogue; none is in
           this copy's data/. An alt without quotes is still an alt somebody
           wrote; alt="" inside a title is the title's text, not an
           attribute; and "spec" is a word of a file name, not four letters
           of "inspection" or "respect". */
        'unquoted'      => product_description_html(['name' => 'Hose X', 'desc' => '<p><img src=a.jpg alt=Coupling></p>']),
        'in_title'      => product_description_html(['name' => 'Hose X', 'desc' => '<img src="a.png" title=\'x alt="" y\'>']),
        'gt_in_value'   => product_description_html(['name' => 'Hose X', 'desc' => '<img src="a.png" title="6mm -> 8mm" alt="Owner">']),
        'gt_then_empty' => product_description_html(['name' => 'Hose X', 'desc' => '<img src="a.png" title="a > b" alt="">']),
        'bare'          => product_description_html(['name' => 'Hose X', 'desc' => '<img src="a.png" alt>']),
        'single_quotes' => product_description_html(['name' => 'Hose X', 'desc' => "<img alt='' src='b.png'>"]),
        'inspection'    => product_description_html(['name' => 'Hose X', 'desc' => '<img src="/x/inspection-photo.jpg" alt="">']),
        'respect'       => product_description_html(['name' => 'Hose X', 'desc' => '<img src="respect.png" alt="">']),
        'special'       => product_description_html(['name' => 'Hose X', 'desc' => '<img src="special-offer.webp">']),
        'spec_word'     => product_description_html(['name' => 'Hose X', 'desc' => '<img src="/x/hose-spec.png" alt="">']),
        'specs_word'    => product_description_html(['name' => 'Hose X', 'desc' => '<img src="specs_table.jpg" alt="">']),
        'unquoted_shot' => product_description_html(['name' => 'Hose X', 'desc' => '<img src=Screenshot-1.png>']),
    ],
];

/* RAW: what Settings holds, before anything corrects the spelling. */
$out['price_suffix_stored'] = (string) setting('price_suffix');
$out['price_suffix']        = price_suffix();
$out['tax_label_stored']    = (string) setting('tax_label');
/* The country as stored too. With the label it is all tax_word() goes on, so
   the check can work out for itself what a sentence should call the tax —
   and not ask tax_word(), which is the function it is there to hold. */
$out['store_country_stored'] = (string) setting('store_country');
$out['tax_word']            = tax_word();
$out['tax_enabled']         = tax_enabled();

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
