<?php
$entry = $url !== '' ? ($seo[$url] ?? []) : [];
ksort($seo);
?>

<div class="card pad-card">
  <h2>Why this matters</h2>
  <p class="muted">
    These titles and descriptions were copied from the WordPress site so the rebuild keeps the
    metadata Google already indexed. Editing one changes what shows in search results — worth doing
    deliberately, not in bulk.
  </p>
</div>

<div class="two-col">
  <div class="card">
    <div class="card-hd"><h2>Pages</h2><span class="muted"><?= count($seo) ?> entries</span></div>
    <div class="seo-list">
      <?php foreach ($seo as $path => $row): ?>
        <a href="/admin/seo?url=<?= e(urlencode($path)) ?>" class="<?= $path === $url ? 'on' : '' ?>">
          <b><?= e($path) ?></b>
          <span><?= e($row['title'] ?? '— no title —') ?></span>
          <?php if (empty($row['description'])): ?><em class="warn">no description</em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <aside>
    <?php if ($url === ''): ?>
      <div class="card pad-card">
        <h2>Pick a page</h2>
        <p class="muted">Choose one on the left to edit its title and description.</p>
      </div>
    <?php else: ?>
      <form method="post" class="card pad-card">
        <?= csrf_field() ?>
        <input type="hidden" name="url" value="<?= e($url) ?>">
        <h2><?= e($url) ?></h2>

        <label for="seo-title">Title</label>
        <input id="seo-title" name="title" type="text" value="<?= e($entry['title'] ?? '') ?>" maxlength="200">
        <p class="hint"><?= strlen($entry['title'] ?? '') ?> characters — search results usually cut around 60.</p>

        <label for="seo-desc">Description</label>
        <textarea id="seo-desc" name="description" rows="4" maxlength="400"><?= e($entry['description'] ?? '') ?></textarea>
        <p class="hint"><?= strlen($entry['description'] ?? '') ?> characters — aim for 140–160.</p>

        <label for="seo-robots">Robots</label>
        <select id="seo-robots" name="robots">
          <?php foreach (['' => 'index, follow (default)', 'noindex, follow' => 'noindex, follow',
                          'index, nofollow' => 'index, nofollow', 'noindex, nofollow' => 'noindex, nofollow'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($entry['robots'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="seo-canon">Canonical URL</label>
        <input id="seo-canon" name="canonical" type="url" value="<?= e($entry['canonical'] ?? '') ?>">
        <p class="hint">Leave blank to use the page's own address.</p>

        <button type="submit">Save metadata</button>
        <a class="ghost block" href="<?= e($url) ?>" target="_blank" rel="noopener">View the page ↗</a>
      </form>
    <?php endif; ?>
  </aside>
</div>
