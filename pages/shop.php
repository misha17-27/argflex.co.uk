<?php
/**
 * Shop — the full catalogue with search, category, price and sort filters.
 */
declare(strict_types=1);

$q      = trim((string) ($_GET['q'] ?? ''));
$catQ   = trim((string) ($_GET['cat'] ?? ''));
$sort   = (string) ($_GET['sort'] ?? 'default');
$maxQ   = $_GET['max'] ?? '';

$items = all_products();

if ($q !== '') {
    $needle = lower($q);
    $items = array_values(array_filter($items, function ($p) use ($needle) {
        $hay = lower($p['name'] . ' ' . strip_tags($p['short']) . ' ' . implode(' ', $p['cats']));
        return str_contains($hay, $needle);
    }));
}

if ($catQ !== '' && find_category($catQ)) {
    $inCat = array_column(products_in_category($catQ), 'slug');
    $items = array_values(array_filter($items, fn($p) => in_array($p['slug'], $inCat, true)));
}

if (is_numeric($maxQ) && (int) $maxQ > 0) {
    $cap   = (int) $maxQ * 100;
    $items = array_values(array_filter($items, fn($p) => $p['price_min'] > 0 && $p['price_min'] <= $cap));
}

switch ($sort) {
    case 'price-asc':  usort($items, fn($a, $b) => $a['price_min'] <=> $b['price_min']); break;
    case 'price-desc': usort($items, fn($a, $b) => $b['price_max'] <=> $a['price_max']); break;
    case 'name':       usort($items, fn($a, $b) => strcasecmp($a['name'], $b['name']));  break;
}

$ceiling = 0;
foreach (all_products() as $p) { $ceiling = max($ceiling, (int) ceil($p['price_min'] / 100)); }

$title = $q !== '' ? "Search results for “{$q}”" : 'Shop';
set_page([
    'title'       => $title . ' — ' . SITE_NAME,
    'description' => 'The complete Arg Flex catalogue: rubber hoses, PVC and PU hoses, clamps and couplings. Filter by category and price, all prices per metre excluding VAT.',
    'crumbs'      => [['label' => 'Shop']],
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow">Catalogue</span>
    <h1><?= e($title) ?></h1>
    <p><?= count($items) ?> product<?= count($items) === 1 ? '' : 's' ?><?= $q !== '' ? ' matching your search' : ' in stock, priced per metre and excluding VAT' ?>.</p>
  </div>
</section>

<section class="shop">
  <div class="wrap">
    <div class="shop-grid">
      <aside class="side">
        <form method="get" action="/shop/">
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

          <div class="side-box">
            <h3>Categories</h3>
            <ul class="side-cats">
              <li><a href="/shop/<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>" class="<?= $catQ === '' ? 'on' : '' ?>">All products <span><?= count(all_products()) ?></span></a></li>
              <?php foreach (top_categories() as $c): ?>
                <li>
                  <a href="?<?= e(http_build_query(array_filter(['q' => $q, 'cat' => $c['slug'], 'sort' => $sort !== 'default' ? $sort : null]))) ?>" class="<?= $catQ === $c['slug'] ? 'on' : '' ?>">
                    <?= e($c['name']) ?> <span><?= count(products_in_category($c['slug'])) ?></span>
                  </a>
                  <?php $kids = child_categories($c['slug']); if ($kids): ?>
                    <ul>
                      <?php foreach ($kids as $k): ?>
                        <li><a href="?<?= e(http_build_query(array_filter(['q' => $q, 'cat' => $k['slug'], 'sort' => $sort !== 'default' ? $sort : null]))) ?>" class="<?= $catQ === $k['slug'] ? 'on' : '' ?>">
                          <?= e($k['name']) ?> <span><?= count(products_in_category($k['slug'])) ?></span>
                        </a></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="side-box">
            <h3>Maximum price</h3>
            <label class="rng">
              <input type="range" name="max" min="1" max="<?= $ceiling ?>" value="<?= is_numeric($maxQ) ? (int) $maxQ : $ceiling ?>" oninput="this.nextElementSibling.textContent='£'+this.value">
              <output>£<?= is_numeric($maxQ) ? (int) $maxQ : $ceiling ?></output>
            </label>
            <?php if ($catQ !== ''): ?><input type="hidden" name="cat" value="<?= e($catQ) ?>"><?php endif; ?>
            <?php if ($sort !== 'default'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
            <button class="btn btn-dark" type="submit" style="width:100%;justify-content:center;margin-top:14px">Apply filter</button>
            <?php if ($catQ !== '' || is_numeric($maxQ) || $q !== ''): ?>
              <a class="clear" href="/shop/">Clear all filters</a>
            <?php endif; ?>
          </div>

          <div class="side-box">
            <h3>Need help choosing?</h3>
            <p class="side-note">Send us the medium, the bore size and the working pressure and we will confirm the right hose.</p>
            <a class="btn btn-primary" href="/contacts/" style="width:100%;justify-content:center">Ask a question</a>
          </div>
        </form>
      </aside>

      <div class="shop-main">
        <div class="toolbar">
          <span><?= count($items) ?> result<?= count($items) === 1 ? '' : 's' ?></span>
          <form method="get" action="/shop/" class="sorter">
            <?php foreach (['q' => $q, 'cat' => $catQ, 'max' => is_numeric($maxQ) ? (string) (int) $maxQ : ''] as $k => $v): ?>
              <?php if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <label for="sort">Sort</label>
            <select id="sort" name="sort" onchange="this.form.submit()">
              <option value="default"    <?= $sort === 'default'    ? 'selected' : '' ?>>Default</option>
              <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Name A–Z</option>
              <option value="price-asc"  <?= $sort === 'price-asc'  ? 'selected' : '' ?>>Price: low to high</option>
              <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Price: high to low</option>
            </select>
          </form>
        </div>

        <?php if ($items): ?>
          <div class="prods cols-3">
            <?php foreach ($items as $i => $p) { $eager = $i < 3; include ROOT_DIR . '/partials/product-card.php'; } ?>
          </div>
        <?php else: ?>
          <div class="empty">
            <h3>Nothing matched that search</h3>
            <p>Try a shorter term, a standard such as <em>SAE J30</em>, or a bore size such as <em>16 mm</em>.</p>
            <a class="btn btn-primary" href="/shop/">Show all products</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
