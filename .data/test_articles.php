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

    // an injected anchor must read as the standard it links, never prose;
    // a link the author wrote into the article is theirs and is left alone
    $authored = preg_match_all('~<a href="/product/[^"]*">([^<]*)</a>~', (string) $p['content'], $m0) ? $m0[0] : [];
    foreach (preg_match_all('~<a href="/product/[^"]*">([^<]*)</a>~', $html, $m) ? $m[0] : [] as $i => $anchor) {
        $at = array_search($anchor, $authored, true);
        if ($at !== false) { unset($authored[$at]); continue; }
        $text = $m[1][$i];
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

/* ---------------------------------------------------------------------------
   WHICH LISTING OF A HOSE AN ARTICLE POINTS AT.

   Every article that said "SAE J30 R6" linked the 3.2 mm, because "cheapest
   in stock" always answers the same: thirteen bores, one of them linked, and
   the article titled after the 12.7 mm linked the 3.2 mm too.

   The articles are made up here, in memory, against whichever family the
   catalogue really has — the rule must hold whatever data/posts.php holds
   today, and the live one holds more than this copy does. */
echo "\nAN ARTICLE NAMED AFTER A BORE LINKS THAT BORE\n";

$standard = '~\b(SAE\s?J\d+\s?R\d+|DIN\s?\d{4,5})\b~i';
$family   = [];
$code     = '';
foreach (all_families() as $members) {
    $single = array_values(array_filter($members, fn($x) => count(product_bores($x)) === 1));
    if (count($single) < 3 || !preg_match($standard, (string) $single[0]['name'], $m)) continue;
    $family = $single;
    $code   = $m[1];
    break;
}

if (!$family) {
    check('a family of one hose in several bores exists', true, '(none in this catalogue: nothing to test)');
} else {
    $link    = fn(array $article) => link_standards((string) $article['content'], post_products($article));
    $target  = fn(array $article) => preg_match('~<a href="(/product/[^"]+)">' . preg_quote($code, '~') . '</a>~', $link($article), $m) ? $m[1] : '';
    $article = fn(string $slug, string $title, string $body = '') => [
        'slug' => $slug, 'title' => $title, 'date' => '2026-01-01', 'excerpt' => '', 'image' => '',
        'content' => $body !== '' ? $body : '<h2>About ' . $code . '</h2><p>A hose to ' . $code . ' carries fuel and oil.</p>',
    ];

    // one made-up article per bore, named in the title as the shop writes it
    $missed = [];
    foreach ($family as $member) {
        $bore = product_bores($member)[0];
        $made = $article('a-guide-to-' . $bore['slug'] . '-hose', 'Oil-Resistant Hose (' . $code . ' – ' . $bore['name'] . '): a guide');
        // two listings can be the same bore (the catalogue has two 6 mm), so
        // it is the bore that is compared, not the address
        $got  = find_product(basename($target($made)));
        if (!$got || bore_key(bore_value(product_bores($got)[0]['name'] ?? '')) !== bore_key(bore_value($bore['name']))) {
            $missed[] = $bore['name'] . ' -> ' . $target($made);
        }
    }
    check('a bore in the title is answered by that bore', !$missed, implode(' | ', array_slice($missed, 0, 3)));

    // the slug alone says it too, in the only way a slug can: 12-7mm
    $missed = [];
    foreach ($family as $member) {
        $bore = product_bores($member)[0];
        $made = $article('oil-resistant-hose-' . trim(preg_replace('~[^a-z0-9]+~', '-', lower($code)), '-') . '-' . $bore['slug'] . '-explained', $code . ' explained');
        $got  = find_product(basename($target($made)));
        if (!$got || bore_key(bore_value(product_bores($got)[0]['name'] ?? '')) !== bore_key(bore_value($bore['name']))) {
            $missed[] = $bore['slug'] . ' -> ' . $target($made);
        }
    }
    check('a bore in the slug is answered by that bore', !$missed, implode(' | ', array_slice($missed, 0, 3)));

    // the real one, when this copy of the blog has it
    $real = find_post('oil-resistant-hose-sae-j30-r6-12-7mm-designed-for-fuel-and-oil-applications');
    if ($real && find_product('oil-resistant-hose-sae-j30-r6-12-7mm')) {
        check('the 12.7 mm article links the 12.7 mm hose',
              str_contains(link_standards((string) $real['content'], post_products($real)),
                           'href="/product/oil-resistant-hose-sae-j30-r6-12-7mm/"'),
              implode(', ', array_column(post_products($real), 'slug')));
    }

    echo "\nAN ARTICLE THAT NAMES NO BORE DOES NOT ALWAYS GET THE SAME ONE\n";

    /* This asked 400 made-up articles one at a time and counted how many
       listings a hash of their slugs reached. The listings are no longer
       chosen by a hash: the articles that name a standard are dealt one each,
       in turn (post_family_member()), so an article asked about on its own is
       always first in the queue and the old loop reached one listing of
       thirteen. What matters now is that articles asked about TOGETHER get
       different listings — as many articles as there are listings reach
       every one, and no two share one until then. */
    $urls     = array_map('product_url', $family);
    $sellable = array_filter($family, fn($x) => product_in_stock($x) && (int) ($x['price_min'] ?? 0) > 0);
    $expected = array_map('product_url', array_values($sellable ?: $family));

    $together = [];
    for ($i = 0; $i < count($expected) + 3; $i++) {
        $together[] = ['date' => sprintf('2026-02-%02d', 1 + $i % 5)]
                    + $article('a-made-up-article-' . $i, 'What ' . $code . ' hose is for');
    }
    $dealt = [];
    foreach ($together as $one) {
        $html = link_standards((string) $one['content'], post_products($one, 3, $together));
        $dealt[] = preg_match('~<a href="(/product/[^"]+)">' . preg_quote($code, '~') . '</a>~', $html, $m) ? $m[1] : '';
    }
    $got = array_count_values($dealt);
    check('every listing is some article\'s link', !array_diff($expected, array_keys($got)),
          count($got) . ' of ' . count($expected) . ' reached');
    check('and nothing outside the family is', !array_diff(array_keys($got), $urls),
          implode(', ', array_slice(array_diff(array_keys($got), $urls), 0, 2)));
    check('no listing is linked three times while another is linked once',
          $got && max($got) - min($got) <= 1, json_encode(array_values($got)));

    // the oldest article gets the middle of the range, not an end of it
    usort($together, fn($a, $b) => [$a['date'], $a['slug']] <=> [$b['date'], $b['slug']]);
    $sizes = array_map(fn($x) => bore_value(product_bores($x)[0]['name']), array_values($sellable ?: $family));
    sort($sizes);
    $first = find_product(basename(preg_match('~<a href="(/product/[^"]+)">~',
                 link_standards((string) $together[0]['content'], post_products($together[0], 3, $together)), $m) ? $m[1] : ''));
    check('the first article dealt gets the middle bore',
          $first && bore_value(product_bores($first)[0]['name']) === $sizes[intdiv(count($sizes) - 1, 2)],
          $first ? (string) $first['slug'] : 'nothing');

    $once  = $article('the-same-article', 'What ' . $code . ' hose is for');
    $first = $target($once);
    check('the same article always gets the same one',
          $first !== '' && $first === $target($once)
          && $first === $target(['excerpt' => 'changed', 'date' => '2030-01-01'] + $once), $first);

    // a title that names two bores is about the range, not about either
    $ends  = [product_bores($family[0])[0]['name'], product_bores($family[count($family) - 1])[0]['name']];
    $range = $article('the-same-article', $code . ' sizes: every bore from ' . $ends[0] . ' to ' . $ends[1]);
    check('a range of bores names none', $target($range) === $first, $target($range));

    echo "\nA LINK THE AUTHOR WROTE IS THE AUTHOR'S\n";
    $own  = '<p>See <a href="' . product_url($family[0]) . '">' . $code . '</a> for the small one. '
          . 'Later, ' . $code . ' again.</p>';
    $made = $article('a-guide-to-' . product_bores($family[count($family) - 1])[0]['slug'] . '-hose',
                     $code . ' (' . product_bores($family[count($family) - 1])[0]['name'] . ')', $own);
    $html = $link($made);
    check('it keeps its address and its words',
          str_contains($html, '<a href="' . product_url($family[0]) . '">' . $code . '</a> for the small one'), '');
    check('and the next mention goes where the article points',
          str_contains($html, 'Later, <a href="' . product_url($family[count($family) - 1]) . '">' . $code . '</a> again'),
          '');
}

/* ---------------------------------------------------------------------------
   WHAT AN ARTICLE OFFERS TO READ NEXT, AND WHICH ARTICLES A HOSE OFFERS.
   The rendered blocks are held to the same rules by check_link_blocks.py;
   this is the working underneath them. */
echo "\nWHAT TO READ NEXT IS CHOSEN, NOT THE NEWEST THREE\n";

$offered = array_fill_keys(array_column($posts, 'slug'), 0);
$broken  = $cold = [];
foreach ($posts as $p) {
    $next = array_column(related_posts($p, 3), 'slug');
    if (in_array($p['slug'], $next, true) || count(array_unique($next)) !== count($next)
        || count($next) !== min(3, count($posts) - 1)) {
        $broken[] = $p['slug'];
    }
    foreach ($next as $slug) $offered[$slug]++;

    $closest = 0;
    foreach ($posts as $q) {
        if ($q['slug'] !== $p['slug']) $closest = max($closest, post_affinity($p, $q));
    }
    if ($next && post_affinity($p, find_post($next[0])) < $closest) $cold[] = $p['slug'];
}
check('three others, none twice, never itself', !$broken, implode(', ', array_slice($broken, 0, 3)));
check('the closest article leads', !$cold, implode(', ', array_slice($cold, 0, 3)));

$short = array_keys(array_filter($offered, fn($n) => $n < 2));
check('every article is offered by two others', count($posts) <= 3 || !$short,
      $short ? implode(', ', array_slice($short, 0, 3)) : '(least: ' . ($offered ? min($offered) : 0) . ', most: ' . ($offered ? max($offered) : 0) . ')');

echo "\nA HOSE OFFERS THE ARTICLES WRITTEN ABOUT IT\n";
$stray = $lost = [];
foreach (all_products() as $x) {
    $guides = product_guides($x, 4);
    if (count($guides) > 4) $stray[] = $x['slug'] . ': more than four';
    foreach ($guides as $g) {
        $t = post_topics($g);
        if (!in_array($x['slug'], $t['products'], true) && !in_array(product_group($x), $t['groups'], true)) {
            $stray[] = $x['slug'] . ': ' . $g['slug'];
        }
    }
}
foreach ($posts as $p) {
    $lead = post_products($p)[0] ?? null;
    if ($lead === null) continue;
    $about = array_filter($posts, fn($q) => (post_products($q)[0]['slug'] ?? '') === $lead['slug']);
    if (count($about) <= 4 && !in_array($p['slug'], array_column(product_guides($lead, 4), 'slug'), true)) {
        $lost[] = $p['slug'] . ' / ' . $lead['slug'];
    }
}
check('only articles that refer to it or its model', !$stray, implode(' | ', array_slice($stray, 0, 2)));
check('and the hose an article leads with offers it back', !$lost, implode(' | ', array_slice($lost, 0, 2)));

printf("\n  %d passed, %d failed%s\n", $ok, $bad, $bad ? '  <-- LOOK' : '');
exit($bad ? 1 : 0);
