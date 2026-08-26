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
        ['id' => 11, 'title' => '1-2 days(1-5m)',    'cost' => 420],
        ['id' => 17, 'title' => '1-2 days(5-10m)',   'cost' => 592],
        ['id' => 12, 'title' => '1-2 days (10-25m)', 'cost' => 828],
        ['id' => 13, 'title' => '1-2 days (25-50m)', 'cost' => 1168],
        ['id' => 14, 'title' => '3-4 days(1-5m)',    'cost' => 320],
        ['id' => 18, 'title' => '3-4 days(5-10m)',   'cost' => 528],
        ['id' => 15, 'title' => '3-4 days (10-25m)', 'cost' => 724],
        ['id' => 16, 'title' => '3-4 days (25-50m)', 'cost' => 934],
    ],

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
         'when' => [['gt', 10], ['lt', 24]],
         'disable' => [11, 17, 13, 14, 18, 16]],

        // Four, where every sibling removes six — see known_faults F1.
        ['id' => 29381, 'name' => '25-50',
         'when' => [['gt', 25]],
         'disable' => [11, 12, 14, 15]],
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
         'cart' => [['gte', 10], ['eq', 24]],
         'line' => [['weight', 'gte', 10], ['subtotal', 'lte', 2400]],
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
     * Faults carried across on purpose, each reproducible and each fixable
     * here alone. Nothing reads this array; it is the note that stops the
     * next person "tidying up" a rule without knowing what it costs.
     *
     * F1  Rule 29381 removes four rates where every sibling removes six, so a
     *     basket over 25 m that no package captured is also offered the 5-10 m
     *     rates — 26 metres can ship for £5.28. The dump shows 29381 was last
     *     touched two days before the others were reconfigured. Setting its
     *     disable list to [11, 17, 12, 14, 18, 15] — package 635's own list —
     *     is what the configuration evidently meant.
     *
     * F2  Three weights fall through every rule and see all eight rates:
     *     10, 24 and 25. They are the shop's commonest baskets, because all
     *     22 of the 25 m coils are tagged 24. Bands of <=5 / 6-9 / 10-25 /
     *     >=26 would close it.
     *
     * F3  Package 634 decides by PRICE as well as length: a 25 m coil under
     *     £24 is carried in the 10-25 m band, an identical 25 m coil above it
     *     is not. Replacing ['subtotal','lte',2400] with ['weight','lte',24]
     *     removes the price from the question.
     *
     * F4  The six TERMORESIST variations have no weight, so a £85.84 ten-metre
     *     duct ships in the under-5 m band. That is missing data rather than a
     *     rule, and the fix belongs in data/products.php.
     */
    'known_faults' => ['F1', 'F2', 'F3', 'F4'],
];
