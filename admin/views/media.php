<form method="post" enctype="multipart/form-data" class="card pad-card narrow-card">
  <?= csrf_field() ?>
  <h2>Upload an image</h2>
  <label for="file">File</label>
  <input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
  <label for="folder">Folder</label>
  <select id="folder" name="folder">
    <?php foreach ($folders as $f): ?><option value="<?= e($f) ?>">assets/img/<?= e($f) ?>/</option><?php endforeach; ?>
  </select>
  <p class="hint">JPEG, PNG, WebP or GIF, up to 4&nbsp;MB. The file type is checked by reading the image itself, not by its name.</p>
  <button type="submit">Upload</button>
</form>

<?php foreach ($folders as $folder):
  $files = glob(ROOT_DIR . '/assets/img/' . $folder . '/*') ?: [];
  rsort($files); ?>
  <div class="card">
    <div class="card-hd"><h2>assets/img/<?= e($folder) ?>/</h2><span class="muted"><?= count($files) ?> files</span></div>
    <div class="media-grid">
      <?php foreach (array_slice($files, 0, 60) as $file):
        $rel = 'assets/img/' . $folder . '/' . basename($file); ?>
        <figure>
          <img src="/<?= e($rel) ?>" alt="" loading="lazy">
          <figcaption><input type="text" readonly value="<?= e($rel) ?>" onclick="this.select()"></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
