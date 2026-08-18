<?php
/**
 * Site configuration, data access and view helpers.
 */
declare(strict_types=1);

const SITE_NAME  = 'Arg Flex Ltd';
const SITE_TAG   = 'Solutions for fluid transfer and industrial applications';
const SITE_PHONE = '+44 (0) 7717 217388';
const SITE_PHONE_HREF = '+447717217388';
const SITE_EMAIL = 'sales@argflex.co.uk';
const SITE_ADDR  = '1st floor, 107 George Lane, South Woodford, London, E18 1AN';
const SITE_HOURS_WEEK = 'Mon–Fri 9:00–17:00';
const SITE_HOURS_WEEKEND = 'Sat–Sun 10:00–18:00';
const ASSET_VER  = '13';

define('ROOT_DIR', dirname(__DIR__));

/* ------------------------------------------------------------------ data */

function data(string $name): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $cache[$name] = require ROOT_DIR . "/data/{$name}.php";
    }
    return $cache[$name];
}

function all_products(): array   { return data('products'); }
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

function find_product(string $slug): ?array
{
    $key = slug_key($slug);
    foreach (all_products() as $p) {
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

function featured_products(int $limit = 12): array
{
    $want = [
        'oil-resistant-hose-sae-j30-r6-7mm', 'submersible-fuel-hose-sae-j30-r10-0-5m-50m',
        'sandblast-hose-56-mm%c2%b3', 'car-heater-hose-125c-sae-j20-r3',
        'oxygen-hose-agoma', 'twin-line-welding-hose-for-oxygen-and-acetylene',
        'pvc-ventilation-hose-termoresist', 'pu-hose-for-pneumatic-tools-notas-pu',
        'pvc-garden-hose-hobby', 'fuel-hose-din-73379-b', 'asfa-clamps', 'gbs-clamps',
    ];
    $out = [];
    foreach ($want as $slug) {
        if ($p = find_product($slug)) $out[] = $p;
    }
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

/** Page state, filled in by each page before including the header. */
$GLOBALS['page'] = [
    'title'       => SITE_NAME,
    'description' => SITE_TAG,
    'crumbs'      => [],
    'body_class'  => '',
    'preload'     => null,
];

function set_page(array $values): void
{
    $GLOBALS['page'] = array_merge($GLOBALS['page'], $values);
}

function page(string $key)
{
    return $GLOBALS['page'][$key] ?? null;
}
