<div class="tabs">
  <a href="/admin/orders" class="<?= $filter === '' ? 'on' : '' ?>">All</a>
  <?php /* Money owed is the tab somebody opens this screen for, so it sits
           beside All rather than at the end of the statuses. */ ?>
  <a href="/admin/orders?status=unpaid" class="<?= $filter === 'unpaid' ? 'on' : '' ?>">Unpaid</a>
  <?php foreach (ORDER_STATUSES as $key => $label): ?>
    <a href="/admin/orders?status=<?= e($key) ?>" class="<?= $filter === $key ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (!$orders): ?>
    <p class="muted pad">No orders with this status.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Reference</th><th>Customer</th><th class="opt">Delivery to</th><th>Items</th><th>Total</th><th>Paid</th><th>Status</th><th class="opt">Placed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): $c = $o['customer'] ?? []; ?>
          <tr>
            <?php $open = '/admin/orders/' . rawurlencode($o['reference']); ?>
            <td><a class="ref" href="<?= e($open) ?>"><b><?= e($o['reference']) ?></b></a></td>
            <td><?= e($c['name'] ?? '—') ?><small><?= e($c['email'] ?? '') ?></small></td>
            <td class="opt"><?= e(trim(($c['city'] ?? '') . ' ' . ($c['postcode'] ?? ''))) ?: '—' ?></td>
            <td><?= count($o['order']['items'] ?? []) ?></td>
            <td><b><?= e(money((int) ($o['order']['total'] ?? 0))) ?></b></td>
            <?php /* Whether the money came in, which is a different question
                     from what stage the order has reached — an order can be
                     Shipped and still unpaid, and that is exactly the one
                     worth spotting from the list rather than one at a time. */ ?>
            <?php $paid = payment_state($o); ?>
            <td><span class="pay-dot <?= e($paid['state']) ?>" title="<?= e($paid['label']) ?>"><?= e($paid['label']) ?></span></td>
            <td><span class="pill <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></td>
            <td class="opt"><?= e(str_replace('T', ' ', substr((string) ($o['placed_at'] ?? ''), 0, 16))) ?></td>
            <?php /* The reference has always been a link, and read as plain
                     text, so the screen looked like a list with nothing behind
                     it. Stated as a button as well — the products list ends
                     the same way, and one of the two will be the one somebody
                     reaches for. */ ?>
            <td class="right"><a class="ghost" href="<?= e($open) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php /* Payments that were started and never finished. The frozen basket is
         deleted the moment the money arrives, so anything still sitting here
         is a payment that did not go through — a card declined, a browser
         closed on the PayPal page. There was nowhere at all to see these, and
         from the admin's side a failed attempt looked exactly like a customer
         who never tried. Only on the All tab: they are not orders, and they
         have no status to be filtered by. */ ?>
<?php if ($filter === '' && $unfinished): ?>
  <div class="card">
    <div class="card-hd"><h2>Payments started but not finished</h2></div>
    <table class="grid">
      <thead><tr><th>Reference</th><th>Customer</th><th>Total</th><th>Method</th><th class="opt">Started</th></tr></thead>
      <tbody>
        <?php foreach ($unfinished as $p): $pc = (array) ($p['customer'] ?? []); ?>
          <tr>
            <td><b><?= e((string) $p['reference']) ?></b></td>
            <td><?= e((string) ($pc['name'] ?? '—')) ?><small><?= e((string) ($pc['email'] ?? '')) ?></small></td>
            <td><b><?= e(money((int) ($p['order']['total'] ?? 0))) ?></b></td>
            <td><?= e((string) ($p['method'] ?? '—')) ?></td>
            <td class="opt"><?= e(str_replace('T', ' ', substr((string) ($p['started'] ?? ''), 0, 16))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint pad">No order exists for any of these and no money was taken. If one
      of them was in fact charged, the gateway's own dashboard is the place to
      confirm it — search there for the reference.</p>
  </div>
<?php endif; ?>
