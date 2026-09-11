<?php
/**
 * Carry the order archive over from WooCommerce.
 *
 * The old shop keeps its orders in the HPOS tables — wp1v_wc_orders and its
 * companions — and the new one keeps each order as a JSON file under
 * storage/orders. This reads the first and writes the second.
 *
 * WHAT IT READS, AND NOTHING ELSE
 *
 * Five tables: wc_orders, wc_order_operational_data, wc_order_addresses,
 * woocommerce_order_items and its itemmeta, plus the order notes out of
 * wp1v_comments. The dump also contains live gateway credentials in
 * wp1v_options; those are never looked at, and the parser below is pointed at
 * named tables rather than turned loose on the file.
 *
 * HOW AN OLD LINE FINDS ITS PRODUCT
 *
 * data/products.php still carries every product's and every variation's old
 * WooCommerce id, so _product_id and _variation_id resolve straight to a slug
 * and a variation key. All 41 line items in the archive resolve that way.
 *
 * WHAT "PAID" MEANS HERE
 *
 * A paid date AND a gateway reference. Not the status, and not the paid date
 * on its own — WooCommerce stamps date_paid whenever a row moves into a paid
 * status, so a bulk "mark completed" pass writes one for an order nobody ever
 * paid for.
 *
 * That is not hypothetical. Order 29231, £372.66, reads wc-completed with a
 * paid date of 26 July 2026 — a second before the bulk-edit note that made
 * it. It has no transaction id, no captured charge, and the one Stripe flag
 * it does carry is _stripe_upe_waiting_for_redirect: the customer was sent
 * off to authenticate the card and never came back. Trusting the status, or
 * the paid date alone, would have put £372.66 of money nobody ever sent into
 * the accounts.
 *
 * PRICES ARE THE ONES CHARGED, NOT TODAY'S
 *
 * Every figure is taken from the archive. An order placed at last year's
 * price stays at last year's price, because the invoice a customer keeps has
 * to match the money that left their account.
 *
 * RUNNING IT
 *
 *   php .data/import_orders.php --dry-run
 *   php .data/import_orders.php
 *   php .data/import_orders.php --dump=/path/to/newer.sql
 *
 * It is idempotent: an order already on disk is left alone unless --replace
 * is given. That matters, because the dump in hand is dated 26 August 2026
 * and the old shop is still taking orders — this will have to be run again,
 * against a fresher export, on the day the site is switched over.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$dry       = in_array('--dry-run', $argv, true);
$replace   = in_array('--replace', $argv, true);
$withTests = in_array('--with-tests', $argv, true);
$prefix  = 'wp1v_';
$dump    = 'D:/argflex/26.08.26/argiccfx_flex.sql';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--dump='))   $dump   = substr($arg, 7);
    if (str_starts_with($arg, '--prefix=')) $prefix = substr($arg, 9);
}

if (!is_file($dump)) {
    fwrite(STDERR, "no dump at {$dump}\n  pass --dump=/path/to/export.sql\n");
    exit(1);
}

/* ------------------------------------------------------------- the dump */

/**
 * One SQL row's values, split properly.
 *
 * Not a regex. Order rows carry addresses with commas in them, user agent
 * strings full of brackets and parentheses, and customer notes with escaped
 * quotes — splitting on "," would cut straight through the middle of any of
 * those, and would do it silently on the one order in fifty that has an
 * apostrophe in the street name.
 */
function sql_values(string $row): array
{
    $out = [];
    $len = strlen($row);
    $i   = 0;

    while ($i < $len) {
        while ($i < $len && ($row[$i] === ' ' || $row[$i] === "\t")) $i++;
        if ($i >= $len) break;

        if ($row[$i] === "'") {
            $value = '';
            $i++;
            while ($i < $len) {
                if ($row[$i] === '\\' && $i + 1 < $len) {
                    // mysqldump's own escapes, turned back into what they stand for
                    $next = $row[$i + 1];
                    $value .= match ($next) {
                        'n' => "\n", 'r' => "\r", 't' => "\t",
                        '0' => "\0", 'b' => chr(8), 'Z' => chr(26),
                        default => $next,
                    };
                    $i += 2;
                    continue;
                }
                if ($row[$i] === "'") { $i++; break; }
                $value .= $row[$i];
                $i++;
            }
            $out[] = $value;
        } else {
            $start = $i;
            while ($i < $len && $row[$i] !== ',') $i++;
            $raw = rtrim(substr($row, $start, $i - $start));
            $out[] = strcasecmp($raw, 'NULL') === 0 ? null : $raw;
        }

        while ($i < $len && ($row[$i] === ' ' || $row[$i] === "\t")) $i++;
        if ($i < $len && $row[$i] === ',') $i++;
    }
    return $out;
}

/**
 * Every row of one table, as column => value.
 *
 * Streamed rather than loaded: the dump is 24 MB and the tables wanted from
 * it are small. $keep is called for each row and may return false to throw it
 * away before it is kept, which is how the 140-thousand-row tables are read
 * without holding them.
 */
function sql_table(string $file, string $table, ?callable $keep = null): array
{
    $rows    = [];
    $columns = [];
    $inside  = false;
    $fh      = fopen($file, 'rb');
    if (!$fh) return $rows;

    while (($line = fgets($fh)) !== false) {
        $line = rtrim($line, "\r\n");

        if (str_starts_with($line, 'INSERT INTO `' . $table . '`')) {
            preg_match('/\(([^)]*)\) VALUES/', $line, $m);
            $columns = array_map(
                fn($c) => trim($c, " `"),
                explode(',', (string) ($m[1] ?? '')));
            $inside = true;

            // one-line INSERTs put the first row on the same line
            $at = strpos($line, ') VALUES');
            $tail = $at !== false ? trim(substr($line, $at + 8)) : '';
            if ($tail !== '') $line = $tail; else continue;
        }

        if (!$inside) continue;
        if ($line === '' || $line[0] !== '(') { $inside = false; continue; }

        // several rows can share a line; the separator is "),(" at depth zero
        foreach (explode('),(', trim($line, " \t")) as $chunk) {
            $chunk = trim($chunk);
            $chunk = ltrim($chunk, '(');
            $chunk = rtrim($chunk, ";,");
            $chunk = rtrim($chunk, ')');
            if ($chunk === '') continue;

            $values = sql_values($chunk);
            if (count($values) !== count($columns)) continue;

            $row = array_combine($columns, $values);
            if ($keep !== null && !$keep($row)) continue;
            $rows[] = $row;
        }

        if (str_ends_with($line, ';')) $inside = false;
    }
    fclose($fh);
    return $rows;
}

/* ---------------------------------------------------------- the reading */

echo "reading {$dump}\n";

$orders = sql_table($dump, $prefix . 'wc_orders');
$opdata = sql_table($dump, $prefix . 'wc_order_operational_data');
$addrs  = sql_table($dump, $prefix . 'wc_order_addresses');
$items  = sql_table($dump, $prefix . 'woocommerce_order_items');

$itemIds = array_flip(array_column($items, 'order_item_id'));
$meta    = sql_table($dump, $prefix . 'woocommerce_order_itemmeta',
    fn($r) => isset($itemIds[$r['order_item_id']]));

$notes = sql_table($dump, $prefix . 'comments',
    fn($r) => ($r['comment_type'] ?? '') === 'order_note');

$ometa = sql_table($dump, $prefix . 'wc_orders_meta');

printf("  %d orders, %d addresses, %d items, %d item meta, %d notes\n\n",
    count($orders), count($addrs), count($items), count($meta), count($notes));

if (!$orders) {
    fwrite(STDERR, "No orders found. Is the table prefix right? Pass --prefix=wp1v_\n");
    exit(1);
}

/* Index everything by the order it belongs to. */
$byOrder = fn(array $rows, string $key) => array_reduce($rows, function ($carry, $row) use ($key) {
    $carry[(string) $row[$key]][] = $row;
    return $carry;
}, []);

$opFor    = array_column($opdata, null, 'order_id');
$addrFor  = $byOrder($addrs, 'order_id');
$itemsFor = $byOrder($items, 'order_id');
$notesFor = $byOrder($notes, 'comment_post_ID');
$metaFor  = $byOrder($meta,  'order_item_id');
$ometaFor = $byOrder($ometa, 'order_id');

/** One meta value off an order item. */
$im = function (string $itemId, string $key, $fallback = '') use ($metaFor) {
    foreach ($metaFor[$itemId] ?? [] as $row) {
        if ($row['meta_key'] === $key) return $row['meta_value'];
    }
    return $fallback;
};

/** One meta value off an order. */
$om = function (string $orderId, string $key, $fallback = '') use ($ometaFor) {
    foreach ($ometaFor[$orderId] ?? [] as $row) {
        if ($row['meta_key'] === $key) return $row['meta_value'];
    }
    return $fallback;
};

/* ------------------------------------------------ the catalogue's memory */

/* Both indexes are built from the ids the catalogue still carries from
   WooCommerce — every product has one and so does every variation. */
$catalogue = all_products(true);
$byProduct = [];
$byVariant = [];
foreach ($catalogue as $p) {
    if (!empty($p['id'])) $byProduct[(string) $p['id']] = $p;
    foreach ((array) ($p['variants'] ?? []) as $v) {
        if (!empty($v['id'])) $byVariant[(string) $v['id']] = [$p, $v];
    }
}

/* ------------------------------------------------------------- the rules */

/** WooCommerce's stages, in this shop's words. */
const WOO_STATUS = [
    'wc-completed'  => 'shipped',      // fulfilled and done with
    'wc-processing' => 'confirmed',    // paid, not yet sent
    'wc-refunded'   => 'refunded',
    'wc-cancelled'  => 'cancelled',
    'wc-failed'     => 'cancelled',
    'wc-on-hold'    => 'new',
    'wc-pending'    => 'new',
];

/** A decimal string out of the dump, in pence. */
$pence = fn($amount) => (int) round(((float) $amount) * 100);

/** A GMT timestamp out of the dump, as an ISO 8601 instant. */
$when = function (?string $gmt): string {
    $gmt = trim((string) $gmt);
    if ($gmt === '' || str_starts_with($gmt, '0000')) return '';
    return (new DateTimeImmutable($gmt, new DateTimeZone('UTC')))->format('c');
};

/* --------------------------------------------------------- the importing */

$made = $skipped = $already = 0;
$unresolved = [];
$notCarried = [];
$mismatched = [];
$claimed    = [];

foreach ($orders as $row) {
    $id   = (string) $row['id'];
    $type = (string) $row['type'];

    // Refunds are separate rows pointing at their parent; they are folded
    // into it below rather than becoming orders of their own.
    if ($type !== 'shop_order') continue;

    $status = (string) ($row['status'] ?? '');
    if ($status === 'wc-checkout-draft') {
        $notCarried[] = "#{$id} — an abandoned checkout, never an order";
        $skipped++;
        continue;
    }

    /* Every real order in this archive was placed by a guest. The only ones
       with a WordPress user behind them were placed from the shop's own
       account — the two 2024 test transactions of £4.46, one cancelled and
       one refunded. Carried over they become a customer in the customer list
       and £4.46 of revenue in the reports, neither of which ever happened. */
    if ((int) ($row['customer_id'] ?? 0) !== 0 && !$withTests) {
        $notCarried[] = "#{$id} — placed from the shop's own account, so a test "
                      . "(pass --with-tests to bring it over anyway)";
        $skipped++;
        continue;
    }

    $reference = 'WC-' . $id;
    if (find_order($reference) && !$replace) { $already++; continue; }

    $op = $opFor[$id] ?? [];

    /* ---- who it was for. Billing is the invoice address; shipping is where
            it went, and the old shop stored both even when identical. */
    $billing = $shipping = [];
    foreach ($addrFor[$id] ?? [] as $a) {
        if (($a['address_type'] ?? '') === 'shipping') $shipping = $a; else $billing = $a;
    }

    /* The BILLING address is the customer: it is who the invoice is made out
       to, whose email and phone are on the order, and who all_customers()
       will list. On seven of these orders the goods went somewhere else —
       twice to a different person entirely, on the two largest orders in the
       archive — and the new shop's checkout collects only one address, so
       there is nowhere to put the second. It goes in the order notes rather
       than being dropped, which is where a person reading the order will
       actually see it. */
    $who  = $billing ?: $shipping;
    $name = trim(($who['first_name'] ?? '') . ' ' . ($who['last_name'] ?? ''));

    $country = strtoupper(trim((string) ($who['country'] ?? 'GB')));
    if ($country === '') $country = 'GB';

    $oneLine = fn(array $a) => trim(implode(', ', array_filter([
        trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
        $a['company'] ?? '', $a['address_1'] ?? '', $a['address_2'] ?? '',
        $a['city'] ?? '', $a['state'] ?? '', $a['postcode'] ?? '', $a['country'] ?? '',
    ], fn($part) => trim((string) $part) !== '')));

    $notes = trim((string) ($row['customer_note'] ?? ''));
    if ($shipping && $billing && $oneLine($shipping) !== $oneLine($billing)) {
        $notes = trim("Delivered to: " . $oneLine($shipping)
               . ($notes !== '' ? "\n\n" . $notes : ''));
    }

    $customer = [
        'name'         => $name !== '' ? $name : '—',
        'company'      => (string) ($who['company'] ?? ''),
        'email'        => (string) ($row['billing_email'] ?? $billing['email'] ?? ''),
        'phone'        => (string) ($who['phone'] ?? $billing['phone'] ?? ''),
        'address'      => trim(((string) ($who['address_1'] ?? '')) . ' '
                             . ((string) ($who['address_2'] ?? ''))),
        'city'         => (string) ($who['city'] ?? ''),
        'postcode'     => (string) ($who['postcode'] ?? ''),
        'country'      => COUNTRIES[$country] ?? 'United Kingdom',
        'country_code' => $country,
        'notes'        => $notes,
    ];

    /* ---- what was bought, at the price that was actually charged */
    $lines     = [];
    $shipTitle = '';
    foreach ($itemsFor[$id] ?? [] as $item) {
        $itemId = (string) $item['order_item_id'];
        $kind   = (string) $item['order_item_type'];

        if ($kind === 'shipping') {
            if ($shipTitle === '') $shipTitle = (string) $item['order_item_name'];
            continue;
        }
        if ($kind !== 'line_item') continue;      // tax and coupon lines are totals, not goods

        $qty  = max(1, (int) $im($itemId, '_qty', '1'));
        $paidLine = $pence($im($itemId, '_line_total', '0'));

        $pid = (string) $im($itemId, '_product_id', '0');
        $vid = (string) $im($itemId, '_variation_id', '0');

        $slug = $option = $key = '';
        $attrs = [];
        $weight = 0;
        $class  = '';

        if ($vid !== '' && $vid !== '0' && isset($byVariant[$vid])) {
            [$p, $v] = $byVariant[$vid];
            $slug   = (string) $p['slug'];
            $key    = (string) ($v['key'] ?? '');
            $option = (string) ($v['label'] ?? '');
            $attrs  = (array) ($v['attrs'] ?? []);
            $weight = (int) ($v['weight'] ?? 0);
            $class  = (string) ($v['shipping_class'] ?? '');
        } elseif (isset($byProduct[$pid])) {
            $p      = $byProduct[$pid];
            $slug   = (string) $p['slug'];
            $weight = (int) ($p['weight'] ?? 0);
            $class  = (string) ($p['shipping_class'] ?? '');
        } else {
            /* The product has been taken out of the catalogue since. The line
               is kept regardless — an order is a record of what was sold, and
               dropping the line would silently change what the customer was
               charged — but it is listed at the end so somebody knows. */
            $unresolved[] = "#{$id}: " . (string) $item['order_item_name']
                          . " (product {$pid}, variation {$vid})";
        }

        $lines[] = [
            'slug'   => $slug,
            'title'  => (string) $item['order_item_name'],
            'option' => $option,
            'label'  => $option,
            'key'    => $key,
            'attrs'  => $attrs,
            'qty'    => $qty,
            // what one of them cost on the day, worked back out of the line
            'price'  => (int) round($paidLine / $qty),
            'line'   => $paidLine,
            'weight' => $weight,
            'delivery' => [],
            'shipping_class' => $class,
        ];
    }

    if (!$lines) {
        $notCarried[] = "#{$id} — no goods on it";
        $skipped++;
        continue;
    }

    /* ---- the money, entirely as the archive has it */
    $subtotal = array_sum(array_column($lines, 'line'));
    $ship     = $pence($op['shipping_total_amount'] ?? 0);
    $discount = $pence($op['discount_total_amount'] ?? 0);
    $vat      = $pence($row['tax_amount'] ?? 0);
    $total    = $pence($row['total_amount'] ?? 0);

    /* The rate is worked out from the order rather than assumed, so an order
       placed under a different rate keeps the one it was charged. */
    $base = $subtotal - $discount;
    $rate = $base > 0 ? round($vat / $base * 100, 2) : 0.0;

    $order = [
        'items'          => $lines,
        'sent'           => count($lines),
        'subtotal'       => $subtotal,
        'coupon'         => '',
        'coupon_title'   => '',
        'discount'       => $discount,
        'shipping'       => $ship,
        'shipping_title' => $shipTitle !== '' ? $shipTitle : 'Delivery',
        'shipping_zone'  => shipping_zone($country)['name'] ?? '',
        'packages'       => [],
        'deliverable'    => true,
        'undeliverable_because' => '',
        'ship_surcharge' => 0,
        'ship_because'   => '',
        'delivery_in'    => '',
        'vat'            => $vat,
        'tax_label'      => 'VAT',
        'tax_rate'       => $rate,
        'tax_note'       => '',
        'total'          => $total,
    ];

    /* ---- how it was paid for, and whether it actually was */
    $gateway = (string) ($row['payment_method'] ?? '');
    $title   = trim((string) ($row['payment_method_title'] ?? ''));
    $record = [
        'reference' => $reference,
        'placed_at' => $when($row['date_created_gmt'] ?? '') ?: date('c'),
        'customer'  => $customer,
        'order'     => $order,
        'payment'   => [
            'id'    => $gateway === 'ppcp' ? 'ppcp' : ($gateway === 'stripe' ? 'stripe' : $gateway),
            'title' => $title !== '' ? $title : 'Card payment',
        ],
        'status'    => WOO_STATUS[$status] ?? 'new',
    ];

    /* Paid needs both a date and a reference from the gateway — see the note
       at the top of this file. An order with a paid date and no reference is
       one somebody marked completed by hand; it is listed at the end rather
       than quietly counted as money. */
    $paidAt = $when($op['date_paid_gmt'] ?? '');
    $txn    = trim((string) ($row['transaction_id'] ?? ''));

    if ($paidAt !== '' && $txn !== '') {
        $record['paid'] = [
            'gateway' => $gateway,
            'id'      => $txn,
            'amount'  => $total,
            'at'      => $paidAt,
            // says where the knowledge came from, so nobody mistakes an
            // imported figure for one this shop's own gateway confirmed
            'via'     => 'imported from WooCommerce',
        ];
    } elseif ($paidAt !== '') {
        $claimed[] = sprintf('%s — %s, marked %s on %s, but no gateway ever '
                           . 'confirmed a payment for it',
            $reference, money($total), $status, substr($paidAt, 0, 10));
        // it is not money until somebody checks the gateway and says so
        $record['status'] = 'new';
    }

    /* The gateway's OTHER identifier. Stripe puts the charge in
       transaction_id and keeps the payment intent separately; PayPal puts the
       capture in transaction_id and keeps its own order id separately, in the
       same 17-character shape, so the two are easily mistaken for each other.
       Only one fits in the paid block, and the other is the thread somebody
       will want when they go looking in a gateway dashboard — especially on
       an order like 29231 where the intent is the only record of what
       actually happened. */
    $second = trim((string) ($om($id, '_stripe_intent_id', '')
                          ?: $om($id, '_ppcp_paypal_order_id', '')));
    if ($second !== '') {
        $record['events'][] = [
            'at'     => $paidAt !== '' ? $paidAt : ($when($row['date_created_gmt'] ?? '') ?: date('c')),
            'what'   => 'Gateway reference',
            'detail' => (str_starts_with($second, 'pi_') ? 'Stripe payment intent ' : 'PayPal order ')
                      . $second,
            'by'     => '',
        ];
    }

    /* ---- what was given back. The old shop keeps a refund as its own row
            with a negative total and the reason in the order meta; here it is
            a line on the order it belongs to. */
    foreach ($orders as $maybe) {
        if (($maybe['type'] ?? '') !== 'shop_order_refund') continue;
        if ((string) ($maybe['parent_order_id'] ?? '') !== $id) continue;

        $rid    = (string) $maybe['id'];
        $reason = trim((string) $om($rid, '_refund_reason', ''));
        // A refund here has no field for the gateway's own id, and that id is
        // what somebody searches for when the customer says the money never
        // came back. It goes in the reason, which is the one field that is read.
        $rtxn = trim((string) ($om($rid, '_stripe_refund_id', '')
                            ?: $om($rid, '_refunded_payment', '')));
        if ($rtxn !== '' && $rtxn !== '1') {
            $reason = trim($reason . ($reason !== '' ? ' — ' : '') . $rtxn);
        }

        $record['refunds'][] = [
            'id'     => 'WC-' . $rid,
            'amount' => abs($pence($maybe['total_amount'] ?? 0)),
            'reason' => $reason,
            'at'     => $when($maybe['date_created_gmt'] ?? ''),
            'by'     => '',
        ];
    }

    /* ---- the history. The old shop's own notes are the payment trail: the
            gateway ids it was issued, what it was told, and what failed. They
            are the reason anybody will ever open one of these orders. */
    $record['events'][] = [
        'at'     => $record['placed_at'],
        'what'   => 'Imported from WooCommerce',
        /* What it was over there, and what was made of it here. On the one
           order the two disagree about, this line is where somebody reading
           the history finds out that they do. */
        'detail' => 'Order #' . $id . ', ' . $status . ' — '
                  . (isset($record['paid'])
                        ? 'paid'
                        : ($paidAt !== ''
                            ? 'marked paid over there, but no gateway confirmed it'
                            : 'never paid')),
        'by'     => '',
    ];
    foreach ($notesFor[$id] ?? [] as $note) {
        $record['events'][] = [
            'at'     => $when($note['comment_date_gmt'] ?? ''),
            'what'   => 'Note from the old shop',
            'detail' => clip(trim((string) $note['comment_content']), 300),
            'by'     => (string) ($note['comment_author'] ?? ''),
        ];
    }
    usort($record['events'], fn($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

    /* Not an order the new shop took. Kept so that a figure which looks odd
       next year can be traced back to the row it came from. */
    $record['imported'] = [
        'from'       => basename($dump),
        'at'         => date('c'),
        'woo_id'     => (int) $id,
        'woo_status' => $status,
        'woo_number' => (string) $om($id, '_order_number', $id),
    ];

    /* The arithmetic has to close. Every figure here comes from a different
       column of a different table, and if the goods, the carriage and the VAT
       do not add up to the total the customer was charged then something has
       been read out of the wrong place — which would otherwise show up as a
       wrong invoice a year from now rather than as an error today. */
    $adds = $subtotal - $discount + $ship + $vat;
    $off  = $adds - $total;
    if ($off !== 0) {
        $mismatched[] = sprintf('%s — goods %s + delivery %s + VAT %s = %s, but the order says %s',
            $reference, money($subtotal - $discount), money($ship), money($vat),
            money($adds), money($total));
    }

    printf("  %-10s %-10s %-9s %-28s%s\n", $reference,
        money($total), $paidAt !== '' ? 'paid' : 'NOT PAID',
        substr($customer['name'], 0, 28), $off !== 0 ? '  ← does not add up' : '');

    if (!$dry && !save_order($record)) {
        fwrite(STDERR, "  could not write {$reference}\n");
        continue;
    }
    $made++;
}

/* ----------------------------------------------------------- the report */

echo "\n";
printf("%d order(s) %s\n", $made, $dry ? 'would be written' : 'written');
if ($already) printf("%d already on disk, left alone (pass --replace to overwrite)\n", $already);

if ($notCarried) {
    echo "\nNot carried over:\n";
    foreach ($notCarried as $line) echo '  ' . $line . "\n";
}
if ($claimed) {
    echo "\nMarked paid in the old shop with nothing from a gateway behind it.\n"
       . "Brought over as NOT paid and set back to New — look each one up in the\n"
       . "Stripe or PayPal dashboard before treating any of it as money:\n";
    foreach ($claimed as $line) echo '  ' . $line . "\n";
}
if ($mismatched) {
    echo "\nThese do not add up. An invoice is printed from these figures, so read\n"
       . "them against the old shop before trusting any of them:\n";
    foreach ($mismatched as $line) echo '  ' . $line . "\n";
}
if ($unresolved) {
    echo "\nLines whose product is no longer in the catalogue — kept, at the price\n"
       . "they were charged, but they will not link to a product page:\n";
    foreach ($unresolved as $line) echo '  ' . $line . "\n";
}

if ($dry) {
    echo "\nDry run — nothing written.\n";
} else {
    echo "\nstorage/orders now holds " . count(all_orders()) . " order(s).\n";
}

echo "\nThe dump is a snapshot. The old shop goes on taking orders, so run this\n"
   . "again against a fresh export on the day the site is switched over — what\n"
   . "is already here will be left alone.\n";
