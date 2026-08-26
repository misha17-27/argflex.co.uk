<?php
/**
 * Bring the facts the shipping rules need onto each variant.
 *
 * The catalogue was imported with prices and labels but nothing else, so a
 * variant carries key, attrs, label, price and sale. The live shop decides
 * carriage from a variation's *weight*, and refuses to sell two of them, so
 * without those fields no rule here can behave the way the live one does.
 *
 * Source: D:\argflex\26.08.26\variations.json, extracted from the 26.08.26
 * database dump. Matching is by the same key the site already uses — the
 * diameter and length slugs — never by label, which is display text and
 * changes.
 *
 *   php .data/import_variation_facts.php --dry-run
 *   php .data/import_variation_facts.php
 *
 * Weight is the metre count, not kilograms: the shop tags every length with
 * its own number. Two deliberate oddities come across untouched, because the
 * live rules are written around them — every 25 m coil is tagged 24, and the
 * six TERMORESIST variations carry no weight at all and count as 0.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

const SOURCE = 'D:\argflex\26.08.26\variations.json';

$dry = in_array('--dry-run', $argv, true);

if (!is_file(SOURCE)) {
    fwrite(STDERR, "Cannot read " . SOURCE . "\n");
    exit(1);
}

$rows = json_decode((string) file_get_contents(SOURCE), true);
if (!is_array($rows)) {
    fwrite(STDERR, "That file is not the variation export.\n");
    exit(1);
}

/* Two variations of the oxygen hose carry a shipping class of their own.
   Nothing prices on it today — every class cost on every rate is blank — but
   it is real data and the archive of past orders refers to it. */
const SHIPPING_CLASS = [460 => '1m', 461 => '5m'];

/* Index the export by the key this site uses. A product whose diameter is
   not a variation axis keys on length alone. */
$facts = [];
foreach ($rows as $r) {
    $dia    = (string) ($r['attribute_pa_inner-diameter'] ?? '');
    $len    = (string) ($r['attribute_pa_length'] ?? '');
    $key    = $dia !== '' ? $dia . '|' . $len : $len;
    $parent = (string) ($r['parent_id'] ?? '');

    $facts[$parent . '#' . $key] = [
        'id'           => (int) $r['variation_id'],
        'weight'       => $r['_weight'] === null || $r['_weight'] === ''
                          ? 0 : (int) $r['_weight'],
        'has_weight'   => !($r['_weight'] === null || $r['_weight'] === ''),
        'stock'        => (string) ($r['_stock_status'] ?? 'instock'),
        'manage_stock' => ($r['_manage_stock'] ?? 'no') === 'yes',
        'stock_qty'    => $r['_stock'] === null || $r['_stock'] === ''
                          ? 0 : (int) $r['_stock'],
        'class'        => SHIPPING_CLASS[(int) $r['variation_id']] ?? '',
    ];
}

$products = all_products(true);
$matched  = 0;
$missed   = [];
$noWeight = [];
$oos      = [];
$capped   = [];

foreach ($products as $i => $p) {
    if (empty($p['variants'])) continue;

    foreach ($p['variants'] as $j => $v) {
        $found = $facts[$p['id'] . '#' . $v['key']] ?? null;
        if (!$found) { $missed[] = $p['slug'] . '  ' . $v['key']; continue; }

        $products[$i]['variants'][$j]['id']           = $found['id'];
        $products[$i]['variants'][$j]['weight']       = $found['weight'];
        $products[$i]['variants'][$j]['stock']        = $found['stock'];
        $products[$i]['variants'][$j]['manage_stock'] = $found['manage_stock'];
        $products[$i]['variants'][$j]['stock_qty']    = $found['stock_qty'];
        $products[$i]['variants'][$j]['shipping_class'] = $found['class'];

        $matched++;
        if (!$found['has_weight'])            $noWeight[] = $p['slug'] . '  ' . $v['key'];
        if ($found['stock'] !== 'instock')    $oos[]      = $p['slug'] . '  ' . $v['key'];
        if ($found['manage_stock'])           $capped[]   = $p['slug'] . '  ' . $v['key']
                                                            . '  ceiling ' . $found['stock_qty'];
    }
}

printf("%d of %d variant(s) matched\n", $matched, count($facts));

if ($missed) {
    printf("\n%d variant(s) the export does not cover:\n", count($missed));
    foreach ($missed as $m) echo "  $m\n";
}
printf("\n%d with no weight of their own (they count as 0, as they do live):\n", count($noWeight));
foreach ($noWeight as $m) echo "  $m\n";
printf("\n%d out of stock:\n", count($oos));
foreach ($oos as $m) echo "  $m\n";
printf("\n%d with a stock ceiling:\n", count($capped));
foreach ($capped as $m) echo "  $m\n";

if ($dry) {
    echo "\nDry run — nothing written.\n";
    exit($missed ? 1 : 0);
}

if ($missed) {
    fwrite(STDERR, "\nRefusing to write while any variant is unmatched.\n");
    exit(1);
}

save_products($products);
echo "\ndata/products.php rewritten\n";
