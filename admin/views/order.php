<?php $c = $order['customer'] ?? []; $o = $order['order'] ?? []; ?>

<p class="back"><a href="/admin/orders">← All orders</a></p>

<div class="two-col">
  <div>
    <div class="card">
      <div class="card-hd"><h2>Items</h2></div>
      <table class="grid">
        <thead><tr><th>Product</th><th>Option</th><th>Qty</th><th>Unit</th><th>Line</th></tr></thead>
        <tbody>
          <?php foreach ($o['items'] ?? [] as $item): ?>
            <tr>
              <td><a href="/product/<?= e($item['slug']) ?>/" target="_blank" rel="noopener"><?= e($item['title']) ?></a></td>
              <td><?= e($item['option'] ?: '—') ?></td>
              <td><?= (int) $item['qty'] ?></td>
              <td><?= e(money((int) $item['price'])) ?></td>
              <td><b><?= e(money((int) $item['line'])) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th colspan="4">Subtotal</th><td><?= e(money((int) ($o['subtotal'] ?? 0))) ?></td></tr>
          <tr><th colspan="4">Delivery</th><td><?= ($o['shipping'] ?? 0) ? e(money((int) $o['shipping'])) : 'Free' ?></td></tr>
          <tr><th colspan="4">VAT</th><td><?= e(money((int) ($o['vat'] ?? 0))) ?></td></tr>
          <tr class="total"><th colspan="4">Total</th><td><b><?= e(money((int) ($o['total'] ?? 0))) ?></b></td></tr>
        </tfoot>
      </table>
    </div>

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

    <form method="post" class="card pad-card danger">
      <?= csrf_field() ?>
      <h2>Delete</h2>
      <p class="muted">Removes the order file for good.</p>
      <button type="submit" name="delete" value="1" class="btn-danger"
              data-confirm="Delete order <?= e($order['reference']) ?>? This cannot be undone.">Delete order</button>
    </form>
  </aside>
</div>
