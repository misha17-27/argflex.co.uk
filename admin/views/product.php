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
      <h2>Product type</h2>
      <?php $isVariable = !empty($p['variants']) || ($p['type'] ?? '') === 'variable'; ?>
      <label class="check">
        <input type="radio" name="type" value="simple" <?= $isVariable ? '' : 'checked' ?> data-type-pick>
        <span><b>Simple</b> — one price, nothing to choose</span>
      </label>
      <label class="check">
        <input type="radio" name="type" value="variable" <?= $isVariable ? 'checked' : '' ?> data-type-pick>
        <span><b>Variable</b> — the buyer picks a size, and each combination has its own price</span>
      </label>
      <p class="hint">A variable product prices itself from its options below, so the single
        price is ignored. Choosing <b>simple</b> hides the attributes and options, and saving
        then removes them — the product falls back to the one price. Say so out loud rather
        than leaving them submitted but unused, which is how a product ends up priced from
        rows nobody can see.</p>
    </div>

    <div class="card pad-card" data-when-variable<?= $isVariable ? '' : ' hidden' ?>>
      <h2>Attributes</h2>
      <p class="hint">
        Pick from the sizes the shop already uses rather than typing them again — a value
        typed twice becomes two different options that never match. Tick
        <b>used for options</b> on the ones a buyer chooses from; those become the buttons
        on the product page.
      </p>

      <?php
        /* Every attribute the shop knows, so a row can offer them.

           The order the boxes are drawn in is the order the buttons appear
           in on the product page, and that order matches the live site. So a
           row lists this product's own values first, exactly as it holds
           them, and only then the ones it does not use yet. Drawing them in
           the attribute file's order instead would silently re-sort every
           selector on the site the first time anybody saved a product. */
        $known = all_attributes();

        $termBoxes = function (int $i, string $attrName, array $mine) use ($known) {
            $chosen = array_column($mine, 'slug');
            $all    = [];
            foreach ($known as $k) {
                if (strcasecmp($k['name'], $attrName) !== 0) continue;
                $all = $k['terms'];
                break;
            }
            $rest = array_values(array_filter($all,
                fn($t) => !in_array($t['slug'], $chosen, true)));

            foreach (array_merge($mine, $rest) as $t):
                $on = in_array($t['slug'], $chosen, true); ?>
              <label class="check inline term-box">
                <input type="checkbox" name="attr[<?= $i ?>][pick][]"
                       value="<?= e($t['name']) ?>" <?= $on ? 'checked' : '' ?>>
                <span><?= e($t['name']) ?></span>
              </label>
            <?php endforeach;
        };
      ?>

      <div class="rows" id="attr-rows">
        <?php foreach ($p['attrs'] ?: [] as $i => $a): ?>
          <div class="attr-line" data-row>
            <div class="attr-head">
              <select name="attr[<?= $i ?>][name]" class="attr-name" aria-label="Attribute">
                <?php $seen = false; ?>
                <?php foreach ($known as $k): ?>
                  <?php $on = strcasecmp($k['name'], $a['name']) === 0; $seen = $seen || $on; ?>
                  <option value="<?= e($k['name']) ?>" <?= $on ? 'selected' : '' ?>><?= e($k['name']) ?></option>
                <?php endforeach; ?>
                <?php if (!$seen): ?>
                  <option value="<?= e($a['name']) ?>" selected><?= e($a['name']) ?></option>
                <?php endif; ?>
              </select>
              <label class="check inline">
                <input type="checkbox" name="attr[<?= $i ?>][variation]" value="1" <?= !empty($a['variation']) ? 'checked' : '' ?>>
                <span>used for options</span>
              </label>
              <button type="button" class="x" data-remove-row aria-label="Remove this attribute">&times;</button>
            </div>
            <div class="term-boxes"><?php $termBoxes($i, (string) $a['name'], (array) $a['terms']); ?></div>
            <input name="attr[<?= $i ?>][terms]" type="text" class="attr-terms"
                   placeholder="A value that is not listed yet — separate several with commas">
          </div>
        <?php endforeach; ?>

        <template id="attr-tpl">
          <?php $next = count($p['attrs'] ?: []) + 900; ?>
          <div class="attr-line" data-row>
            <div class="attr-head">
              <select name="attr[<?= $next ?>][name]" class="attr-name" aria-label="Attribute">
                <?php foreach ($known as $k): ?>
                  <option value="<?= e($k['name']) ?>"><?= e($k['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="check inline">
                <input type="checkbox" name="attr[<?= $next ?>][variation]" value="1" checked>
                <span>used for options</span>
              </label>
              <button type="button" class="x" data-remove-row aria-label="Remove this attribute">&times;</button>
            </div>
            <div class="term-boxes"><?php $termBoxes($next, (string) ($known[0]['name'] ?? ''), []); ?></div>
            <input name="attr[<?= $next ?>][terms]" type="text" class="attr-terms"
                   placeholder="A value that is not listed yet — separate several with commas">
          </div>
        </template>
      </div>
      <button type="button" class="ghost" data-add-row="#attr-tpl">+ Add an attribute</button>

      <?php
        /* So the browser can redraw a row's boxes when the attribute changes,
           without asking the server. */
        $termsByAttr = [];
        foreach ($known as $k) $termsByAttr[$k['name']] = array_column($k['terms'], 'name');
      ?>
      <script>window.ARGFLEX_TERMS = <?= json_encode($termsByAttr, JSON_UNESCAPED_UNICODE) ?>;</script>
    </div>

    <div class="card pad-card">
      <h2>Price and options</h2>

      <div class="pair">
        <div>
          <label for="price">Regular price<?= price_suffix() !== '' ? ' (' . e(price_suffix()) . ')' : '' ?></label>
          <div class="with-unit">
            <span><?= e(currency_symbol()) ?></span>
            <input id="price" name="price" type="number" step="0.01" min="0"
                   value="<?= $p['variants'] ? '' : number_format($p['price_min'] / 100, 2, '.', '') ?>">
          </div>
        </div>
        <div>
          <label for="sale_price">Sale price</label>
          <div class="with-unit">
            <span><?= e(currency_symbol()) ?></span>
            <input id="sale_price" name="sale_price" type="number" step="0.01" min="0"
                   value="<?= $p['variants'] || (int) $p['sale_min'] <= 0 ? '' : number_format($p['sale_min'] / 100, 2, '.', '') ?>">
          </div>
        </div>
      </div>
      <p class="hint">Leave the regular price at 0 for “price on request”. Both are ignored once there are options below — those carry their own prices.</p>

      <div class="pair">
        <div>
          <label for="sale_from">Sale starts</label>
          <input id="sale_from" name="sale_from" type="date" value="<?= e($p['sale_from']) ?>">
        </div>
        <div>
          <label for="sale_to">Sale ends</label>
          <input id="sale_to" name="sale_to" type="date" value="<?= e($p['sale_to']) ?>">
        </div>
      </div>
      <p class="hint">Leave both blank to run the sale until you stop it. The last day counts.
        <?php if (on_sale($p)): ?><b>On sale now — <?= (int) sale_percent($p) ?>% off.</b><?php endif; ?></p>

      <h3>Options</h3>
      <div class="gen-bar">
        <button type="button" class="ghost" id="gen-variants">Build options from the attributes</button>
        <span class="muted">Prices already entered are kept where the option matches.</span>
      </div>

      <div class="var-head"><span>Option</span><span>Price</span><span>Sale</span><span></span></div>
      <div class="rows" id="variant-rows">
        <?php foreach ($p['variants'] as $i => $v): ?>
          <div class="row-line var-line" data-row>
            <input name="variant[<?= $i ?>][label]" type="text" value="<?= e($v['label']) ?>" placeholder="Length: 20m">
            <input name="variant[<?= $i ?>][price]" type="number" step="0.01" min="0"
                   value="<?= number_format($v['price'] / 100, 2, '.', '') ?>" placeholder="0.00" aria-label="Price">
            <input name="variant[<?= $i ?>][sale]" type="number" step="0.01" min="0"
                   value="<?= (int) ($v['sale'] ?? 0) > 0 ? number_format((int) $v['sale'] / 100, 2, '.', '') : '' ?>"
                   placeholder="—" aria-label="Sale price">
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        <?php endforeach; ?>

        <template id="variant-tpl">
          <div class="row-line var-line" data-row>
            <input name="variant[<?= count($p['variants']) + 900 ?>][label]" type="text" placeholder="Length: 20m">
            <input name="variant[<?= count($p['variants']) + 900 ?>][price]" type="number" step="0.01" min="0" placeholder="0.00" aria-label="Price">
            <input name="variant[<?= count($p['variants']) + 900 ?>][sale]" type="number" step="0.01" min="0" placeholder="—" aria-label="Sale price">
            <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
          </div>
        </template>
      </div>
      <button type="button" class="ghost" data-add-row="#variant-tpl">+ Add an option</button>
    </div>

    <div class="card pad-card">
      <h2>Inventory</h2>

      <label class="check">
        <input type="checkbox" name="manage_stock" data-toggle-block="#stock-fields"
               <?= !empty($p['manage_stock']) ? 'checked' : '' ?>>
        Track how many are left
      </label>
      <p class="hint">Off, the product is simply in or out of stock — set that in the Publish box.
        The shop-wide default for a new product is on Settings → <a href="/admin/settings/products">Products</a>.</p>

      <div id="stock-fields" <?= empty($p['manage_stock']) ? 'hidden' : '' ?>>
        <div class="triple">
          <div>
            <label for="stock_qty">Quantity</label>
            <input id="stock_qty" name="stock_qty" type="number" min="0" max="999999"
                   value="<?= (int) $p['stock_qty'] ?>">
          </div>
          <div>
            <label for="low_stock">Low stock at</label>
            <input id="low_stock" name="low_stock" type="number" min="0" max="9999"
                   value="<?= (int) $p['low_stock'] ?>" placeholder="<?= (int) setting('low_stock_qty') ?>">
            <p class="hint">Blank uses <?= (int) setting('low_stock_qty') ?>.</p>
          </div>
          <div>
            <label for="backorders">Backorders</label>
            <select id="backorders" name="backorders">
              <?php foreach (BACKORDER_MODES as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $p['backorders'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php $state = stock_state($p); ?>
        <p class="hint">The shop currently says <b><?= e($state['label']) ?></b> for this product.</p>
      </div>

      <label class="check">
        <input type="checkbox" name="sold_individually" <?= !empty($p['sold_individually']) ? 'checked' : '' ?>>
        Only one of these per order
      </label>
    </div>

    <div class="card pad-card">
      <h2>Shipping</h2>

      <label class="check">
        <input type="checkbox" name="virtual" <?= !empty($p['virtual']) ? 'checked' : '' ?>>
        Nothing is shipped — this is a service or a download
      </label>

      <div class="pair">
        <div>
          <label for="weight">Weight (<?= e(setting('weight_unit')) ?>)</label>
          <input id="weight" name="weight" type="number" step="0.001" min="0" value="<?= e($p['weight']) ?>">
        </div>
        <div>
          <label for="shipping_class">Shipping class</label>
          <input id="shipping_class" name="shipping_class" type="text" list="ship-classes"
                 value="<?= e($p['shipping_class']) ?>" placeholder="None">
          <datalist id="ship-classes">
            <?php foreach (shipping_classes() as $name): ?>
              <option value="<?= e($name) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
      </div>

      <label>Dimensions (<?= e(setting('dimension_unit')) ?>)</label>
      <div class="triple">
        <input name="length" type="number" step="0.1" min="0" value="<?= e($p['length']) ?>" placeholder="Length" aria-label="Length">
        <input name="width"  type="number" step="0.1" min="0" value="<?= e($p['width']) ?>"  placeholder="Width"  aria-label="Width">
        <input name="height" type="number" step="0.1" min="0" value="<?= e($p['height']) ?>" placeholder="Height" aria-label="Height">
      </div>
      <p class="hint">Shown on the product page under Additional information, and on the packing list.</p>
    </div>

    <div class="card pad-card">
      <h2>Linked products</h2>

      <label for="upsells">Upsells</label>
      <select id="upsells" name="upsells[]" multiple size="6">
        <?php foreach (all_products(true) as $other): ?>
          <?php if ($other['slug'] === $p['slug']) continue; ?>
          <option value="<?= e($other['slug']) ?>" <?= in_array($other['slug'], (array) $p['upsells'], true) ? 'selected' : '' ?>>
            <?= e($other['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Suggested on this product's page as something better to buy instead.</p>

      <label for="crosssells">Cross-sells</label>
      <select id="crosssells" name="crosssells[]" multiple size="6">
        <?php foreach (all_products(true) as $other): ?>
          <?php if ($other['slug'] === $p['slug']) continue; ?>
          <option value="<?= e($other['slug']) ?>" <?= in_array($other['slug'], (array) $p['crosssells'], true) ? 'selected' : '' ?>>
            <?= e($other['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Offered in the basket once this product is in it — couplings, clamps, that sort of thing.</p>
    </div>

    <div class="card pad-card">
      <h2>Advanced</h2>

      <label for="purchase_note">Purchase note</label>
      <textarea id="purchase_note" name="purchase_note" rows="2"><?= e($p['purchase_note']) ?></textarea>
      <p class="hint">Added to the confirmation email when this product is ordered.</p>

      <label for="menu_order">Position in the catalogue</label>
      <input id="menu_order" name="menu_order" type="number" min="-999" max="999" value="<?= (int) $p['menu_order'] ?>">
      <p class="hint">Lower comes first when the shop is on its default sorting. Zero for no preference.</p>
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
