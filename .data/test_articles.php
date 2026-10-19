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
