<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card pad-card narrow-card">
  <?= csrf_field() ?>
  <h2>Change your password</h2>
  <p class="muted">Signed in as <b><?= e(current_user()['email']) ?></b>.</p>
  <label for="password">New password (10 characters or more)</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required>
  <label for="password2">Repeat it</label>
  <input id="password2" name="password2" type="password" autocomplete="new-password" required>
  <button type="submit">Change password</button>
</form>
