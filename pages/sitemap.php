<?php
/**
 * sitemap.xml — generated from the catalogue, so it can never drift out of
 * date the way a hand-written one does.
 */
declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['loc' => '/',                'priority' => '1.0', 'freq' => 'weekly'],
    ['loc' => '/shop/',           'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => '/about-us/',       'priority' => '0.6', 'freq' => 'monthly'],
    ['loc' => '/contacts/',       'priority' => '0.6', 'freq' => 'monthly'],
    ['loc' => '/blog/',           'priority' => '0.7', 'freq' => 'weekly'],
    ['loc' => '/refund_returns/', 'priority' => '0.3', 'freq' => 'yearly'],
];

foreach (top_categories() as $c) {
    $urls[] = ['loc' => category_url($c), 'priority' => '0.8', 'freq' => 'weekly'];
    foreach (child_categories($c['slug']) as $k) {
        $urls[] = ['loc' => category_url($k), 'priority' => '0.7', 'freq' => 'weekly'];
    }
}
foreach (all_products() as $p) {
    $urls[] = ['loc' => product_url($p), 'priority' => '0.8', 'freq' => 'weekly'];
}
foreach (all_posts() as $p) {
    $urls[] = ['loc' => post_url($p), 'priority' => '0.6', 'freq' => 'monthly', 'lastmod' => $p['date']];
}

// The attribute archives — one page per bore size and per length. Thirty-five
// of these are already indexed, so they belong here rather than being left
// for a crawler to rediscover.
foreach (all_attributes() as $a) {
    foreach ((array) $a['terms'] as $t) {
        $urls[] = ['loc'      => attribute_term_url($a['slug'], $t['slug']),
                   'priority' => '0.5', 'freq' => 'monthly'];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= e(SITE_URL . $u['loc']) ?></loc>
<?php if (!empty($u['lastmod'])): ?>
    <lastmod><?= e($u['lastmod']) ?></lastmod>
<?php endif; ?>
    <changefreq><?= $u['freq'] ?></changefreq>
    <priority><?= $u['priority'] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
