<?php
/**
 * One customer: what they have bought and what they have asked.
 *
 * @var array $customer
 * @var array $orders
 * @var array $enquiries
 */
$c = $customer;
?>

<p class="back"><a href="/admin/customers">&larr; All customers</a></p>

<div class="stats">
  <div class="stat"><span><?= (int) $c['orders'] ?></span>Order<?= $c['orders'] === 1 ? '' : 's' ?></div>
  <div class="stat hot"><span><?= e(money((int) $c['spent'])) ?></span>Spent<?= $c['cancelled'] ? ', cancelled excluded' : '' ?></div>
  <div class="stat"><span><?= e($c['orders'] ? money((int) round($c['spent'] / max(1, $c['orders'] - $c['cancelled']))) : money(0)) ?></span>Average order</div>
  <div class="stat"><span><?= (int) $c['enquiries'] ?></span>Enquir<?= $c['enquiries'] === 1 ? 'y' : 'ies' ?></div>
</div>

<div class="two-col">
  <div>
    <div class="card">
      <div class="card-hd"><h2>Orders</h2></div>
      <?php if (!$orders): ?>
        <div class="pad"><p class="muted">No orders yet — this contact came in through the enquiry form.</p></div>
      <?php else: ?>
        <table class="grid">
          <thead><tr><th>Reference</th><th class="opt">Placed</th><th class="opt">Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><a href="/admin/orders/<?= e(rawurlencode($o['reference'])) ?>"><b><?= e($o['reference']) ?></b></a></td>
                <td class="muted opt"><?= e(date('j M Y', strtotime($o['placed_at']))) ?></td>
                <td class="opt">
                  <?= count($o['order']['items']) ?>
                  <small><?= e(clip(implode(', ', array_column($o['order']['items'], 'title')), 48)) ?></small>
                </td>
                <td>
                  <b><?= e(money((int) $o['order']['total'])) ?></b>
                  <?php if (!empty($o['order']['coupon'])): ?>
                    <small><?= e($o['order']['coupon']) ?> &minus;<?= e(money((int) $o['order']['discount'])) ?></small>
                  <?php endif; ?>
                </td>
                <td><span class="pill <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></td>
                <td class="right"><a href="/admin/orders/<?= e(rawurlencode($o['reference'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <?php if ($enquiries): ?>
      <div class="card">
        <div class="card-hd"><h2>Enquiries</h2></div>
        <table class="grid">
          <thead><tr><th>Sent</th><th>About</th><th>Message</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($enquiries as $s): ?>
              <tr>
                <td class="muted"><?= e(date('j M Y', strtotime($s['created_at']))) ?></td>
                <td><?= $s['product'] !== '' ? e(clip($s['product'], 40)) : '<span class="muted">General</span>' ?></td>
                <td class="muted"><?= e(clip($s['message'], 90)) ?></td>
                <td class="right"><a href="/admin/submissions#e-<?= e($s['id']) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($orders): ?>
      <div class="card pad-card">
        <h2>What they buy</h2>
        <?php
        $bought = [];
        foreach ($orders as $o) {
            if (($o['status'] ?? 'new') === 'cancelled') continue;
            foreach ($o['order']['items'] as $item) {
                $slug = $item['slug'];
                $bought[$slug]['title'] = $item['title'];
                $bought[$slug]['qty']   = ($bought[$slug]['qty'] ?? 0) + (int) $item['qty'];
                $bought[$slug]['value'] = ($bought[$slug]['value'] ?? 0) + (int) $item['line'];
            }
        }
        uasort($bought, fn($a, $b) => $b['value'] <=> $a['value']);
        ?>
        <?php if (!$bought): ?>
          <p class="muted">Nothing yet — every order so far was cancelled.</p>
        <?php else: ?>
          <table class="grid">
            <thead><tr><th>Product</th><th>Quantity</th><th>Value</th></tr></thead>
            <tbody>
              <?php foreach ($bought as $slug => $row): ?>
                <tr>
                  <td><a href="/product/<?= e($slug) ?>/" target="_blank" rel="noopener"><?= e($row['title']) ?></a></td>
                  <td><?= (int) $row['qty'] ?></td>
                  <td><b><?= e(money((int) $row['value'])) ?></b></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Contact</h2>
      <p class="lead"><?= e($c['name'] !== '' ? $c['name'] : $c['email']) ?></p>
      <?php if ($c['company'] !== ''): ?><p class="muted"><?= e($c['company']) ?></p><?php endif; ?>

      <dl class="facts">
        <dt>Email</dt><dd><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></dd>
        <?php if ($c['phone'] !== ''): ?>
          <dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a></dd>
        <?php endif; ?>
        <?php if ($c['address'] !== ''): ?>
          <dt>Address</dt>
          <dd><?= e($c['address']) ?><br><?= e(trim($c['city'] . ', ' . $c['postcode'], ', ')) ?><br><?= e($c['country']) ?></dd>
        <?php endif; ?>
        <?php if ($c['first_at'] !== ''): ?>
          <dt>First order</dt><dd><?= e(date('j M Y', strtotime($c['first_at']))) ?></dd>
          <dt>Last order</dt><dd><?= e(date('j M Y', strtotime($c['last_at']))) ?></dd>
        <?php endif; ?>
      </dl>

      <a class="btn block" href="mailto:<?= e($c['email']) ?>">Email them</a>
    </div>

    <div class="card pad-card">
      <h2>Where this comes from</h2>
      <p class="hint">Everything on this page is read back from the orders and
        enquiries already on file — there is nothing to edit here. Change a
        detail on the order itself if it needs correcting.</p>
    </div>
  </aside>
</div>
