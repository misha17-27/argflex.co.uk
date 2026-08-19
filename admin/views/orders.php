<div class="tabs">
  <a href="/admin/orders" class="<?= $filter === '' ? 'on' : '' ?>">All</a>
  <?php foreach (ORDER_STATUSES as $key => $label): ?>
    <a href="/admin/orders?status=<?= e($key) ?>" class="<?= $filter === $key ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (!$orders): ?>
    <p class="muted pad">No orders with this status.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Reference</th><th>Customer</th><th>Delivery to</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): $c = $o['customer'] ?? []; ?>
          <tr>
            <td><a href="/admin/orders/<?= e(rawurlencode($o['reference'])) ?>"><b><?= e($o['reference']) ?></b></a></td>
            <td><?= e($c['name'] ?? '—') ?><small><?= e($c['email'] ?? '') ?></small></td>
            <td><?= e(trim(($c['city'] ?? '') . ' ' . ($c['postcode'] ?? ''))) ?: '—' ?></td>
            <td><?= count($o['order']['items'] ?? []) ?></td>
            <td><b><?= e(money((int) ($o['order']['total'] ?? 0))) ?></b></td>
            <td><span class="pill <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></td>
            <td><?= e(str_replace('T', ' ', substr((string) ($o['placed_at'] ?? ''), 0, 16))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
