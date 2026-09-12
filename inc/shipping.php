<?php
/**
 * What delivery costs, and which options the customer is offered.
 *
 * This replaces a placeholder that banded on the order value and quoted one
 * price. The live shop does neither: it prices by the LENGTH of hose, offers
 * a choice of two speeds, and can split one basket into two consignments that
 * are charged separately. Every rule is in data/shipping.php; this file only
 * applies them.
 *
 * How a basket becomes a bill:
 *
 *   1. Outside the UK there is no zone, so there are no rates and no order.
 *   2. A package may claim some of the lines and carry them under its own
 *      heading. At most one ever does, and only if it actually captures a
 *      line — a package that matches the basket but takes nothing does not
 *      form.
 *   3. Whatever is left travels as "Shipping".
 *   4. Each package is priced on its OWN weight, and each is offered the
 *      rates that survive both the package's exclusions and the rules.
 *   5. The customer picks one rate per package. The first offered is the
 *      one preselected — by the shop's order, not by price.
 *
 * Weight is metres. See the note in data/shipping.php.
 */
declare(strict_types=1);

/** The rules, read once. */
/**
 * $fresh drops the cache, for the one caller that changes the file: without
 * it, saving a new price and then pricing anything in the same request reads
 * the figures from before the save.
 */
function shipping_config(bool $fresh = false): array
{
    static $cfg = null;
    if ($cfg === null || $fresh) {
        $file = ROOT_DIR . '/data/shipping.php';
        $cfg  = is_file($file) ? (require $file) : [];
        if (!is_array($cfg)) $cfg = [];
    }
    return $cfg;
}

/** The eight rates, keyed by id, in the order the shop lists them. */
/**
 * Every rate the shop offers today: the eight carried over, plus any the shop
 * has added, minus any it has switched off.
 *
 * The eight in data/shipping.php are a record of what the WooCommerce site
 * charged, and that file's whole purpose is to be traceable back to the dump
 * — so nothing the shopkeeper does is written into it. Rates added here and
 * rates switched off live in the settings, which is where a decision about
 * what to offer belongs. Turning one off is not deleting it: the figure stays
 * on file, and turning it back on restores exactly what was there.
 *
 * Added rates take ids from 1000 up, so they can never collide with the
 * WooCommerce instance ids the rules and packages below are written around.
 */
function shipping_rates(): array
{
    $out = [];
    foreach ((array) (shipping_config()['rates'] ?? []) as $r) {
        $out[(int) $r['id']] = ['id'    => (int) $r['id'],
                                'title' => (string) $r['title'],
                                'cost'  => (int) $r['cost'],
                                'min_goods' => 0];
    }

    foreach ((array) setting('shipping_extra') as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id < 1000) continue;                       // not one of ours
        $out[$id] = ['id'    => $id,
                     'title' => (string) ($r['title'] ?? 'Delivery'),
                     'cost'  => max(0, (int) ($r['cost'] ?? 0)),
                     // 0 means always on offer; anything else is the goods
                     // total, in pence, below which it is not shown
                     'min_goods' => max(0, (int) ($r['min_goods'] ?? 0))];
    }

    foreach ((array) setting('shipping_off') as $id) unset($out[(int) $id]);

    return $out;
}

/**
 * Every rate that exists, switched off ones included, for the admin screen.
 *
 * shipping_rates() answers "what does the checkout offer"; this answers "what
 * is there to offer", which is the question a settings page is asking. Each
 * carries `on`, and `mine` for the ones the shop added rather than inherited.
 */
function shipping_all_rates(): array
{
    $off = array_map('intval', (array) setting('shipping_off'));
    $out = [];

    foreach ((array) (shipping_config()['rates'] ?? []) as $r) {
        $id = (int) $r['id'];
        $out[$id] = ['id' => $id, 'title' => (string) $r['title'], 'cost' => (int) $r['cost'],
                     'min_goods' => 0, 'mine' => false, 'on' => !in_array($id, $off, true)];
    }

    foreach ((array) setting('shipping_extra') as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id < 1000) continue;
        $out[$id] = ['id' => $id, 'title' => (string) ($r['title'] ?? 'Delivery'),
                     'cost' => max(0, (int) ($r['cost'] ?? 0)),
                     'min_goods' => max(0, (int) ($r['min_goods'] ?? 0)),
                     'mine' => true, 'on' => !in_array($id, $off, true)];
    }

    return $out;
}

/** The goods a quote is for, in pence, before delivery and tax. */
function lines_goods(array $lines): int
{
    $total = 0;
    foreach ($lines as $line) $total += (int) ($line['line'] ?? 0);
    return $total;
}

/**
 * The rates for one consignment, with any model's own prices applied.
 *
 * Most of the catalogue goes by the common table. A few models cost more to
 * send than their length suggests — ventilation ducting takes the room of
 * far more than its metre count — and those name their own prices in
 * data/shipping.php, per product and per bore.
 *
 * Where a consignment mixes models the dearest price wins for each band. A
 * bulky hose cannot travel at a thin hose's rate because something small was
 * boxed with it, and taking the cheaper of the two would undercharge every
 * mixed order.
 */
function rates_for_lines(array $lines): array
{
    $common = shipping_rates();
    if (!$lines) return $common;

    $out = null;
    foreach ($lines as $line) {
        // what this one line alone would cost to send: the common table, with
        // whatever its product and its option set for themselves
        $mine = $common;
        foreach ((array) ($line['delivery'] ?? []) as $id => $cost) {
            if ($cost !== '' && $cost !== null && isset($mine[(int) $id])) {
                $mine[(int) $id]['cost'] = (int) $cost;
            }
        }

        if ($out === null) { $out = $mine; continue; }
        foreach ($mine as $id => $rate) {
            $out[$id]['cost'] = max($out[$id]['cost'], $rate['cost']);
        }
    }
    return $out ?? $common;
}

/** Does the shop deliver here at all? */
function ships_to(string $country = ''): bool
{
    $want = strtoupper($country !== '' ? $country : (string) setting('default_country'));
    $list = array_map('strtoupper', (array) (shipping_config()['zone']['countries'] ?? []));
    return in_array($want, $list, true);
}

/** Does VAT go on the carriage? Live says no. */
function tax_on_shipping(): bool
{
    return (bool) (shipping_config()['tax_on_shipping'] ?? false);
}

/**
 * Compare, using the plugins' own operators.
 *
 * gt and lt are strict; gte and lte are inclusive. Getting one of these
 * backwards moves every order into the wrong band, which is why the boundary
 * weights are tested one by one in .data/test_shipping.py.
 */
function shipping_compare(float $value, string $op, float $against): bool
{
    return match ($op) {
        'gt'  => $value >  $against,
        'gte' => $value >= $against,
        'lt'  => $value <  $against,
        'lte' => $value <= $against,
        'eq'  => $value == $against,
        'neq' => $value != $against,
        default => false,
    };
}

/** Every condition has to pass — both plugins join with AND. */
function shipping_all_match(float $value, array $conditions): bool
{
    foreach ($conditions as $c) {
        if (!shipping_compare($value, (string) $c[0], (float) $c[1])) return false;
    }
    return true;
}

/** The weight of a set of basket lines: metres times quantity, summed. */
function lines_weight(array $lines): int
{
    $total = 0;
    foreach ($lines as $line) $total += (int) ($line['weight'] ?? 0) * (int) ($line['qty'] ?? 1);
    return $total;
}

/**
 * Does a package want this line?
 *
 * The per-line tests read the line's own totals, not the basket's: `weight`
 * is metres times quantity, and `subtotal` is the undiscounted price times
 * quantity. It is the second of those that makes an identical coil travel
 * differently depending on what it costs — reproduced here on purpose, and
 * noted as F3 in data/shipping.php.
 */
function package_wants_line(array $line, array $tests): bool
{
    foreach ($tests as $t) {
        $field = (string) $t[0];
        $value = match ($field) {
            'weight'   => (float) ((int) ($line['weight'] ?? 0) * (int) ($line['qty'] ?? 1)),
            'subtotal' => (float) ((int) ($line['price'] ?? 0) * (int) ($line['qty'] ?? 1)),
            'qty'      => (float) (int) ($line['qty'] ?? 1),
            default    => 0.0,
        };
        if (!shipping_compare($value, (string) $t[1], (float) $t[2])) return false;
    }
    return true;
}

/**
 * Split a basket into consignments and price each one.
 *
 * Returns a list of packages, each with a name, its lines, its weight and the
 * rates it may be sent by — or an empty list when the shop does not deliver
 * to that country, which is how the checkout knows to refuse.
 */
function shipping_packages(array $lines, string $country = ''): array
{
    if (!$lines || !ships_to($country)) return [];

    $cfg       = shipping_config();
    $cartWeight = lines_weight($lines);
    $remaining  = $lines;
    $packages   = [];

    // A package tests the whole basket first, then takes what it wants.
    foreach ((array) ($cfg['packages'] ?? []) as $pkg) {
        if (!shipping_all_match((float) $cartWeight, (array) ($pkg['cart'] ?? []))) continue;

        $taken = $left = [];
        foreach ($remaining as $line) {
            if (package_wants_line($line, (array) ($pkg['line'] ?? []))) $taken[] = $line;
            else                                                        $left[]  = $line;
        }
        if (!$taken) continue;              // matched the basket, captured nothing

        $packages[] = ['name'    => (string) $pkg['name'],
                       'lines'   => $taken,
                       'exclude' => array_map('intval', (array) ($pkg['exclude'] ?? []))];
        $remaining = $left;
    }

    if ($remaining) {
        $packages[] = ['name'    => (string) ($cfg['default_package'] ?? 'Shipping'),
                       'lines'   => $remaining,
                       'exclude' => []];      // the leftovers get no package exclusions
    }

    foreach ($packages as $i => $pkg) {
        $weight  = lines_weight($pkg['lines']);
        $allowed = rates_for_lines($pkg['lines']);

        // The package's own exclusions, then the rules. Both plugins run and
        // both remove; neither overrules the other, so what survives is what
        // neither of them struck out.
        foreach ($pkg['exclude'] as $id) unset($allowed[$id]);

        foreach ((array) ($cfg['rules'] ?? []) as $rule) {
            if (!shipping_all_match((float) $weight, (array) ($rule['when'] ?? []))) continue;
            foreach ((array) ($rule['disable'] ?? []) as $id) unset($allowed[(int) $id]);
        }

        $packages[$i]['weight'] = $weight;
        $packages[$i]['rates']  = array_values($allowed);
        unset($packages[$i]['exclude']);
    }

    return $packages;
}

/**
 * The rate a package is sent by: the customer's choice when it is still on
 * offer, otherwise the first — which is the shop's order, not the cheapest.
 */
function chosen_rate(array $package, $wanted = null): ?array
{
    foreach ((array) $package['rates'] as $rate) {
        if ($wanted !== null && (int) $rate['id'] === (int) $wanted) return $rate;
    }
    return $package['rates'][0] ?? null;
}

/**
 * What the whole basket costs to send.
 *
 * $picked maps a package's position to a rate id, which is what the checkout
 * form posts. A basket that cannot be delivered comes back as deliverable
 * => false, and the checkout stops there rather than quoting nothing.
 */
function shipping_quote(array $lines, string $country = '', array $picked = []): array
{
    $packages = shipping_packages($lines, $country);

    /* A rate that only applies over a certain order value — free delivery over
       fifty pounds, and anything else of that shape. Decided here rather than
       per consignment because it is the WHOLE basket that has to reach the
       figure: a customer whose order splits into two parcels has still spent
       the money once, and charging them for the split would be a rule about
       our packing rather than about their order. */
    $goods = lines_goods($lines);
    foreach ($packages as $i => $pkg) {
        $packages[$i]['rates'] = array_values(array_filter(
            (array) $pkg['rates'],
            fn($r) => (int) ($r['min_goods'] ?? 0) <= $goods));
    }

    if (!$packages) {
        return ['deliverable' => false, 'cost' => 0, 'packages' => [],
                'title' => '', 'why' => ships_to($country)
                    ? 'There is nothing to deliver.'
                    : 'We only deliver within the United Kingdom.'];
    }

    $cost  = 0;
    $names = [];
    foreach ($packages as $i => $pkg) {
        $rate = chosen_rate($pkg, $picked[$i] ?? null);
        if (!$rate) {
            return ['deliverable' => false, 'cost' => 0, 'packages' => $packages,
                    'title' => '', 'why' => 'No delivery option fits this basket.'];
        }
        $packages[$i]['chosen'] = $rate;
        $cost  += (int) $rate['cost'];
        $names[] = (string) $rate['title'];
    }

    return [
        'deliverable' => true,
        'cost'        => $cost,
        'packages'    => $packages,
        // One "Shipping" row on the checkout even when there are two
        // consignments, which is how the live site displays it.
        'title'       => count($names) === 1 ? $names[0] : 'Delivery',
        'why'         => '',
    ];
}
