<div class="tabs">
  <a href="/admin/submissions" class="<?= $filter === '' ? 'on' : '' ?>">All (<?= count($all) ?>)</a>
  <a href="/admin/submissions?f=unread" class="<?= $filter === 'unread' ? 'on' : '' ?>">Unread (<?= $unread ?>)</a>
  <a href="/admin/submissions?f=product" class="<?= $filter === 'product' ? 'on' : '' ?>">About a product</a>
  <?php if ($junk): ?>
    <a href="/admin/submissions?f=spam" class="<?= $filter === 'spam' ? 'on' : '' ?>">Spam (<?= count($junk) ?>)</a>
  <?php endif; ?>
</div>

<?php if ($filter === 'spam'): ?>
  <div class="card pad">
    <p class="muted" style="margin:0 0 .6rem">
      These were held back and never mailed on. They are kept because the filter
      can be wrong — if one of these is a real customer, <b>Not spam</b> puts it
      back in the list as unread so it gets answered.
    </p>
    <form method="post" style="margin:0">
      <?= csrf_field() ?>
      <button class="ghost" type="submit" name="act" value="empty"
              data-confirm="Delete every message in the spam list?">Empty the spam list</button>
    </form>
  </div>
<?php endif; ?>

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
              <?php if (!empty($r['spam'])): ?>
                <small class="tagline">Held back: <?= e((string) ($r['spam_why'] ?? 'looked like spam')) ?></small>
              <?php endif; ?>
              <?php if (!empty($r['spam'])): ?>
                <div style="max-height:9rem;overflow:auto"><?= nl2br(e($r['message'])) ?></div>
              <?php else: ?>
                <?= nl2br(e($r['message'])) ?>
              <?php endif; ?>
            </td>
            <td class="right" style="white-space:nowrap">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <?php if (!empty($r['spam'])): ?>
                  <button class="ghost" type="submit" name="act" value="notspam">Not spam</button>
                <?php else: ?>
                  <button class="ghost" type="submit" name="act" value="<?= empty($r['is_read']) ? 'read' : 'unread' ?>">
                    <?= empty($r['is_read']) ? 'Mark read' : 'Mark unread' ?>
                  </button>
                <?php endif; ?>
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
