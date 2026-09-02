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
  <?php $on = usable_payment_methods(); ?>
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
    <?php if (!gateway_ready('stripe') && !gateway_ready('ppcp')): ?>
      <p class="hint">Neither card gateway has its keys yet, so the shop is falling back to
         the invoice route. Fill in one below and it will take its place.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<form method="post" class="setform">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="payments">

  <div class="card pad-card">
    <h2>Stripe — card payments</h2>
    <p class="muted">From your Stripe dashboard, Developers → API keys. The publishable key is
       sent to the browser and is meant to be; the secret key never leaves this server.
       Keys are stored in <code>storage/settings.php</code>, which git ignores and the
       server refuses to serve.</p>

    <label class="check tight">
      <input type="checkbox" name="gw[stripe][test_mode]" <?= stripe_test_mode() ? 'checked' : '' ?>>
      <span>Test mode — use the test keys and take no real money</span>
    </label>

    <?php
      /* A stored key is never printed back. Showing it would put a live
         secret into the page source of every admin session, into the
         browser's cache and into anything looking over a shoulder. */
      $field = function (string $gateway, string $name, string $label, string $hint = '') {
          $stored = trim((string) (gateway_settings($gateway)[$name] ?? ''));
          $id = 'gw-' . $gateway . '-' . $name;
          ?>
          <label for="<?= e($id) ?>"><?= e($label) ?></label>
          <input id="<?= e($id) ?>" type="password" autocomplete="off" spellcheck="false"
                 name="gw[<?= e($gateway) ?>][<?= e($name) ?>]"
                 placeholder="<?= $stored !== ''
                     ? 'set — ' . e(substr($stored, 0, 7)) . '… leave blank to keep it'
                     : 'not set' ?>">
          <?php if ($hint !== ''): ?><p class="hint"><?= e($hint) ?></p><?php endif; ?>
          <?php if ($stored !== ''): ?>
            <label class="check tight">
              <input type="checkbox" name="gw_clear[<?= e($gateway) ?>][<?= e($name) ?>]" value="1">
              <span>Remove this key</span>
            </label>
          <?php endif;
      };
    ?>

    <?php $field('stripe', 'test_publishable', 'Test publishable key', 'Begins pk_test_'); ?>
    <?php $field('stripe', 'test_secret',      'Test secret key',      'Begins sk_test_'); ?>
    <?php $field('stripe', 'live_publishable', 'Live publishable key', 'Begins pk_live_'); ?>
    <?php $field('stripe', 'live_secret',      'Live secret key',      'Begins sk_live_'); ?>
    <?php $field('stripe', 'webhook_secret',   'Webhook signing secret',
                 'Begins whsec_. Point the endpoint at /stripe-webhook.php and subscribe to payment_intent.succeeded.'); ?>

    <p class="hint">Status: <b><?= gateway_ready('stripe')
        ? 'ready, in ' . (stripe_test_mode() ? 'test' : 'live') . ' mode'
        : 'not usable yet — both the publishable and secret key are needed' ?></b></p>
  </div>

  <div class="card pad-card">
    <h2>PayPal</h2>
    <p class="muted">From developer.paypal.com, your app's credentials. Sandbox first:
       it behaves exactly like the real thing and moves no money.</p>

    <label class="check tight">
      <input type="checkbox" name="gw[paypal][sandbox]" <?= paypal_sandbox() ? 'checked' : '' ?>>
      <span>Sandbox — test against PayPal's practice accounts</span>
    </label>

    <?php $field('paypal', 'sandbox_client_id', 'Sandbox client ID'); ?>
    <?php $field('paypal', 'sandbox_secret',    'Sandbox secret'); ?>
    <?php $field('paypal', 'live_client_id',    'Live client ID'); ?>
    <?php $field('paypal', 'live_secret',       'Live secret'); ?>

    <p class="hint">Status: <b><?= gateway_ready('ppcp')
        ? 'ready, in ' . (paypal_sandbox() ? 'sandbox' : 'live') . ' mode'
        : 'not usable yet — both the client ID and the secret are needed' ?></b></p>

    <h3>Webhook</h3>
    <p class="hint">The old WooCommerce plugin needs a webhook to know a payment
      happened at all. This does not: the money is taken by this server, in
      <code>payment.php</code>, so a customer who approves and then closes the tab
      leaves an authorisation that expires and nothing is taken.</p>
    <p class="hint">It is worth setting up for the one case that IS a problem — the
      capture succeeds at PayPal and the answer is lost on the way back. The money
      is gone, no order exists, and without this nobody finds out until the customer
      writes in. Optional, and everything works without it.</p>

    <label>Webhook URL for this site</label>
    <div class="row-line">
      <input type="text" readonly value="<?= e(SITE_URL) ?>/paypal-webhook.php"
             onfocus="this.select()">
    </div>
    <p class="hint">Paste this into the developer dashboard → your app → Webhooks, and
      subscribe to <code>PAYMENT.CAPTURE.COMPLETED</code> alone. PayPal answers with a
      webhook ID; that goes below.</p>

    <?php $field('paypal', 'sandbox_webhook_id', 'Sandbox webhook ID'); ?>
    <?php $field('paypal', 'live_webhook_id',    'Live webhook ID',
                 'Without it nothing can be verified, so the endpoint refuses every message — which is the safe way to be unconfigured.'); ?>

    <p class="hint">Webhook: <b><?= paypal_webhook_id() !== ''
        ? 'listening, in ' . (paypal_sandbox() ? 'sandbox' : 'live') . ' mode'
        : 'off — the endpoint refuses everything until an ID is set' ?></b></p>
  </div>

  <button class="btn btn-primary" type="submit">Save</button>
</form>
