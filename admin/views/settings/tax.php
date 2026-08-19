<?php /** @var array $values */ ?>
<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>Tax</h2>

    <label class="check">
      <input type="checkbox" name="enable_taxes" <?= !empty($values['enable_taxes']) ? 'checked' : '' ?>>
      Add tax to the cart and the checkout
    </label>
    <p class="hint">Catalogue prices are stored and entered <b>net</b>. With this off no tax line appears anywhere and orders total the goods plus delivery.</p>

    <div class="pair">
      <div>
        <label for="vat_rate">Rate (%)</label>
        <input id="vat_rate" name="vat_rate" type="number" min="0" max="100" step="1" value="<?= (int) $values['vat_rate'] ?>">
      </div>
      <div>
        <label for="tax_label">What to call it</label>
        <input id="tax_label" name="tax_label" type="text" maxlength="20" value="<?= e($values['tax_label']) ?>">
        <p class="hint">Shown as "<?= e($values['tax_label']) ?> at <?= (int) $values['vat_rate'] ?>%" on the cart and the checkout.</p>
      </div>
    </div>

    <label for="price_suffix">Note beside catalogue prices</label>
    <input id="price_suffix" name="price_suffix" type="text" maxlength="40" value="<?= e($values['price_suffix']) ?>">
    <p class="hint">Leave blank for nothing. Currently a £10.00 hose reads
      <b><?= e(money(1000)) ?><?= $values['price_suffix'] !== '' ? ' ' . e($values['price_suffix']) : '' ?></b>,
      and £100 of goods invoices at <b><?= e(money(10000 + (!empty($values['enable_taxes']) ? (int) round(10000 * $values['vat_rate'] / 100) : 0))) ?></b> before delivery.</p>
  </div>

  <div class="card pad-card">
    <h2>How the order is worked out</h2>
    <p class="hint">This is what the server does when it re-prices a basket at checkout, whatever the browser sent it.</p>
    <ol class="steps">
      <li>Every line is re-read from the catalogue and multiplied by its quantity.</li>
      <li>The delivery zone is found from the customer's country, then the cheapest method that the subtotal qualifies for is applied — see the <a href="/admin/settings/shipping">Shipping</a> tab.</li>
      <li><?= !empty($values['enable_taxes'])
              ? e($values['tax_label']) . ' at ' . (int) $values['vat_rate'] . '% is added to the goods and the delivery together.'
              : 'No tax is added.' ?></li>
      <li>The order is written to <code>storage/orders/</code> and the emails go out.</li>
    </ol>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
  </div>
</form>
