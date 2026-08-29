<?php
/**
 * Site configuration, data access and view helpers.
 */
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

/* Currencies, countries, tax, delivery and email behaviour. Loaded first so
   the defaults below can name its constants. */
require_once __DIR__ . '/commerce.php';
require_once __DIR__ . '/shipping.php';
require_once __DIR__ . '/gateways.php';
require_once __DIR__ . '/pending.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/security.php';

/* Claim the form cookie here, while nothing has been printed yet.
   Leaving it to the first form on the page does not work: by the time a
   <form> is being written the headers have gone, setcookie() is a no-op, and
   every token minted from that seed is checked against a cookie the browser
   was never given. It is one strictly-necessary cookie, exempt from consent,
   and the shop serves no page from a cache that this could poison. */
if (PHP_SAPI !== 'cli') form_seed();

/**
 * Editable settings live in storage/settings.php, written by the admin panel.
 * The defaults below are what ships, and what is used until anything is saved.
 */
function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $defaults = [
            'site_name'     => 'Arg Flex Ltd',
            'site_tag'      => 'Solutions for fluid transfer and industrial applications',
            'phone'         => '+44 (0) 7717 217388',
            'phone_href'    => '+447717217388',
            // Digits only, no + and no spaces — that is the shape wa.me wants.
            // Blank hides the WhatsApp button everywhere.
            'whatsapp'      => '447717217388',
            'email'         => 'sales@argflex.co.uk',
            'address'       => '1st floor, 107 George Lane, South Woodford, London, E18 1AN',
            'hours_week'    => 'Mon–Fri 9:00–17:00',
            'hours_weekend' => 'Sat–Sun 10:00–18:00',
            'asset_ver'     => '24',

            /* --- where the business is; used on the contacts page and in emails --- */
            'store_addr1'    => '1st floor',
            'store_addr2'    => '107 George Lane, South Woodford',
            'store_city'     => 'London',
            'store_postcode' => 'E18 1AN',
            'store_country'  => 'GB',

            /* --- who can order, and where we deliver --- */
            'sell_to'         => 'all',      // all | selected
            'sell_countries'  => [],
            'ship_to'         => 'sell',     // sell | selected | none
            'ship_countries'  => [],
            'default_country' => 'GB',

            /* --- currency; this is only how prices are printed --- */
            'currency'     => 'GBP',
            'currency_pos' => 'left',        // left | right | left_space | right_space
            'thousand_sep' => ',',
            'decimal_sep'  => '.',
            'decimals'     => 2,

            /* --- tax --- */
            'enable_taxes'  => true,
            'vat_rate'      => 20,           // percent
            'tax_label'     => 'Tax',        // live says Tax, not VAT
            'display_shop'  => 'excl',       // excl | incl - how the catalogue shows prices
            'display_cart'  => 'excl',
            'price_suffix'  => 'Exl vat',    // the live wording, typo and all

            /* Rates that differ by country. The rate above is the default and
               what the catalogue quotes; a country listed here overrides it
               once the customer says where they are. Exporting outside the UK
               is usually zero-rated, which is why this exists at all. */
            'tax_rates'     => [
                ['countries' => [], 'rate' => 0, 'label' => 'Zero-rated export',
                 'note' => 'Outside the UK', 'enabled' => false],
            ],
            'enable_coupons' => false,

            /* --- catalogue --- */
            'shop_per_page'     => 48,   // the live shop lists all 37 on one page; paging starts beyond this
            'default_sort'      => 'default',   // default | name | price-asc | price-desc | new
            'hide_out_of_stock' => false,
            'enable_wishlist'   => true,
            'enable_compare'    => true,
            'enable_reviews'    => false,
            'review_approval'   => true,    // hold new reviews until approved
            'review_verified'   => false,   // only people who ordered it may review
            'shop_notice'       => '',
            'weight_unit'       => 'kg',
            'dimension_unit'    => 'cm',

            /* --- stock --- */
            'manage_stock'      => false,   // the default for a new product
            'low_stock_qty'     => 2,
            'stock_display'     => 'low',   // always | low | never
            'placeholder_image' => '',

            /* --- delivery ---

               The rates, the rules that decide which of them a basket may
               use, and the two packages that can split one order into two
               consignments all live in data/shipping.php, taken from the
               live shop. Nothing about carriage is configured here any more:
               it depends on the metres in the basket, not on its value, and
               a settings screen with a flat price per zone could not express
               that without lying about it. */
            'shipping_classes' => ['1m', '5m', '1-2 days delivery', '3-4 days delivery'],

            /* --- how customers pay ---

               The live shop shows exactly two options, in this order, with
               PayPal selected by default and no description under either.
               Nothing hides one: no minimum, no country rule, no tie to a
               delivery method. The only real condition in the whole payment
               configuration is Stripe's £0.30 floor, and carriage starts at
               £3.20, so no order can reach it.

               The titles are the ones the customer actually sees, which are
               not the ones stored in the WordPress settings — Stripe's plugin
               overwrites `Credit Card (Stripe)` with `Credit / Debit Card` at
               run time, and the stored description with an empty string.

               Taking the money still needs the gateways themselves: a Stripe
               account and a PayPal merchant account, with their keys entered
               under Settings. Until that is done these two record the
               customer's choice and the order is confirmed by email, exactly
               as the proforma route below does. */
            'payment_methods' => [
                ['id' => 'ppcp', 'enabled' => true, 'title' => 'PayPal',
                 'description'  => '',
                 'instructions' => 'After clicking "Pay with PayPal", you will be redirected to PayPal to complete your purchase securely.'],
                ['id' => 'stripe', 'enabled' => true, 'title' => 'Credit / Debit Card',
                 'description'  => '',
                 'instructions' => 'Your card is charged when the order is placed.'],

                /* Kept, switched off, for the invoice route the new site was
                   built around. Turn one on if the gateways are not ready. */
                ['id' => 'proforma', 'enabled' => false, 'title' => 'Proforma invoice',
                 'description'  => 'We confirm stock and cut lengths, then email a proforma invoice with our bank details.',
                 'instructions' => 'Please quote your order reference with the payment. Goods are despatched once it clears.'],
                ['id' => 'bacs', 'enabled' => false, 'title' => 'Direct bank transfer',
                 'description'  => 'Pay straight into our account.',
                 'instructions' => 'Arg Flex Ltd - the sort code and account number are on the invoice.'],
                ['id' => 'collection', 'enabled' => false, 'title' => 'Pay on collection',
                 'description'  => 'Settle up when you pick the order up.',
                 'instructions' => 'Card or cash accepted at the counter.'],
            ],

            /* --- what the site emails, and how those emails look --- */
            'emails' => [
                'new_order'    => ['enabled' => true,  'to' => '',
                                   'subject' => 'New order {reference}',
                                   'heading' => 'You have a new order'],
                'order_placed' => ['enabled' => true,  'to' => '',
                                   'subject' => 'Your {site} order {reference}',
                                   'heading' => 'Thank you for your order'],
                'order_status' => ['enabled' => false, 'to' => '',
                                   'subject' => 'Order {reference} is now {status}',
                                   'heading' => 'Your order has been updated'],
                'enquiry'      => ['enabled' => true,  'to' => '',
                                   'subject' => 'Website enquiry from {name}',
                                   'heading' => 'New enquiry from the website'],
                'enquiry_ack'  => ['enabled' => true,  'to' => '',
                                   'subject' => 'We have your message - {site}',
                                   'heading' => 'Thanks for getting in touch'],
                'review'       => ['enabled' => true,  'to' => '',
                                   'subject' => 'New review of {product}',
                                   'heading' => 'A review is waiting for you'],
                'password_reset' => ['enabled' => true, 'to' => '',
                                   'subject' => 'Reset your {site} password',
                                   'heading' => 'Set a new password'],
            ],
            'email_logo'    => 'assets/img/site/logo.png',
            'email_accent'  => '#ff5a1f',
            'email_bg'      => '#f6f8fb',
            'email_body_bg' => '#ffffff',
            'email_text'    => '#0b1220',
            'email_footer'  => "{site}\n107 George Lane, South Woodford, London, E18 1AN\nSent automatically - replies reach a real person.",

            /* --- what goes on an invoice --- */
            'company_number' => '',
            'vat_number'     => '',
            'bank_name'      => '',
            'bank_sort'      => '',
            'bank_account'   => '',
            'bank_iban'      => '',
            'bank_bic'       => '',
            'invoice_prefix' => 'AF-',
            'invoice_next'   => 1,
            'invoice_days'   => 0,      // 0 means due on receipt
            'invoice_terms'  => 'Goods remain the property of Arg Flex Ltd until paid for in full. '
                              . 'Cut lengths are made to order and cannot be returned unless faulty.',

            /* --- advanced: URLs the WordPress site served that this build renames.
                   They 301 rather than 404 so no inbound link or ranking is lost. --- */
            'redirects' => [
                '/refund-returns/'   => '/refund_returns/',
                '/about/'            => '/about-us/',
                '/contact/'          => '/contacts/',
                '/contact-us/'       => '/contacts/',
                '/products/'         => '/shop/',
                '/product-category/' => '/shop/',
                '/news/'             => '/blog/',
                '/home/'             => '/',
            ],
            'terms_path'     => '/refund_returns/',
            'shop_notice'    => '',
            'catalogue_mode' => false,   // hide prices and the basket entirely

            // where enquiries and orders are sent
            'mail_to'        => 'sales@argflex.co.uk',
            'mail_from'      => 'website@argflex.co.uk',
            'mail_from_name' => 'Arg Flex website',

            // SMTP; leave the host empty to fall back to PHP mail()
            'smtp_host'   => '',
            'smtp_port'   => 587,
            'smtp_user'   => '',
            'smtp_pass'   => '',
            'smtp_secure' => 'tls',     // tls, ssl or none

            // Cloudflare Turnstile; leave blank to switch the checks off
            'turnstile_site'   => '',
            'turnstile_secret' => '',

            'map_url' => 'https://www.google.com/maps?q=107%20George%20Lane%2C%20South%20Woodford%2C%20London%2C%20E18%201AN&z=16&hl=en&output=embed',

            'soc1_name' => 'Facebook',  'soc1_url' => 'https://www.facebook.com/',
            'soc2_name' => 'Instagram', 'soc2_url' => 'https://www.instagram.com/',
            'soc3_name' => 'WhatsApp',  'soc3_url' => 'https://wa.me/447717217388',
            'soc4_name' => '',          'soc4_url' => '',
        ];
        $file  = ROOT_DIR . '/storage/settings.php';
        $saved = is_file($file) ? (require $file) : [];
        $cache = array_merge($defaults, is_array($saved) ? $saved : []);
    }
    return $cache;
}

function setting(string $key, $fallback = null)
{
    return settings()[$key] ?? $fallback;
}

define('SITE_NAME',          setting('site_name'));
define('SITE_TAG',           setting('site_tag'));
define('SITE_PHONE',         setting('phone'));
define('SITE_PHONE_HREF',    setting('phone_href'));
define('SITE_EMAIL',         setting('email'));
define('SITE_ADDR',          setting('address'));
define('SITE_HOURS_WEEK',    setting('hours_week'));
define('SITE_HOURS_WEEKEND', setting('hours_weekend'));
define('ASSET_VER',          (string) setting('asset_ver'));

/* ------------------------------------------------------------------ data */

/**
 * One of the data/*.php files, read once per request.
 *
 * $fresh re-reads it, and every writer in inc/store.php passes it after
 * saving. Without that a request that writes and then reads gets what was
 * there before its own change — which is what taking stock does: an order
 * of two lines counted the first down, then read the untouched list again
 * for the second and wrote the original figure back over it.
 */
function data(string $name, bool $fresh = false): array
{
    static $cache = [];
    if ($fresh || !isset($cache[$name])) {
        $cache[$name] = require ROOT_DIR . "/data/{$name}.php";
    }
    return $cache[$name];
}

/**
 * Products for the public site: drafts are left out. The admin passes
 * $includeDrafts so it can list and edit them.
 */
function all_products(bool $includeDrafts = false): array
{
    $rows = data('products');
    if ($includeDrafts) return $rows;
    return array_values(array_filter($rows, fn($p) => ($p['status'] ?? 'published') === 'published'));
}

/** Everything the shop search looks through for one product. */
function product_haystack(array $p): string
{
    /* The sizes are in here because the search box says "by product, standard
       or bore size" and did not honour the third: bore sizes live on the
       attributes, and searching 16mm found the one hose with it in the name.
       Both the slug and the printed name go in, since a term can be stored as
       3-2mm and shown as 3.2mm and people type either. */
    $sizes = [];
    foreach ((array) ($p['attrs'] ?? []) as $attr) {
        foreach ((array) ($attr['terms'] ?? []) as $term) {
            $sizes[] = (string) ($term['name'] ?? '');
            $sizes[] = (string) ($term['slug'] ?? '');
        }
    }

    return lower($p['name'] . ' ' . strip_tags($p['short']) . ' ' . $p['sku'] . ' '
        . implode(' ', $p['cats']) . ' ' . implode(' ', $p['tags'] ?? []) . ' '
        . implode(' ', array_unique(array_filter($sizes))));
}

/**
 * Can this be bought right now?
 *
 * Not just the in/out flag: a product tracking quantity with none left and
 * backorders switched off is out of stock however the flag reads.
 */
function product_in_stock(array $p): bool
{
    return stock_state($p)['state'] !== 'out';
}
function all_categories(): array { return data('categories'); }
function all_posts(): array      { return data('posts'); }

function all_attributes(): array
{
    return is_file(ROOT_DIR . '/data/attributes.php') ? data('attributes') : [];
}

function find_attribute(string $slug): ?array
{
    foreach (all_attributes() as $a) {
        if ($a['slug'] === $slug) return $a;
    }
    return null;
}

/**
 * One term of one attribute — the thing an archive page is about.
 *
 * These have their own indexed URLs on the live site: /inner-diameter/8mm/
 * and /length/50m/, thirty-five of them. They are ordinary pages to Google,
 * so they have to keep working.
 */
function find_attribute_term(string $attribute, string $slug): ?array
{
    $a = find_attribute($attribute);
    if (!$a) return null;
    $want = rawurldecode($slug);
    foreach ((array) $a['terms'] as $t) {
        if ($t['slug'] === $slug || rawurldecode($t['slug']) === $want) {
            return $t + ['attribute' => $a['name'], 'attribute_slug' => $a['slug']];
        }
    }
    return null;
}

function attribute_term_url(string $attribute, string $slug): string
{
    return '/' . $attribute . '/' . $slug . '/';
}

/**
 * The products offering a term, with the cheapest variation that carries it.
 *
 * A product qualifies if any of its variations names the term, or if the
 * term is one of its attribute values — a fixed diameter shown as a spec row
 * still belongs on that diameter's page, which is how the live archives read.
 */
function products_with_term(string $attribute, string $slug): array
{
    $a = find_attribute($attribute);
    if (!$a) return [];
    $axis = $a['name'];
    $out  = [];

    foreach (all_products() as $p) {
        $carries = false;
        foreach ((array) $p['attrs'] as $attr) {
            if ($attr['name'] !== $axis) continue;
            foreach ((array) $attr['terms'] as $t) {
                if ($t['slug'] === $slug) { $carries = true; break 2; }
            }
        }
        if ($carries) $out[] = $p;
    }
    return $out;
}

/**
 * Slugs are compared in their decoded form so that a URL carrying a
 * percent-encoded character (e.g. mm%c2%b3) matches the stored slug
 * whichever way round the web server hands it to us.
 */
function slug_key(string $slug): string
{
    return strtolower(rawurldecode($slug));
}

function find_product(string $slug, bool $includeDrafts = false): ?array
{
    $key = slug_key($slug);
    foreach (all_products($includeDrafts) as $p) {
        if (slug_key($p['slug']) === $key) return $p;
    }
    return null;
}

function find_category(string $slug): ?array
{
    $key = slug_key($slug);
    foreach (all_categories() as $c) {
        if (slug_key($c['slug']) === $key) return $c;
    }
    return null;
}

function find_post(string $slug): ?array
{
    $key = slug_key($slug);
    foreach (all_posts() as $p) {
        if (slug_key($p['slug']) === $key) return $p;
    }
    return null;
}

/** Direct children of a category slug. */
function child_categories(string $slug): array
{
    $kids = array_values(array_filter(all_categories(), fn($c) => $c['parent'] === $slug));
    usort($kids, fn($a, $b) => ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0)));
    return $kids;
}

/** Top level categories, in catalogue order. */
function top_categories(): array
{
    $order = ['rubber-hoses', 'pvcpu-hoses', 'hose-couplings'];
    $tops  = array_filter(all_categories(), fn($c) => $c['parent'] === '');
    usort($tops, function ($a, $b) use ($order) {
        // an explicit order set in the admin wins over the shipped one
        $sa = (int) ($a['sort'] ?? 0);
        $sb = (int) ($b['sort'] ?? 0);
        if ($sa !== $sb) return $sa <=> $sb;
        $ia = array_search($a['slug'], $order, true);
        $ib = array_search($b['slug'], $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
    return $tops;
}

/** Products in a category, including everything filed under its children. */
/**
 * Products a listing should show.
 *
 * Out-of-stock lines are dropped when the shop is set to hide them, and the
 * default order follows each product's catalogue position then its name --
 * what WooCommerce calls menu order.
 */
function listing_order(array $products): array
{
    if (setting('hide_out_of_stock')) {
        $products = array_values(array_filter($products,
            fn($p) => stock_state($p)['state'] !== 'out'));
    }
    usort($products, fn($a, $b) =>
        [(int) ($a['menu_order'] ?? 0), lower($a['name'])]
        <=> [(int) ($b['menu_order'] ?? 0), lower($b['name'])]);
    return $products;
}

/** Sort a listing the way the shop or the visitor asked. */
function sort_products(array $products, string $how): array
{
    if ($how === 'default' || $how === '') $how = (string) setting('default_sort');

    switch ($how) {
        case 'price-asc':  usort($products, fn($a, $b) => effective_min($a) <=> effective_min($b)); break;
        case 'price-desc': usort($products, fn($a, $b) => effective_max($b) <=> effective_max($a)); break;
        case 'name':       usort($products, fn($a, $b) => strcasecmp($a['name'], $b['name']));      break;
        case 'new':        usort($products, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? '')); break;
        default:           $products = listing_order($products);
    }
    return $products;
}

/**
 * Cut a listing into pages.
 *
 * Returns the slice to show plus everything a pager needs. Page 1 keeps the
 * bare URL — /shop/ and /shop/?page=1 being two addresses for one thing is
 * the sort of duplicate a search engine has to be told about, and not having
 * it is simpler than explaining it.
 */
function paginate(array $items, int $page, ?int $perPage = null): array
{
    $perPage = $perPage ?: max(1, (int) setting('shop_per_page'));
    $total   = count($items);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));

    return [
        'items'   => array_slice($items, ($page - 1) * $perPage, $perPage),
        'page'    => $page,
        'pages'   => $pages,
        'total'   => $total,
        'first'   => $total ? ($page - 1) * $perPage + 1 : 0,
        'last'    => min($page * $perPage, $total),
        'perPage' => $perPage,
    ];
}

/**
 * The page numbers worth printing: always the first and last, always a
 * couple either side of where we are, and a gap marker for the rest.
 */
function pager_numbers(int $page, int $pages, int $around = 1): array
{
    $show = [1, $pages];
    for ($i = $page - $around; $i <= $page + $around; $i++) {
        if ($i > 1 && $i < $pages) $show[] = $i;
    }
    $show = array_values(array_unique(array_filter($show, fn($n) => $n >= 1 && $n <= $pages)));
    sort($show);

    $out = [];
    $previous = 0;
    foreach ($show as $n) {
        if ($previous && $n > $previous + 1) $out[] = null;      // a gap
        $out[] = $n;
        $previous = $n;
    }
    return $out;
}

function products_in_category(string $slug): array
{
    $slugs = [$slug];
    foreach (child_categories($slug) as $c) {
        $slugs[] = $c['slug'];
    }
    return array_values(array_filter(
        all_products(),
        fn($p) => (bool) array_intersect($p['cats'], $slugs)
    ));
}

/** Other products sharing a category, closest first. */
function related_products(array $product, int $limit = 4): array
{
    $scored = [];
    foreach (all_products() as $p) {
        if ($p['slug'] === $product['slug']) continue;
        $shared = count(array_intersect($p['cats'], $product['cats']));
        if ($shared > 0) $scored[] = [$shared, $p];
    }
    usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
    return array_slice(array_column($scored, 1), 0, $limit);
}

/** Products flagged as featured in the admin, topped up if there are too few. */
function featured_products(int $limit = 12): array
{
    $out = array_values(array_filter(all_products(), fn($p) => !empty($p['featured'])));
    foreach (all_products() as $p) {
        if (count($out) >= $limit) break;
        if (!in_array($p, $out, true)) $out[] = $p;
    }
    return array_slice($out, 0, $limit);
}

/* --------------------------------------------------------------- helpers */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim to a length without splitting a UTF-8 character, mbstring or not. */
function clip(string $s, int $len): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $len, 'UTF-8');
    }
    if (strlen($s) <= $len) return $s;
    $cut = substr($s, 0, $len);
    // step back off a partial multi-byte sequence
    while ($cut !== '' && (ord($cut[strlen($cut) - 1]) & 0xC0) === 0x80) {
        $cut = substr($cut, 0, -1);
    }
    return rtrim(substr($cut, 0, -1));
}

function lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/** "£1.60 – £79.05" or a single price, at whatever it costs today. */
function price_label(array $p): string
{
    if ($p['price_min'] <= 0) return 'Price on request';

    $min = effective_min($p);
    $max = effective_max($p);
    return $max > $min ? money($min) . ' – ' . money($max) : money($min);
}

/** The struck-through price beside a sale one, or '' when nothing is on sale. */
function was_label(array $p): string
{
    if (!on_sale($p)) return '';
    return $p['price_max'] > $p['price_min']
        ? money((int) $p['price_min']) . ' – ' . money((int) $p['price_max'])
        : money((int) $p['price_min']);
}

/** The picture for a category tile: its own if set, else its first product's. */
function category_image(array $c): ?string
{
    if (!empty($c['image'])) return $c['image'];
    foreach (products_in_category($c['slug']) as $p) {
        if (!empty($p['images'][0])) return $p['images'][0];
    }
    return null;
}

function category_url(array $c): string
{
    return '/product-category/' . $c['path'] . '/';
}

function product_url(array $p): string
{
    return '/product/' . $p['slug'] . '/';
}

function post_url(array $p): string
{
    return '/' . $p['slug'] . '/';
}

/** "Rubber hoses · Fuel/Oil Products" for a product card. */
function product_cat_label(array $p): string
{
    $names = [];
    foreach ($p['cats'] as $slug) {
        if ($c = find_category($slug)) $names[] = $c['name'];
    }
    return implode(' · ', array_slice($names, 0, 2));
}

/** The most specific category a product belongs to, for breadcrumbs. */
function primary_category(array $p): ?array
{
    // an explicit choice in the admin wins
    if (!empty($p['primary_cat']) && ($chosen = find_category($p['primary_cat']))) {
        return $chosen;
    }
    $best = null;
    foreach ($p['cats'] as $slug) {
        $c = find_category($slug);
        if (!$c) continue;
        if ($best === null || ($c['parent'] !== '' && $best['parent'] === '')) $best = $c;
    }
    return $best;
}

/** Pull "Key: value" lines out of a short description into a spec table. */
function parse_specs(string $short): array
{
    // Any block that closes ends a line, not just <br> and </p>. The short
    // description is edited in a contenteditable box now, and pressing Enter
    // there produces a <div> — which used to leave every spec strung onto one
    // line, so only the first was recognised.
    $text = preg_replace('~<br\s*/?>|</(?:p|div|li|h[1-6]|tr)\s*>~i', "\n", $short) ?? $short;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $specs = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^([A-Z][A-Za-z\/ ]{2,24}):\s*(.+)$/u', $line, $m)) {
            $specs[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
        } elseif ($specs) {
            $specs[count($specs) - 1]['value'] .= ' ' . $line;
        }
    }
    return $specs;
}

function format_date(string $iso): string
{
    $ts = strtotime($iso);
    return $ts ? date('j F Y', $ts) : $iso;
}

function asset(string $path): string
{
    return '/' . ltrim($path, '/') . '?v=' . ASSET_VER;
}

/* --------------------------------------------------------- page content */

/** The stored value for a page field, or null when nothing is saved. */
function page_value(string $path, string $key): ?string
{
    static $pages = null;
    if ($pages === null) {
        $file  = ROOT_DIR . '/data/pages.php';
        $pages = is_file($file) ? (array) require $file : [];
    }
    $value = $pages[$path][$key] ?? null;
    return ($value === null || $value === '') ? null : (string) $value;
}

/**
 * A piece of editable copy for a page, escaped for output.
 *
 * The default passed in is the wording that ships in the template, so a page
 * reads correctly whether or not anything has been saved in the admin. Only
 * keys actually edited are stored.
 *
 * Escaping is the default so that admin-entered copy can never inject markup.
 * Use page_raw() for the handful of fields that are meant to carry HTML.
 */
function page_text(string $path, string $key, string $default = ''): string
{
    // the saved value and the shipped default are escaped the same way, so the
    // markup does not change shape depending on whether anything was edited
    return e(page_value($path, $key) ?? $default);
}

/** Editable copy that is allowed to contain HTML — headings, policy text. */
function page_raw(string $path, string $key, string $default = ''): string
{
    return page_value($path, $key) ?? $default;
}

/** Editable copy split into lines, for lists of tags, checks and so on. */
function page_lines(string $path, string $key, array $default = []): array
{
    $raw = page_text($path, $key, implode("\n", $default));
    $out = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: [])));
    return $out ?: $default;
}

/* ------------------------------------------------------------------- SEO */

const SITE_URL = 'https://argflex.co.uk';

/**
 * Is this the real shop, or a copy of it?
 *
 * A staging or preview host serves identical pages, so it must never be
 * indexed. Anything that is not the canonical host — a subdomain, an IP, a
 * temporary hosting address — is treated as a copy. localhost is a copy too,
 * which costs nothing and keeps the behaviour identical while developing.
 */
function is_live_host(): bool
{
    static $live = null;
    if ($live === null) {
        $here = strtolower(strtok((string) ($_SERVER['HTTP_HOST'] ?? ''), ':'));
        $real = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
        $live = $here === '' || $here === $real || $here === 'www.' . $real;
    }
    return $live;
}

/** Send the noindex header on every page a copy of the site serves. */
function guard_copies(): void
{
    if (!is_live_host()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    }
}

/** The path currently being served, normalised with a trailing slash. */
function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    if ($path !== '/' && !str_ends_with($path, '/')) $path .= '/';
    return $path;
}

/**
 * Title/description/canonical as the live WordPress site serves them.
 * Keeping these byte-identical is what stops the migration moving rankings.
 */
function live_seo(?string $path = null): array
{
    static $map = null;
    if ($map === null) {
        $file = ROOT_DIR . '/data/seo.php';
        $map  = is_file($file) ? require $file : [];
    }
    $path = $path ?? current_path();
    if (isset($map[$path])) return $map[$path];

    // percent-encoded slugs are stored decoded and vice versa — try both
    foreach ($map as $key => $entry) {
        if (rawurldecode($key) === $path) return $entry;
    }
    return [];
}

function canonical_url(): string
{
    $seo  = live_seo();
    $base = $seo['canonical'] ?? (SITE_URL . current_path());

    // Page two of a listing is not a duplicate of page one: it holds
    // different products. Pointing it at page one would tell a search engine
    // to ignore everything only reachable there.
    $page = (int) ($_GET['page'] ?? 1);
    return $page > 1 ? $base . '?page=' . $page : $base;
}

/** Page state, filled in by each page before including the header. */
$GLOBALS['page'] = [
    'title'       => SITE_NAME,
    'description' => SITE_TAG,
    'crumbs'      => [],
    'body_class'  => '',
    'preload'     => null,
    'image'       => null,
    'robots'      => null,
    'schema'      => [],
];

/**
 * Pages pass their own title and description; where the live site already
 * has one for this URL it wins, so nothing Google has indexed changes.
 * Anything the live site left blank keeps the value the page supplied.
 */
/**
 * Basket and account pages are not search landing pages. The live site serves
 * /checkout/ under the title "Cart" because WooCommerce redirects it, so
 * copying that across would be plainly wrong — these keep our own titles and
 * are marked noindex, which is what WooCommerce does by default anyway.
 */
const NO_INDEX_PATHS = ['/cart/', '/checkout/', '/wishlist/', '/compare/', '/my-account/'];

function set_page(array $values): void
{
    $path = current_path();
    if (in_array($path, NO_INDEX_PATHS, true)) {
        $values['robots'] = $values['robots'] ?? 'noindex, follow';
        $GLOBALS['page']  = array_merge($GLOBALS['page'], $values);
        return;
    }

    $seo = live_seo();
    foreach (['title', 'description'] as $key) {
        if (!empty($seo[$key])) $values[$key] = $seo[$key];
    }
    if (!empty($seo['robots']) && !isset($values['robots'])) {
        $values['robots'] = $seo['robots'];
    }
    if (!empty($seo['og_image']) && empty($values['image'])) {
        $values['image'] = $seo['og_image'];
    }
    $GLOBALS['page'] = array_merge($GLOBALS['page'], $values);
}

function page(string $key)
{
    return $GLOBALS['page'][$key] ?? null;
}

/**
 * JSON-LD blocks for the current page: the organisation, whatever the page
 * added, and a breadcrumb trail. Built in here rather than inline in the
 * header so its working variables cannot collide with the page's own.
 */
function page_schema_blocks(): array
{
    $blocks = [[
        '@context'  => 'https://schema.org',
        '@type'     => 'Organization',
        'name'      => SITE_NAME,
        'url'       => SITE_URL . '/',
        'logo'      => SITE_URL . '/assets/img/site/logo.png',
        'telephone' => SITE_PHONE,
        'email'     => SITE_EMAIL,
        'address'   => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => '1st floor, 107 George Lane',
            'addressLocality' => 'South Woodford, London',
            'postalCode'      => 'E18 1AN',
            'addressCountry'  => 'GB',
        ],
    ]];

    foreach (page('schema') ?: [] as $extra) {
        $blocks[] = $extra;
    }

    if (page('crumbs')) {
        $trail = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL . '/']];
        foreach (page('crumbs') as $index => $crumb) {
            $entry = ['@type' => 'ListItem', 'position' => $index + 2, 'name' => $crumb['label']];
            if (!empty($crumb['url'])) $entry['item'] = SITE_URL . $crumb['url'];
            $trail[] = $entry;
        }
        $blocks[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $trail];
    }

    return $blocks;
}

/** The og:image for the current page, falling back to the site hero. */
function page_og_image(): string
{
    return page('image') ?: SITE_URL . '/assets/img/site/hero-1.webp';
}
