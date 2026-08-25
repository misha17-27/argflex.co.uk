<?php
/**
 * Customers, gathered from the orders and enquiries on file.
 *
 * @var array  $customers
 * @var string $q
 * @var string $sort
 */
$sortLink = function (string $key, string $label) use ($q, $sort): string {
    $on  = $sort === $key;
    $url = '/admin/customers?' . http_build_query(array_filter(['q' => $q, 'sort' => $key]));
    return '<a href="' . e($url) . '" class="' . ($on ? 'sorted' : '') . '">' . e($label) . '</a>';
};
?>

<div class="bar-row">
  <form class="search-row" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, company or town…"
           aria-label="Search customers">
    <?php if ($sort !== '') : ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="ghost btn" href="/admin/customers">Clear</a><?php endif; ?>
  </form>
  <span class="muted"><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?></span>
</div>

<?php if (!$customers): ?>
  <div class="card pad-card">
    <h2><?= $q !== '' ? 'Nobody matched' : 'No customers yet' ?></h2>
    <p class="muted"><?= $q !== ''
      ? 'No order or enquiry carries that name, address or town.'
      : 'This list builds itself from orders and enquiries — there are no customer accounts to create. As soon as somebody orders or writes in, they appear here.' ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <table class="grid">
      <thead>
        <tr>
          <th><?= $sortLink('name', 'Customer') ?></th>
          <th class="opt">Location</th>
          <th><?= $sortLink('orders', 'Orders') ?></th>
          <th><?= $sortLink('spent', 'Spent') ?></th>
          <th class="opt">Enquiries</th>
          <th><?= $sortLink('last', 'Last order') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td>
              <a href="/admin/customers/<?= e(rawurlencode($c['email'])) ?>">
                <b><?= e($c['name'] !== '' ? $c['name'] : $c['email']) ?></b>
              </a>
              <small><?= e($c['email']) ?><?= $c['company'] !== '' ? ' · ' . e($c['company']) : '' ?></small>
            </td>
            <td class="muted opt">
              <?php $where = array_filter([$c['city'], $c['country']]); ?>
              <?= $where ? e(implode(', ', $where)) : '—' ?>
            </td>
            <td>
              <?= (int) $c['orders'] ?>
              <?php if ($c['cancelled']): ?><small><?= (int) $c['cancelled'] ?> cancelled</small><?php endif; ?>
            </td>
            <td><b><?= e(money((int) $c['spent'])) ?></b></td>
            <td class="opt"><?= $c['enquiries'] ? (int) $c['enquiries'] : '<span class="muted">—</span>' ?></td>
            <td class="muted">
              <?= $c['last_at'] !== '' ? e(date('j M Y', strtotime($c['last_at']))) : '—' ?>
            </td>
            <td class="right"><a href="/admin/customers/<?= e(rawurlencode($c['email'])) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<p class="hint">There are no customer accounts on this site — an order is placed
without signing in, which is what the business already does. This list is
assembled from the orders in <code>storage/orders/</code> and the enquiries in
<code>storage/submissions.json</code>, matched on email address.</p>
