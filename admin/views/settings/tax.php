<?php /** @var array $values */ ?>
<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>Tax</h2>

    <p class="hint">Catalogue prices are stored and entered <b>net</b>. Tax is
      <?= !empty($values['enable_taxes'])
            ? '<b>switched on</b>'
            : '<b>switched off</b>, so no tax line appears anywhere and orders total the goods plus delivery' ?> —
      change that on the <a href="/admin/settings">General</a> tab.</p>

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
    <h2>Rates by country</h2>
    <p class="hint">The rate above is the default, and what the catalogue quotes.
      A rule here overrides it once the customer says where they are delivering.
      Leave a rule's countries empty to make it the catch-all for everywhere the
      rules above did not name — that is how "zero-rated outside the
      <?= e(COUNTRIES[$values['store_country']] ?? 'UK') ?>" is written without
      listing every country on earth. Your own country always keeps the standard
      rate.</p>

    <?php
    $ruleRow = function (string $key, array $rule) use ($values): void { ?>
      <div class="card pad-card rate" data-rate>
        <div class="pay-hd">
          <label class="check tight">
            <input type="checkbox" name="rate[<?= e($key) ?>][enabled]" <?= !empty($rule['enabled']) ? 'checked' : '' ?>>
            <span>On</span>
          </label>
          <div class="ord">
            <label for="rate-<?= e($key) ?>-r">Rate %</label>
            <input id="rate-<?= e($key) ?>-r" type="number" step="0.1" min="0" max="100"
                   name="rate[<?= e($key) ?>][rate]" value="<?= e((string) ($rule['rate'] ?? 0)) ?>">
          </div>
          <button type="button" class="x" data-remove-rate aria-label="Remove this rule"
                  data-confirm="Remove this rule?">&times;</button>
        </div>

        <div class="pair">
          <div>
            <label for="rate-<?= e($key) ?>-l">What to call it</label>
            <input id="rate-<?= e($key) ?>-l" type="text" maxlength="30"
                   name="rate[<?= e($key) ?>][label]" value="<?= e($rule['label'] ?? '') ?>"
                   placeholder="<?= e($values['tax_label']) ?>">
          </div>
          <div>
            <label for="rate-<?= e($key) ?>-n">Note on the invoice</label>
            <input id="rate-<?= e($key) ?>-n" type="text" maxlength="80"
                   name="rate[<?= e($key) ?>][note]" value="<?= e($rule['note'] ?? '') ?>"
                   placeholder="Outside the UK">
          </div>
        </div>

        <label for="rate-<?= e($key) ?>-c">Countries</label>
        <select id="rate-<?= e($key) ?>-c" name="rate[<?= e($key) ?>][countries][]" multiple size="6">
          <?php foreach (COUNTRIES as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= in_array($code, (array) ($rule['countries'] ?? []), true) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">None picked makes this the catch-all.</p>
      </div>
    <?php };
    foreach ((array) $values['tax_rates'] as $i => $rule) $ruleRow((string) $i, $rule);
    ?>

    <button type="button" class="ghost" data-add-rate>+ Add a rule</button>
    <template id="rate-tpl"><?php $ruleRow('__r__', ['enabled' => false, 'rate' => 0,
        'label' => '', 'note' => '', 'countries' => []]); ?></template>
  </div>

  <div class="card pad-card">
    <h2>What this charges today</h2>
    <table class="grid">
      <thead><tr><th>Delivering to</th><th>Rate</th><th class="opt">Called</th><th>On £100 of goods</th></tr></thead>
      <tbody>
        <?php foreach ([(string) $values['store_country'], 'IE', 'FR', 'US'] as $code):
                $t = tax_for($code); ?>
          <tr>
            <td><b><?= e(COUNTRIES[$code] ?? $code) ?></b></td>
            <td><?= e(rtrim(rtrim(number_format($t['rate'], 1), '0'), '.')) ?>%</td>
            <td class="opt"><?= e($t['label']) ?><?= $t['note'] !== '' ? ' — ' . e($t['note']) : '' ?></td>
            <td><b><?= e(money(10000 + (int) round(10000 * $t['rate'] / 100))) ?></b></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card pad-card">
    <h2>How the order is worked out</h2>
    <p class="hint">This is what the server does when it re-prices a basket at checkout, whatever the browser sent it.</p>
    <ol class="steps">
      <li>Every line is re-read from the catalogue and multiplied by its quantity.</li>
      <li>The delivery zone is found from the customer's country, then the cheapest method that the subtotal qualifies for is applied — see the <a href="/admin/settings/shipping">Shipping</a> tab.</li>
      <li><?= !empty($values['enable_taxes'])
              ? 'The rate for the delivery country is found from the rules above, then added to the goods and the delivery together — and stored on the order, so a later change of rate never rewrites an old invoice.'
              : 'No tax is added.' ?></li>
      <li>The order is written to <code>storage/orders/</code> and the emails go out.</li>
    </ol>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
  </div>
</form>
