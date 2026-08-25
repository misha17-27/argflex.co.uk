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
    <thead><tr><th>Page</th><th>Address</th><th class="opt">Search title</th><th>Edited</th><th></th></tr></thead>
    <tbody>
      <?php foreach (page_schema() as $path => $def):
        $seoRow  = $seo[$path] ?? [];
        $edited  = count($content[$path] ?? []);
        $fields  = array_sum(array_map('count', $def['groups']));
      ?>
        <tr>
          <td><a href="/admin/pages<?= e('?p=' . urlencode($path)) ?>"><b><?= e($def['label']) ?></b></a></td>
          <td><code><?= e($path) ?></code></td>
          <td>
            <?php if (!empty($seoRow['title'])): ?>
              <small><?= e($seoRow['title']) ?></small>
              <?php if (empty($seoRow['description'])): ?><em class="warn">no description</em><?php endif; ?>
            <?php else: ?>
              <em class="warn">not set</em>
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
