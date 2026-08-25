<?php
/**
 * Bring the last WordPress-hosted images into this site.
 *
 * Product and article copy imported from the old site still points at
 * argflex.co.uk/wp-content/uploads/... for the pictures inside the text. They
 * render today only because WordPress is still answering. The moment the
 * domain moves to this build every one of them turns into a broken image, on
 * pages that already rank.
 *
 * So: download each one into assets/img/content/, rewrite the copy to point
 * at the local path, and leave nothing reaching outside.
 *
 *   php .data/pull_wp_media.php --dry-run    list what would change
 *   php .data/pull_wp_media.php              do it
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$dry  = in_array('--dry-run', $argv, true);
$dest = ROOT_DIR . '/assets/img/content';
if (!$dry && !is_dir($dest) && !mkdir($dest, 0775, true)) {
    fwrite(STDERR, "could not create assets/img/content\n");
    exit(1);
}

/** Every external media URL in a piece of copy, srcset entries included. */
function media_urls(string $html): array
{
    $found = [];
    if (preg_match_all('~https?://[^"\'\s)]*wp-content/[^"\'\s)]+~i', $html, $m)) {
        foreach ($m[0] as $url) $found[] = rtrim($url, '.,;');
    }
    return array_values(array_unique($found));
}

/** A stable local filename for a remote one, without collisions. */
function local_name(string $url, array &$taken): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) $ext = 'jpg';

    $base = make_slug(pathinfo($path, PATHINFO_FILENAME)) ?: 'image';
    $base = substr($base, 0, 70);

    $name = $base . '.' . $ext;
    $n = 2;
    while (isset($taken[$name])) $name = $base . '-' . $n++ . '.' . $ext;
    $taken[$name] = true;
    return $name;
}

/* ------------------------------------------------------ collect the work */

$products = all_products(true);
$posts    = all_posts();

$urls = [];
foreach ($products as $p) {
    foreach (['short', 'desc'] as $field) {
        foreach (media_urls((string) $p[$field]) as $u) $urls[$u][] = 'product ' . $p['slug'];
    }
}
foreach ($posts as $p) {
    foreach (media_urls((string) $p['content']) as $u) $urls[$u][] = 'post ' . $p['slug'];
}

if (!$urls) {
    echo "Nothing left pointing at the old site.\n";
    exit(0);
}

echo count($urls) . " image(s) still served by WordPress\n";

/* ---------------------------------------------------------- download them */

$taken = [];
$map   = [];      // remote url => /assets/img/content/name.ext
$bytes = 0;
$failed = [];

foreach ($urls as $url => $where) {
    $name  = local_name($url, $taken);
    $local = $dest . '/' . $name;
    $rel   = '/assets/img/content/' . $name;

    if ($dry) {
        printf("  %-58s -> %s\n", substr($url, 0, 58), $rel);
        $map[$url] = $rel;
        continue;
    }

    if (!is_file($local) || filesize($local) === 0) {
        exec('curl -sS -L --max-time 60 -o ' . escapeshellarg($local) . ' ' . escapeshellarg($url),
             $out, $code);
        if ($code !== 0 || !is_file($local) || filesize($local) === 0) {
            @unlink($local);
            $failed[$url] = $where[0];
            printf("  FAILED  %s\n", $url);
            continue;
        }
    }

    // an HTML error page saved with an image name would be worse than a gap
    $info = @getimagesize($local);
    if (!$info && !str_ends_with($name, '.svg')) {
        @unlink($local);
        $failed[$url] = $where[0];
        printf("  NOT AN IMAGE  %s\n", $url);
        continue;
    }

    $bytes += filesize($local);
    $map[$url] = $rel;
    printf("  %-46s %7s  %s\n", substr($name, 0, 46),
           number_format(filesize($local) / 1024, 0) . 'K', $where[0]);
}

if ($failed) {
    echo "\n" . count($failed) . " could not be fetched — the copy still points at them:\n";
    foreach ($failed as $url => $where) echo "  $url  ($where)\n";
}

if ($dry) {
    echo "\nDry run. Nothing downloaded, nothing rewritten.\n";
    exit(0);
}

/* --------------------------------------------------------- rewrite the copy */

$swap = function (string $html) use ($map): string {
    foreach ($map as $remote => $local) {
        $html = str_replace($remote, $local, $html);
    }
    return $html;
};

$touchedProducts = 0;
foreach ($products as $i => $p) {
    $before = $p['short'] . '|' . $p['desc'];
    $products[$i]['short'] = $swap((string) $p['short']);
    $products[$i]['desc']  = $swap((string) $p['desc']);
    if ($before !== $products[$i]['short'] . '|' . $products[$i]['desc']) $touchedProducts++;
}

$touchedPosts = 0;
foreach ($posts as $i => $p) {
    $rewritten = $swap((string) $p['content']);
    if ($rewritten !== $p['content']) $touchedPosts++;
    $posts[$i]['content'] = $rewritten;
}

if (!save_products($products) || !save_posts($posts)) {
    fwrite(STDERR, "could not write the data files\n");
    exit(1);
}

printf("\n%s downloaded into assets/img/content/\n", number_format($bytes / (1 << 20), 1) . ' MB');
printf("rewritten: %d product(s), %d article(s)\n", $touchedProducts, $touchedPosts);
echo $failed ? "still外 " . count($failed) . " left pointing outside\n"
             : "nothing points at the old site any more\n";
