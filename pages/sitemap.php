<?php
/**
 * sitemap.xml — generated from the catalogue, so it can never drift out of
 * date the way a hand-written one does.
 */
declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');

/* One list, shared with the SEO screen in the admin — see site_content().
   It used to be built here and was going to be built there as well, and the
   second copy is always the one that forgets the size archives. */
$urls = site_content();

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
