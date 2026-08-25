<?php
/**
 * Page links under a listing.
 *
 * @var array  $paged  what paginate() returned
 * @var array  $query  the query string to keep, minus the page number
 * @var string $base   the path these pages live under
 */
if ($paged['pages'] < 2) return;

/** The address of one page, keeping whatever filters are on. */
$pageUrl = function (int $n) use ($query, $base): string {
    $bits = $query;
    unset($bits['page']);
    if ($n > 1) $bits['page'] = $n;                 // page 1 keeps the bare URL
    $bits = array_filter($bits, fn($v) => $v !== '' && $v !== null);
    return $base . ($bits ? '?' . http_build_query($bits) : '');
};
?>

<nav class="pager" aria-label="Pages">
  <?php if ($paged['page'] > 1): ?>
    <a class="pg-step" href="<?= e($pageUrl($paged['page'] - 1)) ?>" rel="prev">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      Previous
    </a>
  <?php else: ?>
    <span class="pg-step off">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      Previous
    </span>
  <?php endif; ?>

  <div class="pg-nums">
    <?php foreach (pager_numbers($paged['page'], $paged['pages']) as $n): ?>
      <?php if ($n === null): ?>
        <span class="pg-gap">…</span>
      <?php elseif ($n === $paged['page']): ?>
        <span class="pg-num on" aria-current="page"><?= $n ?></span>
      <?php else: ?>
        <a class="pg-num" href="<?= e($pageUrl($n)) ?>"><?= $n ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($paged['page'] < $paged['pages']): ?>
    <a class="pg-step" href="<?= e($pageUrl($paged['page'] + 1)) ?>" rel="next">
      Next
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
  <?php else: ?>
    <span class="pg-step off">
      Next
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </span>
  <?php endif; ?>
</nav>

<p class="pg-count">Showing <?= (int) $paged['first'] ?>–<?= (int) $paged['last'] ?>
  of <?= (int) $paged['total'] ?> products</p>
