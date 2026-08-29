<?php
/**
 * Write a selling meta description for every indexed page.
 *
 * The titles are NOT touched. They are what the live site ranks on and they
 * are matched byte for byte by .data/check_seo.py; a description is a
 * click-through decision, not a ranking one, so it is the half that can be
 * rewritten without gambling positions.
 *
 * The descriptions are composed from what each product actually is — its
 * application, standard, bore sizes, temperature range and the price it
 * starts at — rather than from one sentence with the name swapped in. A
 * generated line that says "SAE J20 R3, 16–19 mm bore, −40°C to +125°C, from
 * £3.08/m" is worth more to somebody scanning results than a hand-written one
 * that says "high quality hose for all your needs".
 *
 * The originals are in git. To see what any of them was:
 *
 *     git show HEAD~1:data/seo.php
 *
 *   php .data/write_seo_descriptions.php --dry-run   show them, write nothing
 *   php .data/write_seo_descriptions.php             write data/seo.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require ROOT_DIR . '/inc/store.php';   // save_seo()

$dry   = in_array('--dry-run', $argv, true);
$file  = ROOT_DIR . '/data/seo.php';
$seo   = require $file;

const LIMIT = 158;      // where Google usually cuts

/** Join the pieces that fit, in the order they were given. */
function compose(array $parts, int $limit = LIMIT): string
{
    $out = '';
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') continue;
        $next = $out === '' ? $part : $out . ' ' . $part;
        if (mb_strlen($next) > $limit) continue;    // skip it, try the next
        $out = $next;
    }
    return $out;
}

/**
 * One sentence, ending in a full stop, no longer than $max.
 *
 * The abbreviation guard matters here because the catalogue is full of "max.
 * 10 bar" and "approx. 2 mm": breaking on the first dot produced "for fuels
 * with max." which is worse than no description at all. An unclosed bracket
 * gets closed for the same reason — the copy is written mid-parenthesis more
 * often than not.
 */
function first_sentence(string $text, int $max = 96): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'))) ?? '');
    if ($text === '') return '';

    $guard = '(?<!\bmax)(?<!\bmin)(?<!\bapprox)(?<!\bapprx)(?<!\bno)(?<!\bfig)(?<!\bmm)';
    if (preg_match('/^(.{20,' . $max . '}?' . $guard . '[.!?])(?:\s|$)/u', $text . ' ', $m)) {
        return tidy_clause($m[1]);
    }
    if (mb_strlen($text) <= $max) return tidy_clause($text);

    $cut = mb_substr($text, 0, $max);
    $cut = mb_substr($cut, 0, max(1, (int) mb_strrpos($cut, ' ')));
    return tidy_clause($cut);
}

/** Close a stray bracket, drop a dangling comma, end with exactly one stop. */
function tidy_clause(string $s): string
{
    $s = trim($s);
    if (substr_count($s, '(') > substr_count($s, ')')) {
        $s = rtrim($s, ' .,;:');
        // "(for fuels with" says nothing — lose the fragment rather than close it
        $open = mb_strrpos($s, '(');
        if ($open !== false) $s = rtrim(mb_substr($s, 0, $open), ' ,;:-');
    }
    $s = rtrim($s, ' .,;:-');
    /* A cut list ends "in factories, vessels and" more often than not, and a
       description that trails off on a conjunction reads as broken copy. */
    $s = preg_replace('/[\s,]+(?:and|or|with|for|to|in|of|the|a|an)$/i', '', $s) ?? $s;
    $s = rtrim($s, ' .,;:-');
    if ($s === '') return '';
    // A question keeps its question mark rather than gaining a stop after it.
    return preg_match('/[?!]$/', $s) ? $s : $s . '.';
}

/** The label of one parsed spec row, or ''. */
function spec(array $specs, string $want): string
{
    foreach ($specs as $s) {
        if (strcasecmp(trim($s['label']), $want) === 0) return trim($s['value']);
    }
    return '';
}

/** 12.70 -> "12.7", 8.00 -> "8". */
function tidy_number(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

/** Every value of the attribute whose name contains $needle, as numbers. */
function attr_numbers(array $p, string $needle): array
{
    foreach ($p['attrs'] as $a) {
        if (stripos($a['name'], $needle) === false || !$a['terms']) continue;
        $nums = array_values(array_filter(array_map(
            fn($t) => (float) preg_replace('/[^0-9.]/', '', (string) $t['name']), $a['terms'])));
        sort($nums);
        return $nums;
    }
    return [];
}

/** "8 mm bore" or "Bore 3.2–14 mm". */
function bore_phrase(array $p): string
{
    $nums = attr_numbers($p, 'diameter');
    if (!$nums) return '';
    if (count($nums) === 1) return tidy_number($nums[0]) . ' mm bore.';
    return 'Bore ' . tidy_number($nums[0]) . '–' . tidy_number(end($nums)) . ' mm.';
}

/**
 * How it is sold, and for how much.
 *
 * The two shapes are not decoration. Almost everything here is cut from a
 * metre up, so the cheapest option IS the metre price and "from £2.04 per
 * metre" is true. The garden hose starts at a 25 m coil, where the cheapest
 * option is the whole coil — calling that a metre price would be a fivefold
 * understatement on the page most likely to be compared on price.
 */
function price_phrase(array $p): string
{
    if ($p['price_min'] <= 0) return 'Ask us for a price.';

    $from    = money((int) effective_min($p));
    $lengths = attr_numbers($p, 'length');

    if (!$lengths) return 'From ' . $from . ' ex VAT.';

    $low  = tidy_number($lengths[0]);
    $high = tidy_number(end($lengths));

    /* Written tight on purpose. Every character spent on "per metre excluding
       VAT, cut from one metre to fifty" is a character not spent on what the
       hose is FOR, and the reader is scanning, not reading. */
    return abs($lengths[0] - 1.0) < 0.001
        ? 'From ' . $from . '/m ex VAT, cut 1-' . $high . ' m.'
        : 'From ' . $from . ' ex VAT, ' . $low . '-' . $high . ' m coils.';
}

$written = [];

/* ------------------------------------------------------------- products */
foreach (all_products() as $p) {
    $url   = product_url($p);
    $specs = parse_specs($p['short']);

    $standard = tidy_clause(spec($specs, 'Standard'));
    $temp     = tidy_clause(str_replace('/', ' to ', spec($specs, 'Temperature')));
    $bore     = bore_phrase($p);

    /* The facts are built FIRST and the opening sentence gets whatever budget
       is left, rather than the other way round. Taking the sentence first ate
       the whole allowance on the long-winded products and pushed the price
       out of the description entirely — and a hose page in a search result
       without a price is a page nobody clicks.

       The bore leads the facts because it is what separates this page from
       the eleven others in the same family. Twelve descriptions differing
       only in a price would be twelve wasted results. */
    $facts = compose([$bore, $standard !== '' ? 'To ' . $standard : '', price_phrase($p)]);

    $budget = LIMIT - mb_strlen($facts) - 1;
    $what   = $budget >= 40
        ? first_sentence(spec($specs, 'Applications') ?: strip_tags($p['short']), $budget)
        : '';
    if ($what === '' && $facts === '') $what = $p['name'] . '.';

    /* The temperature and the reassurance are last because they are the two
       that can go without the result becoming useless. */
    $written[$url] = compose([$what, $facts, $temp, 'Shipped from the UK.']);
}

/* ----------------------------------------------------------- categories */
/* Written rather than generated. There are twelve of them, and what the live
   site holds is "Explore our extensive range of premium..." — waffle that
   says nothing a buyer is deciding on and does not survive being cut to 158
   characters. The stock count and the price come from the catalogue, so they
   stay true as the shop changes. */
const CATEGORY_LEADS = [
    'hose-couplings'           => 'Worm-drive, heavy-duty and mini hose clamps for hose from 8 mm to 160 mm.',
    'pvcpu-hoses'              => 'PVC and PU hose for air, water, fuel and ducting — light, flexible and clear where you need to see the flow.',
    'rubber-hoses'             => 'Rubber hose for fuel, oil, gas, water, coolant and abrasive media, to SAE, DIN and EN standards.',
    'abrasive-materials'       => 'Sandblast hose built for grit and shot at pressure, with a thick abrasion-resistant tube.',
    'chemicals'                => 'Chemical-resistant rubber hose for transferring solvents, acids and industrial fluids safely.',
    'cooling-system'           => 'Engine coolant and heater hose to SAE J20 R3, glycol resistant and good to +125°C.',
    'flat-water'               => 'Layflat water hose that rolls down to nothing between jobs — irrigation, washdown and site water.',
    'oil-products'             => 'Fuel and oil hose to SAE J30 R6 and DIN 73379, from a 3 mm carburettor line to a 25 mm delivery bore.',
    'gas'                      => 'Acetylene, oxygen and LPG hose to EN 559 and ISO 3821 for welding and gas supply.',
    'oil-products-pvcpu-hoses' => 'Clear PVC tube for petroleum products, small bores for fuel lines, gauges and instrument runs.',
    'ventilation'              => 'Flexible ducting for air, fume and dust extraction, thermally resistant and crush recoverable.',
    'water'                    => 'Rubber water and washdown hose for industrial and garden use, built to take pressure and sun.',
];

foreach (all_categories() as $c) {
    $url  = category_url($c);
    $rows = products_in_category($c['slug']);
    if (!$rows) continue;

    $from = null;
    foreach ($rows as $r) {
        if ($r['price_min'] > 0) $from = $from === null ? $r['price_min'] : min($from, $r['price_min']);
    }

    /* Clamps and couplings are not cut to length and are not priced per
       metre, and a category page that says they are is worse than one that
       says nothing. */
    $cuttable = false;
    foreach ($rows as $r) {
        foreach ($r['attrs'] as $a) {
            if (stripos($a['name'], 'length') !== false && $a['terms']) { $cuttable = true; break 2; }
        }
    }

    $lead = CATEGORY_LEADS[$c['slug']] ?? first_sentence((string) ($c['description'] ?? ''), 90);
    if ($lead === '') $lead = $c['name'] . ' from Arg Flex Ltd.';

    $written[$url] = compose([
        $lead,
        count($rows) . ' line' . (count($rows) === 1 ? '' : 's') . ' in UK stock'
            . ($cuttable ? ', cut to length.' : '.'),
        $from !== null ? 'From ' . money($from) . ($cuttable ? '/m' : '') . ' ex VAT.' : '',
        'Dispatched same day.',
    ]);
}

/* ---------------------------------------------------------------- posts */
/* The first PARAGRAPH of the article, never the excerpt.
   The stored excerpt is the heading and the opening sentence run together
   with no punctuation between them, so anything taken from it begins by
   repeating the title — and the title is the line directly above, in the
   same search result. The content keeps them apart properly: <h2> then <p>. */
foreach (all_posts() as $b) {
    $lead = '';

    /* The FIRST paragraph with something in it. Several of these articles
       open with a one-line question as a sub-heading marked up as a
       paragraph — "What Is an SAE J30 R6 Hose?" — and a description that is
       only a question answers nobody deciding whether to click. */
    if (preg_match_all('~<p[^>]*>(.*?)</p>~is', (string) ($b['content'] ?? ''), $ms)) {
        foreach ($ms[1] as $para) {
            $plain = trim(preg_replace('/\s+/', ' ',
                strip_tags(html_entity_decode($para, ENT_QUOTES, 'UTF-8'))) ?? '');
            if (mb_strlen($plain) < 70) continue;
            $lead = first_sentence($plain, LIMIT - 66);
            if ($lead !== '') break;
        }
    }
    if ($lead === '') {
        // No paragraph markup: fall back to the excerpt, heading and all.
        $lead = first_sentence((string) ($b['excerpt'] ?? ''), LIMIT - 66);
    }
    if ($lead === '') $lead = tidy_clause((string) $b['title']);

    // The tag line goes on only when a whole sentence still leaves room.
    $written['/' . $b['slug'] . '/'] = compose([
        $lead,
        'A practical guide from Arg Flex Ltd, hose specialists in the UK.',
    ]);
}

/* --------------------------------------------------------- fixed pages */
/* Hand written, because each of these is doing a different job and no
   composition of catalogue facts would say what they need to say. */
$fixed = [
    '/' => 'Industrial hose cut to length and shipped from the UK: fuel, oil, gas, water, '
         . 'chemical and abrasive lines from 1 m to 50 m, with clamps and couplings to match.',

    '/shop/' => 'The full Arg Flex catalogue: rubber, PVC and PU hose for fuel, oil, gas, water '
              . 'and air, cut to length from 1 m to 50 m. Priced per metre ex VAT, UK delivery.',

    '/blog/' => 'Practical guides to choosing and using industrial hose: pressure ratings, '
              . 'temperature limits, standards and what actually fails in service.',

    '/about-us/' => 'Arg Flex Ltd supplies industrial hose and couplings across the UK from '
                  . 'South Woodford, London. Over 35 stocked lines, cut to length, dispatched same day.',

    '/contacts/' => 'Call ' . SITE_PHONE . ' or email ' . SITE_EMAIL . ' for a quote on any hose, '
                  . 'cut to length. We answer technical enquiries the same working day.',

    '/refund_returns/' => 'How returns work on hose cut to length, what we can take back, and how '
                        . 'to tell us about a fault. Arg Flex Ltd, UK.',
];
foreach ($fixed as $url => $text) $written[$url] = $text;

/* ------------------------------------------------------------- applying */
$changed = 0;
$long    = 0;

foreach ($written as $url => $text) {
    if ($text === '') continue;
    if (mb_strlen($text) > 175) { $long++; }

    if (!isset($seo[$url])) {
        // A product with no entry at all — /product/fuel-hose-din-73379-b/ is
        // one. It gets a description and nothing else, so its title still
        // comes from the page as before.
        $seo[$url] = ['description' => $text];
        $changed++;
        continue;
    }
    $before = $seo[$url]['description'] ?? '';
    $seo[$url]['description'] = $text;

    /* og:description is what a link to this page shows when it is pasted into
       WhatsApp or LinkedIn, and it was still carrying the old wording. Only
       replaced where it was a copy of the description it sat next to: where
       somebody has written a different one deliberately, that is theirs. */
    if (isset($seo[$url]['og_description'])
        && ($before === '' || $seo[$url]['og_description'] === $before)) {
        $seo[$url]['og_description'] = $text;
    }

    if ($before !== $text) $changed++;
}

printf("%d description(s) written, %d over 175 characters\n", $changed, $long);

if ($dry) {
    foreach ($written as $url => $text) {
        printf("\n%s\n  %s  (%d)\n", $url, $text, mb_strlen($text));
    }
    echo "\nDry run — data/seo.php untouched.\n";
    exit(0);
}

/* Written by save_seo(), which is what Admin -> SEO uses. Rolling my own
   var_export() here produced a file that parsed perfectly and that
   .data/check_seo.py could not read a single URL out of — it greps the source
   for "    '/path' => [" and var_export writes "  '/' => 
  array (".
   Two writers for one file is two formats, eventually. */
if (!save_seo($seo)) {
    fwrite(STDERR, "data/seo.php could not be written
");
    exit(1);
}
echo "data/seo.php rewritten
";
