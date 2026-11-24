<?php
/**
 * The catalogue as check_product_pages.py needs it, as JSON.
 *
 *     php .data/product_facts.php
 *
 * Deliberately RAW, like attribute_facts.php beside it: each product's
 * options with what they cost today, the name of the length each one is, and
 * the stock flag as stored. The check works out for itself, in Python, what
 * the headline and every Offer ought to say, and only then reads the pages.
 * Nothing here calls product_offers_schema() or price_headline() for a real
 * product — that would be the code agreeing with itself.
 *
 * `cases` is the exception, and it is labelled as one. Three situations the
 * local catalogue does not contain today (a product with every length sold
 * out, a priced product with no options that is sold out, options that name
 * no length) are built in memory from a real product and run through the
 * builders, so the check can hold their OUTPUT to the same rules. The live
 * catalogue has the first of them on its busiest page.
 */
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';

$out = [
    'site'     => SITE_URL,
    'currency' => (string) setting('currency'),
    'tax_on'   => tax_rate() > 0,
    'suffix'   => price_suffix(),
    'products' => [],
    'money'    => [],
    'cases'    => [],
];

/* One product as the check reads it. A closure because the situations built
   in memory further down are handed over in the same shape, so the check can
   work out what THEIR headline should say with the code it uses for a page. */
$facts = function (array $product) use (&$out): array {
    /* The length attribute, as stored: its name, whether it is a choice, and
       what each of its terms is called. */
    $lengthName   = '';
    $lengthVaries = false;
    $lengthTerms  = [];
    foreach ((array) $product['attrs'] as $attr) {
        if (stripos((string) $attr['name'], 'length') === false) continue;
        $lengthName   = (string) $attr['name'];
        $lengthVaries = !empty($attr['variation']);
        foreach ((array) $attr['terms'] as $lengthTerm) {
            $lengthTerms[(string) $lengthTerm['slug']] = (string) $lengthTerm['name'];
        }
        break;
    }

    $options = [];
    foreach ((array) ($product['variants'] ?? []) as $option) {
        $lengthSlug = (string) ($option['attrs'][$lengthName] ?? '');
        if ($lengthSlug === '' && !$lengthVaries && count($lengthTerms) === 1) {
            $lengthSlug = (string) array_key_first($lengthTerms);
        }
        $options[] = [
            'key'    => (string) $option['key'],
            'label'  => (string) $option['label'],
            'pence'  => variant_price($option, $product),
            'length' => $lengthTerms[$lengthSlug] ?? '',
            // as stored: the option's own flag, else the product's
            'flag'   => (string) ($option['stock'] ?? $product['stock'] ?? 'instock'),
            // the shop's own answer, for options that count their stock
            'state'  => stock_state(with_variant($product, $option))['state'],
        ];
        $out['money'][(string) variant_price($option, $product)] = money(variant_price($option, $product));
    }

    $out['money'][(string) effective_min($product)] = money(effective_min($product));
    return [
        'url'     => product_url($product),
        'name'    => (string) $product['name'],
        'pence'   => empty($product['variants']) ? effective_min($product) : 0,
        'flag'    => (string) ($product['stock'] ?? 'instock'),
        'state'   => stock_state($product)['state'],
        'options' => $options,
    ];
};

foreach (all_products() as $product) {
    $out['products'][(string) $product['slug']] = $facts($product);
}

/* ---- situations the local catalogue does not hold, built in memory ---- */

$soldOut = function (array $product): array {
    $product['stock'] = 'outofstock';
    foreach ($product['variants'] as $n => $option) {
        $product['variants'][$n]['stock'] = 'outofstock';
    }
    return $product;
};
$render = fn(array $product): array => [
    'name'     => (string) $product['name'],
    'offers'   => product_offers_schema($product),
    'headline' => price_headline($product),
    'facts'    => $facts($product),
];

/* A HEADLINE PRICE NOBODY CAN PAY. "50m length from £50.00" stood over a hose
   whose one £50.00 coil was sold out. One product page here shows that today
   and the day that coil is back in stock none will, so the two halves of it
   are built in memory as well: the cheapest option of all sold out, and the
   cheapest option at the longest length sold out. Taken from a product sold
   in several bores and several lengths, every one stated and all in stock. */
foreach (all_products() as $product) {
    $rows = product_offer_rows($product);
    if (count($rows) < 4 || count($rows) !== count((array) $product['variants'])) continue;
    $lengths = array_values(array_unique(array_map(fn(array $row): float => (float) $row['metres'], $rows)));
    if (in_array(0.0, $lengths, true) || count($lengths) < 2 || count($lengths) === count($rows)) continue;
    if (array_filter($rows, fn($row) => $row['availability'] !== 'https://schema.org/InStock')) continue;

    $longest = max($lengths);
    $first   = ['cheapest' => null, 'longest' => null];
    foreach ($rows as $n => $row) {
        if ($first['cheapest'] === null || $row['pence'] < $rows[$first['cheapest']]['pence']) {
            $first['cheapest'] = $n;
        }
        if ((float) $row['metres'] === $longest
            && ($first['longest'] === null || $row['pence'] < $rows[$first['longest']]['pence'])) {
            $first['longest'] = $n;
        }
    }
    foreach (['cheapest' => 'cheapest_option_sold_out', 'longest' => 'cheapest_long_length_sold_out'] as $which => $case) {
        $changed = $product;
        $changed['variants'][$first[$which]]['stock'] = 'outofstock';
        $out['cases'][$case] = $render($changed);
    }
    break;
}

foreach (all_products() as $product) {
    if (!isset($out['cases']['every_option_sold_out'])
        && count((array) $product['variants']) > 1 && (int) $product['price_min'] > 0) {
        $out['cases']['every_option_sold_out'] = $render($soldOut($product));

        $noLength = $product;
        $noLength['attrs'] = array_values(array_filter((array) $product['attrs'],
            fn($attr) => stripos((string) $attr['name'], 'length') === false));
        $out['cases']['options_without_a_length'] = $render($noLength);
    }
    if (!isset($out['cases']['no_options_sold_out'])
        && empty($product['variants']) && (int) $product['price_min'] > 0) {
        $out['cases']['no_options_sold_out'] = $render($soldOut($product));
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
