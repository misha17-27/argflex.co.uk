<?php
/**
 * Sales figures, worked out from the orders on file.
 *
 * There is no database to query, so everything here reads storage/orders/
 * and adds it up. That is fine at this size — a shop with tens of thousands
 * of orders would want an index, and the shape of these functions is what
 * you would put behind one.
 *
 * Cancelled orders are counted but never contribute money.
 */
declare(strict_types=1);

/** The ranges the Reports screen offers, in days. 0 means everything. */
const REPORT_RANGES = [
    '7'   => 'Last 7 days',
    '30'  => 'Last 30 days',
    '90'  => 'Last 90 days',
    '365' => 'Last 12 months',
    '0'   => 'All time',
];

/** Orders inside a range, newest first. */
function orders_in_range(int $days): array
{
    $orders = all_orders();
    if ($days <= 0) return $orders;

    $from = date('Y-m-d', strtotime("-{$days} days"));
    return array_values(array_filter($orders,
        fn($o) => substr((string) ($o['placed_at'] ?? ''), 0, 10) >= $from));
}

/** Did this order bring money in? */
function order_counts(array $order): bool
{
    return ($order['status'] ?? 'new') !== 'cancelled';
}

/** Headline figures for a set of orders. */
function report_totals(array $orders): array
{
    $paid = array_values(array_filter($orders, 'order_counts'));

    $revenue  = 0;
    $goods    = 0;
    $tax      = 0;
    $ship     = 0;
    $saved    = 0;
    $units    = 0;
    $refunded = 0;
    foreach ($paid as $o) {
        // What was actually kept. A refunded order still happened, and its
        // items still left the shelf, but the money went back.
        $refunded += refunded_total($o);
        $revenue  += (int) ($o['order']['total'] ?? 0);
        $goods   += (int) ($o['order']['subtotal'] ?? 0);
        $tax     += (int) ($o['order']['vat'] ?? 0);
        $ship    += (int) ($o['order']['shipping'] ?? 0);
        $saved   += (int) ($o['order']['discount'] ?? 0);
        foreach ($o['order']['items'] ?? [] as $item) $units += (int) $item['qty'];
    }

    return [
        'orders'    => count($orders),
        'paid'      => count($paid),
        'cancelled' => count($orders) - count($paid),
        'revenue'   => $revenue,
        'goods'     => $goods,
        'tax'       => $tax,
        'shipping'  => $ship,
        'discounts' => $saved,
        'units'     => $units,
        'refunded'  => $refunded,
        'kept'      => $revenue - $refunded,
        'average'   => $paid ? (int) round(($revenue - $refunded) / count($paid)) : 0,
        'customers' => count(array_unique(array_map(
            fn($o) => lower((string) ($o['customer']['email'] ?? '')), $paid))),
    ];
}

/**
 * Revenue and order count per day or per month across a range.
 *
 * Every bucket in the range is present even when nothing sold, so a chart
 * shows the gaps instead of quietly closing them up.
 */
function report_series(array $orders, int $days): array
{
    $monthly = $days === 0 || $days > 120;
    $format  = $monthly ? 'Y-m' : 'Y-m-d';

    $buckets = [];
    if ($days > 0) {
        $step = $monthly ? 'month' : 'day';
        $span = $monthly ? (int) ceil($days / 30) : $days;
        for ($i = $span - 1; $i >= 0; $i--) {
            $buckets[date($format, strtotime("-{$i} {$step}"))] = ['revenue' => 0, 'orders' => 0];
        }
    } else {
        // all time: from the first order to today
        $stamps = array_filter(array_map(fn($o) => (string) ($o['placed_at'] ?? ''), $orders));
        $start  = $stamps ? min($stamps) : date('c');
        $cursor = strtotime(date('Y-m-01', strtotime($start)));
        $end    = strtotime(date('Y-m-01'));
        while ($cursor <= $end) {
            $buckets[date($format, $cursor)] = ['revenue' => 0, 'orders' => 0];
            $cursor = strtotime('+1 month', $cursor);
        }
    }

    foreach ($orders as $o) {
        if (!order_counts($o)) continue;
        $key = date($format, strtotime((string) $o['placed_at']));
        if (!isset($buckets[$key])) continue;
        $buckets[$key]['revenue'] += (int) ($o['order']['total'] ?? 0) - refunded_total($o);
        $buckets[$key]['orders']++;
    }

    return ['monthly' => $monthly, 'buckets' => $buckets];
}

/** Products ranked by what they brought in. */
function report_products(array $orders, int $limit = 10): array
{
    $rows = [];
    foreach ($orders as $o) {
        if (!order_counts($o)) continue;
        foreach ($o['order']['items'] ?? [] as $item) {
            $slug = (string) $item['slug'];
            $rows[$slug]['slug']  = $slug;
            $rows[$slug]['title'] = (string) $item['title'];
            $rows[$slug]['qty']   = ($rows[$slug]['qty']   ?? 0) + (int) $item['qty'];
            $rows[$slug]['value'] = ($rows[$slug]['value'] ?? 0) + (int) $item['line'];
            $rows[$slug]['orders'] = ($rows[$slug]['orders'] ?? 0) + 1;
        }
    }
    uasort($rows, fn($a, $b) => $b['value'] <=> $a['value']);
    return array_slice($rows, 0, $limit, true);
}

/** Categories ranked by what they brought in. */
function report_categories(array $orders, int $limit = 8): array
{
    $rows = [];
    foreach (report_products($orders, 1000) as $p) {
        $product = find_product($p['slug'], true);
        $cats    = $product ? $product['cats'] : [];
        if (!$cats) $cats = ['uncategorised'];

        // a product in two categories counts once in each, which is what the
        // shop owner means when they ask what a category is worth
        foreach ($cats as $slug) {
            $cat = find_category($slug);
            $rows[$slug]['name']  = $cat['name'] ?? 'Uncategorised';
            $rows[$slug]['slug']  = $slug;
            $rows[$slug]['qty']   = ($rows[$slug]['qty']   ?? 0) + $p['qty'];
            $rows[$slug]['value'] = ($rows[$slug]['value'] ?? 0) + $p['value'];
        }
    }
    uasort($rows, fn($a, $b) => $b['value'] <=> $a['value']);
    return array_slice($rows, 0, $limit, true);
}

/** How many orders sit in each status. */
function report_statuses(array $orders): array
{
    $counts = array_fill_keys(array_keys(ORDER_STATUSES), 0);
    foreach ($orders as $o) {
        $status = $o['status'] ?? 'new';
        if (isset($counts[$status])) $counts[$status]++;
    }
    return $counts;
}

/** Which delivery zones and discount codes were used. */
function report_breakdown(array $orders, string $field): array
{
    $rows = [];
    foreach ($orders as $o) {
        if (!order_counts($o)) continue;
        $key = trim((string) ($o['order'][$field] ?? ''));
        if ($key === '') continue;
        $rows[$key]['label']  = $key;
        $rows[$key]['orders'] = ($rows[$key]['orders'] ?? 0) + 1;
        $rows[$key]['value']  = ($rows[$key]['value']  ?? 0) + (int) ($o['order']['total'] ?? 0);
    }
    uasort($rows, fn($a, $b) => $b['value'] <=> $a['value']);
    return $rows;
}

/**
 * A bar chart as inline SVG.
 *
 * No charting library — the whole site ships one 17 KB script, and a bar
 * chart is a handful of rectangles.
 */
function bar_chart(array $buckets, bool $monthly): string
{
    if (!$buckets) return '<p class="muted">Nothing in this range yet.</p>';

    $values = array_column($buckets, 'revenue');
    $peak   = max($values) ?: 1;
    $count  = count($buckets);

    $w = 900;
    $h = 220;
    $pad = 26;
    $gap = $count > 60 ? 1 : ($count > 30 ? 2 : 5);
    $barW = max(1.0, (($w - $pad * 2) - $gap * ($count - 1)) / $count);

    $bars = '';
    $labels = '';
    $i = 0;
    foreach ($buckets as $key => $row) {
        $x  = $pad + $i * ($barW + $gap);
        $bh = $row['revenue'] > 0 ? max(2, ($h - $pad * 2) * $row['revenue'] / $peak) : 0;
        $y  = $h - $pad - $bh;

        $when = $monthly ? date('M Y', strtotime($key . '-01')) : date('j M', strtotime($key));
        $tip  = $when . ' — ' . money((int) $row['revenue'])
              . ' from ' . $row['orders'] . ' order' . ($row['orders'] === 1 ? '' : 's');

        $bars .= '<rect x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($barW, 2)
               . '" height="' . round($bh, 2) . '" rx="' . ($barW > 6 ? 2 : 0) . '">'
               . '<title>' . e($tip) . '</title></rect>';

        // only label the ends and the middle, so they never collide
        if ($i === 0 || $i === $count - 1 || ($count > 4 && $i === intdiv($count, 2))) {
            $anchor = $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle');
            $labels .= '<text class="ax" x="' . round($x + $barW / 2, 2) . '" y="' . ($h - 8)
                     . '" text-anchor="' . $anchor . '">' . e($when) . '</text>';
        }
        $i++;
    }

    return '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" '
         . 'aria-label="Revenue per ' . ($monthly ? 'month' : 'day') . '">'
         . '<line class="base" x1="' . $pad . '" y1="' . ($h - $pad) . '" x2="' . ($w - $pad)
         . '" y2="' . ($h - $pad) . '"/>'
         . '<text class="ax" x="' . $pad . '" y="18">' . e(money((int) $peak)) . '</text>'
         . $bars . $labels . '</svg>';
}
