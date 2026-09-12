<?php
/**
 * Point the share images at files this site actually has.
 *
 * data/seo.php carries og:image for every indexed URL, taken verbatim from
 * the WordPress site so that nothing about how a page presents itself would
 * change in the move. Every one of those 62 values is an absolute
 * https://argflex.co.uk/wp-content/uploads/... address, and the moment the
 * document root stopped being WordPress they all stopped resolving.
 *
 * Most of them never mattered: set_page() only falls back to the stored
 * og:image when the page supplied none, and every product and every post
 * supplies its own from assets/img. Four pages did not — the home page, About
 * us, the blog index and Contacts — and the home page is the one that gets
 * pasted into WhatsApp.
 *
 * So: any og:image still pointing into wp-content is repointed at the local
 * file of the same name where one exists, and dropped where none does. A
 * dropped one is not a loss — the page then falls back to whatever it sets
 * itself, or to nothing, and nothing beats a broken image.
 *
 *   php .data/fix_og_images.php --dry-run
 *   php .data/fix_og_images.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$dry = in_array('--dry-run', $argv, true);

/** Every image the site has, by lowercased filename. */
$have = [];
foreach (glob(ROOT_DIR . '/assets/img/*/*') as $file) {
    $have[strtolower(basename($file))] = ltrim(str_replace(ROOT_DIR, '', $file), '/\\');
}
$have = array_map(fn($p) => str_replace('\\', '/', $p), $have);

/* The four pages that supply no image of their own, so the stored og:image is
   the only thing a share preview has to go on. None of them has a local file
   of the same name — these were WordPress-only photographs — so they are said
   outright rather than left to the matching above, which would drop them and
   leave the home page sharing as a bare link. */
const CHOSEN = [
    '/'           => 'assets/img/site/hero-1.webp',
    '/about-us/'  => 'assets/img/site/about-1.jpg',
    '/blog/'      => 'assets/img/site/hero-1.webp',
    '/contacts/'  => 'assets/img/site/hero-1.webp',
];

$seo     = require ROOT_DIR . '/data/seo.php';
$moved   = 0;
$dropped = 0;

foreach ($seo as $path => $row) {
    $img = (string) ($row['og_image'] ?? '');
    if ($img === '' || !str_contains($img, 'wp-content')) continue;

    /* A basket or a checkout is never a search result and never a share —
       set_page() returns before it reads this. Pointing one at whichever
       product happened to share a filename is worse than having none. */
    if (in_array($path, NO_INDEX_PATHS, true)) {
        unset($seo[$path]['og_image']);
        if (!$seo[$path]) unset($seo[$path]);
        $dropped++;
        continue;
    }

    if (isset(CHOSEN[$path]) && is_file(ROOT_DIR . '/' . CHOSEN[$path])) {
        $seo[$path]['og_image'] = SITE_URL . '/' . CHOSEN[$path];
        printf("  %-52s -> /%s  (chosen)\n", substr($path, 0, 52), CHOSEN[$path]);
        $moved++;
        continue;
    }

    $base = strtolower(basename((string) parse_url($img, PHP_URL_PATH)));
    /* WordPress appends the size to a resized copy — ak-og-1024x535.webp is
       ak-og.webp at a particular width. The stem is what the migration named
       the file it brought across. */
    $stem = preg_replace('/-\d+x\d+$/', '', pathinfo($base, PATHINFO_FILENAME));

    $found = '';
    foreach ([$base, $stem . '.webp', $stem . '.jpg', $stem . '.png'] as $try) {
        if (isset($have[strtolower($try)])) { $found = $have[strtolower($try)]; break; }
    }

    if ($found !== '') {
        $seo[$path]['og_image'] = SITE_URL . '/' . $found;
        printf("  %-52s -> /%s\n", substr($path, 0, 52), $found);
        $moved++;
    } else {
        unset($seo[$path]['og_image']);
        if (!$seo[$path]) unset($seo[$path]);
        $dropped++;
    }
}

printf("\n%d repointed at a local file, %d dropped for want of one\n", $moved, $dropped);

if ($dry) { echo "\nDry run — nothing written.\n"; exit(0); }
if (!$moved && !$dropped) { echo "Nothing to do.\n"; exit(0); }

echo save_seo($seo) ? "data/seo.php updated\n" : "could not write data/seo.php\n";
