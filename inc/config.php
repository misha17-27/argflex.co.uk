<?php
/**
 * Site configuration, data access and view helpers.
 */
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

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
            'email'         => 'sales@argflex.co.uk',
            'address'       => '1st floor, 107 George Lane, South Woodford, London, E18 1AN',
            'hours_week'    => 'Mon–Fri 9:00–17:00',
            'hours_weekend' => 'Sat–Sun 10:00–18:00',
            'free_shipping' => 25000,   // pence, excl. VAT
            'shipping_flat' => 1200,    // pence
            'vat_rate'      => 20,      // percent
            'asset_ver'     => '21',

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

function data(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
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

function product_in_stock(array $p): bool
{
    return ($p['stock'] ?? 'instock') !== 'outofstock';
}
function all_categories(): array { return data('categories'); }
function all_posts(): array      { return data('posts'); }

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
    return array_values(array_filter(all_categories(), fn($c) => $c['parent'] === $slug));
}

/** Top level categories, in catalogue order. */
function top_categories(): array
{
    $order = ['rubber-hoses', 'pvcpu-hoses', 'hose-couplings'];
    $tops  = array_filter(all_categories(), fn($c) => $c['parent'] === '');
    usort($tops, function ($a, $b) use ($order) {
        $ia = array_search($a['slug'], $order, true);
        $ib = array_search($b['slug'], $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
    return $tops;
}

/** Products in a category, including everything filed under its children. */
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

/** Pence to a display price. */
function money(int $pence): string
{
    return '£' . number_format($pence / 100, 2);
}

/** "£1.60 – £79.05" or a single price. */
function price_label(array $p): string
{
    if ($p['price_min'] <= 0) return 'Price on request';
    if ($p['price_max'] > $p['price_min']) {
        return money($p['price_min']) . ' – ' . money($p['price_max']);
    }
    return money($p['price_min']);
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
    $text  = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $short)), ENT_QUOTES, 'UTF-8');
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
    $seo = live_seo();
    return $seo['canonical'] ?? (SITE_URL . current_path());
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
