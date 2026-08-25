<?php
/**
 * Money, tax, delivery, payment and email behaviour.
 *
 * Everything here reads from settings(), so the admin panel drives it:
 * change the currency, the tax rate or a delivery zone and the catalogue,
 * the cart, the checkout and the order emails all follow together.
 */
declare(strict_types=1);

/** Countries the shop can sell and deliver to. */
const COUNTRIES = [
    'GB' => 'United Kingdom (UK)',
    'IE' => 'Ireland',
    'FR' => 'France',
    'DE' => 'Germany',
    'NL' => 'Netherlands',
    'BE' => 'Belgium',
    'LU' => 'Luxembourg',
    'ES' => 'Spain',
    'PT' => 'Portugal',
    'IT' => 'Italy',
    'AT' => 'Austria',
    'CH' => 'Switzerland',
    'DK' => 'Denmark',
    'SE' => 'Sweden',
    'NO' => 'Norway',
    'FI' => 'Finland',
    'IS' => 'Iceland',
    'PL' => 'Poland',
    'CZ' => 'Czechia',
    'SK' => 'Slovakia',
    'HU' => 'Hungary',
    'SI' => 'Slovenia',
    'HR' => 'Croatia',
    'RO' => 'Romania',
    'BG' => 'Bulgaria',
    'GR' => 'Greece',
    'CY' => 'Cyprus',
    'MT' => 'Malta',
    'EE' => 'Estonia',
    'LV' => 'Latvia',
    'LT' => 'Lithuania',
    'RS' => 'Serbia',
    'BA' => 'Bosnia and Herzegovina',
    'MK' => 'North Macedonia',
    'AL' => 'Albania',
    'ME' => 'Montenegro',
    'MD' => 'Moldova',
    'UA' => 'Ukraine',
    'BY' => 'Belarus',
    'RU' => 'Russia',
    'TR' => 'Turkey',
    'GE' => 'Georgia',
    'AM' => 'Armenia',
    'AZ' => 'Azerbaijan',
    'KZ' => 'Kazakhstan',
    'UZ' => 'Uzbekistan',
    'IL' => 'Israel',
    'AE' => 'United Arab Emirates',
    'SA' => 'Saudi Arabia',
    'QA' => 'Qatar',
    'KW' => 'Kuwait',
    'OM' => 'Oman',
    'BH' => 'Bahrain',
    'EG' => 'Egypt',
    'MA' => 'Morocco',
    'TN' => 'Tunisia',
    'DZ' => 'Algeria',
    'ZA' => 'South Africa',
    'NG' => 'Nigeria',
    'KE' => 'Kenya',
    'US' => 'United States (US)',
    'CA' => 'Canada',
    'MX' => 'Mexico',
    'BR' => 'Brazil',
    'AR' => 'Argentina',
    'CL' => 'Chile',
    'AU' => 'Australia',
    'NZ' => 'New Zealand',
    'IN' => 'India',
    'PK' => 'Pakistan',
    'CN' => 'China',
    'HK' => 'Hong Kong',
    'JP' => 'Japan',
    'KR' => 'South Korea',
    'SG' => 'Singapore',
    'MY' => 'Malaysia',
    'TH' => 'Thailand',
    'VN' => 'Vietnam',
    'ID' => 'Indonesia',
    'PH' => 'Philippines',
];

/** The list of country codes making up mainland Europe, for the default zone. */
const EUROPE = ['IE','FR','DE','NL','BE','LU','ES','PT','IT','AT','DK','SE','FI','PL','CZ','SK',
                'HU','SI','HR','RO','BG','GR','EE','LV','LT'];

/* ------------------------------------------------------------- currency */

/** Currencies offered in the admin, code => [name, symbol]. */
const CURRENCIES = [
    'GBP' => ['Pound sterling', '£'],
    'EUR' => ['Euro', '€'],
    'USD' => ['US dollar', '$'],
    'CHF' => ['Swiss franc', 'CHF'],
    'PLN' => ['Polish złoty', 'zł'],
    'SEK' => ['Swedish krona', 'kr'],
    'NOK' => ['Norwegian krone', 'kr'],
    'DKK' => ['Danish krone', 'kr'],
    'TRY' => ['Turkish lira', '₺'],
    'AZN' => ['Azerbaijani manat', '₼'],
    'AED' => ['UAE dirham', 'د.إ'],
    'CAD' => ['Canadian dollar', '$'],
    'AUD' => ['Australian dollar', '$'],
];

const CURRENCY_POSITIONS = [
    'left'        => 'Left (£10.00)',
    'right'       => 'Right (10.00£)',
    'left_space'  => 'Left with a space (£ 10.00)',
    'right_space' => 'Right with a space (10.00 £)',
];

function currency_symbol(): string
{
    return CURRENCIES[(string) setting('currency')][1] ?? '£';
}

/**
 * Pence to a display price.
 *
 * Prices are stored as whole pence so nothing rounds badly; only the way
 * they are printed is configurable.
 */
function money(int $pence): string
{
    static $c = null;
    if ($c === null) {
        $s = settings();
        $c = [
            'sym'  => currency_symbol(),
            'pos'  => (string) $s['currency_pos'],
            'dec'  => max(0, min(4, (int) $s['decimals'])),
            'dsep' => (string) $s['decimal_sep'],
            'tsep' => (string) $s['thousand_sep'],
        ];
    }
    $n = number_format($pence / 100, $c['dec'], $c['dsep'], $c['tsep']);
    return match ($c['pos']) {
        'right'       => $n . $c['sym'],
        'left_space'  => $c['sym'] . ' ' . $n,
        'right_space' => $n . ' ' . $c['sym'],
        default       => $c['sym'] . $n,
    };
}

/* ------------------------------------------------------------------ tax */

function tax_enabled(): bool { return (bool) setting('enable_taxes'); }
function tax_rate(): float   { return tax_enabled() ? (float) setting('vat_rate') : 0.0; }
function tax_label(): string { return (string) setting('tax_label') ?: 'VAT'; }

/** Tax due on a net amount, in pence. */
function tax_on(int $net): int
{
    return (int) round($net * tax_rate() / 100);
}

/** "excl. VAT" for the catalogue, or nothing when tax is switched off. */
function price_suffix(): string
{
    return tax_enabled() ? (string) setting('price_suffix') : '';
}

/* ------------------------------------------------------------- delivery */

const SHIPPING_TYPES = [
    'flat'   => 'Flat rate',
    'free'   => 'Free delivery',
    'pickup' => 'Collection in person',
    'quote'  => 'Quoted after ordering',
];

/** Shipping classes, by name. A product names one; a zone method can charge extra for it. */
function shipping_classes(): array
{
    $names = array_map('trim', (array) setting('shipping_classes'));
    return array_values(array_unique(array_filter($names)));
}

function shipping_zones(): array
{
    $zones = setting('shipping_zones');
    return is_array($zones) ? $zones : [];
}

/**
 * The zone a country falls in. Zones are tried in order and the first that
 * lists the country wins; a zone with no countries is the catch-all, which
 * is how "Rest of the world" works.
 */
function shipping_zone(string $country = ''): array
{
    $want  = strtoupper($country !== '' ? $country : (string) setting('default_country'));
    $empty = null;
    foreach (shipping_zones() as $zone) {
        $list = array_map('strtoupper', (array) ($zone['countries'] ?? []));
        if (!$list) { $empty = $empty ?? $zone; continue; }
        if (in_array($want, $list, true)) return $zone;
    }
    return $empty ?? ['name' => 'Delivery', 'countries' => [], 'methods' => []];
}

/**
 * Methods in a zone that a given subtotal qualifies for, cheapest first.
 *
 * $classes are the shipping classes present in the basket. A method can charge
 * extra for one — a pallet line, a long length — and the highest surcharge in
 * the basket is the one that applies, not the sum, because it goes on one
 * lorry either way.
 */
function shipping_options(int $subtotal, string $country = '', array $classes = []): array
{
    $zone = shipping_zone($country);
    $out  = [];
    foreach ((array) ($zone['methods'] ?? []) as $m) {
        if (empty($m['enabled'])) continue;
        $type = (string) ($m['type'] ?? 'flat');
        $min  = (int) ($m['min_amount'] ?? 0);
        if ($min > 0 && $subtotal < $min) continue;

        $extra = 0;
        $why   = '';
        foreach ($classes as $class) {
            $charge = (int) (($m['classes'][$class] ?? 0));
            if ($charge > $extra) { $extra = $charge; $why = $class; }
        }

        $out[] = [
            'type'      => $type,
            'title'     => (string) ($m['title'] ?? 'Delivery'),
            'estimate'  => (string) ($m['estimate'] ?? ''),
            'cost'      => ($type === 'flat' ? (int) ($m['cost'] ?? 0) : 0) + $extra,
            'surcharge' => $extra,
            'because'   => $why,
            'zone'      => (string) ($zone['name'] ?? ''),
        ];
    }
    usort($out, fn($a, $b) => $a['cost'] <=> $b['cost']);
    return $out;
}

/** The shipping classes present in a set of priced basket lines. */
function basket_classes(array $items): array
{
    $classes = [];
    foreach ($items as $item) {
        $p = find_product((string) ($item['slug'] ?? ''), true);
        $class = trim((string) (product_defaults($p ?? [])['shipping_class'] ?? ''));
        if ($class !== '') $classes[$class] = true;
    }
    return array_keys($classes);
}

/**
 * What delivery costs on a net subtotal — the cheapest method that applies,
 * so a "free over £250" rule automatically beats the flat rate.
 */
function shipping_quote(int $subtotal, string $country = '', array $classes = []): array
{
    if ($subtotal <= 0) {
        return ['cost' => 0, 'title' => 'Delivery', 'estimate' => '', 'type' => 'flat',
                'zone' => '', 'surcharge' => 0, 'because' => ''];
    }
    $options = shipping_options($subtotal, $country, $classes);
    return $options[0] ?? [
        'cost' => 0, 'type' => 'quote', 'zone' => shipping_zone($country)['name'] ?? '',
        'title' => 'Quoted after ordering', 'estimate' => '', 'surcharge' => 0, 'because' => '',
    ];
}

/** The lowest subtotal that earns free delivery in a zone, or 0 if none does. */
function free_delivery_from(string $country = ''): int
{
    $best = 0;
    foreach ((array) (shipping_zone($country)['methods'] ?? []) as $m) {
        if (empty($m['enabled']) || ($m['type'] ?? '') !== 'free') continue;
        $min = (int) ($m['min_amount'] ?? 0);
        if ($best === 0 || $min < $best) $best = $min;
    }
    return $best;
}

/** The countries the checkout offers, following the shipping settings. */
function delivery_countries(): array
{
    $mode = (string) setting('ship_to');
    if ($mode === 'none') return [];

    $codes = $mode === 'selected'
        ? (array) setting('ship_countries')
        : ((string) setting('sell_to') === 'selected' ? (array) setting('sell_countries') : []);

    if (!$codes) return COUNTRIES;

    $out = [];
    foreach ($codes as $code) {
        $code = strtoupper((string) $code);
        if (isset(COUNTRIES[$code])) $out[$code] = COUNTRIES[$code];
    }
    return $out ?: COUNTRIES;
}

/* ------------------------------------------------------------- payments */

/** Payment methods, in the order the admin put them. */
function payment_methods(bool $enabledOnly = true): array
{
    $rows = (array) setting('payment_methods');
    if ($enabledOnly) $rows = array_filter($rows, fn($m) => !empty($m['enabled']));
    return array_values($rows);
}

function find_payment_method(string $id): ?array
{
    foreach (payment_methods(false) as $m) {
        if (($m['id'] ?? '') === $id) return $m;
    }
    return null;
}

/** The method a checkout should default to. */
function default_payment_method(): array
{
    $rows = payment_methods();
    return $rows[0] ?? ['id' => '', 'title' => 'Proforma invoice', 'description' => '', 'instructions' => ''];
}

/* ------------------------------------------------------------- products */

/** Everything a product record carries beyond what the importer produced. */
const PRODUCT_EXTRAS = [
    'sale_min'          => 0,       // pence; 0 means not on sale
    'sale_max'          => 0,
    'sale_from'         => '',      // Y-m-d, blank for no start
    'sale_to'           => '',      // Y-m-d, blank for no end
    'manage_stock'      => false,
    'stock_qty'         => 0,
    'backorders'        => 'no',    // no | notify | yes
    'low_stock'         => 0,       // 0 falls back to the shop-wide figure
    'sold_individually' => false,
    'weight'            => '',
    'length'            => '',
    'width'             => '',
    'height'            => '',
    'shipping_class'    => '',
    'upsells'           => [],
    'crosssells'        => [],
    'purchase_note'     => '',
    'menu_order'        => 0,
    'virtual'           => false,
];

const BACKORDER_MODES = [
    'no'     => 'Do not allow',
    'notify' => 'Allow, but tell the customer',
    'yes'    => 'Allow',
];

const STOCK_DISPLAY = [
    'always' => 'Always show how many are left',
    'low'    => 'Only when stock is low',
    'never'  => 'Never show a number',
];

/** Fill in anything an older record is missing, so views can read it freely. */
function product_defaults(array $p): array
{
    return $p + PRODUCT_EXTRAS;
}

/** Is this product's sale price live today? */
function on_sale(array $p): bool
{
    $p = product_defaults($p);
    if ((int) $p['sale_min'] <= 0) return false;

    $today = date('Y-m-d');
    if ($p['sale_from'] !== '' && $today < $p['sale_from']) return false;
    if ($p['sale_to']   !== '' && $today > $p['sale_to'])   return false;
    return true;
}

/** What a product costs today: the sale price when one is running. */
function effective_min(array $p): int
{
    return on_sale($p) ? (int) product_defaults($p)['sale_min'] : (int) $p['price_min'];
}

function effective_max(array $p): int
{
    return on_sale($p) ? (int) product_defaults($p)['sale_max'] : (int) $p['price_max'];
}

/** What one variant costs today. */
function variant_price(array $v, array $p): int
{
    $sale = (int) ($v['sale'] ?? 0);
    return ($sale > 0 && on_sale($p)) ? $sale : (int) $v['price'];
}

/** How much off, as a percentage, for the sale badge. */
function sale_percent(array $p): int
{
    if (!on_sale($p) || (int) $p['price_min'] <= 0) return 0;
    return (int) round((1 - effective_min($p) / (int) $p['price_min']) * 100);
}

/**
 * What the shop can say about this product's stock.
 *
 * Returns state (in | low | backorder | out), how many are left when that is
 * being shown, and the words to print.
 */
function stock_state(array $p): array
{
    $p   = product_defaults($p);
    $out = ($p['stock'] ?? 'instock') === 'outofstock';

    if (!$p['manage_stock']) {
        return $out
            ? ['state' => 'out', 'qty' => null, 'label' => 'Out of stock']
            : ['state' => 'in',  'qty' => null, 'label' => 'In stock'];
    }

    $qty  = (int) $p['stock_qty'];
    $low  = (int) ($p['low_stock'] ?: setting('low_stock_qty'));
    $show = (string) setting('stock_display');

    if ($qty <= 0) {
        if ($p['backorders'] === 'no' || $out) {
            return ['state' => 'out', 'qty' => 0, 'label' => 'Out of stock'];
        }
        return ['state' => 'backorder', 'qty' => 0,
                'label' => $p['backorders'] === 'notify' ? 'Available on backorder' : 'In stock'];
    }

    $isLow  = $qty <= $low;
    $number = $show === 'always' || ($show === 'low' && $isLow);

    return [
        'state' => $isLow ? 'low' : 'in',
        'qty'   => $qty,
        'label' => $number ? ($qty . ' in stock') : 'In stock',
    ];
}

/** Can this many be added to a basket? */
function stock_allows(array $p, int $qty): bool
{
    $p = product_defaults($p);
    if (($p['stock'] ?? 'instock') === 'outofstock') return false;
    if (!$p['manage_stock']) return true;
    if ($p['backorders'] !== 'no') return true;
    return $qty <= (int) $p['stock_qty'];
}

/** The most one order may take, or 0 for no limit. */
function stock_ceiling(array $p): int
{
    $p = product_defaults($p);
    if (!empty($p['sold_individually'])) return 1;
    if (!$p['manage_stock'] || $p['backorders'] !== 'no') return 0;
    return max(0, (int) $p['stock_qty']);
}

/* --------------------------------------------------------------- basket */

/**
 * Re-price posted basket lines from the catalogue.
 *
 * The basket lives in the visitor's browser, so nothing it says about price
 * is trusted: only the slug, the chosen option and the quantity are read
 * back, and the money comes from data/products.php every time.
 */
function price_basket_lines(array $lines): array
{
    $items = [];
    foreach ($lines as $line) {
        $p = find_product((string) ($line['slug'] ?? ''));
        if (!$p) continue;

        $qty    = max(1, min(999, (int) ($line['qty'] ?? 1)));
        $option = (string) ($line['option'] ?? '');
        $price  = null;

        if ($p['variants']) {
            foreach ($p['variants'] as $v) {
                if ($v['label'] === $option) { $price = variant_price($v, $p); break; }
            }
        } elseif ($p['price_min'] > 0) {
            $price = effective_min($p);
        }
        if ($price === null) continue;      // unknown option, or price on request

        // stock has the last word: the browser can ask for any quantity it
        // likes, but a sold-individually or limited line is capped here
        $ceiling = stock_ceiling($p);
        if ($ceiling > 0) $qty = min($qty, $ceiling);
        if (!stock_allows($p, $qty)) continue;

        $items[] = [
            'slug'   => $p['slug'],
            'title'  => $p['name'],
            'option' => $option,
            'qty'    => $qty,
            'price'  => $price,
            'line'   => $price * $qty,
        ];
    }
    return $items;
}

/* -------------------------------------------------------------- reviews */

const REVIEW_STATUSES = [
    'approved' => 'Published',
    'pending'  => 'Awaiting approval',
    'spam'     => 'Spam',
];

function reviews_enabled(): bool
{
    return (bool) setting('enable_reviews');
}

function all_reviews(): array
{
    return data('reviews');
}

/** The published reviews of one product, newest first. */
function product_reviews(string $slug): array
{
    $rows = array_values(array_filter(all_reviews(),
        fn($r) => $r['product'] === $slug && $r['status'] === 'approved'));
    usort($rows, fn($a, $b) => strcmp($b['created'], $a['created']));
    return $rows;
}

/**
 * The star summary for a product: average, count, and how many gave each
 * score. Returns null when there is nothing to show, so callers can leave
 * the whole block out rather than print an empty one.
 */
function rating_summary(string $slug): ?array
{
    $rows = product_reviews($slug);
    if (!$rows) return null;

    $spread = array_fill_keys([5, 4, 3, 2, 1], 0);
    $total  = 0;
    foreach ($rows as $r) {
        $stars = max(1, min(5, (int) $r['rating']));
        $spread[$stars]++;
        $total += $stars;
    }

    return [
        'count'   => count($rows),
        'average' => round($total / count($rows), 1),
        'spread'  => $spread,
    ];
}

/** Five stars as inline SVG, filled to the given score. */
function stars(float $score, int $size = 15): string
{
    $out = '<span class="stars" role="img" aria-label="'
         . e(rtrim(rtrim(number_format($score, 1), '0'), '.')) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $on = $score >= $i - 0.25;
        $out .= '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
              . 'class="' . ($on ? 'on' : 'off') . '" aria-hidden="true">'
              . '<path d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5-5.9-3.1-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z"/></svg>';
    }
    return $out . '</span>';
}

/** Has this email address actually ordered this product? */
function has_bought(string $email, string $slug): bool
{
    $email = lower(trim($email));
    if ($email === '' || !function_exists('all_orders')) return false;

    foreach (all_orders() as $order) {
        if (lower((string) ($order['customer']['email'] ?? '')) !== $email) continue;
        if (($order['status'] ?? 'new') === 'cancelled') continue;
        foreach ($order['order']['items'] ?? [] as $item) {
            if (($item['slug'] ?? '') === $slug) return true;
        }
    }
    return false;
}

/* -------------------------------------------------------------- coupons */

const COUPON_TYPES = [
    'percent' => 'Percentage off',
    'fixed'   => 'Fixed amount off',
];

function coupons_enabled(): bool
{
    return (bool) setting('enable_coupons');
}

function all_coupons(): array
{
    return data('coupons');
}

function find_coupon(string $code): ?array
{
    $want = lower(trim($code));
    if ($want === '') return null;
    foreach (all_coupons() as $c) {
        if (lower((string) $c['code']) === $want) return $c;
    }
    return null;
}

/** "10% off" or "£15.00 off". */
function coupon_label(array $c): string
{
    if (($c['type'] ?? 'percent') === 'percent') {
        $n = rtrim(rtrim(number_format((float) $c['amount'], 2, '.', ''), '0'), '.');
        return $n . '% off';
    }
    return money((int) $c['amount']) . ' off';
}

/** Does this coupon cover a given product? No list means everything. */
function coupon_covers(array $c, string $slug): bool
{
    $products   = (array) ($c['products'] ?? []);
    $categories = (array) ($c['categories'] ?? []);
    if (!$products && !$categories) return true;
    if (in_array($slug, $products, true)) return true;

    $p = find_product($slug, true);
    if (!$p) return false;
    foreach ($categories as $cat) {
        if (in_array($cat, $p['cats'], true)) return true;
    }
    return false;
}

/**
 * Check a code against a basket and work out what it takes off.
 *
 * Every rule lives here and nowhere else, so the cart, the checkout and the
 * stored order can never disagree about what a coupon is worth. Returns
 * ['ok' => false, 'error' => 'why'] when it does not apply.
 */
function coupon_apply(string $code, array $items, int $subtotal): array
{
    $fail = fn(string $why) => ['ok' => false, 'error' => $why, 'code' => '', 'title' => '',
                                'discount' => 0, 'free_shipping' => false];

    if (!coupons_enabled())   return $fail('Discount codes are not being accepted at the moment.');
    if (trim($code) === '')   return $fail('Enter a code.');

    $c = find_coupon($code);
    if (!$c || empty($c['enabled'])) return $fail('That code was not recognised.');

    $today = date('Y-m-d');
    if (($c['starts'] ?? '')  !== '' && $today < $c['starts'])  return $fail('That code is not active yet.');
    if (($c['expires'] ?? '') !== '' && $today > $c['expires']) return $fail('That code has expired.');
    if ((int) ($c['usage_limit'] ?? 0) > 0 && (int) ($c['used'] ?? 0) >= (int) $c['usage_limit']) {
        return $fail('That code has been used up.');
    }
    if ((int) ($c['min_spend'] ?? 0) > 0 && $subtotal < (int) $c['min_spend']) {
        return $fail('That code needs an order of at least ' . money((int) $c['min_spend']) . '.');
    }
    if ((int) ($c['max_spend'] ?? 0) > 0 && $subtotal > (int) $c['max_spend']) {
        return $fail('That code only applies to orders up to ' . money((int) $c['max_spend']) . '.');
    }

    // only the lines the coupon covers count towards the discount
    $eligible = 0;
    foreach ($items as $item) {
        if (coupon_covers($c, (string) ($item['slug'] ?? ''))) $eligible += (int) ($item['line'] ?? 0);
    }
    if ($eligible <= 0) return $fail('That code does not apply to anything in your basket.');

    $discount = ($c['type'] ?? 'percent') === 'percent'
        ? (int) round($eligible * min(100, max(0, (float) $c['amount'])) / 100)
        : min((int) $c['amount'], $eligible);

    return [
        'ok'            => true,
        'error'         => '',
        'code'          => (string) $c['code'],
        'title'         => trim((string) ($c['description'] ?? '')) !== ''
                              ? (string) $c['description'] : coupon_label($c),
        'discount'      => max(0, min($discount, $subtotal)),
        'free_shipping' => !empty($c['free_shipping']),
    ];
}

/* --------------------------------------------------------------- emails */

/**
 * The messages the site can send. "to" says who receives it: shop means the
 * address on the Emails tab, customer means whoever placed the order.
 */
const EMAIL_KINDS = [
    'new_order'   => ['label' => 'New order',            'to' => 'shop',
                      'when'  => 'Sent to you as soon as an order is placed.'],
    'order_placed' => ['label' => 'Order received',      'to' => 'customer',
                      'when'  => 'The confirmation the customer gets with their order summary.'],
    'order_status' => ['label' => 'Order status changed', 'to' => 'customer',
                      'when'  => 'Sent when you move an order to confirmed, invoiced or shipped.'],
    'enquiry'     => ['label' => 'New enquiry',          'to' => 'shop',
                      'when'  => 'Sent to you when someone uses the contact form.'],
    'enquiry_ack' => ['label' => 'Enquiry received',     'to' => 'customer',
                      'when'  => 'The acknowledgement the sender gets back.'],
    'review'      => ['label' => 'Review to approve',    'to' => 'shop',
                      'when'  => 'Sent to you when somebody reviews a product.'],
];

/** One notification's settings, with anything unsaved filled from the defaults. */
function email_conf(string $kind): array
{
    $all  = (array) setting('emails');
    $row  = (array) ($all[$kind] ?? []);
    return array_merge(
        ['enabled' => true, 'to' => '', 'subject' => '', 'heading' => ''],
        $row
    );
}

/** Replace {reference}, {name}, {site} and friends in a subject or heading. */
function email_tokens(string $text, array $vars): string
{
    foreach ($vars as $key => $value) {
        $text = str_replace('{' . $key . '}', (string) $value, $text);
    }
    return $text;
}

/**
 * Wrap body HTML in the email template.
 *
 * Deliberately table-based with inline styles — that is the only thing every
 * mail client still renders the same way.
 */
function email_html(string $heading, string $bodyHtml, string $preheader = ''): string
{
    $accent = (string) setting('email_accent');
    $bg     = (string) setting('email_bg');
    $card   = (string) setting('email_body_bg');
    $ink    = (string) setting('email_text');
    $footer = email_tokens((string) setting('email_footer'), ['site' => SITE_NAME, 'year' => date('Y')]);
    $logo   = (string) setting('email_logo');
    $font   = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    $logoTag = '';
    if ($logo !== '') {
        $src = str_starts_with($logo, 'http') ? $logo : SITE_URL . '/' . ltrim($logo, '/');
        $logoTag = '<img src="' . e($src) . '" alt="' . e(SITE_NAME) . '" width="150" '
                 . 'style="display:block;border:0;max-width:150px;height:auto;margin:0 auto 6px">';
    }

    return '<!doctype html><html><body style="margin:0;padding:0;background:' . e($bg) . ';">'
      . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($preheader) . '</div>' : '')
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . e($bg) . ';padding:24px 12px;">'
      . '<tr><td align="center">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:' . e($card) . ';border-radius:12px;overflow:hidden;font-family:' . $font . ';color:' . e($ink) . ';">'
      . '<tr><td style="background:' . e($accent) . ';padding:26px 30px;text-align:center;">'
      . $logoTag
      . '<h1 style="margin:0;font-size:21px;line-height:1.25;color:#fff;font-weight:700;">' . e($heading) . '</h1>'
      . '</td></tr>'
      . '<tr><td style="padding:28px 30px;font-size:15px;line-height:1.6;">' . $bodyHtml . '</td></tr>'
      . '<tr><td style="padding:18px 30px 26px;border-top:1px solid #e6e9ef;font-size:12.5px;line-height:1.6;color:#6b7688;">'
      . nl2br(e($footer)) . '</td></tr>'
      . '</table></td></tr></table></body></html>';
}

/** An order's lines as an HTML table for the email template. */
function email_order_table(array $order): string
{
    $rows = '';
    foreach ($order['items'] as $item) {
        $name = e($item['title']) . ($item['option'] !== '' ? '<br><span style="color:#6b7688;font-size:13px">' . e($item['option']) . '</span>' : '');
        $rows .= '<tr>'
              . '<td style="padding:9px 0;border-bottom:1px solid #eef1f6;font-size:14px;">' . $name . '</td>'
              . '<td style="padding:9px 0;border-bottom:1px solid #eef1f6;font-size:14px;text-align:center;">' . (int) $item['qty'] . '</td>'
              . '<td style="padding:9px 0;border-bottom:1px solid #eef1f6;font-size:14px;text-align:right;white-space:nowrap;">' . e(money((int) $item['line'])) . '</td>'
              . '</tr>';
    }

    $total = function (string $label, string $value, bool $strong = false) {
        $w = $strong ? 'font-weight:700;font-size:16px;' : '';
        return '<tr><td colspan="2" style="padding:6px 0;text-align:right;color:#5b6880;' . $w . '">' . e($label) . '</td>'
             . '<td style="padding:6px 0;text-align:right;white-space:nowrap;' . $w . '">' . e($value) . '</td></tr>';
    };

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:6px 0 4px;">'
      . '<tr><th align="left" style="padding:0 0 8px;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;color:#5b6880;">Item</th>'
      . '<th style="padding:0 0 8px;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;color:#5b6880;">Qty</th>'
      . '<th align="right" style="padding:0 0 8px;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;color:#5b6880;">Total</th></tr>'
      . $rows
      . $total('Subtotal', money((int) $order['subtotal']))
      . (!empty($order['discount'])
            ? $total(trim('Discount ' . ($order['coupon'] ?? '')), '-' . money((int) $order['discount']))
            : '')
      . $total($order['shipping_title'] ?? 'Delivery', $order['shipping'] ? money((int) $order['shipping']) : 'Free')
      . (tax_enabled() ? $total(tax_label() . ' at ' . (int) tax_rate() . '%', money((int) $order['vat'])) : '')
      . $total('Total', money((int) $order['total']), true)
      . '</table>';
}
