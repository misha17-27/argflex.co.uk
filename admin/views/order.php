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
