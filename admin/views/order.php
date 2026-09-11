<?php $c = $order['customer'] ?? []; $o = $order['order'] ?? []; ?>

<p class="back"><a href="/admin/orders">← All orders</a></p>

<div class="two-col">
  <div>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <div class="card-hd">
        <h2>Items</h2>
        <span class="muted">Change a quantity, drop a line, or add one</span>
      </div>
      <table class="grid">
        <thead><tr><th>Product</th><th class="opt">Option</th><th>Qty</th><th class="opt">Unit</th><th>Line</th><th>Drop</th></tr></thead>
        <tbody>
          <?php foreach ($o['items'] ?? [] as $i => $item): ?>
            <tr>
              <td><a href="/product/<?= e($item['slug']) ?>/" target="_blank" rel="noopener"><?= e($item['title']) ?></a></td>
              <td class="opt"><?= e($item['option'] ?: '—') ?></td>
              <td><input type="number" name="line[<?= $i ?>][qty]" value="<?= (int) $item['qty'] ?>"
                         min="1" max="9999" aria-label="Quantity"></td>
              <td class="opt"><?= e(money((int) $item['price'])) ?></td>
              <td><b><?= e(money((int) $item['line'])) ?></b></td>
              <td><input type="checkbox" name="line[<?= $i ?>][remove]" aria-label="Remove this line"></td>
            </tr>
          <?php endforeach; ?>
          <tr class="add-line">
            <td>
              <select name="add_slug" aria-label="Add a product">
                <option value="">Add a product…</option>
                <?php foreach (all_products(true) as $candidate): ?>
                  <option value="<?= e($candidate['slug']) ?>"><?= e($candidate['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="opt"><input type="text" name="add_option" placeholder="Option, if it has one" aria-label="Option"></td>
            <td><input type="number" name="add_qty" value="1" min="1" max="9999" aria-label="Quantity"></td>
            <td class="opt muted">today's price</td>
            <td colspan="2"></td>
          </tr>
        </tbody>
        <tfoot>
          <tr><th colspan="4">Subtotal</th><td colspan="2"><?= e(money((int) ($o['subtotal'] ?? 0))) ?></td></tr>
          <?php if (!empty($o['discount'])): ?>
            <tr><th colspan="4">Discount<?= !empty($o['coupon']) ? ' (' . e($o['coupon']) . ')' : '' ?></th>
                <td colspan="2">&minus;<?= e(money((int) $o['discount'])) ?></td></tr>
          <?php endif; ?>
          <tr><th colspan="4"><?= e($o['shipping_title'] ?? 'Delivery') ?></th>
              <td colspan="2">
                <div class="with-unit">
                  <span><?= e(currency_symbol()) ?></span>
                  <input type="number" step="0.01" min="0" name="shipping"
                         value="<?= number_format((int) ($o['shipping'] ?? 0) / 100, 2, '.', '') ?>"
                         aria-label="Delivery">
                </div>
              </td></tr>
          <tr><th colspan="4"><?= e($o['tax_label'] ?? 'VAT') ?> at <?= (int) ($o['tax_rate'] ?? 0) ?>%</th>
              <td colspan="2"><?= e(money((int) ($o['vat'] ?? 0))) ?></td></tr>
          <tr class="total"><th colspan="4">Total</th>
              <td colspan="2"><b><?= e(money((int) ($o['total'] ?? 0))) ?></b></td></tr>
          <?php if (refunded_total($order)): ?>
            <tr><th colspan="4">Refunded</th>
                <td colspan="2" class="refunded">&minus;<?= e(money(refunded_total($order))) ?></td></tr>
            <tr class="total"><th colspan="4">Still owed</th>
                <td colspan="2"><b><?= e(money(order_outstanding($order))) ?></b></td></tr>
          <?php endif; ?>
        </tfoot>
      </table>

      <?php
        /* How it actually travels.
           The customer sees one Delivery figure, which is how the live shop
           shows it. Whoever packs the order needs the other half: an order
           can be two consignments, charged and sent separately, and one row
           reading £14.20 does not say that. */
        $consignments = (array) ($o['packages'] ?? []);
      ?>
      <?php if (count($consignments) > 1): ?>
        <div class="pad">
          <h3>Sent as <?= count($consignments) ?> consignments</h3>
          <ul class="steps">
            <?php foreach ($consignments as $pkg): ?>
              <li>
                <b><?= e($pkg['name']) ?></b> —
                <?= e($pkg['chosen']['title'] ?? '') ?>,
                <?= e(money((int) ($pkg['chosen']['cost'] ?? 0))) ?>
                <span class="muted">(<?= (int) $pkg['weight'] ?> m)</span>
                <br>
                <span class="muted"><?= e(implode(', ', array_map(
                    fn($l) => $l['qty'] . ' × ' . $l['title']
                              . (($l['label'] ?? '') !== '' ? ' — ' . $l['label'] : ''),
                    (array) ($pkg['lines'] ?? [])))) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="pad">
        <button type="submit" name="relines" value="1"
                data-confirm="Save these lines and work the totals out again?">Save the lines</button>
        <span class="hint">Existing lines keep the price they were sold at. A line added
          here takes today's price. The <?= e(lower($o['tax_label'] ?? 'VAT')) ?> rate stays
          at the <?= (int) ($o['tax_rate'] ?? 0) ?>% this order was placed on.</span>
        <?php if (!empty($order['edited_at'])): ?>
          <p class="hint">Last edited <?= e(date('j M Y, H:i', strtotime($order['edited_at']))) ?>.</p>
        <?php endif; ?>
      </div>
    </form>

    <div class="card">
      <div class="card-hd"><h2>Customer</h2></div>
      <dl class="detail">
        <div><dt>Name</dt><dd><?= e($c['name'] ?? '—') ?></dd></div>
        <?php if (!empty($c['company'])): ?><div><dt>Company</dt><dd><?= e($c['company']) ?></dd></div><?php endif; ?>
        <div><dt>Email</dt><dd><a href="mailto:<?= e($c['email'] ?? '') ?>"><?= e($c['email'] ?? '—') ?></a></dd></div>
        <div><dt>Phone</dt><dd><a href="tel:<?= e($c['phone'] ?? '') ?>"><?= e($c['phone'] ?? '—') ?></a></dd></div>
        <div><dt>Address</dt><dd><?= e(trim(($c['address'] ?? '') . ', ' . ($c['city'] ?? '') . ', ' . ($c['postcode'] ?? '') . ', ' . ($c['country'] ?? ''), ', ')) ?></dd></div>
        <?php if (!empty($c['notes'])): ?><div><dt>Notes</dt><dd><?= nl2br(e($c['notes'])) ?></dd></div><?php endif; ?>
      </dl>
    </div>
  </div>

  <aside>
    <form method="post" class="card pad-card">
      <?= csrf_field() ?>
      <h2>Status</h2>
      <select name="status">
        <?php foreach (ORDER_STATUSES as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="note">Internal note</label>
      <textarea id="note" name="note" rows="4" placeholder="Stock checked, invoice sent…"><?= e($order['note'] ?? '') ?></textarea>

      <label class="check">
        <input type="checkbox" name="notify" <?= !empty(email_conf('order_status')['enabled']) ? 'checked' : '' ?>>
        Email the customer about this change
      </label>
      <button type="submit">Save</button>

      <dl class="detail small">
        <div><dt>Placed</dt><dd><?= e(str_replace('T', ' ', substr((string) ($order['placed_at'] ?? ''), 0, 16))) ?></dd></div>
        <?php if (!empty($order['updated_at'])): ?>
          <div><dt>Updated</dt><dd><?= e(str_replace('T', ' ', substr((string) $order['updated_at'], 0, 16))) ?></dd></div>
        <?php endif; ?>
      </dl>
    </form>

    <?php
    /* Whether the money arrived, which the order screen could not say at all.
       Every gateway path had been writing a `paid` block since the day it was
       built and nothing read it, so an order could be Confirmed, Invoiced and
       Shipped without anybody being able to tell from this screen whether a
       penny had been taken for it. */
    $pay   = payment_state($order);
    $how   = (array) ($order['payment'] ?? []);
    $money = (array) ($order['paid'] ?? []);
    ?>
    <form method="post" class="card pad-card">
      <?= csrf_field() ?>
      <h2>Payment</h2>

      <p class="pay-state <?= e($pay['state']) ?>"><?= e($pay['label']) ?></p>

      <dl class="detail small">
        <div><dt>Method</dt><dd><?= e($how['title'] ?? $how['id'] ?? '—') ?></dd></div>
        <?php if ($money): ?>
          <div><dt>Taken</dt><dd><?= e(money((int) ($money['amount'] ?? 0))) ?></dd></div>
          <div><dt>When</dt>
            <dd><?= e(str_replace('T', ' ', substr((string) ($money['at'] ?? ''), 0, 16))) ?></dd></div>
          <?php if (($money['id'] ?? '') !== ''): ?>
            <?php /* The gateway's own identifier. It is what you search for in
                     the Stripe or PayPal dashboard when a customer rings up
                     about a charge, so it is printed in full and left
                     selectable rather than shortened to look tidy. */ ?>
            <div><dt>Reference</dt><dd><code class="pay-id"><?= e((string) $money['id']) ?></code></dd></div>
          <?php endif; ?>
          <?php if (($money['via'] ?? '') !== ''): ?>
            <div><dt>Recorded</dt><dd><?= e((string) $money['via']) ?></dd></div>
          <?php endif; ?>
        <?php endif; ?>
      </dl>

      <?php if ($pay['state'] === 'unpaid'): ?>
        <?php /* Most of this shop's orders are paid by bank transfer against a
                 proforma invoice, and no gateway will ever tell us about those.
                 Without this the answer to "has it been paid?" for the commonest
                 route was somebody's memory. */ ?>
        <label for="paid_amount">Amount received</label>
        <div class="with-unit">
          <span><?= e(currency_symbol()) ?></span>
          <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0"
                 placeholder="<?= number_format((int) $order['order']['total'] / 100, 2, '.', '') ?>">
        </div>

        <label for="paid_ref">Reference</label>
        <input id="paid_ref" name="paid_ref" type="text" maxlength="190"
               placeholder="Bank transfer reference, cheque number…">

        <button type="submit" name="mark_paid" value="1" class="block"
                data-confirm="Record this order as paid?">Mark as paid</button>
        <p class="hint">Writes it down against the order. No money moves — this is
          for a transfer that has landed in the bank.</p>
      <?php else: ?>
        <button type="submit" name="mark_unpaid" value="1" class="ghost block"
                data-confirm="Take the payment record off this order? What was there is kept in the history.">Not paid after all</button>
      <?php endif; ?>
    </form>

    <?php if ($events = array_reverse((array) ($order['events'] ?? []))): ?>
      <div class="card pad-card">
        <h2>History</h2>
        <?php /* Newest first, and never shown to the customer. The note field
                 above is a scratchpad that saving overwrites; this is the
                 record of what actually happened and when. */ ?>
        <ul class="events">
          <?php foreach ($events as $ev): ?>
            <li>
              <b><?= e((string) $ev['what']) ?></b>
              <span><?= e(str_replace('T', ' ', substr((string) $ev['at'], 0, 16))) ?><?php
                if (($ev['by'] ?? '') !== '') echo ' · ' . e((string) $ev['by']); ?></span>
              <?php if (($ev['detail'] ?? '') !== ''): ?><em><?= e((string) $ev['detail']) ?></em><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="card pad-card">
      <?= csrf_field() ?>
      <h2>Refund</h2>

      <?php if ($refunds = (array) ($order['refunds'] ?? [])): ?>
        <ul class="refunds">
          <?php foreach ($refunds as $r): ?>
            <li>
              <b>&minus;<?= e(money((int) $r['amount'])) ?></b>
              <span><?= e(date('j M Y', strtotime($r['at']))) ?><?= $r['by'] !== '' ? ' · ' . e($r['by']) : '' ?></span>
              <?php if ($r['reason'] !== ''): ?><em><?= e($r['reason']) ?></em><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="hint"><?= e(money(refunded_total($order))) ?> refunded,
          <b><?= e(money(order_outstanding($order))) ?></b> still owed.</p>
      <?php endif; ?>

      <?php if (order_outstanding($order) > 0): ?>
        <label for="refund_amount">Amount</label>
        <div class="with-unit">
          <span><?= e(currency_symbol()) ?></span>
          <input id="refund_amount" name="refund_amount" type="number" step="0.01" min="0"
                 max="<?= number_format(order_outstanding($order) / 100, 2, '.', '') ?>"
                 placeholder="<?= number_format(order_outstanding($order) / 100, 2, '.', '') ?>">
        </div>

        <label for="refund_reason">Reason</label>
        <input id="refund_reason" name="refund_reason" type="text" maxlength="140"
               placeholder="Returned faulty, short delivery…">

        <button type="submit" name="refund" value="1" class="block"
                data-confirm="Record this refund against the order?">Record the refund</button>
        <p class="hint">This writes it down against the order — it does not move any
          money. Refund in full and the order's status becomes Refunded.</p>
      <?php else: ?>
        <p class="hint">Fully refunded. Nothing left owed on this order.</p>
      <?php endif; ?>
    </form>

    <div class="card pad-card">
      <h2>Paperwork</h2>
      <?php if (!empty($order['invoice']['number'])): ?>
        <p class="hint">Invoice <b><?= e($order['invoice']['number']) ?></b>, issued
          <?= e(date('j M Y', strtotime($order['invoice']['issued_at']))) ?>.</p>
      <?php else: ?>
        <p class="hint">Opening the invoice gives this order its number. Nothing is
          sent — print it or save it as a PDF from the browser.</p>
      <?php endif; ?>
      <a class="btn block" href="/admin/orders/<?= e(rawurlencode($order['reference'])) ?>/invoice"
         target="_blank" rel="noopener">Proforma invoice</a>
      <a class="ghost btn block" href="/admin/orders/<?= e(rawurlencode($order['reference'])) ?>/note"
         target="_blank" rel="noopener">Delivery note</a>
    </div>

    <form method="post" class="card pad-card danger">
      <?= csrf_field() ?>
      <h2>Delete</h2>
      <p class="muted">Removes the order file for good.</p>
      <button type="submit" name="delete" value="1" class="btn-danger"
              data-confirm="Delete order <?= e($order['reference']) ?>? This cannot be undone.">Delete order</button>
    </form>
  </aside>
</div>
