<?php
/**
 * Delivery, checked against the live shop's own behaviour.
 *
 * Every expectation here was derived from the 26.08.26 database dump and the
 * plugin sources, not from what the rules ought to say. Several of them look
 * wrong — 26 metres for £5.28, two identical coils charged differently — and
 * they are wrong, on the live site, today. They are asserted so that a change
 * to the rules is a decision somebody made rather than something that drifted.
 *
 *   php .data/test_shipping.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';

$failed = 0;
$ran    = 0;

function line(string $slug, string $key, int $qty = 1): array
{
    $p = find_product($slug);
    if (!$p) { fwrite(STDERR, "no such product: $slug\n"); exit(1); }
    $v = find_variant($p, $key);
    if (!$v && $p['variants']) { fwrite(STDERR, "no such option: $slug $key\n"); exit(1); }
    return ['slug' => $slug, 'option' => $key, 'qty' => $qty];
}

/** The rate ids a basket is offered, package by package. */
function offered(array $lines, string $country = 'GB'): array
{
    $out = [];
    foreach (shipping_packages(price_basket_lines($lines), $country) as $pkg) {
        $out[] = ['name'   => $pkg['name'],
                  'weight' => $pkg['weight'],
                  'ids'    => array_column($pkg['rates'], 'id')];
    }
    return $out;
}

function check(string $label, $got, $want): void
{
    global $failed, $ran;
    $ran++;
    $ok = $got === $want;
    if (!$ok) $failed++;
    printf("  %-62s %s\n", $label, $ok ? 'OK' : 'FAILED');
    if (!$ok) {
        echo "        wanted: " . json_encode($want) . "\n";
        echo "        got:    " . json_encode($got) . "\n";
    }
}

/* ------------------------------------------------- the bands, boundary by boundary */

echo "WEIGHT BANDS — the operators, one metre at a time\n";

// 1 m of acetylene hose weighs 1; the 5 m weighs 5, and so on.
check('W=1  under five metres',      offered([line('acetylene-hose', '8mm|1m')])[0]['ids'],  [11, 14]);
check('W=5  five exactly, inclusive', offered([line('acetylene-hose', '8mm|5m')])[0]['ids'], [11, 14]);
check('W=6  five 1m lengths plus one', offered([line('acetylene-hose', '8mm|1m', 6)])[0]['ids'], [17, 18]);
check('W=9  nine metres, inclusive',  offered([line('acetylene-hose', '8mm|1m', 9)])[0]['ids'], [17, 18]);

// Ten falls between `lte 9` and `gt 10`, so nothing fires. Fault F2.
check('W=10 falls through every rule — all eight',
      offered([line('acetylene-hose', '8mm|10m')])[0]['ids'], [11, 17, 12, 13, 14, 18, 15, 16]);
check('W=10 and the first offered is the £4.20, not the £3.20',
      shipping_packages(price_basket_lines([line('acetylene-hose', '8mm|10m')]), 'GB')[0]['rates'][0]['cost'], 420);

check('W=11 inside the ten-to-25 band',  offered([line('acetylene-hose', '8mm|1m', 11)])[0]['ids'], [12, 15]);
check('W=23 still inside it',            offered([line('acetylene-hose', '8mm|1m', 23)])[0]['ids'], [12, 15]);
check('W=26 as one line is captured by the package instead',
      offered([line('acetylene-hose', '8mm|1m', 26)])[0]['ids'], [13, 16]);

// Three ten-metre lines: thirty metres, but no single line reaches 25, so the
// package takes nothing and the basket meets rule 29381 — which removes four
// rates where its siblings remove six, and leaves the 5-10m pair behind.
check('W=30 across three lines — rule 29381 leaves the 5-10m rates (F1)',
      offered([line('oxygen-hose-agoma', '6-3mm|10m'),
               line('oxygen-hose-agoma', '8mm|10m'),
               line('oxygen-hose-agoma', '10mm|10m')])[0]['ids'], [17, 13, 18, 16]);
check('  so thirty metres can be sent for £5.28',
      shipping_quote(price_basket_lines([line('oxygen-hose-agoma', '6-3mm|10m'),
                                         line('oxygen-hose-agoma', '8mm|10m'),
                                         line('oxygen-hose-agoma', '10mm|10m')]), 'GB',
                     [0 => 18])['cost'], 528);

/* ------------------------------------------------------------- weight is metres */

echo "\nWEIGHT IS METRES, NOT MASS\n";

check('a 25m coil is tagged 24, not 25',
      find_variant(find_product('pvc-tube-for-petroleum-products-2'), '3mm|25m')['weight'], 24);
check('the ventilation hose carries no weight and counts as nought',
      find_variant(find_product('pvc-ventilation-hose-termoresist'), '152mm|10m')['weight'], 0);
check('so a £77.88 ten-metre duct ships in the under-five band (F4)',
      offered([line('pvc-ventilation-hose-termoresist', '152mm|10m')])[0]['ids'], [11, 14]);

/* ----------------------------------------------------------- the split packages */

echo "\nTWO CONSIGNMENTS IN ONE BASKET\n";

// 25 m coil at £11.10 (weight 24, under the £24 line) plus a weightless duct.
$two = offered([line('pvc-tube-for-petroleum-products', '5mm|25m'),
                line('pvc-ventilation-hose-termoresist', '152mm|1m')]);
check('a cheap 25m coil beside a weightless line splits in two', count($two), 2);
check('  the first is the ten-to-25 package', $two[0]['name'] ?? '', '1-2 days(10-25m)');
check('  carrying only the coil',             $two[0]['ids'] ?? [], [12, 15]);
check('  the second is the leftovers',        $two[1]['name'] ?? '', 'Shipping');
check('  priced on its own weight of nought', $two[1]['ids'] ?? [], [11, 14]);

$q = shipping_quote(price_basket_lines([line('pvc-tube-for-petroleum-products', '5mm|25m'),
                                        line('pvc-ventilation-hose-termoresist', '152mm|1m')]), 'GB');
check('  and the two are charged together, not once', $q['cost'], 828 + 420);

// What it comes to if the customer picks the slower option in both.
$cheap = shipping_quote(price_basket_lines([line('pvc-tube-for-petroleum-products', '5mm|25m'),
                                            line('pvc-ventilation-hose-termoresist', '152mm|1m')]),
                        'GB', [0 => 15, 1 => 14]);
check('  the cheapest the two can be sent for', $cheap['cost'], 724 + 320);
check('  and one delivery row is shown, not two', $q['title'], 'Delivery');

// The same weight, the same money, but the price puts it the other side of 634.
check('an expensive 25m coil is NOT captured, and sees all eight (F3)',
      offered([line('nts-garden-hose', '12-5mm|25m')])[0]['ids'], [11, 17, 12, 13, 14, 18, 15, 16]);

/* ---------------------------------------------- one line heavy enough, or not */

echo "\nTHE PACKAGE TESTS EACH LINE, NOT THE BASKET\n";

check('five 5m lengths make one 25m line — captured',
      offered([line('oxygen-hose-agoma', '6-3mm|5m', 5)])[0]['name'], '1-2 days(25-50m)');
check('  and it is offered the 25-50m rates only',
      offered([line('oxygen-hose-agoma', '6-3mm|5m', 5)])[0]['ids'], [13, 16]);

$mixed = offered([line('oxygen-hose-agoma', '6-3mm|15m'), line('oxygen-hose-agoma', '6-3mm|10m')]);
check('15m plus 10m is the same 25 metres and the same money',
      lines_weight(price_basket_lines([line('oxygen-hose-agoma', '6-3mm|15m'),
                                       line('oxygen-hose-agoma', '6-3mm|10m')])), 25);
check('  but no single line reaches 25, so nothing is captured', count($mixed), 1);
check('  and it falls through every rule — all eight',
      $mixed[0]['ids'], [11, 17, 12, 13, 14, 18, 15, 16]);

/* ------------------------------------------------------------------ elsewhere */

echo "\nOUTSIDE THE UNITED KINGDOM\n";

check('Germany gets no package at all', offered([line('acetylene-hose', '8mm|1m')], 'DE'), []);
$abroad = shipping_quote(price_basket_lines([line('acetylene-hose', '8mm|1m')]), 'DE');
check('  so the basket is not deliverable', $abroad['deliverable'], false);
check('  and says why',                     $abroad['why'], 'We only deliver within the United Kingdom.');

/* --------------------------------------------------------------------- stock */

echo "\nSTOCK, BY VARIATION\n";

check('the out-of-stock 50m fuel hose is refused',
      price_basket_lines([line('fuel-hose-din-73379-b', '3-2mm|50m')]), []);
check('while the 1m of the same hose sells',
      count(price_basket_lines([line('fuel-hose-din-73379-b', '3-2mm|1m')])), 1);
check('and a ceiling of ten caps an order of eleven',
      price_basket_lines([line('fuel-hose-din-73379-b', '3-2mm|1m', 11)])[0]['qty'], 10);

/* ------------------------------------------------------------------ the money */

echo "\nWHAT THE CUSTOMER PAYS\n";

check('delivery carries no VAT', tax_on_shipping(), false);

echo "\n";
printf("%d checks, %s\n", $ran, $failed ? "$failed FAILED" : 'all passing');
exit($failed ? 1 : 0);
