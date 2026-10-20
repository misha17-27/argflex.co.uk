<?php
/**
 * The image library modal, shared by every screen that picks a picture.
 * The button that opens it carries data-pick-image="#some-container"; the
 * chosen path lands in the first empty text input inside that container.
 *
 * Include it as many times as you like — it renders once. Screens used to
 * require it by hand at the foot of the page, which is fine until somebody
 * adds a picker to a screen and forgets: the post editor shipped with a
 * "Choose from the library" button wired to a dialog that was not on the
 * page, so it did nothing at all and said nothing about why. image-field.php
 * now brings its own, and this guard keeps the screens that already ask for
 * it from drawing a second one with the same ids.
 *
 * $pickerFolder names the folder the upload box starts on, so a blog cover
 * does not default into assets/img/products/.
 */
if (defined('ADMIN_PICKER_DRAWN')) return;
define('ADMIN_PICKER_DRAWN', 1);

$pickerFolder = $pickerFolder ?? ($GLOBALS['admin_picker'] ?? 'products');
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
      <span class="muted" data-picker-count><?= count($library) ?> images</span>

      <?php /* A real upload, here, rather than a link that opened the Images
               screen in another tab and left the shop to come back and find
               the product half-edited. The file goes to the same handler that
               screen posts to; the picture it creates is added to the grid and
               chosen straight away, which is what somebody who just picked a
               photograph off their computer meant to happen. */ ?>
      <div class="picker-up">
        <?= csrf_field() ?>
        <label for="picker-folder" class="sr-only">Folder</label>
        <select id="picker-folder" data-picker-folder>
          <?php foreach (['products', 'blog', 'site'] as $f): ?>
            <option value="<?= e($f) ?>" <?= $f === $pickerFolder ? 'selected' : '' ?>>assets/img/<?= e($f) ?>/</option>
          <?php endforeach; ?>
        </select>
        <input type="file" id="picker-file" data-picker-file hidden
               accept="image/jpeg,image/png,image/webp,image/gif">
        <button type="button" class="ghost" data-picker-upload>Upload from this computer</button>
        <span class="muted" data-picker-status role="status"></span>
      </div>
    </footer>
  </div>
</div>
