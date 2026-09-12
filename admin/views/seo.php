<?php
/**
 * Every page the site serves, and what its search appearance is set to.
 *
 * This screen used to list only the entries in data/seo.php — the metadata
 * carried over from WordPress — so a product added since, or a size archive
 * that never had any, simply was not here. It could not answer the one
 * question worth asking, which is "what is missing".
 *
 * The list comes from site_content(), the same one sitemap.xml is built from,
 * so a page the search engines are told about is a page this screen knows
 * about.
 *
 * FOUR STATES, not two. A blank is not automatically a fault: a product with
 * nothing written here still has a title, built from its name by its own
 * template, and saying "missing" would send somebody to fill in 37 boxes that
 * did not need filling. What is worth acting on is a blank with nothing
 * behind it — the size archives set no description at all — and a value that
 * is written but the wrong length.
 *
 * @var array  $seo      data/seo.php, keyed by path
 * @var array  $content  site_content()
 * @var string $url      the page being edited, if any
 * @var string $show     which rows to list
 */

$entry = $url !== '' ? ($seo[$url] ?? []) : [];

$DOTS = [
    'ok'   => ['Set', 'ok'],
    'warn' => ['Length', 'warn'],
    'auto' => ['Built in', 'auto'],
    'none' => ['Missing', 'none'],
];

/* Worked out once, for the counters and the table both. */
$rows = [];
$tally = ['ok' => 0, 'warn' => 0, 'auto' => 0, 'none' => 0];

foreach ($content as $row) {
    $e   = $seo[$row['loc']] ?? [];
    $own = seo_has_own_description($row);

    $row['title_value'] = (string) ($e['title'] ?? '');
    $row['desc_value']  = (string) ($e['description'] ?? '');
    $row['title_state'] = seo_state($row['title_value'], 'title', true);
    $row['desc_state']  = seo_state($row['desc_value'], 'description', $own);
    $row['robots']      = (string) ($e['robots'] ?? '');
    $row['fallback']    = seo_fallback_title($row);

    // the worse of the two decides which filters the row answers to
    foreach (['title_state', 'desc_state'] as $k) $tally[$row[$k]]++;
    $rows[] = $row;
}

$needs = fn(array $r) => $r['title_state'] === 'none' || $r['desc_state'] === 'none';
$long  = fn(array $r) => $r['title_state'] === 'warn' || $r['desc_state'] === 'warn';
$bare  = fn(array $r) => $r['title_value'] === '' && $r['desc_value'] === '';

$filters = [
    ''        => ['Everything',            fn(array $r) => true],
    'missing' => ['Nothing behind it',     $needs],
    'length'  => ['Written, wrong length', $long],
    'auto'    => ['Nothing written here',  $bare],
    /* Tested for the word, not for "something is set". Almost every entry
       carries `index, follow, max-image-preview:large, …` from Yoast, which is
       permission to index — counting those as kept out of the index put 71 of
       110 pages under that heading and every one of them was wrong. */
    'noindex' => ['Kept out of the index',
                  fn(array $r) => str_contains(strtolower($r['robots']), 'noindex')],
];
$show   = isset($filters[$show]) ? $show : '';
$listed = array_values(array_filter($rows, $filters[$show][1]));
?>

<div class="card pad-card">
  <h2>What search engines see</h2>
  <p class="muted">Every address in <a href="/sitemap.xml" target="_blank" rel="noopener">sitemap.xml</a>
    is here — <?= count($rows) ?> of them. A page with nothing written builds its own title from
    the product, post or category name, which is usually right; that is <b>Built in</b> below and
    not something to go and fix. <b>Missing</b> is a blank with nothing behind it, and
    <b>Length</b> is written but shorter or longer than a search result will show.</p>

  <div class="seo-tally">
    <?php
      $counts = [
        'none' => ['Missing',  $tally['none']],
        'warn' => ['Length',   $tally['warn']],
        'ok'   => ['Set',      $tally['ok']],
        'auto' => ['Built in', $tally['auto']],
      ];
      foreach ($counts as $key => [$label, $n]): ?>
      <span class="seo-count"><i class="seo-dot <?= e($key) ?>"></i><b><?= (int) $n ?></b> <?= e($label) ?></span>
    <?php endforeach; ?>
    <span class="muted">across <?= count($rows) * 2 ?> titles and descriptions</span>
  </div>
</div>

<div class="card">
  <div class="card-hd">
    <h2><?= e($filters[$show][0]) ?></h2>
    <span class="muted"><?= count($listed) ?> of <?= count($rows) ?></span>
  </div>

  <div class="pad">
    <div class="seo-filters">
      <?php foreach ($filters as $key => [$label, $test]):
              $n = count(array_filter($rows, $test)); ?>
        <a class="chip <?= $show === $key ? 'on' : '' ?>"
           href="/admin/seo<?= $key === '' ? '' : '?show=' . e($key) ?>"><?= e($label) ?> <b><?= $n ?></b></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="table-scroll">
    <table class="grid seo-table">
      <thead>
        <tr>
          <th>Page</th>
          <th>Title</th>
          <th>Description</th>
          <th class="right">Where it is edited</th>
        </tr>
      </thead>
      <tbody>
        <?php $kind = null; foreach ($listed as $r): ?>
          <?php if ($r['kind'] !== $kind): $kind = $r['kind']; ?>
            <tr class="seo-group"><td colspan="4"><?= e($kind) ?></td></tr>
          <?php endif; ?>
          <tr>
            <td>
              <b><?= e($r['name']) ?></b>
              <small><a href="<?= e($r['loc']) ?>" target="_blank" rel="noopener"><?= e($r['loc']) ?></a>
                <?php /* Only the one worth seeing. The Yoast directive on almost
                         every row is permission to index and says nothing. */ ?>
                <?php if (str_contains(strtolower($r['robots']), 'noindex')): ?>
                  · <em class="warn">noindex</em>
                <?php endif; ?></small>
            </td>
            <?php foreach ([['title_state', 'title_value', 'fallback'],
                            ['desc_state',  'desc_value',  '']] as [$st, $val, $back]): ?>
              <td class="seo-cell">
                <i class="seo-dot <?= e($r[$st]) ?>" title="<?= e($DOTS[$r[$st]][0]) ?>"></i>
                <?php if ($r[$val] !== ''): ?>
                  <span><?= e(mb_strimwidth($r[$val], 0, 58, '…')) ?></span>
                  <em><?= mb_strlen($r[$val]) ?></em>
                <?php elseif ($back !== '' && $r[$back] !== ''): ?>
                  <span class="auto"><?= e(mb_strimwidth($r[$back], 0, 58, '…')) ?></span>
                <?php else: ?>
                  <span class="auto"><?= e($DOTS[$r[$st]][0]) ?></span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td class="right">
              <a class="ghost" href="<?= e($r['edit']) ?>">Open</a>
              <a class="ghost" href="/admin/seo?url=<?= e(urlencode($r['loc'])) ?>">Metadata</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$listed): ?>
          <tr><td colspan="4" class="muted" style="padding:26px 16px">Nothing here — which is the
            answer you want from this one.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($url !== ''): ?>
  <div class="card pad-card" id="editing">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="url" value="<?= e($url) ?>">
      <h2><?= e($url) ?></h2>
      <p class="hint">A product, post or page also carries these on its own screen; this edits the
        same entry either way.</p>

      <label for="seo-title">Title</label>
      <input id="seo-title" name="title" type="text" value="<?= e($entry['title'] ?? '') ?>" maxlength="200">
      <p class="hint"><?= mb_strlen((string) ($entry['title'] ?? '')) ?> characters — search results
        usually cut around 60. Blank lets the page build its own.</p>

      <label for="seo-desc">Description</label>
      <textarea id="seo-desc" name="description" rows="4" maxlength="400"><?= e($entry['description'] ?? '') ?></textarea>
      <p class="hint"><?= mb_strlen((string) ($entry['description'] ?? '')) ?> characters — aim for 140–160.</p>

      <label for="seo-robots">Robots</label>
      <select id="seo-robots" name="robots">
        <?php foreach (['' => 'index, follow (default)', 'noindex, follow' => 'noindex, follow',
                        'index, nofollow' => 'index, nofollow', 'noindex, nofollow' => 'noindex, nofollow'] as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= ($entry['robots'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="seo-canon">Canonical URL</label>
      <input id="seo-canon" name="canonical" type="url" value="<?= e($entry['canonical'] ?? '') ?>">
      <p class="hint">Leave blank to use the page's own address.</p>

      <div class="savebar">
        <button type="submit">Save metadata</button>
        <a class="ghost" href="<?= e($url) ?>" target="_blank" rel="noopener">View the page ↗</a>
      </div>
    </form>
  </div>
<?php endif; ?>
