<?php
/**
 * Discount codes, listed.
 *
 * @var array  $coupons
 * @var string $q
 */
$today = date('Y-m-d');

/** Why a code is not currently working, if it is not. */
$state = function (array $c) use ($today): array {
    if (empty($c['enabled']))                                     return ['off',     'Off'];
    if (($c['expires'] ?? '') !== '' && $today > $c['expires'])   return ['expired', 'Expired'];
    if (($c['starts'] ?? '')  !== '' && $today < $c['starts'])    return ['soon',    'Not yet'];
    if ((int) ($c['usage_limit'] ?? 0) > 0
        && (int) ($c['used'] ?? 0) >= (int) $c['usage_limit'])    return ['used',    'Used up'];
    return ['live', 'Live'];
};
?>

<div class="bar-row">
  <form class="search-row" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search codes…" aria-label="Search codes">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="ghost btn" href="/admin/coupons">Clear</a><?php endif; ?>
  </form>
  <span class="muted"><?= count($coupons) ?> code<?= count($coupons) === 1 ? '' : 's' ?></span>
</div>

<?php if (!coupons_enabled()): ?>
  <div class="flash bad">
    Discount codes are switched off, so none of these are being accepted.
    Turn them on under <a href="/admin/settings">Settings → General</a>.
  </div>
<?php endif; ?>

<?php if (!$coupons): ?>
  <div class="card pad-card">
    <h2><?= $q !== '' ? 'Nothing matched' : 'No codes yet' ?></h2>
    <p class="muted"><?= $q !== ''
      ? 'No code has that in its name or description.'
      : 'A code takes a percentage or a fixed amount off the basket, and can be limited to certain products, a minimum order, a date range or a number of uses.' ?></p>
    <p><a class="btn" href="/admin/coupons/new">Add the first code</a></p>
  </div>
<?php else: ?>
  <form method="post" id="bulk">
    <?= csrf_field() ?>
    <div class="bulk">
      <select name="bulk" aria-label="Bulk action">
        <option value="">Bulk actions</option>
        <option value="enable">Switch on</option>
        <option value="disable">Switch off</option>
        <option value="delete">Delete</option>
      </select>
      <button type="submit" data-confirm="Apply this to the ticked codes?">Apply</button>
    </div>

    <div class="card">
      <table class="grid">
        <thead>
          <tr>
            <th><input type="checkbox" data-check-all aria-label="Select every code"></th>
            <th>Code</th>
            <th>Discount</th>
            <th>Applies to</th>
            <th>Conditions</th>
            <th>Used</th>
            <th>Ends</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($coupons as $c): [$cls, $label] = $state($c); ?>
            <tr>
              <td><input type="checkbox" name="codes[]" value="<?= e($c['code']) ?>" aria-label="Select <?= e($c['code']) ?>"></td>
              <td>
                <a href="/admin/coupons/<?= e(rawurlencode($c['code'])) ?>"><b><?= e($c['code']) ?></b></a>
                <small class="cstate <?= e($cls) ?>"><?= e($label) ?></small>
              </td>
              <td>
                <b><?= e(coupon_label($c)) ?></b>
                <?php if (!empty($c['free_shipping'])): ?><small>+ free delivery</small><?php endif; ?>
              </td>
              <td>
                <?php
                $on = [];
                foreach ((array) $c['products'] as $slug) {
                    $p = find_product($slug, true);
                    if ($p) $on[] = $p['name'];
                }
                foreach ((array) $c['categories'] as $slug) {
                    $cat = find_category($slug);
                    if ($cat) $on[] = $cat['name'];
                }
                ?>
                <?= $on ? e(clip(implode(', ', $on), 60)) : '<span class="muted">Everything</span>' ?>
              </td>
              <td class="muted">
                <?php
                $rules = [];
                if ((int) $c['min_spend'] > 0) $rules[] = 'over ' . money((int) $c['min_spend']);
                if ((int) $c['max_spend'] > 0) $rules[] = 'up to ' . money((int) $c['max_spend']);
                if (($c['starts'] ?? '') !== '') $rules[] = 'from ' . $c['starts'];
                ?>
                <?= $rules ? e(implode(', ', $rules)) : '—' ?>
              </td>
              <td>
                <?= (int) ($c['used'] ?? 0) ?><?= (int) $c['usage_limit'] > 0 ? ' / ' . (int) $c['usage_limit'] : '' ?>
              </td>
              <td class="muted"><?= ($c['expires'] ?? '') !== '' ? e(date('j M Y', strtotime($c['expires']))) : 'No end date' ?></td>
              <td class="right"><a href="/admin/coupons/<?= e(rawurlencode($c['code'])) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
<?php endif; ?>
