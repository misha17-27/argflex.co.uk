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

/** Methods in a zone that a given subtotal qualifies for, cheapest first. */
function shipping_options(int $subtotal, string $country = ''): array
{
    $zone = shipping_zone($country);
    $out  = [];
    foreach ((array) ($zone['methods'] ?? []) as $m) {
        if (empty($m['enabled'])) continue;
        $type = (string) ($m['type'] ?? 'flat');
        $min  = (int) ($m['min_amount'] ?? 0);
        if ($min > 0 && $subtotal < $min) continue;
        $out[] = [
            'type'     => $type,
            'title'    => (string) ($m['title'] ?? 'Delivery'),
            'estimate' => (string) ($m['estimate'] ?? ''),
            'cost'     => $type === 'flat' ? (int) ($m['cost'] ?? 0) : 0,
            'zone'     => (string) ($zone['name'] ?? ''),
        ];
    }
    usort($out, fn($a, $b) => $a['cost'] <=> $b['cost']);
    return $out;
}

/**
 * What delivery costs on a net subtotal — the cheapest method that applies,
 * so a "free over £250" rule automatically beats the flat rate.
 */
function shipping_quote(int $subtotal, string $country = ''): array
{
    if ($subtotal <= 0) {
        return ['cost' => 0, 'title' => 'Delivery', 'estimate' => '', 'type' => 'flat', 'zone' => ''];
    }
    $options = shipping_options($subtotal, $country);
    return $options[0] ?? [
        'cost' => 0, 'type' => 'quote', 'zone' => shipping_zone($country)['name'] ?? '',
        'title' => 'Quoted after ordering', 'estimate' => '',
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
      . $total($order['shipping_title'] ?? 'Delivery', $order['shipping'] ? money((int) $order['shipping']) : 'Free')
      . (tax_enabled() ? $total(tax_label() . ' at ' . (int) tax_rate() . '%', money((int) $order['vat'])) : '')
      . $total('Total', money((int) $order['total']), true)
      . '</table>';
}
