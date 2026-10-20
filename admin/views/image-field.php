<?php
/**
 * One picture, chosen and cleared where it is shown.
 *
 * The cover image used to be a text box and a line of instructions: open the
 * Images page in another tab, upload there, copy the path, come back, paste
 * it. Four steps and a different screen to set one picture, and nothing on
 * the card told you whether the path you pasted was even a real file.
 *
 * This shows the picture, opens the library (which uploads from the computer
 * too — see picker.php), and clears it, all in the same place. The path stays
 * visible underneath because it is still what gets saved, and seeing it is
 * how you notice you have pointed at a file that is not there yet.
 *
 * @var string $name   the form field name
 * @var string $value  what is set now
 * @var string $fid    a unique id, because the picker targets it by selector
 * @var string $empty  what to say when nothing is chosen
 */
$fid   = $fid   ?? 'image-field';
$empty = $empty ?? 'No image chosen';
$value = (string) ($value ?? '');
$folder = $folder ?? 'products';
?>
<?php
/* The control is useless without the library, and the post editor did not
   have it: the button was wired to a dialog that was not on the page, so it
   did nothing and said nothing about why.

   Asked for rather than included here, because this partial sits inside the
   editing form and the dialog carries a CSRF field and a file input of its
   own — nesting those in somebody else's form is how a page ends up posting
   two _token values. layout.php draws it once, after the view, outside every
   form. */
$GLOBALS['admin_picker'] = $folder;
?>
<div class="img-field" id="<?= e($fid) ?>" data-image-field>
  <div class="img-field-pv" data-image-preview>
    <img src="<?= $value !== '' ? '/' . e($value) : '' ?>" alt=""
         <?= $value === '' ? 'hidden' : '' ?> data-image-img loading="lazy">
    <span <?= $value !== '' ? 'hidden' : '' ?> data-image-empty><?= e($empty) ?></span>
  </div>

  <div class="img-field-go">
    <button type="button" class="ghost" data-pick-image="#<?= e($fid) ?>">Choose from the library</button>
    <button type="button" class="ghost" data-image-clear
            <?= $value === '' ? 'hidden' : '' ?>>Clear</button>
  </div>

  <?php /* Still a text input: the picker writes into one, and somebody who
           knows the path can type it. Small and quiet, because it is no
           longer how the picture is normally set. */ ?>
  <input class="img-field-path" name="<?= e($name) ?>" type="text" value="<?= e($value) ?>"
         placeholder="assets/img/blog/name.webp" data-image-path
         aria-label="Image path" spellcheck="false">
</div>
<?php
/* Dropped on the way out. These are ordinary names — $name especially — and
   a view that sets one before including this and reads it afterwards would
   get this picture's instead of its own. That collision has bitten this
   codebase before. */
unset($name, $value, $fid, $empty, $folder, $pickerFolder);
?>
