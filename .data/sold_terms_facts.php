<?php
/**
 * Facts for check_sold_terms.py: which chosen-by terms no option carries.
 *
 *   php .data/sold_terms_facts.php
 *
 * Prints JSON: for every published product, the terms of each chosen-by
 * attribute as stored, as the public catalogue shows them, and the values its
 * options really carry. Reads only; writes nothing.
 */
require dirname(__DIR__) . '/inc/config.php';

$stored = [];
foreach (all_products(true) as $p) {
    if (($p['status'] ?? 'published') === 'published') $stored[$p['slug']] = $p;
}

$out = [];
foreach (all_products() as $p) {
    $raw  = $stored[$p['slug']];
    $axes = [];
    foreach ((array) $raw['attrs'] as $i => $attr) {
        if (empty($attr['variation'])) continue;
        $carried = [];
        $any     = false;
        foreach ((array) ($raw['variants'] ?? []) as $option) {
            $value = (string) ($option['attrs'][$attr['name']] ?? '');
            if ($value === '') { $any = true; continue; }
            /* As a slug, whichever way the option names it: the importer
               stored slugs, the admin stores names. Worked out here on its
               own, not by calling the function under test. */
            $as = $value;
            foreach ((array) $attr['terms'] as $t) {
                if (strcasecmp($value, (string) $t['slug']) === 0 || strcasecmp($value, (string) $t['name']) === 0) {
                    $as = (string) $t['slug'];
                    break;
                }
            }
            $carried[$as] = true;
        }
        $axes[] = [
            'name'    => $attr['name'],
            'stored'  => array_column((array) $attr['terms'], 'slug'),
            'shown'   => array_column((array) ($p['attrs'][$i]['terms'] ?? []), 'slug'),
            'names'   => array_column((array) ($p['attrs'][$i]['terms'] ?? []), 'name'),
            'carried' => array_map('strval', array_keys($carried)),
            'any'     => $any,
        ];
    }
    $out[] = [
        'slug'     => $p['slug'],
        'options'  => count((array) ($raw['variants'] ?? [])),
        'axes'     => $axes,
        'same_as_stored_otherwise' => array_diff_key($p, ['attrs' => 1]) == array_diff_key($raw, ['attrs' => 1]),
    ];
}

$listings = [];
foreach (['inner-diameter', 'length'] as $axis) {
    $a = find_attribute($axis);
    if (!$a) continue;
    foreach ((array) $a['terms'] as $t) {
        $listings[$axis . '/' . $t['slug']] = array_column(products_with_term($axis, $t['slug']), 'slug');
    }
}

$axisNames = [];
foreach (['inner-diameter', 'length'] as $axis) {
    $a = find_attribute($axis);
    if ($a) $axisNames[$axis] = $a['name'];
}

/* The rule on records made up for it, so that each exception is held by a
   case and not by whatever the catalogue happens to contain today. */
$terms  = fn(array $slugs) => array_map(fn($s) => ['name' => $s, 'slug' => $s], $slugs);
$option = fn(array $attrs) => ['key' => implode('|', $attrs), 'attrs' => $attrs, 'price' => 100];
$record = fn(array $attrs, array $options) => ['slug' => 'x', 'attrs' => $attrs, 'variants' => $options];
$kept   = fn(array $p) => array_map(fn($a) => array_column($a['terms'], 'slug'), product_sold_terms($p)['attrs']);

$length = fn(array $slugs, bool $chosen = true) => ['name' => 'Length', 'variation' => $chosen, 'terms' => $terms($slugs)];
$bore   = fn(array $slugs, bool $chosen = true) => ['name' => 'Inner Diameter', 'variation' => $chosen, 'terms' => $terms($slugs)];

$cases = [
    ['name' => 'an orphan length goes',
     'got'  => $kept($record([$bore(['16mm', '19mm']), $length(['1m', '20m', '50m'])], [
                   $option(['Inner Diameter' => '16mm', 'Length' => '1m']),
                   $option(['Inner Diameter' => '19mm', 'Length' => '50m'])])),
     'want' => [['16mm', '19mm'], ['1m', '50m']]],
    ['name' => 'a fixed specification row is left alone',
     'got'  => $kept($record([$bore(['16mm', '19mm'], false), $length(['1m', '50m'])], [
                   $option(['Length' => '1m']), $option(['Length' => '50m'])])),
     'want' => [['16mm', '19mm'], ['1m', '50m']]],
    ['name' => 'a product with no options is left alone',
     'got'  => $kept($record([$length(['1m', '20m'])], [])),
     'want' => [['1m', '20m']]],
    ['name' => 'an option that says "any" keeps every term',
     'got'  => $kept($record([$bore(['16mm', '19mm']), $length(['1m', '20m'])], [
                   $option(['Inner Diameter' => '16mm', 'Length' => '1m']),
                   $option(['Inner Diameter' => '19mm', 'Length' => ''])])),
     'want' => [['16mm', '19mm'], ['1m', '20m']]],
    ['name' => 'an option with the axis missing keeps every term',
     'got'  => $kept($record([$bore(['16mm']), $length(['1m', '20m'])], [
                   $option(['Inner Diameter' => '16mm'])])),
     'want' => [['16mm'], ['1m', '20m']]],
    ['name' => 'an axis none of whose terms is carried is left alone',
     'got'  => $kept($record([$length(['1m', '20m'])], [$option(['Length' => '1-m'])])),
     'want' => [['1m', '20m']]],
    ['name' => 'an option added in the admin holds the term NAME, and its term stays',
     'got'  => $kept($record([['name' => 'Length', 'variation' => true, 'terms' => [
                   ['name' => '0.5m', 'slug' => '0-5m'], ['name' => '1m', 'slug' => '1m'],
                   ['name' => '20m', 'slug' => '20m']]]], [
                   $option(['Length' => '0.5m']), $option(['Length' => '1M'])])),
     'want' => [['0-5m', '1m']]],
    ['name' => 'the order of the terms is kept and the list is renumbered',
     'got'  => $kept($record([$length(['50m', '20m', '1m'])], [
                   $option(['Length' => '1m']), $option(['Length' => '50m'])])),
     'want' => [['50m', '1m']]],
];

echo json_encode(['products' => $out, 'listings' => $listings, 'axis_names' => $axisNames, 'cases' => $cases], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
