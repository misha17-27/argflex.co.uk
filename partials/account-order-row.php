<?php
/**
 * One line in the customer's order list.
 *
 * Its own file because the dashboard shows the most recent order and the
 * orders section shows all of them, and two copies of this would drift.
 *
 * @var array $o one order record
 */
?>
<li>
  <div>
    <a href="/my-account/orders/<?= e($o['reference']) ?>/"><b><?= e($o['reference']) ?></b></a>
    <span><?= e(date('j M Y', strtotime($o['placed_at']))) ?> ·
      <?= count($o['order']['items']) ?> item<?= count($o['order']['items']) === 1 ? '' : 's' ?></span>
  </div>
  <div class="acc-order-right">
    <b><?= e(money((int) $o['order']['total'])) ?></b>
    <span class="acc-status <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span>
  </div>
</li>
