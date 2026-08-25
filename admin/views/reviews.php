<?php
/**
 * Product reviews, waiting to be judged.
 *
 * @var array  $reviews
 * @var array  $counts
 * @var string $filter
 * @var string $q
 */
?>

<div class="tabs">
  <?php
  $tabs = ['pending' => 'Awaiting approval', 'approved' => 'Published',
           'spam' => 'Spam', '' => 'All'];
  foreach ($tabs as $key => $label):
      $url = '/admin/reviews' . ($key !== '' ? '?status=' . $key : '');
  ?>
    <a href="<?= e($url) ?>" class="<?= $filter === $key ? 'on' : '' ?>">
      <?= e($label) ?> <i><?= (int) ($counts[$key] ?? 0) ?></i>
    </a>
  <?php endforeach; ?>
</div>

<div class="bar-row">
  <form class="search-row" method="get">
    <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, wording or product…" aria-label="Search reviews">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="ghost btn" href="/admin/reviews">Clear</a><?php endif; ?>
  </form>
  <span class="muted"><?= count($reviews) ?> shown</span>
</div>

<?php if (!reviews_enabled()): ?>
  <div class="flash bad">
    Reviews are switched off, so nobody can leave one and none are shown on the
    site. Turn them on under <a href="/admin/settings/products">Settings → Products</a>.
  </div>
<?php endif; ?>

<?php if (!$reviews): ?>
  <div class="card pad-card">
    <h2><?= $q !== '' ? 'Nothing matched' : 'Nothing here' ?></h2>
    <p class="muted"><?= $q !== ''
      ? 'No review carries that name, wording or product.'
      : 'Reviews land here as they are written. Nothing is published until you say so, unless you turn approval off.' ?></p>
  </div>
<?php else: ?>
  <form method="post" id="bulk">
    <?= csrf_field() ?>
    <div class="bulk">
      <select name="bulk" aria-label="Bulk action">
        <option value="">Bulk actions</option>
        <option value="approved">Publish</option>
        <option value="pending">Send back for approval</option>
        <option value="spam">Mark as spam</option>
        <option value="delete">Delete</option>
      </select>
      <button type="submit" data-confirm="Apply this to the ticked reviews?">Apply</button>
    </div>

    <div class="card">
      <table class="grid">
        <thead>
          <tr>
            <th><input type="checkbox" data-check-all aria-label="Select every review"></th>
            <th>Review</th>
            <th class="opt">Product</th>
            <th class="opt">Written</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reviews as $r): $product = find_product($r['product'], true); ?>
            <tr class="<?= $r['status'] === 'pending' ? 'unread' : '' ?>">
              <td><input type="checkbox" name="ids[]" value="<?= e($r['id']) ?>" aria-label="Select this review"></td>
              <td>
                <div class="rv-admin-head">
                  <?= stars((float) $r['rating'], 14) ?>
                  <b><?= e($r['author']) ?></b>
                  <?php if (!empty($r['verified'])): ?>
                    <span class="rv-verified">Bought it</span>
                  <?php endif; ?>
                  <span class="pill <?= $r['status'] === 'approved' ? 'shipped'
                      : ($r['status'] === 'spam' ? 'cancelled' : 'new') ?>">
                    <?= e(REVIEW_STATUSES[$r['status']] ?? $r['status']) ?>
                  </span>
                </div>
                <p class="rv-admin-body"><?= nl2br(e($r['body'])) ?></p>
                <small><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></small>
              </td>
              <td class="opt">
                <?php if ($product): ?>
                  <a href="/admin/products/<?= e(rawurlencode($product['slug'])) ?>"><?= e($product['name']) ?></a>
                  <small><a href="<?= e(product_url($product)) ?>#reviews" target="_blank" rel="noopener">View on the site ↗</a></small>
                <?php else: ?>
                  <span class="muted"><?= e($r['product']) ?> — deleted</span>
                <?php endif; ?>
              </td>
              <td class="muted opt"><?= e(date('j M Y', strtotime($r['created']))) ?></td>
              <td class="right rv-actions">
                <?php foreach (['approved' => 'Publish', 'pending' => 'Unpublish', 'spam' => 'Spam'] as $to => $label): ?>
                  <?php if ($r['status'] === $to) continue; ?>
                  <button type="submit" name="one" value="<?= e($to . ':' . $r['id']) ?>" class="ghost small"><?= e($label) ?></button>
                <?php endforeach; ?>
                <button type="submit" name="one" value="delete:<?= e($r['id']) ?>" class="x"
                        data-confirm="Delete this review?" aria-label="Delete">&times;</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
<?php endif; ?>

<p class="hint">A review counts as a verified purchase when the email address on
it matches an order that was not cancelled. Published reviews show on the
product page and go into its structured data, which is what puts stars beside
it in search results.</p>
