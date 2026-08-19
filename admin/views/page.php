<?php
/** @var string $path @var array $def @var array $values @var array $seoRow */
$defaults = $defaults ?? [];
?>

<p class="back"><a href="/admin/pages">← All pages</a></p>

<form method="post" class="two-col">
  <?= csrf_field() ?>
  <input type="hidden" name="path" value="<?= e($path) ?>">

  <div>
    <?php foreach ($def['groups'] as $groupName => $fields): ?>
      <div class="card pad-card">
        <h2><?= e($groupName) ?></h2>

        <?php foreach ($fields as $field):
          [$key, $label, $type] = $field;
          $hint    = $field[3] ?? '';
          $value   = $values[$key] ?? '';
          $default = $defaults[$key] ?? '';
          $id      = 'f-' . $key;
        ?>
          <label for="<?= e($id) ?>"><?= e($label) ?></label>

          <?php if ($type === 'text'): ?>
            <input id="<?= e($id) ?>" name="f[<?= e($key) ?>]" type="text"
                   value="<?= e($value) ?>" placeholder="<?= e($default) ?>">
          <?php elseif ($type === 'html'): ?>
            <textarea id="<?= e($id) ?>" name="f[<?= e($key) ?>]" rows="18" placeholder="<?= e($default) ?>"><?= e($value) ?></textarea>
          <?php elseif ($type === 'lines'): ?>
            <textarea id="<?= e($id) ?>" name="f[<?= e($key) ?>]" rows="6" placeholder="<?= e($default) ?>"><?= e($value) ?></textarea>
          <?php else: ?>
            <textarea id="<?= e($id) ?>" name="f[<?= e($key) ?>]" rows="3" placeholder="<?= e($default) ?>"><?= e($value) ?></textarea>
          <?php endif; ?>

          <p class="hint">
            <?php if ($hint): ?><?= $hint ?> <?php endif; ?>
            <?php if ($default !== ''): ?>
              <?= $value === '' ? 'Empty means the built-in wording is used.' : '' ?>
            <?php endif; ?>
          </p>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Save</h2>
      <button type="submit">Save page</button>
      <?php if ($path !== '/404'): ?>
        <a class="ghost block" href="<?= e($path) ?>" target="_blank" rel="noopener">View on site ↗</a>
      <?php endif; ?>
      <p class="hint">Leaving a field blank restores the wording that ships with the template — nothing breaks.</p>
    </div>

    <div class="card pad-card">
      <h2>Search engines</h2>
      <p class="hint">What Google shows for this page. It was copied from the old site, so change it deliberately.</p>

      <label for="seo-title">Title</label>
      <input id="seo-title" name="seo_title" type="text" maxlength="200" value="<?= e($seoRow['title'] ?? '') ?>">
      <p class="hint"><?= strlen($seoRow['title'] ?? '') ?> characters — results usually cut around 60.</p>

      <label for="seo-desc">Description</label>
      <textarea id="seo-desc" name="seo_description" rows="4" maxlength="400"><?= e($seoRow['description'] ?? '') ?></textarea>
      <p class="hint"><?= strlen($seoRow['description'] ?? '') ?> characters — aim for 140–160.</p>

      <label for="seo-canon">Canonical URL</label>
      <input id="seo-canon" name="seo_canonical" type="url" value="<?= e($seoRow['canonical'] ?? '') ?>">
      <p class="hint">Blank uses the page's own address.</p>
    </div>
  </aside>
</form>
