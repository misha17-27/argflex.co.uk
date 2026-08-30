<?php
/**
 * Delivery, band by band.
 *
 * The rates, the rules and the packages all came from the 26.08.26 database
 * dump. Three faults in that configuration have since been corrected on the
 * owner's instruction — the bands had gaps at 10, 24 and 25 metres, one rule
 * removed four rates where its siblings removed six, and a package decided
 * partly by price, so two coils of the same length shipped differently
 * because of what they cost. See known_faults in data/shipping.php.
 *
 * What is asserted now is that every weight lands in exactly one band and is
 * offered exactly that band's two rates. The boundaries are checked one
 * metre at a time, because an inclusive operator written as a strict one
 * silently re-prices every order that lands on the edge.
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

// Ten used to fall between `lte 9` and `gt 10` and see all eight rates. The
// bands are closed now, so every weight lands in exactly one of them.
check('W=10 opens the ten-to-25 band',   offered([line('acetylene-hose', '8mm|10m')])[0]['ids'], [12, 15]);
check('W=11 inside it',                  offered([line('acetylene-hose', '8mm|1m', 11)])[0]['ids'], [12, 15]);
check('W=24, which is how a 25m coil is tagged',
      offered([line('acetylene-hose', '8mm|1m', 24)])[0]['ids'], [12, 15]);
check('W=25 closes it',                  offered([line('acetylene-hose', '8mm|1m', 25)])[0]['ids'], [12, 15]);
check('W=26 opens the last band',        offered([line('acetylene-hose', '8mm|1m', 26)])[0]['ids'], [13, 16]);

// Three ten-metre lines: thirty metres, and no single line reaches 25, so no
// package captures anything and the basket is decided by the rule alone.
// This used to leave the 5-10m pair behind and send thirty metres for £5.28.
check('W=30 across three lines — the 25-50m pair only',
      offered([line('oxygen-hose-agoma', '6-3mm|10m'),
               line('oxygen-hose-agoma', '8mm|10m'),
               line('oxygen-hose-agoma', '10mm|10m')])[0]['ids'], [13, 16]);
check('  so thirty metres costs at least £9.34',
      shipping_quote(price_basket_lines([line('oxygen-hose-agoma', '6-3mm|10m'),
                                         line('oxygen-hose-agoma', '8mm|10m'),
                                         line('oxygen-hose-agoma', '10mm|10m')]), 'GB',
                     [0 => 16])['cost'], 934);

// Every band shows exactly two rates and never all eight.
foreach ([1 => [11, 14], 5 => [11, 14], 6 => [17, 18], 9 => [17, 18],
          10 => [12, 15], 25 => [12, 15], 26 => [13, 16], 50 => [13, 16]] as $metres => $want) {
    check("  W={$metres} offers its own pair and nothing else",
          offered([line('acetylene-hose', '8mm|1m', $metres)])[0]['ids'], $want);
}

/* ------------------------------------------- models with their own delivery */

echo "\nMODELS THAT COST MORE TO SEND THAN THEIR LENGTH SUGGESTS\n";

/** What one basket is offered, as pence. */
function costs(array $rows, string $country = 'GB'): array
{
    $lines = array_map(fn($r) => line($r[0], $r[1], $r[2] ?? 1), $rows);
    $pkg   = shipping_packages(price_basket_lines($lines), $country);
    return $pkg ? array_column($pkg[0]['rates'], 'cost') : [];
}

// Most of the catalogue is on the common table and stays there.
check('the acetylene hose is on the common table', costs([['acetylene-hose', '8mm|1m']]), [592, 528]);
check('  and so is its fifty-metre coil',          costs([['acetylene-hose', '8mm|50m']]), [1168, 934]);

// Small-bore PVC tube is a little under it for the first band only.
check('small-bore PVC tube, up to five metres',
      costs([['pvc-tube-for-petroleum-products-2', '5mm|1m']]), [389, 320]);
check('  the oil and fuel tube matches it',
      costs([['pvc-tube-for-petroleum-products', '8mm|1m']]), [389, 320]);
check('  and its 25m coil is back on the common price',
      costs([['pvc-tube-for-petroleum-products-2', '5mm|25m']]), [1235, 1028]);

// The wider bores of the same product are dearer in every band, which is why
// the override has to key on the bore and not just the product.
// The default is now the wide tube's own table, so this needs no exception.
check('the same product at 16mm is the default',
      costs([['pvc-tube-for-petroleum-products-2', '16mm|1m']]), [592, 528]);
check('  and by the coil too',
      costs([['pvc-tube-for-petroleum-products-2', '16mm|25m']]), [1235, 1028]);

check('car heater hose, one metre',  costs([['car-heater-hose-125c-sae-j20-r3', '16mm|1m']]), [592, 528]);
check('  and its fifty-metre coil',  costs([['car-heater-hose-125c-sae-j20-r3', '16mm|50m']]), [2428, 1936]);
check('ventilation ducting',         costs([['pvc-ventilation-hose-termoresist', '152mm|1m']]), [592, 528]);

// A bulky hose cannot travel at a thin hose's rate because something small
// was boxed with it.
check('a mixed consignment pays the dearer of the two',
      costs([['pvc-tube-for-petroleum-products-2', '5mm|1m'],
             ['car-heater-hose-125c-sae-j20-r3', '16mm|1m']]), [592, 528]);
check('  and the cheap one alone still pays its own',
      costs([['pvc-tube-for-petroleum-products-2', '5mm|1m']]), [389, 320]);

/* ------------------------------------------------------------- weight is metres */

echo "\nWEIGHT IS METRES, NOT MASS\n";

check('a 25m coil is tagged 24, not 25',
      find_variant(find_product('pvc-tube-for-petroleum-products-2'), '3mm|25m')['weight'], 24);
// These six carried no weight at all and shipped in the under-5 m band.
check('the ventilation hose holds its metre count now',
      find_variant(find_product('pvc-ventilation-hose-termoresist'), '152mm|10m')['weight'], 10);
check('  so a ten-metre duct is charged for ten metres',
      costs([['pvc-ventilation-hose-termoresist', '152mm|10m']]), [1235, 1028]);

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
// The duct is on its own table, so the leftovers cost 592 rather than the
// common 420 — which is the point of the overrides.
check('  and the two are charged together, not once', $q['cost'], 1235 + 592);

// What it comes to if the customer picks the slower option in both.
$cheap = shipping_quote(price_basket_lines([line('pvc-tube-for-petroleum-products', '5mm|25m'),
                                            line('pvc-ventilation-hose-termoresist', '152mm|1m')]),
                        'GB', [0 => 15, 1 => 14]);
check('  the cheapest the two can be sent for', $cheap['cost'], 1028 + 528);
check('  and one delivery row is shown, not two', $q['title'], 'Delivery');

// Two coils of the same length now ship the same way. Package 634 used to
// decide by price as well, so this one at £37.95 fell outside it and saw all
// eight rates while an identical 25 m coil at £5.00 saw two.
check('an expensive 25m coil is in the same band as a cheap one',
      offered([line('nts-garden-hose', '12-5mm|25m')])[0]['ids'], [12, 15]);
check('  and so is the cheap one',
      offered([line('pvc-tube-for-petroleum-products-2', '3mm|25m')])[0]['ids'], [12, 15]);

/* ---------------------------------------------- one line heavy enough, or not */

echo "\nTHE PACKAGE TESTS EACH LINE, NOT THE BASKET\n";

check('five 5m lengths make one 25m line — captured',
      offered([line('oxygen-hose-agoma', '6-3mm|5m', 5)])[0]['name'], '1-2 days(10-25m)');
check('  and twenty-five metres is the top of the 10-25m band',
      offered([line('oxygen-hose-agoma', '6-3mm|5m', 5)])[0]['ids'], [12, 15]);

$mixed = offered([line('oxygen-hose-agoma', '6-3mm|15m'), line('oxygen-hose-agoma', '6-3mm|10m')]);
check('15m plus 10m is the same 25 metres and the same money',
      lines_weight(price_basket_lines([line('oxygen-hose-agoma', '6-3mm|15m'),
                                       line('oxygen-hose-agoma', '6-3mm|10m')])), 25);
check('  and however it is split, twenty-five metres is one band',
      $mixed[0]['ids'], [12, 15]);

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

/* -------------------------------------------------- changing what it charges */

/* The eight prices are editable from Settings -> Shipping, and the saver
   edits data/shipping.php IN PLACE so the file keeps its own documentation:
   where every figure came from in the dump, and the four faults corrected.
   That makes it a regex over source, which is exactly the kind of thing that
   works today and eats a bracket in six months. This writes to the real file
   and restores it byte for byte at the end. */

echo "\nCHANGING WHAT IT CHARGES\n";

require_once ROOT_DIR . '/inc/store.php';

$file   = ROOT_DIR . '/data/shipping.php';
$backup = (string) file_get_contents($file);
$before = shipping_config(true);
$byId   = fn(array $cfg, int $id) => current(array_filter($cfg['rates'], fn($r) => $r['id'] === $id)) ?: [];

check('a price and a title can be saved',
      save_shipping_rates([11 => ['cost' => 649, 'title' => '1-2 days(1-5m)'],
                           15 => ['cost' => 1099, 'title' => '3-4 days (10-25m) tracked']]), true);

$after = shipping_config(true);
check('  the price moved',             $byId($after, 11)['cost'] ?? 0, 649);
check('  the title moved',             $byId($after, 15)['title'] ?? '', '3-4 days (10-25m) tracked');
check('  a rate nobody touched did not',
      [$byId($after, 12)['cost'] ?? 0, $byId($after, 12)['title'] ?? ''],
      [$byId($before, 12)['cost'] ?? -1, $byId($before, 12)['title'] ?? '']);
check('  there are still eight',       count($after['rates']), 8);

// The rules and the packages name rates by id; losing one silently unprices a band.
foreach (['rules', 'packages', 'zone', 'default_package', 'tax_on_shipping'] as $key) {
    check("  {$key} survived untouched", $after[$key] ?? null, $before[$key] ?? false);
}

$now = (string) file_get_contents($file);
check('  the file kept its documentation',
      str_contains($now, 'Every 25 m coil is tagged 24')
   && str_contains($now, 'Taken from the 26.08.26 database dump'), true);

$was  = explode("\n", $backup);
$is   = explode("\n", $now);
$moved = 0;
foreach ($was as $i => $l) { if (($is[$i] ?? null) !== $l) $moved++; }
check('  and nothing else in it moved', [count($is), $moved], [count($was), 2]);

check('  a basket is quoted the new price',
      (int) shipping_quote(price_basket_lines([['slug' => 'acetylene-hose',
                                                'option' => '8mm|1m', 'qty' => 1]]), 'GB')['cost'], 649);

check('  an id that is not a rate is refused, not ignored',
      save_shipping_rates([999 => ['cost' => 1, 'title' => 'not a rate']]), false);
check('  and it changed nothing', count(shipping_config(true)['rates']), 8);

save_shipping_rates([11 => ['cost' => 700, 'title' => "Ken's van, 1-5m"]]);
check('  an apostrophe in a title does not break the file',
      $byId(shipping_config(true), 11)['title'] ?? '', "Ken's van, 1-5m");

/* Everything below is a defect that shipped in the first version of this
   saver and was found by review rather than by these tests. Each one wrote a
   file the shop believed was saved and was not. */

$costWas  = $byId(shipping_config(true), 12)['cost'] ?? 0;
$titleWas = $byId(shipping_config(true), 12)['title'] ?? '';

check('  a title on its own does not zero the price',
      save_shipping_rates([12 => ['title' => 'Next working day']]), true);
check('    the title moved',      $byId(shipping_config(true), 12)['title'] ?? '', 'Next working day');
check('    the price stayed put', $byId(shipping_config(true), 12)['cost'] ?? 0, $costWas);

check('  a price on its own leaves the title alone',
      save_shipping_rates([12 => ['cost' => 999]]), true);
check('    the title is still the one we set',
      $byId(shipping_config(true), 12)['title'] ?? '', 'Next working day');

/* A newline in a title is legal inside a single-quoted PHP string, so the
   file would still parse and still hold eight rates — and the rates block
   would have a new place for its regex to stop, wedging every later save. */
check('  a newline in a title is refused',
      save_shipping_rates([13 => ['cost' => 500, 'title' => "Fast\n    ],\nx"]]), false);
check('    and nothing was written',
      $byId(shipping_config(true), 13)['cost'] ?? 0, $byId($before, 13)['cost'] ?? -1);

/* One id matching and the rest not used to be written and reported a success:
   the rate count is eight either way, so the check below the write is blind
   to it. Simulated by asking for a real id and an impossible one together. */
check('  a batch with one id that cannot match is refused whole',
      save_shipping_rates([11 => ['cost' => 123], 4242 => ['cost' => 456]]), false);
check('    and the id that COULD have matched was not written',
      $byId(shipping_config(true), 11)['cost'] ?? 0, 700);

check('  the marker that stops a deploy reverting this is dropped',
      is_file(ROOT_DIR . '/storage/.catalogue-edited'), true);

file_put_contents($file, $backup);
shipping_config(true);
check('  and the file is restored byte for byte', (string) file_get_contents($file), $backup);

echo "\n";
printf("%d checks, %s\n", $ran, $failed ? "$failed FAILED" : 'all passing');
exit($failed ? 1 : 0);
