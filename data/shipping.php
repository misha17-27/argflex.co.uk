<?php
/**
 * Delivery, exactly as argflex.co.uk charges it today.
 *
 * Taken from the 26.08.26 database dump, not invented. Every figure here can
 * be traced: the rates are the eight enabled flat_rate instances of shipping
 * zone 1, the rules are the four wcs_ruleset posts of Conditional Shipping
 * for WooCommerce, and the packages are the two live shipping_package posts
 * of WooCommerce Advanced Shipping Packages.
 *
 * The shop prices carriage by LENGTH, and it does so through the weight
 * field: a variation's weight is its metre count, not its mass. One metre of
 * 3 mm tube and one metre of 32 mm sandblast hose both weigh 1.
 *
 * Two oddities are deliberate and must survive. Every 25 m coil is tagged 24,
 * not 25, and the six TERMORESIST ventilation variations carry no weight at
 * all and count as zero. The live rules are written around both.
 *
 * Where the live configuration is plainly wrong it is reproduced anyway and
 * flagged in `known_faults` below, because a migration that quietly reprices
 * live orders is worse than one that carries a fault across. Each one can be
 * corrected by editing this file alone; nothing in inc/shipping.php assumes
 * the current values.
 */

// The same guard every other data file carries: if .htaccess is ever ignored
// — a move to nginx, a misconfigured host — a direct request for this still
// gets a 404 rather than the source. This file was written by hand rather
// than by write_php_file(), which is why it was the one without it.
if (!defined('ROOT_DIR')) { http_response_code(404); exit; }

return [

    /* Zone 1 "UK", one location: GB. Zone 0 has no methods at all, so
       anywhere else gets no rate and cannot check out. */
    'zone' => [
        'name'      => 'UK',
        'countries' => ['GB'],
    ],

    /* The eight enabled flat rates, in method_order — which is the order the
       customer sees, and the first is the one preselected. Costs are pence.
       Titles are byte-for-byte from the dump, inconsistent spacing before the
       bracket included; they sit beside the package names below, which space
       it the other way, and normalising either half invents a mismatch. */
    'rates' => [
        ['id' => 11, 'title' => '1-2 days(1-5m)',    'cost' => 592],
        ['id' => 17, 'title' => '1-2 days(5-10m)',   'cost' => 828],
        ['id' => 12, 'title' => '1-2 days (10-25m)', 'cost' => 1235],
        ['id' => 13, 'title' => '1-2 days (25-50m)', 'cost' => 1168],
        ['id' => 14, 'title' => '3-4 days(1-5m)',    'cost' => 528],
        ['id' => 18, 'title' => '3-4 days(5-10m)',   'cost' => 724],
        ['id' => 15, 'title' => '3-4 days (10-25m)', 'cost' => 1028],
        ['id' => 16, 'title' => '3-4 days (25-50m)', 'cost' => 934],
    ],

    /* A model that costs more to send than its length suggests names its own
       price on the product itself, or on one of its options — see the
       Shipping section in the admin. A price belongs with the thing it
       prices, not in a list here that nobody can edit without opening a file.

       Where a consignment mixes models the dearest wins band by band: a
       bulky hose cannot travel at a thin hose's rate because something
       small was boxed with it. */

    /* Conditional Shipping rulesets. Each tests the weight of ONE package and,
       when every condition passes, removes the listed rates. Operators are the
       plugin's own: gt and lt are strict, gte and lte inclusive. */
    'rules' => [
        ['id' => 29384, 'name' => '1-5',
         'when' => [['lte', 5]],
         'disable' => [17, 12, 13, 18, 15, 16]],

        ['id' => 29379, 'name' => '5-10',
         'when' => [['gte', 6], ['lte', 9]],
         'disable' => [11, 12, 13, 14, 15, 16]],

        ['id' => 29380, 'name' => '10-25',
         'when' => [['gte', 10], ['lte', 25]],
         'disable' => [11, 17, 13, 14, 18, 16]],

        ['id' => 29381, 'name' => '25-50',
         'when' => [['gte', 26]],
         'disable' => [11, 17, 12, 14, 18, 15]],
    ],

    /* Advanced Shipping Packages, in menu_order. A package first checks the
       WHOLE basket; if that passes it takes the lines that match its own
       per-line test and carries them separately, with its own heading and its
       own rates. Whatever is left over becomes the default package below.
       If a package matches the basket but captures no line it does not form.

       Three of the five live packages can never match — 29382 has a malformed
       condition, 633 needs a width no product in the shop has, and 487 has no
       conditions at all — so they are not carried over. */
    'packages' => [
        ['id' => 634, 'name' => '1-2 days(10-25m)',
         'cart' => [['gte', 10], ['lte', 25]],
         'line' => [['weight', 'gte', 10], ['weight', 'lte', 25]],
         'exclude' => [11, 17, 13, 14, 18, 16]],

        ['id' => 635, 'name' => '1-2 days(25-50m)',
         'cart' => [['gte', 25]],
         'line' => [['weight', 'gte', 25]],
         'exclude' => [11, 17, 12, 14, 18, 15]],
    ],

    /* WooCommerce's own name for the leftovers. The live site stores an empty
       default package name, which falls back to this. */
    'default_package' => 'Shipping',

    /* Live charges no VAT on delivery: the single tax rate row has its
       shipping flag off. Every one of the 37 shipping lines in the order
       archive carries zero tax. Turning this on reprices every order. */
    'tax_on_shipping' => false,

    /**
     * Three faults in the live configuration were corrected here on the
     * owner's instruction, after seeing a 25 m coil offered all eight rates.
     * Each is written down because the bands above no longer match the live
     * site exactly, and somebody comparing the two deserves to know why.
     *
     * F1  FIXED. Rule 29381 removed four rates where every sibling removed
     *     six, so a basket over 25 m that no package captured was also
     *     offered the 5-10 m rates — thirty metres could ship for £5.28. Its
     *     list is now package 635's own, which is what the configuration
     *     evidently meant: the dump shows 29381 was last touched two days
     *     before everything else was reconfigured.
     *
     * F2  FIXED. Three weights fell through every rule and saw all eight
     *     rates: 10, 24 and 25 — the shop's commonest baskets, because all
     *     22 of the 25 m coils are tagged 24. The operators were `gt 10` and
     *     `lt 24`; they are now `gte 10` and `lte 25`, and 29381 starts at
     *     26. The bands are <=5 / 6-9 / 10-25 / >=26 and no weight falls
     *     between them.
     *
     * F3  FIXED. Package 634 decided by PRICE as well as length: a 25 m coil
     *     under £24 was carried in the 10-25 m band and an identical one
     *     above it was not, so two coils of the same length shipped
     *     differently because of what they cost. It keys on weight now.
     *
     * F4  FIXED, in data/products.php rather than here. The six TERMORESIST
     *     variations carried no weight at all, so a £85.84 ten-metre duct
     *     shipped in the under-5 m band. They hold their metre count now —
     *     1 and 10 — which is the only way the 5-to-10 m price the owner
     *     quoted for that product could ever apply to it.
     */
    'known_faults' => [],
];
