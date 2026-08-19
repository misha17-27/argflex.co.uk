<?php
/**
 * Delivery zones and their methods.
 *
 * @var array $values
 */

/** One method row. $z and $m are only ever used as form-array keys. */
$methodRow = function (string $z, string $m, array $row): void { ?>
  <div class="ship-method" data-method>
    <label class="check tight">
      <input type="checkbox" name="zone[<?= e($z) ?>][method][<?= e($m) ?>][enabled]" <?= !empty($row['enabled']) ? 'checked' : '' ?>>
      <span>On</span>
    </label>

    <div>
      <input type="text" name="zone[<?= e($z) ?>][method][<?= e($m) ?>][title]"
             value="<?= e($row['title'] ?? '') ?>" placeholder="What the customer sees" aria-label="Method name">
    </div>

    <select name="zone[<?= e($z) ?>][method][<?= e($m) ?>][type]" aria-label="Method type">
      <?php foreach (SHIPPING_TYPES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= ($row['type'] ?? 'flat') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="with-unit">
      <span><?= e(currency_symbol()) ?></span>
      <input type="number" step="0.01" min="0" name="zone[<?= e($z) ?>][method][<?= e($m) ?>][cost]"
             value="<?= number_format((int) ($row['cost'] ?? 0) / 100, 2, '.', '') ?>" aria-label="Cost">
    </div>

    <div class="with-unit">
      <span><?= e(currency_symbol()) ?></span>
      <input type="number" step="0.01" min="0" name="zone[<?= e($z) ?>][method][<?= e($m) ?>][min_amount]"
             value="<?= number_format((int) ($row['min_amount'] ?? 0) / 100, 2, '.', '') ?>" aria-label="Order must reach">
    </div>

    <div>
      <input type="text" name="zone[<?= e($z) ?>][method][<?= e($m) ?>][estimate]"
             value="<?= e($row['estimate'] ?? '') ?>" placeholder="2–4 working days" aria-label="Delivery estimate">
    </div>

    <button type="button" class="x" data-remove-method aria-label="Remove this method">&times;</button>
  </div>
<?php };

/** One zone card. */
$zoneCard = function (string $z, array $zone) use ($methodRow): void { ?>
  <div class="card pad-card zone" data-zone="<?= e($z) ?>">
    <div class="zone-hd">
      <div class="grow">
        <label for="zone-<?= e($z) ?>-name">Zone name</label>
        <input id="zone-<?= e($z) ?>-name" type="text" name="zone[<?= e($z) ?>][name]"
               value="<?= e($zone['name'] ?? '') ?>" placeholder="United Kingdom">
      </div>
      <button type="button" class="ghost small" data-remove-zone
              data-confirm="Remove this zone and its methods?">Remove zone</button>
    </div>

    <label for="zone-<?= e($z) ?>-c">Countries in this zone</label>
    <select id="zone-<?= e($z) ?>-c" name="zone[<?= e($z) ?>][countries][]" multiple size="7">
      <?php foreach (COUNTRIES as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= in_array($code, (array) ($zone['countries'] ?? []), true) ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="hint">Leave every country unpicked to make this the catch-all — it then covers anywhere the zones above it do not.</p>

    <h3>Methods</h3>
    <div class="ship-head">
      <span></span><span>Name</span><span>Type</span><span>Cost</span><span>Order over</span><span>Estimate</span><span></span>
    </div>
    <div class="ship-rows">
      <?php foreach ((array) ($zone['methods'] ?? []) as $mi => $row) $methodRow($z, (string) $mi, $row); ?>
    </div>
    <button type="button" class="ghost small" data-add-method>+ Add a method</button>
  </div>
<?php }; ?>

<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>How delivery is worked out</h2>
    <p class="hint">A customer falls into the <b>first</b> zone that lists their country, so put the specific ones above the catch-all. Within that zone the site offers the <b>cheapest method the order qualifies for</b> — which is how "free over <?= e(money(25000)) ?>" beats a flat rate on its own, with no extra rule to write.</p>
    <p class="hint"><b>Cost</b> only applies to a flat rate; free, collection and quoted methods are always <?= e(money(0)) ?>. <b>Order over</b> hides a method until the goods reach that much — that is where a free-delivery threshold goes.</p>
  </div>

  <div id="zones">
    <?php foreach ((array) $values['shipping_zones'] as $zi => $zone) $zoneCard((string) $zi, $zone); ?>
  </div>

  <button type="button" class="ghost" data-add-zone>+ Add a zone</button>

  <template id="zone-tpl"><?php $zoneCard('__z__', ['name' => '', 'countries' => [], 'methods' => [
      ['type' => 'flat', 'title' => '', 'cost' => 0, 'min_amount' => 0, 'estimate' => '', 'enabled' => true],
  ]]); ?></template>

  <template id="method-tpl"><?php $methodRow('__z__', '__m__',
      ['type' => 'flat', 'title' => '', 'cost' => 0, 'min_amount' => 0, 'estimate' => '', 'enabled' => true]); ?></template>

  <div class="savebar">
    <button type="submit">Save changes</button>
    <span class="hint">A zone with no name is dropped when you save.</span>
  </div>
</form>

<div class="card pad-card">
  <h2>What this quotes today</h2>
  <table class="grid">
    <thead><tr><th>Country</th><th>Zone</th><th>Order</th><th>Delivery</th><th>Estimate</th></tr></thead>
    <tbody>
      <?php foreach ([[(string) $values['default_country'], 5000], [(string) $values['default_country'], 30000],
                      ['FR', 5000], ['US', 5000]] as [$code, $sub]):
              $q = shipping_quote($sub, $code); ?>
        <tr>
          <td><b><?= e(COUNTRIES[$code] ?? $code) ?></b></td>
          <td><?= e($q['zone'] !== '' ? $q['zone'] : '—') ?></td>
          <td><?= e(money($sub)) ?></td>
          <td><?= $q['cost'] > 0 ? e(money($q['cost']))
                     : ($q['type'] === 'quote' ? '<b>On request</b>' : '<b>Free</b>') ?>
              <small><?= e($q['title']) ?></small></td>
          <td class="muted"><?= e($q['estimate'] !== '' ? $q['estimate'] : '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
