<?php
/**
 * Propose which products are one model sold as several listings.
 *
 * A family is DATA, not a guess made at render time — see inc/families.php for
 * why. This proposes the obvious ones from the naming, prints them, and only
 * writes when told to. Everything it does can be undone by clearing the field
 * on the product in the admin.
 *
 * The rule is deliberately narrow: a name that ends in a bracketed bore, with
 * everything before the bracket identical. That catches the thirteen
 * "Oil resistant hose SAE J30 R6 (Xmm)" and nothing else, which is the point —
 * "PVC garden hose HOBBY" and "NTS Garden Hose" carry the same four bores and
 * are different hoses, and a family that joined them would send somebody
 * looking for one to the other.
 *
 *   php .data/set_families.php --dry-run    show what it would do
 *   php .data/set_families.php              write data/products.php
 *   php .data/set_families.php --clear      remove every family
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$dry   = in_array('--dry-run', $argv, true);
$clear = in_array('--clear', $argv, true);

$products = all_products(true);

if ($clear) {
    $n = 0;
    foreach ($products as $i => $p) {
        if (($p['family'] ?? '') !== '') { $products[$i]['family'] = ''; $n++; }
    }
    printf("%d product(s) cleared\n", $n);
    if (!$dry && $n) { save_products($products); echo "data/products.php rewritten\n"; }
    exit(0);
}

/** "Oil resistant hose SAE J30 R6 (12.7mm)" -> "Oil resistant hose SAE J30 R6" */
function stem(string $name): string
{
    $stem = preg_replace('/\s*\([^()]*\)\s*$/u', '', trim($name)) ?? $name;
    return trim(preg_replace('/\s+/', ' ', $stem) ?? $stem);
}

/** True when the name ends in a bracket holding a measurement. */
function names_a_bore(string $name): bool
{
    return (bool) preg_match('/\(\s*[0-9]+(?:[.,][0-9]+)?\s*(?:mm|"|inch)?\s*\)\s*$/ui', trim($name));
}

$groups = [];
foreach ($products as $p) {
    if (!names_a_bore((string) $p['name'])) continue;
    if (!product_bores($p)) continue;               // nothing to switch on
    $groups[stem((string) $p['name'])][] = $p;
}

$groups = array_filter($groups, fn($rows) => count($rows) > 1);

if (!$groups) { echo "nothing to group\n"; exit(0); }

$assign = [];
foreach ($groups as $stem => $rows) {
    $slug = make_slug($stem);
    printf("\n%s\n  family: %s   (%d listings)\n", $stem, $slug, count($rows));
    foreach ($rows as $r) {
        $bores = implode(', ', array_column(product_bores($r), 'name'));
        printf("    %-46s %-12s %s\n", mb_substr((string) $r['name'], 0, 46), $bores, product_url($r));
        $assign[$r['slug']] = $slug;
    }
    $clash = [];
    foreach ($rows as $r) {
        foreach (product_bores($r) as $b) $clash[$b['name']][] = (string) $r['name'];
    }
    foreach (array_filter($clash, fn($w) => count($w) > 1) as $bore => $who) {
        printf("    ! %s is claimed by %d listings: %s\n", $bore, count($who), implode(' / ', $who));
        echo  "      the first in catalogue order is the one the picker offers;\n"
            . "      decide in the admin which should survive.\n";
    }
}

printf("\n%d product(s) in %d famil%s\n", count($assign), count($groups),
       count($groups) === 1 ? 'y' : 'ies');

if ($dry) { echo "\nDry run — nothing written.\n"; exit(0); }

$changed = 0;
foreach ($products as $i => $p) {
    $want = $assign[$p['slug']] ?? ($p['family'] ?? '');
    if ((string) ($p['family'] ?? '') === (string) $want) continue;
    $products[$i]['family'] = $want;
    $changed++;
}

if (!$changed) { echo "\nAlready set — nothing to write.\n"; exit(0); }

save_products($products);
printf("\ndata/products.php rewritten, %d changed\n", $changed);
