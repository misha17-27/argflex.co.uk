<?php
/**
 * What an order does to the sitemap's dates, as JSON.
 *
 *     php .data/lastmod_facts.php <sandbox>
 *
 * Run by check_small_defects.py and not by hand. <sandbox> is a throwaway
 * copy of inc/ and data/ with an empty storage/ beside them, which the check
 * makes and deletes: this script WRITES the product list, and the real one is
 * never to be written by a check. It refuses to run against the repository.
 *
 * The sitemap dated every product, category and size listing from the time
 * of data/products.php, and an order for a hose whose stock is counted
 * rewrites that file (take_stock() in inc/store.php). So one sale re-dated
 * sixty-eight pages. Each step below is one thing that may or may not move
 * the date, and the check holds what it expects of each.
 */
declare(strict_types=1);

$sandbox = realpath((string) ($argv[1] ?? '')) ?: '';
if ($sandbox === '' || !is_file($sandbox . '/inc/config.php')
    || $sandbox === realpath(dirname(__DIR__))) {
    fwrite(STDERR, "usage: php .data/lastmod_facts.php <sandbox copy of the site>\n");
    exit(2);
}

$_SERVER['HTTP_HOST']   = 'argflex.co.uk';
$_SERVER['REQUEST_URI'] = '/';
require $sandbox . '/inc/config.php';
require_once ROOT_DIR . '/inc/store.php';

$file  = ROOT_DIR . '/data/products.php';
$old   = '2020-01-02';
$today = gmdate('Y-m-d');

$sitemapDays = function (): array {
    $days = [];
    foreach (site_content() as $row) {
        // products only: a category or a listing is also dated by a file of
        // its own, which the sandbox copied with whatever day it had
        if ($row['kind'] === 'Products') $days[$row['lastmod']] = ($days[$row['lastmod']] ?? 0) + 1;
    }
    ksort($days);
    return $days;
};
$count = function (string $slug): int {
    foreach (all_products(true) as $listed) {
        if ($listed['slug'] === $slug) return (int) ($listed['stock_qty'] ?? -1);
    }
    return -1;
};

// one product sold without options, with its stock counted
$products = all_products(true);
$counted  = '';
foreach ($products as $at => $listed) {
    if (!empty($listed['variants'])) continue;
    $products[$at]['manage_stock'] = true;
    $products[$at]['stock_qty']    = 5;
    $products[$at]['backorders']   = 'no';
    $counted = (string) $listed['slug'];
    break;
}

$out = ['today' => $today, 'old' => $old, 'counted' => $counted];

// 1. an edit records today
save_products($products);
$out['after_edit'] = ['recorded' => products_edited_day(), 'day' => products_day()];

// 2. a server that has never recorded a day: the file's own time is all there is
@unlink(ROOT_DIR . '/' . PRODUCTS_EDITED_FILE);
touch($file, strtotime($old . ' 12:00:00 UTC'));
clearstatcache();
$out['unrecorded'] = ['recorded' => products_edited_day(), 'day' => products_day()];

// 3. an order counts stock down: the file is written, the day must not move
take_stock([['slug' => $counted, 'qty' => 1, 'key' => '']]);
clearstatcache();
$out['after_order'] = [
    'left'      => $count($counted),
    'file_day'  => gmdate('Y-m-d', (int) filemtime($file)),
    'recorded'  => products_edited_day(),
    'day'       => products_day(),
    'sitemap'   => $sitemapDays(),
];

// 4. and a second order
take_stock([['slug' => $counted, 'qty' => 2, 'key' => '']]);
clearstatcache();
$out['after_second_order'] = ['left' => $count($counted), 'day' => products_day()];

// 5. the next edit moves it again
save_products(all_products(true));
$out['after_second_edit'] = ['day' => products_day()];

echo json_encode($out, JSON_UNESCAPED_SLASHES);
