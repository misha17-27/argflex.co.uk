<?php
/**
 * The catalogue as check_attribute_pages.py needs it, as JSON.
 *
 *     php .data/attribute_facts.php
 *
 * Deliberately RAW: which products carry each size, what every option costs
 * today, what each category lists. The check works the "from" price and the
 * indexable/noindex rule out again for itself from these, in Python, and only
 * then compares with what the pages say. The site's own answers
 * (attribute_term_indexable, the lone-product redirect) are sent alongside so
 * a disagreement between the two workings is itself a failure. If this file
 * called attribute_term_from_price() for the listings the pages show, the
 * check would be the code agreeing with itself. It is called for `cases`
 * only: two listings with sold-out options that no page here holds, sent
 * with the changed products so the check works out its own answer for them.
 */
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';

$out = ['terms' => [], 'categories' => [], 'products' => [], 'posts' => [],
        'lengths' => [], 'length_name' => 'Length', 'minimum' => ATTRIBUTE_LISTING_MIN_PRODUCTS];

foreach (all_attributes() as $axis) {
    if ($axis['slug'] === 'length') {
        $out['length_name'] = (string) $axis['name'];
        foreach ((array) $axis['terms'] as $axisTerm) {
            $out['lengths'][(string) $axisTerm['slug']] = (string) $axisTerm['name'];
        }
    }
    foreach ((array) $axis['terms'] as $axisTerm) {
        $lone = attribute_term_lone_product((string) $axis['slug'], (string) $axisTerm['slug']);
        $out['terms'][] = [
            'path'      => attribute_term_url((string) $axis['slug'], (string) $axisTerm['slug']),
            'axis'      => (string) $axis['name'],
            'slug'      => (string) $axisTerm['slug'],
            'name'      => (string) $axisTerm['name'],
            'lone'      => $lone ? product_url($lone) : '',
            'indexable' => attribute_term_indexable((string) $axis['slug'], (string) $axisTerm['slug']),
            'products'  => array_map(fn($product) => (string) $product['slug'],
                                     products_with_term((string) $axis['slug'], (string) $axisTerm['slug'])),
        ];
    }
}

foreach (all_categories() as $category) {
    $out['categories'][(string) $category['slug']] = array_map(
        fn($product) => (string) $product['slug'],
        products_in_category((string) $category['slug']));
}

$facts = function (array $product): array {
    $attrs = [];
    foreach ((array) $product['attrs'] as $attr) {
        $attrs[(string) $attr['name']] = !empty($attr['variation']);
    }
    $options = [];
    foreach ((array) ($product['variants'] ?? []) as $option) {
        $options[] = ['attrs' => (array) ($option['attrs'] ?? []),
                      'pence' => variant_price($option, $product),
                      // can it be bought: the stored flag, else the shop's answer
                      'flag'  => (string) ($option['stock'] ?? $product['stock'] ?? 'instock'),
                      'state' => stock_state(with_variant($product, $option))['state']];
    }
    return ['url' => product_url($product), 'varies' => (object) $attrs, 'options' => $options];
};

foreach (all_products() as $product) {
    $out['products'][(string) $product['slug']] = $facts($product);
}

/* A "FROM" PRICE NOBODY CAN PAY, built in memory because no listing here has
   one: the cheapest option of a size is sold out and the next is not. The
   first indexable listing whose cheapest option is alone at its price, with
   that option's stock set to out; then the same listing with EVERY option
   sold out, where the price has nothing better to fall back to. The check is
   handed the changed products in the same shape as the real ones, and what
   attribute_term_from_price() said of them — the one place it is called here,
   on a catalogue the pages do not hold. */
$out['cases'] = [];
foreach ($out['terms'] as $listing) {
    if (!$listing['indexable']) continue;
    [, $axisSlug] = explode('/', (string) $listing['path']);
    $listed = products_with_term($axisSlug, (string) $listing['slug']);
    $honest = attribute_term_from_price($axisSlug, (string) $listing['slug'], $listed);
    if (!$honest) continue;

    $changed = $listed;
    $allGone = $listed;
    $struck  = 0;
    foreach ($listed as $at => $product) {
        $varies = !empty(((array) $facts($product)['varies'])[$listing['axis']]);
        foreach ((array) ($product['variants'] ?? []) as $n => $option) {
            if ($varies && (string) ($option['attrs'][$listing['axis']] ?? '') !== $listing['slug']) continue;
            $allGone[$at]['variants'][$n]['stock'] = 'outofstock';
            if (variant_price($option, $product) === (int) $honest['pence']) {
                $changed[$at]['variants'][$n]['stock'] = 'outofstock';
                $struck++;
            }
        }
    }
    $after = attribute_term_from_price($axisSlug, (string) $listing['slug'], $changed);
    // something dearer must be left to buy, or the case shows nothing
    if ($struck < 1 || !$after || (int) $after['pence'] <= (int) $honest['pence']) continue;

    foreach (['cheapest_sold_out' => $changed, 'all_sold_out' => $allGone] as $case => $products) {
        $said = attribute_term_from_price($axisSlug, (string) $listing['slug'], $products);
        $out['cases'][$case] = [
            'term'     => ['axis' => $listing['axis'], 'slug' => $listing['slug'], 'path' => $listing['path']],
            'products' => array_map($facts, array_values($products)),
            'said'     => $said ? ['pence' => (int) $said['pence'], 'metres' => (float) $said['metres']] : null,
        ];
    }
    break;
}

foreach (all_posts() as $post) {
    $out['posts'][] = post_url($post);
}

/* How this shop prints each option's price, so the check need not guess the
   currency settings: it works out the pence and looks the wording up here. */
$out['money'] = [];
foreach ($out['products'] as $row) {
    foreach ($row['options'] as $option) {
        $out['money'][(string) $option['pence']] = money((int) $option['pence']);
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
