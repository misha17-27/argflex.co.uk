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

  <div class="card pad-card">
    <h2>Invoices</h2>
    <p class="hint">What goes on the proforma invoice printed from an order. The
      company and <?= e(lower(tax_label())) ?> numbers live on the
      <a href="/admin/settings">General</a> tab, with the rest of the address.</p>

    <div class="pair">
      <div>
        <label for="invoice_prefix">Number prefix</label>
        <input id="invoice_prefix" name="invoice_prefix" type="text" maxlength="12"
               value="<?= e($values['invoice_prefix']) ?>">
        <p class="hint">Next invoice will be
          <b><?= e($values['invoice_prefix'] . str_pad((string) (int) $values['invoice_next'], 5, '0', STR_PAD_LEFT)) ?></b>.</p>
      </div>
      <div>
        <label for="invoice_days">Payment due after (days)</label>
        <input id="invoice_days" name="invoice_days" type="number" min="0" max="180"
               value="<?= (int) $values['invoice_days'] ?>">
        <p class="hint">Zero prints no due date, which is right for a proforma.</p>
      </div>
    </div>

    <label for="invoice_next">Next number</label>
    <input id="invoice_next" name="invoice_next" type="number" min="1" max="999999"
           value="<?= (int) $values['invoice_next'] ?>">
    <p class="hint">An invoice takes the next number the first time it is opened, and
      keeps it. Only change this when moving from another system.</p>

    <h3>Bank details</h3>
    <p class="hint">Printed under "How to pay". Leave a field blank to leave it off.</p>
    <div class="pair">
      <div>
        <label for="bank_name">Account name</label>
        <input id="bank_name" name="bank_name" type="text" value="<?= e($values['bank_name']) ?>">
      </div>
      <div>
        <label for="bank_sort">Sort code</label>
        <input id="bank_sort" name="bank_sort" type="text" value="<?= e($values['bank_sort']) ?>" placeholder="00-00-00">
      </div>
    </div>
    <div class="triple">
      <div>
        <label for="bank_account">Account number</label>
        <input id="bank_account" name="bank_account" type="text" value="<?= e($values['bank_account']) ?>">
      </div>
      <div>
        <label for="bank_iban">IBAN</label>
        <input id="bank_iban" name="bank_iban" type="text" value="<?= e($values['bank_iban']) ?>">
      </div>
      <div>
        <label for="bank_bic">BIC / SWIFT</label>
        <input id="bank_bic" name="bank_bic" type="text" value="<?= e($values['bank_bic']) ?>">
      </div>
    </div>

    <label for="invoice_terms">Terms</label>
    <textarea id="invoice_terms" name="invoice_terms" rows="3"><?= e($values['invoice_terms']) ?></textarea>
  </div>

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
