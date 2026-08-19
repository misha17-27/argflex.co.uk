<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<div class="two-col reverse">
  <div>
    <div class="card">
      <div class="card-hd">
        <h2>Attributes</h2>
        <span class="muted"><?= count($attributes) ?> defined</span>
      </div>
      <table class="grid">
        <thead><tr><th>Name</th><th>Slug</th><th>Order by</th><th>Terms</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($attributes as $a): ?>
            <tr>
              <td><a href="/admin/attributes?edit=<?= e(urlencode($a['slug'])) ?>"><b><?= e($a['name']) ?></b></a></td>
              <td class="muted"><code><?= e($a['slug']) ?></code></td>
              <td class="muted"><?= e(ATTR_ORDERS[$a['order_by']] ?? 'Custom ordering') ?></td>
              <td>
                <span class="terms"><?= e(implode(', ', array_column($a['terms'], 'name'))) ?></span>
                <small><?= count($a['terms']) ?> terms · used by <?= $usage[$a['name']] ?? 0 ?> product<?= ($usage[$a['name']] ?? 0) === 1 ? '' : 's' ?></small>
              </td>
              <td class="right">
                <a class="ghost" href="/admin/attributes?edit=<?= e(urlencode($a['slug'])) ?>">Configure terms</a>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="slug" value="<?= e($a['slug']) ?>">
                  <button class="x" type="submit" name="act" value="delete"
                          data-confirm="Delete the “<?= e($a['name']) ?>” attribute? Products keep the copy they already hold."
                          aria-label="Delete">&times;</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$attributes): ?>
            <tr><td colspan="5" class="muted pad">No attributes yet. Add one on the left.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <aside>
    <form method="post" class="card pad-card">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="original" value="<?= e($editing['slug'] ?? '') ?>">

      <h2><?= $editing ? 'Edit attribute' : 'Add a new attribute' ?></h2>
      <p class="hint">
        Attributes hold the values a buyer picks from — length, bore size and so on.
        Defining them here means every product can reuse the same list.
      </p>

      <label for="a-name">Name</label>
      <input id="a-name" name="name" type="text" value="<?= e($editing['name'] ?? '') ?>" required>
      <p class="hint">Shown on the product page, above the buttons.</p>

      <label for="a-slug">Slug</label>
      <input id="a-slug" name="slug" type="text" maxlength="28" value="<?= e($editing['slug'] ?? '') ?>"
             placeholder="built from the name">

      <label for="a-order">Default sort order</label>
      <select id="a-order" name="order_by">
        <?php foreach (ATTR_ORDERS as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= ($editing['order_by'] ?? 'custom') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">How the buttons are ordered on the product page.</p>

      <label for="a-terms">Terms</label>
      <textarea id="a-terms" name="terms" rows="8" placeholder="1m&#10;5m&#10;10m"><?= e($editing ? implode("\n", array_column($editing['terms'], 'name')) : '') ?></textarea>
      <p class="hint">One per line, or separated by commas.</p>

      <button type="submit"><?= $editing ? 'Save attribute' : 'Add attribute' ?></button>
      <?php if ($editing): ?>
        <a class="ghost block" href="/admin/attributes">Cancel and add a new one instead</a>
      <?php endif; ?>
    </form>
  </aside>
</div>
