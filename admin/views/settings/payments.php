<?php
/**
 * Payment methods offered at checkout.
 *
 * @var array $values
 */

$row = function (string $key, array $m): void { ?>
  <div class="card pad-card pay" data-pay>
    <div class="pay-hd">
      <label class="check tight">
        <input type="checkbox" name="pay[<?= e($key) ?>][enabled]" <?= !empty($m['enabled']) ? 'checked' : '' ?>>
        <span>Enabled</span>
      </label>
      <div class="ord">
        <label for="pay-<?= e($key) ?>-o">Order</label>
        <input id="pay-<?= e($key) ?>-o" type="number" min="0" max="99" name="pay[<?= e($key) ?>][order]" value="<?= (int) ($m['order'] ?? 0) ?>">
      </div>
      <button type="button" class="x" data-remove-pay aria-label="Remove this method"
              data-confirm="Remove this payment method?">&times;</button>
    </div>

    <input type="hidden" name="pay[<?= e($key) ?>][id]" value="<?= e($m['id'] ?? '') ?>">

    <label for="pay-<?= e($key) ?>-t">Title</label>
    <input id="pay-<?= e($key) ?>-t" type="text" name="pay[<?= e($key) ?>][title]" value="<?= e($m['title'] ?? '') ?>"
           placeholder="Proforma invoice">

    <label for="pay-<?= e($key) ?>-d">Description</label>
    <textarea id="pay-<?= e($key) ?>-d" name="pay[<?= e($key) ?>][description]" rows="2"
              placeholder="Shown beside the option at checkout."><?= e($m['description'] ?? '') ?></textarea>

    <label for="pay-<?= e($key) ?>-i">Instructions</label>
    <textarea id="pay-<?= e($key) ?>-i" name="pay[<?= e($key) ?>][instructions]" rows="2"
              placeholder="Added to the confirmation email once the order is placed."><?= e($m['instructions'] ?? '') ?></textarea>
  </div>
<?php }; ?>

<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>Payment providers</h2>
    <p class="hint">The checkout collects no card details — it ends with a confirmed order and an invoice, which is how the business already works. Switch on more than one method and the customer picks; switch on exactly one and it is applied silently. The chosen method is stored with the order and its instructions go into the confirmation email.</p>
    <p class="hint">Business location: <b><?= e(COUNTRIES[$values['store_country']] ?? $values['store_country']) ?></b> — change it on the <a href="/admin/settings">General</a> tab.</p>
  </div>

  <div id="pays">
    <?php foreach ((array) $values['payment_methods'] as $i => $m) $row((string) $i, $m); ?>
  </div>

  <button type="button" class="ghost" data-add-pay>+ Add a method</button>

  <template id="pay-tpl"><?php $row('__p__', ['id' => '', 'enabled' => false, 'order' => 90,
      'title' => '', 'description' => '', 'instructions' => '']); ?></template>

  <div class="savebar">
    <button type="submit">Save changes</button>
    <span class="hint">A method with no title is dropped when you save.</span>
  </div>
</form>

<div class="card pad-card">
  <h2>What the checkout shows now</h2>
  <?php $on = payment_methods(); ?>
  <?php if (!$on): ?>
    <p class="muted">Nothing is switched on, so the checkout says payment will be arranged after the order.</p>
  <?php else: ?>
    <ol class="steps">
      <?php foreach ($on as $m): ?>
        <li><b><?= e($m['title']) ?></b><?= $m['description'] !== '' ? ' — ' . e($m['description']) : '' ?></li>
      <?php endforeach; ?>
    </ol>
    <p class="hint"><?= count($on) === 1
      ? 'One method, so it is applied without asking.'
      : e((string) count($on)) . ' methods, so the customer chooses one at checkout.' ?></p>
  <?php endif; ?>
</div>
