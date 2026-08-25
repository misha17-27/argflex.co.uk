<div class="tabs">
  <a href="/admin/submissions" class="<?= $filter === '' ? 'on' : '' ?>">All (<?= count($all) ?>)</a>
  <a href="/admin/submissions?f=unread" class="<?= $filter === 'unread' ? 'on' : '' ?>">Unread (<?= $unread ?>)</a>
  <a href="/admin/submissions?f=product" class="<?= $filter === 'product' ? 'on' : '' ?>">About a product</a>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted pad">No enquiries here yet. Messages sent from the contacts page land in this list.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Received</th><th>From</th><th>Message</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr id="e-<?= e($r['id']) ?>" class="<?= empty($r['is_read']) ? 'unread' : '' ?>">
            <td class="muted" style="white-space:nowrap">
              <?= e(str_replace('T', ' ', substr((string) $r['created_at'], 0, 16))) ?>
              <?php if (empty($r['is_read'])): ?><span class="dot" title="Unread"></span><?php endif; ?>
            </td>
            <td>
              <b><?= e($r['name']) ?></b>
              <small><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></small>
              <?php if (!empty($r['phone'])): ?><small><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></small><?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['product'])): $p = find_product($r['product']); ?>
                <small class="tagline">About:
                  <?php if ($p): ?><a href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener"><?= e($p['name']) ?></a>
                  <?php else: ?><?= e($r['product']) ?><?php endif; ?>
                </small>
              <?php endif; ?>
              <?= nl2br(e($r['message'])) ?>
            </td>
            <td class="right" style="white-space:nowrap">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="ghost" type="submit" name="act" value="<?= empty($r['is_read']) ? 'read' : 'unread' ?>">
                  <?= empty($r['is_read']) ? 'Mark read' : 'Mark unread' ?>
                </button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="x" type="submit" name="act" value="delete"
                        data-confirm="Delete this enquiry for good?" aria-label="Delete">&times;</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
