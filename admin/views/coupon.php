<?php
/**
 * One discount code.
 *
 * @var array $coupon
 * @var array $errors
 * @var bool  $isNew
 */
$sym = currency_symbol();
?>

<p class="back"><a href="/admin/coupons">&larr; All discount codes</a></p>

<?php if ($errors): ?>
  <div class="flash bad">
    <?php foreach ($errors as $line): ?><div><?= e($line) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" class="two-col">
  <?= csrf_field() ?>

  <div>
    <div class="card pad-card">
      <h2>The code</h2>

      <label for="c-code">Code</label>
      <input id="c-code" name="code" type="text" class="codefield" maxlength="32" required
             value="<?= e($coupon['code']) ?>" placeholder="SPRING10"
             autocomplete="off" spellcheck="false">
      <p class="hint">Letters, numbers, hyphens and underscores. Customers type it in the basket, so keep it short — case does not matter.</p>

      <label for="c-desc">Description</label>
      <input id="c-desc" name="description" type="text" maxlength="120" value="<?= e($coupon['description']) ?>"
             placeholder="Spring promotion">
      <p class="hint">Shown to the customer beside the code once it applies. Blank shows the discount itself.</p>
    </div>

    <div class="card pad-card">
      <h2>What it takes off</h2>

      <div class="pair">
        <div>
          <label for="c-type">Type</label>
          <select id="c-type" name="type" data-coupon-type>
            <?php foreach (COUPON_TYPES as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $coupon['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="c-amount">Amount</label>
          <div class="with-unit">
            <span data-coupon-unit><?= $coupon['type'] === 'percent' ? '%' : e($sym) ?></span>
            <input id="c-amount" name="amount" type="number" step="0.01" min="0"
                   value="<?= $coupon['type'] === 'percent'
                            ? e(rtrim(rtrim(number_format((float) $coupon['amount'], 2, '.', ''), '0'), '.'))
                            : e(number_format((int) $coupon['amount'] / 100, 2, '.', '')) ?>">
          </div>
        </div>
      </div>
      <p class="hint">A percentage comes off the lines the code covers; a fixed amount comes off the basket, capped at what those lines are worth.</p>

      <label class="check">
        <input type="checkbox" name="free_shipping" <?= !empty($coupon['free_shipping']) ? 'checked' : '' ?>>
        Delivery is free with this code
      </label>
    </div>

    <div class="card pad-card">
      <h2>Limits</h2>

      <div class="pair">
        <div>
          <label for="c-min">Minimum order</label>
          <div class="with-unit">
            <span><?= e($sym) ?></span>
            <input id="c-min" name="min_spend" type="number" step="0.01" min="0"
                   value="<?= e(number_format((int) $coupon['min_spend'] / 100, 2, '.', '')) ?>">
          </div>
        </div>
        <div>
          <label for="c-max">Maximum order</label>
          <div class="with-unit">
            <span><?= e($sym) ?></span>
            <input id="c-max" name="max_spend" type="number" step="0.01" min="0"
                   value="<?= e(number_format((int) $coupon['max_spend'] / 100, 2, '.', '')) ?>">
          </div>
        </div>
      </div>
      <p class="hint">Zero means no limit. Both are measured on the goods before delivery and <?= e(lower(tax_label())) ?>.</p>

      <div class="pair">
        <div>
          <label for="c-starts">Valid from</label>
          <input id="c-starts" name="starts" type="date" value="<?= e($coupon['starts']) ?>">
        </div>
        <div>
          <label for="c-expires">Valid until</label>
          <input id="c-expires" name="expires" type="date" value="<?= e($coupon['expires']) ?>">
        </div>
      </div>
      <p class="hint">Leave either blank for no limit. The last day counts.</p>

      <div class="pair">
        <div>
          <label for="c-limit">Total uses allowed</label>
          <input id="c-limit" name="usage_limit" type="number" min="0" max="100000"
                 value="<?= (int) $coupon['usage_limit'] ?>">
          <p class="hint">Zero means unlimited.</p>
        </div>
        <div>
          <label>Used so far</label>
          <p class="bignum"><?= (int) ($coupon['used'] ?? 0) ?></p>
          <?php if ((int) ($coupon['used'] ?? 0) > 0): ?>
            <label class="check">
              <input type="checkbox" name="reset_used"> Reset this to zero
            </label>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card pad-card">
      <h2>What it applies to</h2>
      <p class="hint">Pick nothing and the code covers the whole basket. Pick some products or categories and only those lines earn the discount.</p>

      <label for="c-cats">Categories</label>
      <select id="c-cats" name="categories[]" multiple size="8">
        <?php foreach (all_categories() as $cat): ?>
          <option value="<?= e($cat['slug']) ?>" <?= in_array($cat['slug'], (array) $coupon['categories'], true) ? 'selected' : '' ?>>
            <?= e($cat['parent'] !== '' ? '— ' . $cat['name'] : $cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="c-prods">Products</label>
      <select id="c-prods" name="products[]" multiple size="10">
        <?php foreach (all_products(true) as $p): ?>
          <option value="<?= e($p['slug']) ?>" <?= in_array($p['slug'], (array) $coupon['products'], true) ? 'selected' : '' ?>>
            <?= e($p['name']) ?><?= ($p['status'] ?? 'published') === 'draft' ? ' (draft)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Ctrl or ⌘ to pick several.</p>
    </div>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Publish</h2>
      <label class="check">
        <input type="checkbox" name="enabled" <?= !empty($coupon['enabled']) ? 'checked' : '' ?>>
        Accept this code
      </label>
      <button type="submit"><?= $isNew ? 'Create the code' : 'Save changes' ?></button>
      <?php if (!coupons_enabled()): ?>
        <p class="hint">Discount codes are switched off site-wide, so nothing will be accepted until you turn them on under <a href="/admin/settings">Settings → General</a>.</p>
      <?php endif; ?>
    </div>

    <?php if (!$isNew): ?>
      <div class="card pad-card">
        <h2>Summary</h2>
        <p class="muted"><?= e(coupon_label($coupon)) ?><?= !empty($coupon['free_shipping']) ? ' and free delivery' : '' ?>,
          <?= (array) $coupon['products'] || (array) $coupon['categories'] ? 'on selected items' : 'on anything' ?>.</p>
        <p class="muted">
          <?php
          $when = [];
          if ($coupon['starts'] !== '')  $when[] = 'from ' . date('j M Y', strtotime($coupon['starts']));
          if ($coupon['expires'] !== '') $when[] = 'until ' . date('j M Y', strtotime($coupon['expires']));
          ?>
          <?= $when ? e(ucfirst(implode(' ', $when))) : 'No date limit' ?>.
        </p>
      </div>
    <?php endif; ?>

    <?php if (!$isNew): ?>
      <div class="card pad-card danger">
        <h2>Delete</h2>
        <p class="hint">Orders that already used this code keep their discount — only the code itself goes.</p>
        <button type="submit" name="delete" value="1" class="btn-danger block"
                data-confirm="Delete <?= e($coupon['code']) ?>?">Delete this code</button>
      </div>
    <?php endif; ?>
  </aside>
</form>
