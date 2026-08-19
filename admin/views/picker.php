<?php
/**
 * The image library modal, shared by every screen that picks a picture.
 * The button that opens it carries data-pick-image="#some-container"; the
 * chosen path lands in the first empty text input inside that container.
 */
?>
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
