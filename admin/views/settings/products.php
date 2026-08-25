<?php /** @var array $values */ ?>
<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>The catalogue</h2>

    <label for="default_sort">Default sorting</label>
    <select id="default_sort" name="default_sort">
      <?php foreach ([
        'default'    => 'Catalogue position, then name',
        'name'       => 'Name, A to Z',
        'price-asc'  => 'Price, low to high',
        'price-desc' => 'Price, high to low',
        'new'        => 'Newest first',
      ] as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $values['default_sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="hint">What the shop and every category show before a visitor picks
      something else. Catalogue position is the number on each product's Advanced card.</p>

    <label for="shop_notice">Shop notice</label>
    <input id="shop_notice" name="shop_notice" type="text" maxlength="180" value="<?= e($values['shop_notice']) ?>"
           placeholder="Works closed 24–26 December">
    <p class="hint">A line across the top of every page. Blank hides it.</p>

    <div class="pair">
      <div>
        <label class="check">
          <input type="checkbox" name="enable_wishlist" <?= !empty($values['enable_wishlist']) ? 'checked' : '' ?>>
          Let visitors save products
        </label>
        <p class="hint">The heart on each product, and <a href="/wishlist/">/wishlist/</a>.</p>
      </div>
      <div>
        <label class="check">
          <input type="checkbox" name="enable_compare" <?= !empty($values['enable_compare']) ? 'checked' : '' ?>>
          Let visitors compare two products
        </label>
        <p class="hint">The compare button, and <a href="/compare/">/compare/</a>.</p>
      </div>
    </div>
  </div>

  <div class="card pad-card">
    <h2>Stock</h2>

    <label class="check">
      <input type="checkbox" name="manage_stock" <?= !empty($values['manage_stock']) ? 'checked' : '' ?>>
      Track quantities on new products by default
    </label>
    <p class="hint">Each product can still decide for itself on its Inventory card. With
      tracking off a product is simply in or out of stock, which suits cut-to-order hose.</p>

    <div class="pair">
      <div>
        <label for="low_stock_qty">Low stock at</label>
        <input id="low_stock_qty" name="low_stock_qty" type="number" min="0" max="9999"
               value="<?= (int) $values['low_stock_qty'] ?>">
        <p class="hint">Used by any product that has not set its own figure.</p>
      </div>
      <div>
        <label for="stock_display">Show how many are left</label>
        <select id="stock_display" name="stock_display">
          <?php foreach (STOCK_DISPLAY as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $values['stock_display'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label class="check">
      <input type="checkbox" name="hide_out_of_stock" <?= !empty($values['hide_out_of_stock']) ? 'checked' : '' ?>>
      Hide out-of-stock products from the shop and its categories
    </label>
    <p class="hint">They keep their own page, so an existing link or a search result still
      works and can offer the enquiry form instead of a 404.</p>
  </div>

  <div class="card pad-card">
    <h2>Units</h2>
    <div class="pair">
      <div>
        <label for="weight_unit">Weight</label>
        <select id="weight_unit" name="weight_unit">
          <?php foreach (['kg' => 'kg', 'g' => 'g', 'lbs' => 'lbs', 'oz' => 'oz'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $values['weight_unit'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="dimension_unit">Dimensions</label>
        <select id="dimension_unit" name="dimension_unit">
          <?php foreach (['cm' => 'cm', 'mm' => 'mm', 'm' => 'm', 'in' => 'in'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $values['dimension_unit'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <p class="hint">Printed beside a product's weight and dimensions under Additional information.</p>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
  </div>
</form>

<div class="card pad-card">
  <h2>Where the catalogue stands</h2>
  <?php
  $all      = all_products(true);
  $live     = array_filter($all, fn($p) => ($p['status'] ?? 'published') !== 'draft');
  $onSale   = array_filter($live, 'on_sale');
  $tracked  = array_filter($live, fn($p) => !empty($p['manage_stock']));
  $lowNow   = array_filter($tracked, fn($p) => stock_state($p)['state'] === 'low');
  $outNow   = array_filter($live, fn($p) => stock_state($p)['state'] === 'out');
  ?>
  <table class="grid">
    <tbody>
      <tr><td><b>Published</b></td><td><?= count($live) ?> of <?= count($all) ?></td>
          <td class="muted opt">Drafts never appear on the shop</td></tr>
      <tr><td><b>On sale today</b></td>
          <td><?= count($onSale) ?><?= $onSale ? ' — ' . e(clip(implode(', ', array_column($onSale, 'name')), 60)) : '' ?></td>
          <td class="muted opt">A sale price only counts while its dates allow it</td></tr>
      <tr><td><b>Tracking quantity</b></td><td><?= count($tracked) ?></td>
          <td class="muted opt">The rest are simply in or out of stock</td></tr>
      <tr><td><b>Low stock</b></td><td><?= count($lowNow) ?></td>
          <td class="muted opt">At or below the threshold</td></tr>
      <tr><td><b>Out of stock</b></td><td><?= count($outNow) ?></td>
          <td class="muted opt"><?= !empty($values['hide_out_of_stock']) ? 'Hidden from listings' : 'Still listed' ?></td></tr>
    </tbody>
  </table>
</div>
