<?php
/**
 * A second image for every product, drawn from what the product already says.
 *
 * The catalogue has one photograph per hose, and the hoses look alike in a
 * photograph — they are told apart by a bore, a standard and a temperature
 * range. So the second image is not another photograph: it is those facts,
 * set large. That is what the hover on a card reveals, and it is the thing a
 * buyer is actually comparing.
 *
 * Drawn as SVG because it costs about two kilobytes, stays sharp at any size,
 * and is generated from data/products.php — change a temperature in the admin,
 * run this again, and the card follows. Nothing here is copied from anywhere.
 *
 * A real photograph always wins: a product that already has two images is left
 * alone, and dropping a real second photo in the admin replaces the card.
 *
 *   php .data/make_spec_cards.php --dry-run
 *   php .data/make_spec_cards.php
 *   php .data/make_spec_cards.php --clear     remove them again
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';

$dry   = in_array('--dry-run', $argv, true);
$clear = in_array('--clear', $argv, true);

const CARD_DIR = 'assets/img/products';
const CARD_TAG = '-spec.svg';          // how a generated card is recognised again

/* The site's own tokens, so a card sitting beside a photograph on the same
   grid does not look like it came from somewhere else. */
const INK   = '#0b1220';
const STEEL = '#5b6880';
const LINE  = '#e3e7ee';
const SOFT  = '#f4f6fa';
const BRAND = '#c34515';
const FONT  = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";

function x(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Trim a spec value down to something that fits on one line. */
function short_value(string $v, int $max = 30): string
{
    $v = trim(preg_replace('/\s+/', ' ', $v) ?? $v);
    $v = rtrim($v, ' .');
    // "9 mm (5 mm, 12 mm, 14 mm are available…)" — the bracket is detail
    $v = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $v) ?? $v);
    return mb_strlen($v) > $max ? rtrim(mb_substr($v, 0, $max - 1), ' ,;:') . '…' : $v;
}

/** One spec row by label, or ''. */
function spec_of(array $specs, string $want): string
{
    foreach ($specs as $s) {
        if (strcasecmp(trim((string) $s['label']), $want) === 0) return (string) $s['value'];
    }
    return '';
}

/**
 * The headline: the number a buyer is looking for.
 *
 * A hose is chosen by bore, so that leads. Anything with no bore at all — the
 * clamps — leads on the standard, and failing that on its category, because a
 * card with a blank middle is worse than one that repeats something.
 */
function headline(array $p, array $specs): array
{
    $bores = array_map(fn($b) => bore_value($b['name']), product_bores($p));
    $bores = array_values(array_filter($bores, fn($v) => $v < PHP_FLOAT_MAX));

    if ($bores) {
        sort($bores);
        $text = count($bores) === 1
            ? tidy_number($bores[0])
            : tidy_number($bores[0]) . '–' . tidy_number(end($bores));
        return [$text, 'mm', 'Internal diameter'];
    }

    /* "EN 559/ISO 3821/AS 1335" is three standards. As a headline it has to be
       one — the rest is in the facts below, and a truncated "EN 559/ISO 382…"
       set 130 points high is worse than either. */
    $standard = short_value(spec_of($specs, 'Standard'), 40);
    if ($standard !== '') {
        $first = trim(explode('/', $standard)[0]);
        return [mb_strlen($first) >= 4 ? $first : short_value($standard, 22), '', 'Standard'];
    }

    /* A clamp has no bore; what it has is the range of hose it closes on.
       "17-19 ÷ 253-265" is the smallest and largest it will take. */
    $dims = spec_of($specs, 'Dimensions');
    if ($dims !== '' && preg_match_all('/[0-9]+(?:[.,][0-9]+)?/', $dims, $m) && count($m[0]) >= 2) {
        $nums = array_map(fn($n) => (float) str_replace(',', '.', $n), $m[0]);
        return [tidy_number(min($nums)) . '–' . tidy_number(max($nums)), 'mm', 'Clamping range'];
    }

    $band = short_value(spec_of($specs, 'Band width'), 16);
    if ($band !== '') return [$band, '', 'Band width'];

    /* Some names carry the fact the product is sold on — "Oil Delivery Hose
       10 Bar". Better the number in the name than the category it sits in. */
    if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*bar\b/i', (string) $p['name'], $m)) {
        return [tidy_number((float) str_replace(',', '.', $m[1])), 'bar', 'Working pressure'];
    }

    /* Nothing else distinguishes it, so lead on what it will take. A silicone
       hose good to +220°C is bought for exactly that. */
    $temp = short_value(str_replace('/', ' to ', spec_of($specs, 'Temperature')), 20);
    if ($temp !== '') return [$temp, '', 'Temperature range'];

    $primary = primary_category($p);
    return [short_value($primary['name'] ?? SITE_NAME, 22), '', 'Arg Flex Ltd'];
}

/** 12.70 -> "12.7", 8.00 -> "8". */
function tidy_number(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

/**
 * A file name that survives being a URL.
 *
 * One slug in the catalogue is "sandblast-hose-56-mm%c2%b3" — the ³ arrived
 * from WordPress already percent-encoded and was stored that way. Writing a
 * file under that name puts a literal % on disk, and the browser then asks
 * for the DECODED name, which is a different file. The card existed and 404'd
 * on eleven pages. Letters, digits and dashes only.
 */
function card_name(string $slug, array &$taken): string
{
    $name = preg_replace('/%[0-9a-f]{2}/i', '', strtolower($slug)) ?? $slug;
    $name = trim(preg_replace('/[^a-z0-9]+/', '-', $name) ?? $name, '-');
    if ($name === '') $name = 'product';

    $try = $name;
    for ($n = 2; isset($taken[$try]); $n++) $try = $name . '-' . $n;
    $taken[$try] = true;
    return $try;
}

/** Up to three supporting facts, in the order a specifier reads them. */
function facts(array $p, array $specs): array
{
    $out = [];

    $standard = short_value(spec_of($specs, 'Standard'), 26);
    if ($standard !== '') $out[] = ['Standard', $standard];

    $temp = short_value(str_replace('/', ' to ', spec_of($specs, 'Temperature')), 26);
    if ($temp !== '') $out[] = ['Temperature', $temp];

    foreach ($p['attrs'] as $a) {
        if (stripos((string) $a['name'], 'length') === false || !$a['terms']) continue;
        $nums = array_values(array_filter(array_map(
            fn($t) => (float) preg_replace('/[^0-9.]/', '', (string) $t['name']), $a['terms'])));
        if (!$nums) break;
        sort($nums);
        $out[] = ['Cut to length', count($nums) > 1
            ? tidy_number($nums[0]) . ' m to ' . tidy_number(end($nums)) . ' m'
            : tidy_number($nums[0]) . ' m'];
        break;
    }

    if (count($out) < 3) {
        $band = short_value(spec_of($specs, 'Band width'), 26);
        if ($band !== '') $out[] = ['Band width', $band];
    }
    if (count($out) < 3) {
        $dim = short_value(spec_of($specs, 'Dimensions'), 26);
        if ($dim !== '') $out[] = ['Dimensions', $dim];
    }

    return array_slice($out, 0, 3);
}

/**
 * The card itself. 800x600 to match the 4:3 frame a product card renders in,
 * so it never letterboxes beside the photograph it fades into.
 */
function card_svg(array $p): string
{
    $specs = parse_specs((string) $p['short']);
    [$big, $unit, $bigLabel] = headline($p, $specs);
    $rows = facts($p, $specs);

    /* Sized down as the string gets longer, so "3.2–14" and "8" both sit in
       the same box. textLength then guarantees it, whatever font the viewer's
       machine substitutes — an SVG in an <img> cannot carry one with it. */
    $len  = mb_strlen($big);
    $size = $len <= 2 ? 210 : ($len <= 4 ? 175 : ($len <= 7 ? 135 : ($len <= 12 ? 92 : 62)));
    $wide = min(660, (int) round($size * 0.62 * $len));

    $factY = 430;
    $factSvg = '';
    foreach ($rows as $i => [$label, $value]) {
        $y = $factY + $i * 46;
        $factSvg .= '<text x="70" y="' . $y . '" font-family="' . FONT . '" font-size="19" '
                  . 'font-weight="700" letter-spacing="1.6" fill="' . STEEL . '">'
                  . x(mb_strtoupper($label)) . '</text>'
                  . '<text x="730" y="' . $y . '" text-anchor="end" font-family="' . FONT . '" '
                  . 'font-size="23" font-weight="600" fill="' . INK . '">' . x($value) . '</text>'
                  . '<line x1="70" y1="' . ($y + 16) . '" x2="730" y2="' . ($y + 16)
                  . '" stroke="' . LINE . '" stroke-width="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
      . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600" '
      . 'role="img" aria-label="' . x($p['name'] . ' — ' . $bigLabel . ' ' . $big . $unit) . '">'
      . '<rect width="800" height="600" fill="' . SOFT . '"/>'
      . '<rect x="0" y="0" width="800" height="6" fill="' . BRAND . '"/>'

      . '<text x="70" y="86" font-family="' . FONT . '" font-size="20" font-weight="800" '
      . 'letter-spacing="3" fill="' . STEEL . '">ARG FLEX LTD</text>'

      . '<text x="400" y="270" text-anchor="middle" textLength="' . $wide . '" '
      . 'lengthAdjust="spacingAndGlyphs" font-family="' . FONT . '" font-size="' . $size . '" '
      . 'font-weight="800" letter-spacing="-3" fill="' . INK . '">' . x($big) . '</text>'
      . ($unit !== ''
          ? '<text x="400" y="330" text-anchor="middle" font-family="' . FONT . '" font-size="44" '
            . 'font-weight="700" fill="' . STEEL . '">' . x($unit) . '</text>'
          : '')
      . '<text x="400" y="' . ($unit !== '' ? 378 : 336) . '" text-anchor="middle" '
      . 'font-family="' . FONT . '" font-size="22" font-weight="700" letter-spacing="4" '
      . 'fill="' . BRAND . '">' . x(mb_strtoupper($bigLabel)) . '</text>'

      . $factSvg
      . '</svg>';
}

/* ------------------------------------------------------------------ apply */

$products = all_products(true);
$dir      = ROOT_DIR . '/' . CARD_DIR;

if ($clear) {
    $gone = 0;
    foreach ($products as $i => $p) {
        $kept = array_values(array_filter((array) $p['images'],
            fn($src) => !str_ends_with((string) $src, CARD_TAG)));
        if (count($kept) !== count($p['images'])) { $products[$i]['images'] = $kept; $gone++; }
    }
    foreach (glob($dir . '/*' . CARD_TAG) ?: [] as $file) {
        if (!$dry) @unlink($file);
    }
    printf("%d product(s) %s a generated card\n", $gone, $dry ? 'would lose' : 'lost');
    if ($dry) { echo "\nDry run — nothing written.\n"; exit(0); }
    if ($gone) save_products($products);
    echo "cards removed\n";
    exit(0);
}

if (!is_dir($dir) && !$dry) @mkdir($dir, 0775, true);

$made = 0;
$skipped = 0;
$taken = [];

foreach ($products as $i => $p) {
    $p = product_defaults($p);

    /* A real photograph always wins. Only the slot after the first is filled,
       and only when nothing is in it — so dropping a genuine second photo in
       the admin quietly replaces this and running the script again leaves it
       alone. */
    $images = array_values(array_filter((array) $p['images'], fn($s) => trim((string) $s) !== ''));
    $has    = count($images) > 1 && !str_ends_with((string) $images[1], CARD_TAG);
    if (!$images || $has) { $skipped++; continue; }

    $file = CARD_DIR . '/' . card_name((string) $p['slug'], $taken) . CARD_TAG;
    $svg  = card_svg($p);

    [$big, $unit, $label] = headline($p, parse_specs((string) $p['short']));
    printf("  %-46s %s%s  (%s)\n", mb_substr((string) $p['name'], 0, 46), $big, $unit, strtolower($label));

    if (!$dry) {
        file_put_contents(ROOT_DIR . '/' . $file, $svg);
        $images[1] = $file;
        ksort($images);
        $products[$i]['images'] = array_values($images);
    }
    $made++;
}

printf("\n%d card(s)%s, %d left alone\n", $made, $dry ? ' would be made' : ' written', $skipped);

if ($dry) { echo "\nDry run — nothing written.\n"; exit(0); }

save_products($products);
echo "data/products.php updated\n";
