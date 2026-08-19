<div class="stats">
  <a class="stat" href="/admin/orders">
    <span><?= (int) $counts['orders'] ?></span>Orders
  </a>
  <a class="stat <?= $counts['new'] ? 'hot' : '' ?>" href="/admin/orders?status=new">
    <span><?= (int) $counts['new'] ?></span>Awaiting action
  </a>
  <div class="stat">
    <span><?= e(money((int) $counts['revenue'])) ?></span>Ordered value, incl. VAT
  </div>
  <a class="stat" href="/admin/products">
    <span><?= (int) $counts['products'] ?></span>Products
  </a>
  <a class="stat" href="/admin/posts">
    <span><?= (int) $counts['posts'] ?></span>Blog posts
  </a>
</div>

<div class="card">
  <div class="card-hd">
    <h2>Latest orders</h2>
    <a href="/admin/orders">All orders</a>
  </div>

  <?php if (!$orders): ?>
    <p class="muted pad">No orders yet. They appear here the moment a customer checks out.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Reference</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><a href="/admin/orders/<?= e(rawurlencode($o['reference'])) ?>"><b><?= e($o['reference']) ?></b></a></td>
            <td><?= e($o['customer']['name'] ?? '—') ?><small><?= e($o['customer']['email'] ?? '') ?></small></td>
            <td><?= count($o['order']['items'] ?? []) ?></td>
            <td><?= e(money((int) ($o['order']['total'] ?? 0))) ?></td>
            <td><span class="pill <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></td>
            <td><?= e(substr((string) ($o['placed_at'] ?? ''), 0, 10)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-hd"><h2>Quick actions</h2></div>
  <div class="quick">
    <a href="/admin/products/new">Add a product</a>
    <a href="/admin/posts/new">Write a post</a>
    <a href="/admin/media">Upload an image</a>
    <a href="/admin/settings">Edit contact details</a>
  </div>
</div>
