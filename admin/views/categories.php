<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<div class="two-col reverse">
  <div>
    <form method="get" class="filter-bar">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search categories…">
      <button type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="ghost" href="/admin/categories">Clear</a><?php endif; ?>
    </form>

    <form method="post">
      <?= csrf_field() ?>
      <div class="card">
        <div class="bulk-bar">
          <select name="bulk">
            <option value="">Bulk actions…</option>
            <option value="delete">Delete</option>
          </select>
          <button class="ghost" type="submit" name="act" value="bulk"
                  data-confirm="Delete every ticked category? Products keep their other categories.">Apply</button>
          <span class="muted"><?= count($rows) ?> of <?= count($categories) ?> categories</span>
        </div>

        <table class="grid">
          <thead>
            <tr>
              <th class="tick"><input type="checkbox" data-check-all aria-label="Select all"></th>
              <th>Image</th>
              <th>Name</th>
              <th title="Has a search title and description">SEO</th>
              <th>Slug</th>
              <th>Count</th>
              <th>Order</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $c):
              $seoRow = $seo[category_url($c)] ?? [];
              $img    = $c['image'] ?? '';
            ?>
              <tr>
                <td class="tick"><input type="checkbox" name="slugs[]" value="<?= e($c['slug']) ?>" aria-label="Select <?= e($c['name']) ?>"></td>
                <td class="thumb">
                  <?php if ($img): ?><img src="/<?= e($img) ?>" alt="" width="46" height="36" loading="lazy">
                  <?php else: ?><span class="no-img" aria-hidden="true">—</span><?php endif; ?>
                </td>
                <td>
                  <a href="/admin/categories?edit=<?= e(urlencode($c['slug'])) ?>">
                    <b><?= $c['parent'] ? '— ' : '' ?><?= e($c['name']) ?></b>
                  </a>
                  <?php if ($c['description']): ?><small><?= e(clip($c['description'], 70)) ?></small><?php endif; ?>
                </td>
                <td>
                  <span class="seo-dot <?= !empty($seoRow['title']) ? 'set' : '' ?>"
                        title="<?= !empty($seoRow['title']) ? e($seoRow['title']) : 'No search title set' ?>"></span>
                  <span class="seo-dot <?= !empty($seoRow['description']) ? 'set' : '' ?>"
                        title="<?= !empty($seoRow['description']) ? 'Description set' : 'No description set' ?>"></span>
                </td>
                <td class="muted"><code><?= e($c['slug']) ?></code></td>
                <td><a href="/admin/products?cat=<?= e(urlencode($c['slug'])) ?>"><?= count(products_in_category($c['slug'])) ?></a></td>
                <td class="muted"><?= (int) ($c['sort'] ?? 0) ?></td>
                <td class="right">
                  <a class="ghost" href="<?= e(category_url($c)) ?>" target="_blank" rel="noopener">View ↗</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="8" class="muted pad">Nothing matched “<?= e($q) ?>”.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>

  <aside>
    <form method="post" class="card pad-card">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="original" value="<?= e($editing['slug'] ?? '') ?>">

      <h2><?= $editing ? 'Edit category' : 'Add a new category' ?></h2>

      <label for="c-name">Name</label>
      <input id="c-name" name="name" type="text" value="<?= e($editing['name'] ?? '') ?>" required>
      <p class="hint">How it appears on the site.</p>

      <label for="c-slug">Slug</label>
      <input id="c-slug" name="slug" type="text" value="<?= e($editing['slug'] ?? '') ?>" placeholder="built from the name">
      <p class="hint">The URL-friendly version — lowercase letters, numbers and hyphens.</p>

      <label for="c-parent">Parent category</label>
      <select id="c-parent" name="parent">
        <option value="">None</option>
        <?php foreach ($categories as $other): ?>
          <?php if ($other['parent'] !== '' || ($editing && $other['slug'] === $editing['slug'])) continue; ?>
          <option value="<?= e($other['slug']) ?>" <?= ($editing['parent'] ?? '') === $other['slug'] ? 'selected' : '' ?>>
            <?= e($other['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="hint">A child sits under its parent in the URL and the menu.</p>

      <label for="c-desc">Description</label>
      <textarea id="c-desc" name="description" rows="4"><?= e($editing['description'] ?? '') ?></textarea>
      <p class="hint">Shown under the heading on the category page.</p>

      <label for="c-image">Image</label>
      <div class="rows" id="cat-image">
        <div class="row-line" data-row>
          <?php if (!empty($editing['image'])): ?>
            <img class="mini" src="/<?= e($editing['image']) ?>" alt="" width="40" height="32">
          <?php endif; ?>
          <input id="c-image" name="image" type="text" value="<?= e($editing['image'] ?? '') ?>"
                 placeholder="assets/img/products/name.jpg">
        </div>
      </div>
      <button type="button" class="ghost block" data-pick-image="#cat-image">Choose from the library</button>
      <p class="hint">Left blank, the tile borrows the first product's photo.</p>

      <label for="c-sort">Order</label>
      <input id="c-sort" name="sort" type="number" value="<?= (int) ($editing['sort'] ?? 0) ?>">
      <p class="hint">Lower numbers come first in the menu and on the homepage.</p>

      <fieldset class="seo-set">
        <legend>Search engines</legend>
        <?php $seoRow = $editing ? ($seo[category_url($editing)] ?? []) : []; ?>

        <label for="c-seo-title">SEO title</label>
        <input id="c-seo-title" name="seo_title" type="text" value="<?= e($seoRow['title'] ?? '') ?>">

        <label for="c-seo-desc">Meta description</label>
        <textarea id="c-seo-desc" name="seo_description" rows="3"><?= e($seoRow['description'] ?? '') ?></textarea>

        <label for="c-seo-robots">Robots</label>
        <select id="c-seo-robots" name="seo_robots">
          <?php foreach (['' => 'index, follow (default)', 'noindex, follow' => 'noindex, follow',
                          'index, nofollow' => 'index, nofollow', 'noindex, nofollow' => 'noindex, nofollow'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ($seoRow['robots'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <button type="submit"><?= $editing ? 'Save category' : 'Add new category' ?></button>
      <?php if ($editing): ?>
        <a class="ghost block" href="/admin/categories">Cancel and add a new one instead</a>
      <?php endif; ?>
    </form>
  </aside>
</div>

<div class="picker" id="picker" hidden>
  <div class="picker-sc" data-picker-close></div>
  <div class="picker-pn">
    <header>
      <h2>Choose an image</h2>
      <button type="button" class="x" data-picker-close aria-label="Close">&times;</button>
    </header>
    <div class="picker-grid">
      <?php
      $library = [];
      foreach (['products', 'blog', 'site'] as $folder) {
          foreach (glob(ROOT_DIR . '/assets/img/' . $folder . '/*') ?: [] as $file) {
              if (is_file($file)) $library[] = 'assets/img/' . $folder . '/' . basename($file);
          }
      }
      sort($library);
      foreach ($library as $src): ?>
        <button type="button" class="pick" data-src="<?= e($src) ?>">
          <img src="/<?= e($src) ?>" alt="" loading="lazy">
          <span><?= e(basename($src)) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <footer>
      <span class="muted"><?= count($library) ?> images</span>
      <a class="ghost" href="/admin/media" target="_blank" rel="noopener">Upload more ↗</a>
    </footer>
  </div>
</div>
