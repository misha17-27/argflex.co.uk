<?php
/**
 * Write the delivery prices that differ from the shop's own onto the models
 * that charge them.
 *
 * These came from the owner's own figures. Everything not named here uses
 * the common table in data/shipping.php, which is most of the catalogue.
 *
 * Run again at any time: it sets exactly these and leaves the rest alone.
 *
 *   php .data/set_model_delivery.php --dry-run
 *   php .data/set_model_delivery.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$dry = in_array('--dry-run', $argv, true);

/* Rate ids: 11/14 are up to 5 m, 17/18 are 5 to 10, 12/15 are 10 to 25,
   13/16 are 25 to 50 — the faster of each pair first. */
const ON_OPTIONS = [
    // The small-bore PVC tubes are under the common price for the first band.
    ['product'   => 'pvc-tube-for-petroleum-products-2',
     'diameters' => ['3mm', '4mm', '5mm', '6mm', '7mm', '8mm', '10mm'],
     'prices'    => [11 => 389, 14 => 320]],

    ['product'   => 'pvc-tube-for-petroleum-products',
     'diameters' => ['5mm', '6mm', '8mm', '10mm', '12mm', '14mm'],
     'prices'    => [11 => 389, 14 => 320]],
];

const ON_PRODUCTS = [
    // Car heater hose: its own price from ten metres up, at any bore.
    ['product' => 'car-heater-hose-125c-sae-j20-r3',
     'prices'  => [12 => 824, 15 => 772, 13 => 2428, 16 => 1936]],
];

$products = all_products(true);
$options  = 0;
$whole    = 0;

foreach (ON_PRODUCTS as $rule) {
    foreach ($products as $i => $p) {
        if ($p['slug'] !== $rule['product']) continue;
        $products[$i]['delivery'] = $rule['prices'];
        printf("  %-38s the product itself, %d band price(s)\n", $p['slug'], count($rule['prices']));
        $whole++;
        break;
    }
}

foreach (ON_OPTIONS as $rule) {
    foreach ($products as $i => $p) {
        if ($p['slug'] !== $rule['product']) continue;
        $hits = 0;
        foreach ((array) $p['variants'] as $j => $v) {
            $bore = (string) (($v['attrs'] ?? [])['Inner Diameter'] ?? '');
            if (!in_array($bore, $rule['diameters'], true)) continue;
            $products[$i]['variants'][$j]['delivery'] = $rule['prices'];
            $hits++;
        }
        printf("  %-38s %d option(s), %d band price(s)\n", $p['slug'], $hits, count($rule['prices']));
        $options += $hits;
        break;
    }
}

printf("\n%d product(s) and %d option(s) carry a price of their own\n", $whole, $options);

if ($dry) { echo "\nDry run — nothing written.\n"; exit(0); }

save_products($products);
echo "\ndata/products.php rewritten\n";
