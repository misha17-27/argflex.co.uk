<?php $p = $product; ?>

<p class="back"><a href="/admin/products">← All products</a></p>

<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="two-col">
  <?= csrf_field() ?>

  <div>
    <div class="card pad-card">
      <h2>Basics</h2>

      <label for="name">Name *</label>
      <input id="name" name="name" type="text" value="<?= e($p['name']) ?>" required>

      <label for="slug">URL slug</label>
      <input id="slug" name="slug" type="text" value="<?= e($p['slug']) ?>" placeholder="left blank, built from the name">
      <p class="hint">Page address: <code>/product/<?= e($p['slug'] ?: '…') ?>/</code>. Changing it breaks existing links.</p>

      <label for="sku">SKU</label>
      <input id="sku" name="sku" type="text" value="<?= e($p['sku']) ?>">

      <label for="short">Short description</label>
      <textarea id="short" name="short" rows="7"><?= e($p['short']) ?></textarea>
      <p class="hint">Shown beside the price. Lines in the form <code>Tube: Synthetic rubber.</code> are picked out in bold.</p>

      <label for="desc">Full description</label>
      <textarea id="desc" name="desc" rows="10"><?= e($p['desc']) ?></textarea>
      <p class="hint">HTML is allowed here — it fills the Description tab.</p>
    </div>

    <div class="card pad-card">
      <h2>Price and options</h2>

      <?php $hasVariants = !empty($p['variants']); ?>
      <label for="price">Single price (£, excl. VAT)</label>
      <input id="price" name="price" type="number" step="0.01" min="0"
             value="<?= $hasVariants ? '' : number_format($p['price_min'] / 100, 2, '.', '') ?>">
      <p class="hint">Leave at 0 for “price on request”. Ignored if you add options below.</p>

      <h3>Options</h3>
      <p class="hint">One row per buyable variant, for example <code>Length: 20m</code>. The page shows them as buttons and picks the price from the row.</p>

      <div class="rows">
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
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Save</h2>
      <button type="submit">Save product</button>
      <?php if (!$isNew): ?>
        <a class="ghost block" href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">View on site ↗</a>
      <?php endif; ?>
    </div>

    <div class="card pad-card">
      <h2>Categories</h2>
      <div class="checks">
        <?php foreach (all_categories() as $c): ?>
          <label class="check">
            <input type="checkbox" name="cats[]" value="<?= e($c['slug']) ?>"
                   <?= in_array($c['slug'], $p['cats'], true) ? 'checked' : '' ?>>
            <span><?= e($c['name']) ?><?= $c['parent'] ? ' <em>in ' . e($c['parent']) . '</em>' : '' ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card pad-card">
      <h2>Images</h2>
      <p class="hint">Paths under <code>assets/img/</code>. Upload new ones on the <a href="/admin/media">Images</a> page.</p>
      <div class="rows">
        <?php foreach ($p['images'] ?: [''] as $i => $src): ?>
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
      <button type="button" class="ghost" data-add-row="#image-tpl">+ Add an image</button>
    </div>
  </aside>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card pad-card danger narrow-card">
    <?= csrf_field() ?>
    <h2>Delete</h2>
    <p class="muted">Removes the product from the catalogue. Its image files stay on disk.</p>
    <button type="submit" name="delete" value="1" class="btn-danger"
            data-confirm="Delete “<?= e($p['name']) ?>”? This cannot be undone.">Delete product</button>
  </form>
<?php endif; ?>
