<?php
/**
 * Bring across the combination each product arrives already showing.
 *
 * Every one of the 28 variable products on the live shop opens with a size
 * chosen and a price on screen. Here the customer met an unpriced page and
 * had to pick before the shop would tell them anything — a real difference
 * in how the catalogue reads, and the sort that costs orders quietly.
 *
 * Source: the _default_attributes postmeta of the 26.08.26 dump, exported to
 * D:\argflex\26.08.26\defaults.txt.
 *
 *   php .data/import_defaults.php --dry-run
 *   php .data/import_defaults.php
 *
 * One oddity survives on purpose. The ventilation hose's default names the
 * diameter "127m", which is the slug of a term displayed as "127mm" — a typo
 * in the live data. It is load-bearing: two variations store it, and it is a
 * live indexed URL. Correcting it would break the lookup to fix a string
 * nobody sees.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

const SOURCE = 'D:\argflex\26.08.26\defaults.txt';

$dry = in_array('--dry-run', $argv, true);

$raw = @file_get_contents(SOURCE);
if ($raw === false) { fwrite(STDERR, 'Cannot read ' . SOURCE . "\n"); exit(1); }

$rows = json_decode($raw, true);
if (!is_array($rows)) { fwrite(STDERR, "That file is not the defaults export.\n"); exit(1); }

/** WooCommerce stores these as serialised PHP keyed by taxonomy. */
$wanted = [];
foreach ($rows as $slug => $serialised) {
    $decoded = @unserialize((string) $serialised);
    if (!is_array($decoded)) continue;

    $byAxis = [];
    foreach ($decoded as $taxonomy => $value) {
        if ((string) $value === '') continue;
        $byAxis[$taxonomy === 'pa_inner-diameter' ? 'Inner Diameter' : 'Length'] = (string) $value;
    }
    if ($byAxis) $wanted[$slug] = $byAxis;
}

$products = all_products(true);
$set = 0;
$partial = [];
$unknown = [];

foreach ($products as $i => $p) {
    $want = $wanted[$p['slug']] ?? null;
    if (!$want || empty($p['variants'])) continue;

    // Keep only axes this product actually varies on, and only values that
    // exist. A default naming a term the product does not carry would leave
    // the selector in a state no variation matches.
    $axes = [];
    foreach ($p['attrs'] as $a) {
        if (empty($a['variation'])) continue;
        $name = (string) $a['name'];
        if (!isset($want[$name])) continue;
        $slugs = array_column((array) $a['terms'], 'slug');
        if (!in_array($want[$name], $slugs, true)) {
            $unknown[] = $p['slug'] . '  ' . $name . ' = ' . $want[$name];
            continue;
        }
        $axes[$name] = $want[$name];
    }

    $varying = count(array_filter($p['attrs'], fn($a) => !empty($a['variation'])));
    if (!$axes) continue;
    if (count($axes) < $varying) $partial[] = $p['slug'] . '  (' . implode(', ', array_keys($axes)) . ' only)';

    $products[$i]['default_attrs'] = $axes;
    $set++;
}

printf("%d of %d product(s) given the combination they open on\n", $set, count($wanted));

if ($partial) {
    printf("\n%d open with only part of the choice made:\n", count($partial));
    foreach ($partial as $m) echo "  $m\n";
}
if ($unknown) {
    printf("\n%d default(s) naming a term the product does not carry:\n", count($unknown));
    foreach ($unknown as $m) echo "  $m\n";
}

if ($dry) { echo "\nDry run — nothing written.\n"; exit(0); }

save_products($products);
echo "\ndata/products.php rewritten\n";
