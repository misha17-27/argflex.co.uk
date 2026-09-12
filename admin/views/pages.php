<div class="card pad-card">
  <h2>Site pages</h2>
  <p class="muted">
    The wording on every fixed page, plus the title and description search engines show.
    Product, category and blog pages are edited in their own sections; their metadata lives under
    <a href="/admin/seo">SEO</a>.
  </p>
</div>

<div class="card">
  <table class="grid">
    <thead><tr><th>Page</th><th>Address</th><th class="seo-dots" title="Search title and description — hover a dot for what it says">SEO</th><th class="opt">Search title</th><th>Edited</th><th></th></tr></thead>
    <tbody>
      <?php foreach (page_schema() as $path => $def):
        $seoRow  = $seo[$path] ?? [];
        $edited  = count($content[$path] ?? []);
        $fields  = array_sum(array_map('count', $def['groups']));
      ?>
        <tr>
          <td><a href="/admin/pages<?= e('?p=' . urlencode($path)) ?>"><b><?= e($def['label']) ?></b></a></td>
          <td><code><?= e($path) ?></code></td>
          <td class="seo-dots"><?= seo_dots($path, 'Pages') ?></td>
          <td class="opt">
            <?php /* "not set" was printed here in red. A page with nothing
                     written still has a title — its own — so that warning was
                     about nothing, on every row nobody had edited. The dots
                     beside it say which of the four states it is in; this
                     column only shows the words. */ ?>
            <?php if (!empty($seoRow['title'])): ?>
              <small><?= e($seoRow['title']) ?></small>
            <?php else: ?>
              <small class="muted">built from the page</small>
            <?php endif; ?>
          </td>
          <td class="muted"><?= $edited ? $edited . ' of ' . $fields : '—' ?></td>
          <td class="right">
            <?php if ($path !== '/404'): ?>
              <a class="ghost" href="<?= e($path) ?>" target="_blank" rel="noopener">View ↗</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
