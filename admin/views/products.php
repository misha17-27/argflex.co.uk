<form method="get" class="filter-bar">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU…">

  <select name="cat">
    <option value="">All categories</option>
    <?php foreach (all_categories() as $c): ?>
      <option value="<?= e($c['slug']) ?>" <?= $cat === $c['slug'] ? 'selected' : '' ?>>
        <?= $c['parent'] ? '— ' : '' ?><?= e($c['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="type">
    <option value="">Any type</option>
    <option value="simple"   <?= $type === 'simple'   ? 'selected' : '' ?>>Simple</option>
    <option value="variable" <?= $type === 'variable' ? 'selected' : '' ?>>With options</option>
  </select>

  <select name="stock">
    <option value="">Any stock</option>
    <option value="instock"    <?= $stock === 'instock'    ? 'selected' : '' ?>>In stock</option>
    <option value="outofstock" <?= $stock === 'outofstock' ? 'selected' : '' ?>>Out of stock</option>
  </select>

  <button type="submit">Filter</button>
  <?php if ($q !== '' || $cat !== '' || $type !== '' || $stock !== '' || $status !== ''): ?>
    <a class="ghost" href="/admin/products">Reset</a>
  <?php endif; ?>
</form>

<div class="bar-row">
  <div class="tabs">
    <?php
    $counts = ['' => count($products),
               'published' => count(array_filter($products, fn($p) => ($p['status'] ?? 'published') === 'published')),
               'draft'     => count(array_filter($products, fn($p) => ($p['status'] ?? 'published') === 'draft')),
               'featured'  => count(array_filter($products, fn($p) => !empty($p['featured']))),
               'outofstock'=> count(array_filter($products, fn($p) => ($p['stock'] ?? 'instock') === 'outofstock'))];
    $labels = ['' => 'All', 'published' => 'Published', 'draft' => 'Drafts',
               'featured' => 'Featured', 'outofstock' => 'Out of stock'];
    foreach ($labels as $key => $label):
      $qs = array_filter(['q' => $q, 'cat' => $cat, 'type' => $type, 'status' => $key]);
    ?>
      <a href="/admin/products<?= $qs ? '?' . e(http_build_query($qs)) : '' ?>" class="<?= $status === $key ? 'on' : '' ?>">
        <?= e($label) ?> <span class="count"><?= (int) $counts[$key] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="bar-actions">
    <a class="ghost" href="/admin/products/export">Export CSV</a>
    <a class="ghost" href="/admin/products/import">Import CSV</a>
    <a class="btn" href="/admin/products/new">+ Add product</a>
  </div>
</div>

<form method="post" id="bulk">
  <?= csrf_field() ?>
  <div class="card">
    <div class="bulk-bar">
      <select name="bulk">
        <option value="">Bulk actions…</option>
        <option value="publish">Publish</option>
        <option value="draft">Move to drafts</option>
        <option value="feature">Mark featured</option>
        <option value="unfeature">Remove featured</option>
        <option value="instock">Mark in stock</option>
        <option value="outofstock">Mark out of stock</option>
        <option value="delete">Delete</option>
      </select>
      <button class="ghost" type="submit" data-confirm="Apply this to every ticked product?">Apply</button>
      <span class="muted"><?= count($rows) ?> of <?= count($products) ?> products</span>
    </div>

    <table class="grid">
      <thead>
        <tr>
          <th class="tick"><input type="checkbox" data-check-all aria-label="Select all"></th>
          <th></th>
          <?php
          $sortLink = function (string $key, string $label) use ($q, $cat, $type, $stock, $status, $sort) {
              $next = $sort === $key ? $key . '-desc' : $key;
              $qs = array_filter(['q' => $q, 'cat' => $cat, 'type' => $type, 'stock' => $stock,
                                  'status' => $status, 'sort' => $next]);
              $arrow = str_starts_with($sort, $key) ? (str_ends_with($sort, '-desc') ? ' ↓' : ' ↑') : '';
              return '<a href="/admin/products?' . e(http_build_query($qs)) . '">' . e($label) . $arrow . '</a>';
          };
          ?>
          <th><?= $sortLink('name', 'Name') ?></th>
          <th class="opt">SKU</th>
          <th class="opt">Categories</th>
          <th><?= $sortLink('price', 'Price') ?></th>
          <th>Stock</th>
          <th class="tick" title="Featured">★</th>
          <th><?= $sortLink('date', 'Date') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $p): ?>
          <tr class="<?= ($p['status'] ?? 'published') === 'draft' ? 'is-draft' : '' ?>">
            <td class="tick"><input type="checkbox" name="slugs[]" value="<?= e($p['slug']) ?>" aria-label="Select <?= e($p['name']) ?>"></td>
            <td class="thumb">
              <?php if (!empty($p['images'][0])): ?>
                <img src="/<?= e($p['images'][0]) ?>" alt="" width="46" height="36" loading="lazy">
              <?php endif; ?>
            </td>
            <td>
              <a href="/admin/products/<?= e(rawurlencode($p['slug'])) ?>"><b><?= e($p['name']) ?></b></a>
              <?php if (($p['status'] ?? 'published') === 'draft'): ?><span class="pill">Draft</span><?php endif; ?>
              <small>/product/<?= e($p['slug']) ?>/</small>
            </td>
            <td class="muted"><?= e($p['sku'] !== '' ? $p['sku'] : '—') ?></td>
            <td><small><?= e(product_cat_label($p) ?: '—') ?></small></td>
            <td><?= e(price_label($p)) ?><?php if ($p['variants']): ?><small><?= count($p['variants']) ?> options</small><?php endif; ?></td>
            <td>
              <?php if (($p['stock'] ?? 'instock') === 'outofstock'): ?>
                <span class="pill cancelled">Out of stock</span>
              <?php else: ?>
                <span class="in-stock">In stock</span>
              <?php endif; ?>
            </td>
            <td class="tick"><?= !empty($p['featured']) ? '<span class="star on">★</span>' : '<span class="star">☆</span>' ?></td>
            <td class="muted" style="white-space:nowrap"><?= e($p['created'] ?? '—') ?></td>
            <td class="right"><a class="ghost" href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">View ↗</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="muted pad">Nothing matched those filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>
