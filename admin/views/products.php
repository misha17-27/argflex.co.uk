<?php
$needle = strtolower(trim($q));
$rows = $needle === '' ? $products : array_values(array_filter($products,
    fn($p) => str_contains(strtolower($p['name'] . ' ' . $p['slug']), $needle)));
usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));
?>

<div class="bar-row">
  <form method="get" class="search-row">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products…">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="ghost" href="/admin/products">Clear</a><?php endif; ?>
  </form>
  <a class="btn" href="/admin/products/new">+ Add product</a>
</div>

<div class="card">
  <table class="grid">
    <thead><tr><th></th><th>Name</th><th>Categories</th><th>Price</th><th>Options</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td class="thumb">
            <?php if (!empty($p['images'][0])): ?>
              <img src="/<?= e($p['images'][0]) ?>" alt="" width="46" height="36" loading="lazy">
            <?php endif; ?>
          </td>
          <td>
            <a href="/admin/products/<?= e(rawurlencode($p['slug'])) ?>"><b><?= e($p['name']) ?></b></a>
            <small>/product/<?= e($p['slug']) ?>/</small>
          </td>
          <td><small><?= e(product_cat_label($p) ?: '—') ?></small></td>
          <td><?= e(price_label($p)) ?></td>
          <td><?= $p['variants'] ? count($p['variants']) : '—' ?></td>
          <td class="right">
            <a class="ghost" href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">View ↗</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="muted pad">Nothing matched “<?= e($q) ?>”.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
