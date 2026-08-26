<?php
/**
 * Convert the catalogue and article images to WebP, and repoint everything
 * that refers to them.
 *
 *   php .data/convert_images.php --dry-run
 *   php .data/convert_images.php
 *
 * The site already serves 53 WebP files, so this raises no new question of
 * browser support — it just stops half the pictures being twice the size
 * they need to be.
 *
 * Left alone on purpose:
 *
 *   assets/img/site/      the logo goes into order emails, and Outlook still
 *                         cannot read WebP
 *   assets/img/favicon/   browsers want PNG and ICO there
 *
 * A converted file is only kept when it is actually smaller. Some tightly
 * compressed JPEGs are not worth re-encoding, and a bigger file that also
 * loses a generation of quality is the worst of both.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

if (!extension_loaded('gd') || empty(gd_info()['WebP Support'])) {
    fwrite(STDERR, "This needs PHP's gd extension with WebP support.\n");
    exit(1);
}

const QUALITY = 82;
const FOLDERS = ['products', 'blog', 'content'];

$dry = in_array('--dry-run', $argv, true);

/** Read an image whatever it is, or null. */
function load_image(string $path): ?GdImage
{
    $info = @getimagesize($path);
    $image = match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_GIF  => @imagecreatefromgif($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        default        => null,
    };
    return $image ?: null;
}

/* -------------------------------------------------------------- convert */

$plan    = [];      // old relative path => new relative path
$claimed = [];      // targets already spoken for in this run
$saved  = 0;
$before = 0;
$kept   = [];

foreach (FOLDERS as $folder) {
    foreach (glob(ROOT_DIR . '/assets/img/' . $folder . '/*') ?: [] as $file) {
        if (!is_file($file)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) continue;

        $base = pathinfo($file, PATHINFO_FILENAME);
        $webp = dirname($file) . '/' . $base . '.webp';
        $rel  = 'assets/img/' . $folder . '/' . $base;

        // Two sources can want the same target: assets/img/products carries
        // both asfa-clamps.jpg and asfa-clamps.jpeg, and letting the second
        // overwrite the first would silently swap one product's photo for
        // another's. A .webp already sitting there is likewise a different
        // picture, not this one converted.
        $newRel = $rel . '.webp';
        if (is_file($webp) || in_array($newRel, $claimed, true)) {
            $n = 2;
            do {
                $webp   = dirname($file) . '/' . $base . '-' . $n . '.webp';
                $newRel = $rel . '-' . $n . '.webp';
                $n++;
            } while (is_file($webp) || in_array($newRel, $claimed, true));
        }
        $claimed[] = $newRel;

        $wasSize = filesize($file);
        $before += $wasSize;

        if ($dry) {
            printf("  %-52s %6sK -> webp\n", basename($file), number_format($wasSize / 1024, 0));
            $plan['assets/img/' . $folder . '/' . basename($file)] = $newRel;
            continue;
        }

        $image = load_image($file);
        if (!$image) { printf("  SKIP  %s could not be read\n", basename($file)); continue; }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $tmp = $webp . '.tmp';
        if (!imagewebp($image, $tmp, QUALITY)) {
            imagedestroy($image);
            @unlink($tmp);
            printf("  SKIP  %s would not encode\n", basename($file));
            continue;
        }
        imagedestroy($image);

        $nowSize = filesize($tmp);
        if ($nowSize >= $wasSize) {
            @unlink($tmp);
            $kept[] = basename($file);
            printf("  keep  %-46s webp was bigger\n", basename($file));
            continue;
        }

        rename($tmp, $webp);
        $saved += $wasSize - $nowSize;
        $plan['assets/img/' . $folder . '/' . basename($file)] = $newRel;
        printf("  %-46s %6sK -> %6sK  (-%d%%)\n", basename($file),
               number_format($wasSize / 1024, 0), number_format($nowSize / 1024, 0),
               (int) round((1 - $nowSize / $wasSize) * 100));
    }
}

if (!$plan) {
    echo "\nNothing to convert.\n";
    exit(0);
}

printf("\n%d converted, %d left as they were, %s saved\n",
       count($plan), count($kept), number_format($saved / (1 << 20), 1) . ' MB');

if ($dry) {
    echo "Dry run — nothing written.\n";
    exit(0);
}

/* ------------------------------------------------------ repoint the site */

$swap = function (string $text) use ($plan): string {
    foreach ($plan as $old => $new) {
        $text = str_replace($old, $new, $text);
    }
    return $text;
};

$products = all_products(true);
foreach ($products as $i => $p) {
    $products[$i]['images'] = array_map($swap, (array) $p['images']);
    $products[$i]['short']  = $swap((string) $p['short']);
    $products[$i]['desc']   = $swap((string) $p['desc']);
}
save_products($products);

$posts = all_posts();
foreach ($posts as $i => $p) {
    if (!empty($p['image'])) $posts[$i]['image'] = $swap((string) $p['image']);
    $posts[$i]['content'] = $swap((string) $p['content']);
}
save_posts($posts);

$categories = all_categories();
foreach ($categories as $i => $c) {
    if (!empty($c['image'])) $categories[$i]['image'] = $swap((string) $c['image']);
}
save_categories($categories);

// anything hard-coded in a template or a stylesheet
$touched = 0;
foreach (array_merge(
    glob(ROOT_DIR . '/pages/*.php') ?: [],
    glob(ROOT_DIR . '/partials/*.php') ?: [],
    glob(ROOT_DIR . '/inc/*.php') ?: [],
    glob(ROOT_DIR . '/assets/css/*.css') ?: []
) as $file) {
    $was = (string) file_get_contents($file);
    $now = $swap($was);
    if ($now !== $was) { file_put_contents($file, $now); $touched++; }
}

echo "rewritten: the catalogue, the articles, the categories";
echo $touched ? ", and {$touched} template(s)\n" : "\n";

/* --------------------------------------- and only then drop the originals */

$gone = 0;
foreach (array_keys($plan) as $old) {
    if (@unlink(ROOT_DIR . '/' . $old)) $gone++;
}
echo "{$gone} original(s) removed\n";
