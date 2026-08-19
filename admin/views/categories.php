<form method="post">
  <?= csrf_field() ?>

  <div class="card">
    <div class="card-hd">
      <h2>Categories</h2>
      <span class="muted">Counts come from the catalogue and are not edited here.</span>
    </div>

    <table class="grid editable">
      <thead><tr><th>Name</th><th>Slug</th><th>Parent</th><th>Description</th><th>Products</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($categories as $i => $c): ?>
          <tr data-row>
            <td>
              <input name="cat[<?= $i ?>][name]" type="text" value="<?= e($c['name']) ?>" required>
              <input name="cat[<?= $i ?>][id]" type="hidden" value="<?= (int) $c['id'] ?>">
              <input name="cat[<?= $i ?>][count]" type="hidden" value="<?= (int) $c['count'] ?>">
            </td>
            <td><input name="cat[<?= $i ?>][slug]" type="text" value="<?= e($c['slug']) ?>" required></td>
            <td>
              <select name="cat[<?= $i ?>][parent]">
                <option value="">— top level —</option>
                <?php foreach ($categories as $other): ?>
                  <?php if ($other['slug'] === $c['slug'] || $other['parent'] !== '') continue; ?>
                  <option value="<?= e($other['slug']) ?>" <?= $c['parent'] === $other['slug'] ? 'selected' : '' ?>>
                    <?= e($other['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input name="cat[<?= $i ?>][description]" type="text" value="<?= e($c['description']) ?>" placeholder="Shown under the heading"></td>
            <td class="muted"><?= count(products_in_category($c['slug'])) ?></td>
            <td class="right">
              <a class="ghost" href="/product-category/<?= e($c['path']) ?>/" target="_blank" rel="noopener">View ↗</a>
              <button type="button" class="x" data-remove-row data-confirm="Remove “<?= e($c['name']) ?>” from the list? Products filed under it keep the tag until you edit them." aria-label="Remove">&times;</button>
            </td>
          </tr>
        <?php endforeach; ?>

        <template id="cat-tpl">
          <tr data-row>
            <td>
              <input name="cat[900][name]" type="text" placeholder="New category">
              <input name="cat[900][id]" type="hidden" value="0">
              <input name="cat[900][count]" type="hidden" value="0">
            </td>
            <td><input name="cat[900][slug]" type="text" placeholder="new-category"></td>
            <td>
              <select name="cat[900][parent]">
                <option value="">— top level —</option>
                <?php foreach ($categories as $other): if ($other['parent'] !== '') continue; ?>
                  <option value="<?= e($other['slug']) ?>"><?= e($other['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input name="cat[900][description]" type="text"></td>
            <td class="muted">0</td>
            <td class="right"><button type="button" class="x" data-remove-row aria-label="Remove">&times;</button></td>
          </tr>
        </template>
      </tbody>
    </table>

    <div class="pad">
      <button type="button" class="ghost" data-add-row="#cat-tpl">+ Add a category</button>
      <button type="submit">Save categories</button>
    </div>
  </div>
</form>
