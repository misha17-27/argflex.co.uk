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
      <textarea id="short" name="short" rows="8" data-rich><?= e($p['short']) ?></textarea>
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
            $order = array_merge($mine, $rest);
            $names = array_column($mine, 'name');
            ?>
            <div class="term-drop" data-term-drop>
              <button type="button" class="term-toggle" aria-expanded="false"
                      aria-label="Values of <?= e($attrName) ?>">
                <span class="term-chips" data-term-summary><?= $names
                    ? e(implode(', ', $names)) : 'Choose the values this product comes in' ?></span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div class="term-panel" hidden>
                <div class="term-tools">
                  <button type="button" class="ghost" data-pick-all>Select all</button>
                  <button type="button" class="ghost" data-pick-none>Select none</button>
                </div>
                <?php foreach ($order as $t): ?>
                  <label class="check term-opt">
                    <input type="checkbox" name="attr[<?= $i ?>][pick][]" value="<?= e($t['name']) ?>"
                           <?= in_array($t['slug'], $chosen, true) ? 'checked' : '' ?>>
                    <span><?= e($t['name']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?php
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

      <?php
        /* The attributes a buyer actually chooses from. Each option row gets
           one list per axis rather than a typed-out label: a label typed by
           hand is a label that can be spelled wrong, and a variation whose
           spelling does not match its attribute can never be selected on the
           product page. */
        $axes = array_values(array_filter($p['attrs'] ?: [],
            fn($a) => !empty($a['variation']) && !empty($a['terms'])));

        /* One list of terms, with the variation's own value chosen.

           A variation records its choice as the slug — 3.2mm is stored as
           3-2mm — while the list has to post the name, because that is what
           builds the label. So the match is against either, and the value
           posted is always the name. Comparing names alone left five of this
           product's rows with nothing selected, and a row that posts nothing
           for an axis becomes a different variation on save. */
        $axisSelect = function (int $i, array $axis, string $picked) { ?>
          <select name="variant[<?= $i ?>][pick][<?= e($axis['name']) ?>]"
                  aria-label="<?= e($axis['name']) ?>">
            <?php foreach ($axis['terms'] as $t):
                $on = $picked !== '' && ($t['name'] === $picked || $t['slug'] === $picked); ?>
              <option value="<?= e($t['name']) ?>" <?= $on ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php };

        /** What this variation holds for one axis, from its own record. */
        $valueFor = function (array $v, string $axisName): string {
            if (isset($v['attrs'][$axisName])) return (string) $v['attrs'][$axisName];
            // older rows only carry the label
            foreach (explode(',', (string) ($v['label'] ?? '')) as $piece) {
                [$name, $term] = array_pad(explode(':', $piece, 2), 2, '');
                if (strcasecmp(trim($name), $axisName) === 0) return trim($term);
            }
            return '';
        };
      ?>

      <div class="var-head">
        <span><?= $axes ? e(implode(' · ', array_column($axes, 'name'))) : 'Option' ?></span>
        <span>Price</span><span>Sale</span><span></span>
      </div>
      <?php
        /* One variation, its summary line and the panel behind it.

           A variation is not just a price: it has its own picture, its own
           stock, and its own weight, which is what decides carriage. None of
           that fitted on the row, so the row opens. */
        $variantRow = function (int $i, array $v) use ($axes, $axisSelect, $valueFor) { ?>
          <div class="var-item" data-row>
            <div class="row-line var-line">
              <?php if ($axes): ?>
                <div class="var-picks">
                  <?php foreach ($axes as $axis): ?>
                    <?php $axisSelect($i, $axis, $valueFor($v, (string) $axis['name'])); ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <input name="variant[<?= $i ?>][label]" type="text"
                       value="<?= e($v['label'] ?? '') ?>" placeholder="Length: 20m">
              <?php endif; ?>
              <input name="variant[<?= $i ?>][price]" type="number" step="0.01" min="0"
                     value="<?= isset($v['price']) ? number_format((int) $v['price'] / 100, 2, '.', '') : '' ?>"
                     placeholder="0.00" aria-label="Price">
              <input name="variant[<?= $i ?>][sale]" type="number" step="0.01" min="0"
                     value="<?= (int) ($v['sale'] ?? 0) > 0 ? number_format((int) $v['sale'] / 100, 2, '.', '') : '' ?>"
                     placeholder="—" aria-label="Sale price">
              <button type="button" class="ghost var-more" data-var-toggle aria-expanded="false">Edit</button>
              <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
            </div>

            <div class="var-detail" hidden>
              <div class="var-detail-grid">
                <div class="var-image" data-var-image>
                  <label>Picture</label>
                  <input name="variant[<?= $i ?>][image]" type="text"
                         value="<?= e((string) ($v['image'] ?? '')) ?>"
                         placeholder="assets/img/products/name.webp">
                  <button type="button" class="ghost block" data-pick-image="[data-var-image]">Choose from the library</button>
                  <p class="hint">Shown instead of the main photo once this option is picked.
                     Leave it empty to keep the product's own.</p>
                </div>

                <div>
                  <label>SKU</label>
                  <input name="variant[<?= $i ?>][sku]" type="text" value="<?= e((string) ($v['sku'] ?? '')) ?>">

                  <label>Weight, in metres</label>
                  <input name="variant[<?= $i ?>][weight]" type="number" step="1" min="0"
                         value="<?= (int) ($v['weight'] ?? 0) ?>">
                  <p class="hint">What delivery is priced on — see the note in data/shipping.php.</p>
                </div>

                <div>
                  <label for="vstock-<?= $i ?>">Availability</label>
                  <select id="vstock-<?= $i ?>" name="variant[<?= $i ?>][stock]">
                    <option value="instock" <?= ($v['stock'] ?? 'instock') !== 'outofstock' ? 'selected' : '' ?>>In stock</option>
                    <option value="outofstock" <?= ($v['stock'] ?? '') === 'outofstock' ? 'selected' : '' ?>>Out of stock</option>
                  </select>

                  <label class="check">
                    <input type="checkbox" name="variant[<?= $i ?>][manage_stock]" value="1"
                           <?= !empty($v['manage_stock']) ? 'checked' : '' ?> data-toggle-next>
                    <span>Count them</span>
                  </label>
                  <div class="var-qty" <?= empty($v['manage_stock']) ? 'hidden' : '' ?>>
                    <label>How many are left</label>
                    <input name="variant[<?= $i ?>][stock_qty]" type="number" step="1" min="0"
                           value="<?= (int) ($v['stock_qty'] ?? 0) ?>">
                    <p class="hint">Counted down as orders come in. At nought this option
                       goes out of stock on its own and cannot be added to a basket.</p>
                  </div>

                  <label for="vclass-<?= $i ?>">Shipping class</label>
                  <select id="vclass-<?= $i ?>" name="variant[<?= $i ?>][shipping_class]">
                    <option value="">Same as the product</option>
                    <?php foreach (shipping_classes() as $klass): ?>
                      <option value="<?= e($klass) ?>"
                              <?= ($v['shipping_class'] ?? '') === $klass ? 'selected' : '' ?>><?= e($klass) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <?php if (!empty($v['id'])): ?>
                <p class="hint">WooCommerce id #<?= (int) $v['id'] ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php };
      ?>

      <div class="rows" id="variant-rows">
        <?php foreach ($p['variants'] as $i => $v): ?>
          <?php $variantRow($i, $v); ?>
        <?php endforeach; ?>

        <template id="variant-tpl">
          <?php $variantRow(count($p['variants']) + 900, ['stock' => 'instock']); ?>
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
