<?php
/**
 * Sales figures.
 *
 * @var int    $days
 * @var array  $orders
 * @var array  $totals
 * @var array  $series
 * @var array  $products
 * @var array  $categories
 * @var array  $statuses
 * @var array  $zones
 * @var array  $codes
 */
$rangeLabel = REPORT_RANGES[(string) $days] ?? 'All time';
$bar = function (int $value, int $peak): string {
    $pct = $peak > 0 ? max(2, round($value / $peak * 100)) : 0;
    return '<span class="meter"><i style="width:' . $pct . '%"></i></span>';
};
?>

<div class="tabs">
  <?php foreach (REPORT_RANGES as $key => $label): ?>
    <a href="/admin/reports?range=<?= e($key) ?>" class="<?= (string) $days === $key ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$totals['orders']): ?>
  <div class="card pad-card">
    <h2>Nothing in <?= e(lower($rangeLabel)) ?></h2>
    <p class="muted">Figures appear here as soon as orders are placed. Everything on
      this screen is worked out from <code>storage/orders/</code> — there is nothing
      to switch on and no third party involved.</p>
  </div>
<?php else: ?>

  <div class="stats">
    <div class="stat hot"><span><?= e(money((int) $totals['kept'])) ?></span>Revenue, incl. <?= e(tax_label()) ?><?= $totals['refunded'] ? ', after refunds' : '' ?></div>
    <div class="stat"><span><?= (int) $totals['paid'] ?></span>Orders<?= $totals['cancelled'] ? ', ' . (int) $totals['cancelled'] . ' cancelled' : '' ?></div>
    <div class="stat"><span><?= e(money((int) $totals['average'])) ?></span>Average order</div>
    <div class="stat"><span><?= (int) $totals['customers'] ?></span>Customer<?= $totals['customers'] === 1 ? '' : 's' ?></div>
    <div class="stat"><span><?= (int) $totals['units'] ?></span>Items sold</div>
  </div>

  <div class="card pad-card">
    <div class="card-top">
      <h2>Revenue per <?= $series['monthly'] ? 'month' : 'day' ?></h2>
      <span class="muted"><?= e($rangeLabel) ?></span>
    </div>
    <?= bar_chart($series['buckets'], $series['monthly']) ?>
    <p class="hint">Hover a bar for the date, what it took and how many orders it was.
      Cancelled orders are left out.</p>
  </div>

  <div class="two-col">
    <div>
      <div class="card">
        <div class="card-hd"><h2>Best sellers</h2><span class="muted">by value</span></div>
        <table class="grid">
          <thead><tr><th>Product</th><th>Sold</th><th>Value</th><th></th></tr></thead>
          <tbody>
            <?php $peak = $products ? max(array_column($products, 'value')) : 0; ?>
            <?php foreach ($products as $p): ?>
              <tr>
                <td>
                  <a href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener"><b><?= e($p['title']) ?></b></a>
                  <small><?= (int) $p['orders'] ?> order<?= $p['orders'] === 1 ? '' : 's' ?></small>
                </td>
                <td><?= (int) $p['qty'] ?></td>
                <td><b><?= e(money((int) $p['value'])) ?></b></td>
                <td class="meter-cell opt"><?= $bar((int) $p['value'], (int) $peak) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <div class="card-hd"><h2>Categories</h2><span class="muted">by value</span></div>
        <table class="grid">
          <thead><tr><th>Category</th><th>Items</th><th>Value</th><th></th></tr></thead>
          <tbody>
            <?php $peak = $categories ? max(array_column($categories, 'value')) : 0; ?>
            <?php foreach ($categories as $c): ?>
              <tr>
                <td><b><?= e($c['name']) ?></b></td>
                <td><?= (int) $c['qty'] ?></td>
                <td><b><?= e(money((int) $c['value'])) ?></b></td>
                <td class="meter-cell opt"><?= $bar((int) $c['value'], (int) $peak) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="pad">
          <p class="hint">A product in two categories counts in both, so these add up
            to more than the revenue above.</p>
        </div>
      </div>
    </div>

    <aside>
      <div class="card pad-card">
        <h2>Where the money is</h2>
        <dl class="facts">
          <dt>Goods</dt><dd><?= e(money((int) $totals['goods'])) ?></dd>
          <?php if ($totals['discounts']): ?>
            <dt>Discounts</dt><dd>&minus;<?= e(money((int) $totals['discounts'])) ?></dd>
          <?php endif; ?>
          <dt>Delivery</dt><dd><?= e(money((int) $totals['shipping'])) ?></dd>
          <?php if (tax_enabled()): ?>
            <dt><?= e(tax_label()) ?></dt><dd><?= e(money((int) $totals['tax'])) ?></dd>
          <?php endif; ?>
          <?php if ($totals['refunded']): ?>
            <dt>Invoiced</dt><dd><?= e(money((int) $totals['revenue'])) ?></dd>
            <dt>Refunded</dt><dd>&minus;<?= e(money((int) $totals['refunded'])) ?></dd>
          <?php endif; ?>
          <dt>Total</dt><dd><b><?= e(money((int) $totals['kept'])) ?></b></dd>
        </dl>
      </div>

      <div class="card pad-card">
        <h2>Order status</h2>
        <dl class="facts">
          <?php foreach ($statuses as $key => $count): ?>
            <?php if (!$count) continue; ?>
            <dt><span class="pill <?= e($key) ?>"><?= e(ORDER_STATUSES[$key]) ?></span></dt>
            <dd><a href="/admin/orders?status=<?= e($key) ?>"><?= (int) $count ?></a></dd>
          <?php endforeach; ?>
        </dl>
      </div>

      <?php if ($zones): ?>
        <div class="card pad-card">
          <h2>Delivery zones</h2>
          <dl class="facts">
            <?php foreach ($zones as $z): ?>
              <dt><?= e($z['label']) ?></dt>
              <dd><?= (int) $z['orders'] ?> · <?= e(money((int) $z['value'])) ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endif; ?>

      <?php if ($codes): ?>
        <div class="card pad-card">
          <h2>Discount codes used</h2>
          <dl class="facts">
            <?php foreach ($codes as $c): ?>
              <dt><a href="/admin/coupons/<?= e(rawurlencode($c['label'])) ?>"><?= e($c['label']) ?></a></dt>
              <dd><?= (int) $c['orders'] ?> · <?= e(money((int) $c['value'])) ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endif; ?>

      <div class="card pad-card">
        <h2>Export</h2>
        <p class="hint">Every order in this range, one row per order, ready for a spreadsheet.</p>
        <a class="ghost btn block" href="/admin/reports/export?range=<?= (int) $days ?>">Download CSV</a>
      </div>
    </aside>
  </div>

<?php endif; ?>
