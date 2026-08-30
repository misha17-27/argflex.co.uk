<?php
/**
 * Products that are one model sold as several listings.
 *
 * The live shop sells thirteen separate products called "Oil resistant hose
 * SAE J30 R6 (3.2mm)", "(4mm)", "(5mm)" and so on. They are the same hose in
 * different bores, and WooCommerce would normally make that one variable
 * product with an Inner Diameter attribute.
 *
 * It cannot be done that way here. Each of those thirteen URLs is indexed and
 * ranks, and the whole migration rests on not moving a URL. Merging them would
 * throw away twelve pages to gain one.
 *
 * So they stay separate and are joined at the front instead: a product names a
 * FAMILY, and its page offers every bore in that family. Its own bores are
 * swatches in the page, as they always were. A sibling's bore is a link to the
 * sibling — which is honest about what is happening, keeps every URL exactly
 * where it was, and still lets somebody who wants 16 mm get there in one click
 * from the 10 mm page.
 *
 * Families are DATA, not a guess from the name. Two products can look related
 * and not be — "PVC garden hose HOBBY" and "NTS Garden Hose" carry the same
 * four bores and are different hoses — and a wrong guess sends a customer to
 * the wrong product. .data/set_families.php proposes them; the admin decides.
 */
declare(strict_types=1);

/** The bores a product offers, in the order its attribute lists them. */
function product_bores(array $p): array
{
    foreach ((array) ($p['attrs'] ?? []) as $a) {
        if (stripos((string) $a['name'], 'diameter') === false || !$a['terms']) continue;
        return array_values(array_map(
            fn($t) => ['name' => (string) $t['name'], 'slug' => (string) $t['slug']],
            (array) $a['terms']));
    }
    return [];
}

/** The name of the attribute a family switches on, as this product spells it. */
function family_axis(array $p): string
{
    foreach ((array) ($p['attrs'] ?? []) as $a) {
        if (stripos((string) $a['name'], 'diameter') !== false && $a['terms']) {
            return (string) $a['name'];
        }
    }
    return 'Inner Diameter';
}

/** Every published product in a family, in catalogue order. Empty for ''. */
function family_members(string $family): array
{
    $family = trim($family);
    if ($family === '') return [];

    return array_values(array_filter(all_products(),
        fn($p) => trim((string) (product_defaults($p)['family'] ?? '')) === $family));
}

/**
 * The bore picker for a product's page: every bore in its family, each
 * marked with where it lives.
 *
 * Returns [['name','slug','mine','url','product'], ...] sorted by the bore
 * itself, so 3.2mm comes before 10mm — the catalogue's own order is by name,
 * which puts 10 before 3.2 and reads as a mistake.
 *
 * A bore the current product offers is always 'mine', whatever a sibling
 * claims. Two products CAN claim the same bore — the live catalogue has two
 * different 6 mm listings of the SAE J30 R6 — and showing it twice would ask
 * the customer to choose between two identical labels. The first sibling in
 * catalogue order wins and the rest are dropped; family_clashes() reports them
 * so the shop can see what it has rather than only the tidied version.
 */
function family_options(array $p): array
{
    $family = trim((string) (product_defaults($p)['family'] ?? ''));
    if ($family === '') return [];

    $members = family_members($family);
    if (count($members) < 2) return [];

    $out = [];
    foreach (product_bores($p) as $bore) {
        $out[$bore['slug']] = $bore + ['mine' => true, 'url' => '', 'product' => $p['name']];
    }

    foreach ($members as $sibling) {
        if ($sibling['slug'] === $p['slug']) continue;
        foreach (product_bores($sibling) as $bore) {
            if (isset($out[$bore['slug']])) continue;      // ours, or an earlier sibling's
            $out[$bore['slug']] = $bore + [
                'mine'    => false,
                'url'     => product_url($sibling),
                'product' => $sibling['name'],
            ];
        }
    }

    /* By the number, not the label: sorting "10mm" and "3.2mm" as text puts
       the 10 first, which reads as a catalogue that cannot count. */
    uasort($out, fn($a, $b) => bore_value($a['name']) <=> bore_value($b['name']));

    return array_values($out);
}

/** "12.7mm" -> 12.7, "8.0+8.0mm" -> 8.0. Unparseable sorts last. */
function bore_value(string $label): float
{
    return preg_match('/[0-9]+(?:[.,][0-9]+)?/', $label, $m)
        ? (float) str_replace(',', '.', $m[0])
        : PHP_FLOAT_MAX;
}

/**
 * Bores that more than one product in a family claims.
 *
 * Shown in the admin rather than fixed here: which of two identical listings
 * should survive is the shop's decision, not this file's.
 *
 * Returns [bore label => [product name, ...]].
 */
function family_clashes(string $family): array
{
    $seen = [];
    foreach (family_members($family) as $p) {
        foreach (product_bores($p) as $bore) {
            $seen[$bore['name']][] = (string) $p['name'];
        }
    }
    return array_filter($seen, fn($who) => count($who) > 1);
}

/** Every family in the catalogue, as [slug => [product, ...]]. */
function all_families(bool $includeDrafts = false): array
{
    $out = [];
    foreach (all_products($includeDrafts) as $p) {
        $family = trim((string) (product_defaults($p)['family'] ?? ''));
        if ($family !== '') $out[$family][] = $p;
    }
    ksort($out);
    return $out;
}

/**
 * The length attribute term asked for in the URL, if the product has it.
 *
 * Somebody looking at 50 m of the 10 mm and switching to 16 mm wants 50 m of
 * the 16 mm, not the sibling's default. The choice travels as ?length=<slug>,
 * added by assets/js/site.js when the link is followed rather than written
 * into every href — a hundred and fifty parameterised copies of pages that
 * already exist is not something to put in front of a crawler on a site whose
 * whole constraint is search. This end reads it whoever produced it, so a
 * shared or bookmarked link works too, and the canonical stays clean.
 */
function requested_length(array $p): string
{
    $want = trim((string) ($_GET['length'] ?? ''));
    if ($want === '') return '';

    foreach ((array) ($p['attrs'] ?? []) as $a) {
        if (stripos((string) $a['name'], 'length') === false) continue;
        foreach ((array) $a['terms'] as $t) {
            if ((string) $t['slug'] === $want) return $want;
        }
    }
    return '';
}
