<?php
/**
 * Settings shell. Each tab is its own file under views/settings/ and posts
 * back to its own URL, so saving one tab can never disturb another.
 *
 * @var string $tab
 * @var array  $values
 */
?>
<div class="subtabs">
  <?php foreach (SETTINGS_TABS as $slug => $label): ?>
    <a href="/admin/settings<?= $slug === 'general' ? '' : '/' . e($slug) ?>"
       class="<?= $tab === $slug ? 'on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/settings/' . $tab . '.php'; ?>
