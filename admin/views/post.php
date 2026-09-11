<p class="back"><a href="/admin/posts">&larr; All posts</a></p>

<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="two-col">
  <?= csrf_field() ?>
  <div>
    <div class="card pad-card">
      <label for="title">Title *</label>
      <input id="title" name="title" type="text" value="<?= e($item['title']) ?>" required>

      <label for="slug">URL slug</label>
      <input id="slug" name="slug" type="text" value="<?= e($item['slug']) ?>">
      <p class="hint">Page address: <code>/<?= e($item['slug'] ?: '&hellip;') ?>/</code></p>

      <label for="excerpt">Excerpt</label>
      <textarea id="excerpt" name="excerpt" rows="3"><?= e($item['excerpt']) ?></textarea>
      <p class="hint">Used on cards and as the meta description when none is set.</p>

      <label for="content">Content</label>
      <textarea id="content" name="content" rows="22" data-rich><?= e($item['content']) ?></textarea>
      <p class="hint">HTML. Headings start at <code>&lt;h2&gt;</code> &mdash; the page supplies the <code>&lt;h1&gt;</code>.</p>
    </div>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Save</h2>
      <button type="submit">Save post</button>
      <?php if (!$isNew): ?>
        <a class="ghost block" href="/<?= e($item['slug']) ?>/" target="_blank" rel="noopener">View on site &#8599;</a>
      <?php endif; ?>
    </div>

    <div class="card pad-card">
      <h2>Details</h2>
      <label for="date">Date</label>
      <input id="date" name="date" type="date" value="<?= e($item['date']) ?>">

      <label for="image">Cover image</label>
      <input id="image" name="image" type="text" value="<?= e((string) $item['image']) ?>" placeholder="assets/img/blog/name.webp">
      <?php if (!empty($item['image'])): ?>
        <img class="preview" src="/<?= e($item['image']) ?>" alt="" loading="lazy">
      <?php endif; ?>
      <p class="hint">Upload on the <a href="/admin/media">Images</a> page, then paste the path.</p>
    </div>

    <?php /* Posts had every one of these already — carried over from the old
             site and served on the page — and no way to read or change one.
             The card is the same as the product's and the page's, because it
             writes to the same place: data/seo.php, keyed on the address. */ ?>
    <div class="card pad-card">
      <h2>Search engines</h2>
      <p class="hint">What Google shows for this article. It was copied from the old
        site, so change it deliberately.</p>

      <label for="seo-title">Title</label>
      <input id="seo-title" name="seo_title" type="text" maxlength="200"
             value="<?= e($seoRow['title'] ?? '') ?>"
             placeholder="<?= e($item['title'] !== '' ? $item['title'] . ' — ' . SITE_NAME : '') ?>">
      <p class="hint"><?= strlen($seoRow['title'] ?? '') ?> characters — results usually cut
        around 60. Blank uses the article's own title.</p>

      <label for="seo-desc">Description</label>
      <textarea id="seo-desc" name="seo_description" rows="4" maxlength="400"><?= e($seoRow['description'] ?? '') ?></textarea>
      <p class="hint"><?= strlen($seoRow['description'] ?? '') ?> characters — aim for 140–160.
        Blank uses the excerpt above.</p>

      <label for="seo-robots">Robots</label>
      <select id="seo-robots" name="seo_robots">
        <?php foreach (['' => 'index, follow (default)', 'noindex, follow' => 'noindex, follow',
                        'index, nofollow' => 'index, nofollow', 'noindex, nofollow' => 'noindex, nofollow'] as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= ($seoRow['robots'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Only change this to keep the article out of search results.</p>

      <label for="seo-canon">Canonical URL</label>
      <input id="seo-canon" name="seo_canonical" type="url" value="<?= e($seoRow['canonical'] ?? '') ?>">
      <p class="hint">Blank uses the article's own address.</p>
    </div>
  </aside>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card pad-card danger narrow-card">
    <?= csrf_field() ?>
    <h2>Delete</h2>
    <button type="submit" name="delete" value="1" class="btn-danger"
            data-confirm="Delete &ldquo;<?= e($item['title']) ?>&rdquo;?">Delete post</button>
  </form>
<?php endif; ?>
