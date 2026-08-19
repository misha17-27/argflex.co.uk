<?php
/** @var array $product @var array $errors @var bool $isNew @var array $seoRow */
$p      = $product;
$seoRow = $seoRow ?? [];
$url    = '/product/' . ($p['slug'] ?: '…') . '/';
?>

<p class="back"><a href="/admin/products">← All products</a></p>

<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="two-col" id="prodform">
  <?= csrf_field() ?>

  <div>
    <div class="card pad-card">
      <label for="name">Product name *</label>
      <input id="name" name="name" type="text" value="<?= e($p['name']) ?>" required>

      <p class="permalink">
        Permalink: <code><?= e(SITE_URL . $url) ?></code>
        <?php if (!$isNew): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener">open ↗</a><?php endif; ?>
      </p>

      <div class="pair">
        <div>
          <label for="slug">URL slug</label>
          <input id="slug" name="slug" type="text" value="<?= e($p['slug']) ?>" placeholder="built from the name">
          <p class="hint">Changing this breaks links that already point at the product.</p>
        </div>
        <div>
          <label for="sku">SKU</label>
          <input id="sku" name="sku" type="text" value="<?= e($p['sku']) ?>">
        </div>
      </div>
    </div>

    <div class="card pad-card">
      <h2>Short description</h2>
      <p class="hint">Sits beside the price. Lines shaped <code>Tube: Synthetic rubber.</code> are picked out in bold on the page.</p>
      <textarea id="short" name="short" rows="8"><?= e($p['short']) ?></textarea>
    </div>

    <div class="card pad-card">
      <h2>Full description</h2>
      <p class="hint">Fills the Description tab on the product page.</p>
      <textarea id="desc" name="desc" rows="12" data-rich><?= e($p['desc']) ?></textarea>
    </div>

    <div class="card pad-card">
      <h2>Attributes</h2>
      <p class="hint">
        One row per attribute, values separated by commas. Tick <b>used for options</b> on the
        ones a buyer picks from — those become the buttons on the product page.
      </p>

      <div class="rows" id="attr-rows">
        <?php foreach ($p['attrs'] ?: [] as $i => $a): ?>
          <div class="attr-line" data-row>
            <input name="attr[<?= $i ?>][name]" type="text" value="<?= e($a['name']) ?>" placeholder="Length" class="attr-name">
            <input name="attr[<?= $i ?>][terms]" type="text" class="attr-terms"
                   value="<?= e(implode(', ', array_column($a['terms'], 'name'))) ?>" placeholder="1m, 5m, 10m">
            <label class="check inline">
              <input type="checkbox" name="attr[<?= $i ?>][variation]" value="1" <?= !empty($a['variation']) ? 'checked' : '' ?>>
              <span>used for options</span>
            </label>
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        <?php endforeach; ?>

        <template id="attr-tpl">
          <div class="attr-line" data-row>
            <input name="attr[<?= count($p['attrs'] ?: []) + 900 ?>][name]" type="text" placeholder="Length" class="attr-name">
            <input name="attr[<?= count($p['attrs'] ?: []) + 900 ?>][terms]" type="text" class="attr-terms" placeholder="1m, 5m, 10m">
            <label class="check inline">
              <input type="checkbox" name="attr[<?= count($p['attrs'] ?: []) + 900 ?>][variation]" value="1" checked>
              <span>used for options</span>
            </label>
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        </template>
      </div>
      <button type="button" class="ghost" data-add-row="#attr-tpl">+ Add an attribute</button>
    </div>

    <div class="card pad-card">
      <h2>Price and options</h2>

      <label for="price">Single price (£, excl. VAT)</label>
      <input id="price" name="price" type="number" step="0.01" min="0"
             value="<?= $p['variants'] ? '' : number_format($p['price_min'] / 100, 2, '.', '') ?>">
      <p class="hint">Leave at 0 for “price on request”. Ignored once there are options below.</p>

      <h3>Options</h3>
      <div class="gen-bar">
        <button type="button" class="ghost" id="gen-variants">Build options from the attributes</button>
        <span class="muted">Prices already entered are kept where the option matches.</span>
      </div>

      <div class="rows" id="variant-rows">
        <?php foreach ($p['variants'] as $i => $v): ?>
          <div class="row-line" data-row>
            <input name="variant[<?= $i ?>][label]" type="text" value="<?= e($v['label']) ?>" placeholder="Length: 20m">
            <input name="variant[<?= $i ?>][price]" type="number" step="0.01" min="0"
                   value="<?= number_format($v['price'] / 100, 2, '.', '') ?>" placeholder="0.00">
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        <?php endforeach; ?>

        <template id="variant-tpl">
          <div class="row-line" data-row>
            <input name="variant[<?= count($p['variants']) + 900 ?>][label]" type="text" placeholder="Length: 20m">
            <input name="variant[<?= count($p['variants']) + 900 ?>][price]" type="number" step="0.01" min="0" placeholder="0.00">
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        </template>
      </div>
      <button type="button" class="ghost" data-add-row="#variant-tpl">+ Add an option</button>
    </div>

    <div class="card pad-card">
      <h2>Search appearance</h2>
      <p class="hint">How this product looks in Google. Left blank, the page builds its own from the name and specs.</p>

      <div class="serp">
        <div class="serp-url"><?= e(SITE_URL . $url) ?></div>
        <div class="serp-title" data-serp-title><?= e($seoRow['title'] ?? ($p['name'] . ' — ' . SITE_NAME)) ?></div>
        <div class="serp-desc" data-serp-desc><?= e($seoRow['description'] ?? clip(strip_tags($p['short']), 160)) ?></div>
      </div>

      <label for="seo_title">SEO title</label>
      <input id="seo_title" name="seo_title" type="text" maxlength="200" value="<?= e($seoRow['title'] ?? '') ?>"
             data-counter="#c-title" data-serp="title">
      <p class="hint"><span id="c-title"><?= strlen($seoRow['title'] ?? '') ?></span> characters — results usually cut around 60.</p>

      <label for="seo_desc">Meta description</label>
      <textarea id="seo_desc" name="seo_description" rows="3" maxlength="400"
                data-counter="#c-desc" data-serp="desc"><?= e($seoRow['description'] ?? '') ?></textarea>
      <p class="hint"><span id="c-desc"><?= strlen($seoRow['description'] ?? '') ?></span> characters — aim for 140–160.</p>

      <div class="pair">
        <div>
          <label for="seo_robots">Robots</label>
          <select id="seo_robots" name="seo_robots">
            <?php foreach (['' => 'index, follow (default)', 'noindex, follow' => 'noindex, follow',
                            'index, nofollow' => 'index, nofollow', 'noindex, nofollow' => 'noindex, nofollow'] as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= ($seoRow['robots'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="seo_canonical">Canonical URL</label>
          <input id="seo_canonical" name="seo_canonical" type="url" value="<?= e($seoRow['canonical'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Publish</h2>

      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="published" <?= ($p['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft"     <?= ($p['status'] ?? 'published') === 'draft'     ? 'selected' : '' ?>>Draft — hidden from the site</option>
      </select>

      <label for="stock">Stock</label>
      <select id="stock" name="stock">
        <option value="instock"    <?= ($p['stock'] ?? 'instock') === 'instock'    ? 'selected' : '' ?>>In stock</option>
        <option value="outofstock" <?= ($p['stock'] ?? 'instock') === 'outofstock' ? 'selected' : '' ?>>Out of stock</option>
      </select>

      <label for="created">Date</label>
      <input id="created" name="created" type="date" value="<?= e($p['created'] ?? date('Y-m-d')) ?>">

      <label class="check" style="margin-top:16px">
        <input type="checkbox" name="featured" value="1" <?= !empty($p['featured']) ? 'checked' : '' ?>>
        <span>Show on the homepage</span>
      </label>

      <button type="submit" style="margin-top:16px">Save product</button>
      <?php if (!$isNew): ?>
        <a class="ghost block" href="<?= e($url) ?>" target="_blank" rel="noopener">Preview on the site ↗</a>
      <?php endif; ?>
    </div>

    <div class="card pad-card">
      <h2>Categories</h2>
      <p class="hint">The one marked with the dot is used for the breadcrumb.</p>
      <div class="checks">
        <?php $primary = $p['primary_cat'] ?? ''; ?>
        <?php foreach (all_categories() as $c): ?>
          <label class="check cat-row">
            <input type="checkbox" name="cats[]" value="<?= e($c['slug']) ?>"
                   <?= in_array($c['slug'], $p['cats'], true) ? 'checked' : '' ?>>
            <span><?= $c['parent'] ? '— ' : '' ?><?= e($c['name']) ?></span>
            <input type="radio" name="primary_cat" value="<?= e($c['slug']) ?>"
                   title="Use as the primary category" <?= $primary === $c['slug'] ? 'checked' : '' ?>>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card pad-card">
      <h2>Tags</h2>
      <input name="tags" type="text" value="<?= e(implode(', ', $p['tags'] ?? [])) ?>" placeholder="fuel, welding, marine">
      <p class="hint">Separate with commas. Used by the shop search.</p>
    </div>

    <div class="card pad-card">
      <h2>Images</h2>
      <p class="hint">The first is the main image; the rest form the gallery.</p>

      <div class="rows" id="image-rows">
        <?php foreach ($p['images'] ?: [''] as $src): ?>
          <div class="row-line" data-row>
            <?php if ($src): ?><img class="mini" src="/<?= e($src) ?>" alt="" width="40" height="32"><?php endif; ?>
            <input name="images[]" type="text" value="<?= e($src) ?>" placeholder="assets/img/products/name.jpg">
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        <?php endforeach; ?>
        <template id="image-tpl">
          <div class="row-line" data-row>
            <input name="images[]" type="text" placeholder="assets/img/products/name.jpg">
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        </template>
      </div>

      <button type="button" class="ghost" data-add-row="#image-tpl">+ Add a row</button>
      <button type="button" class="ghost block" data-pick-image="#image-rows">Choose from the library</button>
    </div>
  </aside>
</form>

<?php if (!$isNew): ?>
  <div class="two-col">
    <form method="post" class="card pad-card danger">
      <?= csrf_field() ?>
      <h2>Delete</h2>
      <p class="muted">Removes the product from the catalogue. Its image files stay on disk.</p>
      <button type="submit" name="delete" value="1" class="btn-danger"
              data-confirm="Delete “<?= e($p['name']) ?>”? This cannot be undone.">Delete product</button>
    </form>

    <form method="post" class="card pad-card">
      <?= csrf_field() ?>
      <h2>Duplicate</h2>
      <p class="muted">Copies everything into a new draft, so you can work on it without touching this one.</p>
      <button type="submit" name="duplicate" value="1" class="ghost">Copy to a new draft</button>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/picker.php'; ?>
