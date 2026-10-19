<?php
/**
 * What every article owes the reader: a finished standfirst, and a way to buy
 * the hose it is about.
 *
 * Twenty articles once carried three links between them and sent people who
 * had already found the right hose back to Google. Three of those articles
 * matched no product at all, and three opened on a sentence that stopped dead.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/config.php';
require_once ROOT_DIR . '/inc/commerce.php';

$ok = $bad = 0;
function check(string $label, bool $cond, string $note = ''): void {
    global $ok, $bad;
    $cond ? $ok++ : $bad++;
    printf("  %-52s %s %s\n", $label, $cond ? 'OK  ' : 'FAIL', $note);
}

$posts = all_posts();
$flat  = fn($s) => trim(preg_replace('~\s+~', ' ', strip_tags((string) $s)));

echo "EVERY STANDFIRST FINISHES ITS SENTENCE\n";
$cut = [];
foreach ($posts as $p) {
    $l = post_lede($p);
    if ($l === '' || !preg_match('~[.!?]["\')]?$~', $l)
        || preg_match('~\b(where|which|that|and|but|or|with|for|from|to|of|in|on'
                    . '|is|are|was|the|a|an|its|their)\s*[.!?]$~i', $l)) {
        $cut[] = $p['slug'];
    }
}
check('no article opens on a broken sentence', !$cut, $cut ? implode(', ', $cut) : '');

echo "\nEVERY ARTICLE OFFERS SOMETHING TO BUY\n";
$orphan = [];
foreach ($posts as $p) {
    if (post_products($p) || post_category($p)) continue;
    $orphan[] = $p['slug'];
}
check('each has a product or a category behind it', !$orphan, $orphan ? implode(', ', $orphan) : '');

echo "\nTHE STANDARDS LINKS DO NOT CORRUPT THE BODY\n";
$nested = $wrong = [];
foreach ($posts as $p) {
    $html = link_standards((string) $p['content'], post_products($p));

    $depth = 0;
    foreach (preg_split('~(<[^>]+>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE) as $bit) {
        if ($bit === '' || $bit[0] !== '<') continue;
        if (preg_match('~^<\s*a\b~i', $bit))        $depth++;
        elseif (preg_match('~^<\s*/\s*a\b~i', $bit)) $depth--;
        if ($depth > 1 || $depth < 0) { $nested[] = $p['slug']; break; }
    }

    // an injected anchor must read as the standard it links, never prose
    foreach (preg_match_all('~<a href="/product/[^"]*">([^<]*)</a>~', $html, $m) ? $m[1] : [] as $text) {
        if (!preg_match('~^\s*(SAE\s*J\d+\s*R\d+|DIN\s*\d+\s*B?)\s*$~i', $text)) $wrong[] = $p['slug'] . ': ' . $text;
    }

    // the body must survive the filter otherwise untouched
    if ($flat(preg_replace('~</?a[^>]*>~', '', $html)) !== $flat($p['content'])) {
        $wrong[] = $p['slug'] . ': text changed beyond the links';
    }
}
check('no nested or unbalanced anchors', !$nested, $nested ? implode(', ', array_unique($nested)) : '');
check('every injected anchor is a standard code', !$wrong, $wrong ? implode(' | ', array_slice($wrong, 0, 3)) : '');

echo "\nWHAT CAN BE SHIPPED COMES FIRST\n";
$sellable = fn(array $p) => product_in_stock($p) && (int) ($p['price_min'] ?? 0) > 0;
$outOfOrder = [];
foreach ($posts as $p) {
    $seenDead = false;
    foreach (post_products($p) as $x) {
        if (!$sellable($x)) { $seenDead = true; continue; }
        if ($seenDead) { $outOfOrder[] = $p['slug']; break; }
    }
}
check('no sellable hose sits below an unavailable one', !$outOfOrder,
      $outOfOrder ? implode(', ', $outOfOrder) : '');

echo "\nNO ARTICLE OFFERS THE SAME HOSE TWICE\n";
$twice = [];
foreach ($posts as $p) {
    $shown = post_products($p);
    foreach ($shown as $i => $a) {
        foreach (array_slice($shown, $i + 1) as $b) {
            if (same_family($a, $b)) { $twice[] = $p['slug'] . ': ' . $a['slug'] . ' / ' . $b['slug']; }
        }
    }
}
check('each listing is a different hose', !$twice, $twice ? implode(' | ', array_slice($twice, 0, 2)) : '');

echo "\nA STANDARD IN THE TITLE IS ANSWERED BY THAT STANDARD\n";
$missed = [];
foreach ($posts as $p) {
    if (!preg_match_all('~\b(SAE\s?J\d+\s?R\d+)\b~i', (string) $p['title'], $m)) continue;
    $shown = implode(' ', array_map(fn($x) => (string) $x['name'], post_products($p)));
    foreach ($m[1] as $code) {
        $re = '~\b' . preg_replace('~\s+~', '\s*', preg_quote(strtoupper($code), '~')) . '\b~i';
        $anywhere = false;
        foreach (all_products() as $q) {
            if (preg_match($re, (string) $q['name'])) { $anywhere = true; break; }
        }
        if ($anywhere && !preg_match($re, $shown)) $missed[] = $p['slug'] . ' (' . $code . ')';
    }
}
check('the titled grade is among the hoses shown', !$missed,
      $missed ? implode(', ', $missed) : '');

echo "\nTHE FILTER NEVER RUNS AWAY\n";
$most = 0;
foreach ($posts as $p) {
    $n = preg_match_all('~<a href="/product/~', link_standards((string) $p['content'], post_products($p)));
    $before = preg_match_all('~<a href="/product/~', (string) $p['content']);
    $most = max($most, $n - $before);
}
check('at most two links added to any article', $most <= 2, '(most added: ' . $most . ')');

printf("\n  %d passed, %d failed%s\n", $ok, $bad, $bad ? '  <-- LOOK' : '');
exit($bad ? 1 : 0);
