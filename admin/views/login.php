<form method="post" class="auth-box">
  <?= csrf_field() ?>
  <img src="/assets/img/site/logo.png" alt="<?= SITE_NAME ?>" width="150" height="40">
  <h1>Sign in</h1>
  <?php if ($wait): ?>
    <p class="bad">Too many attempts. Try again in <?= (int) ceil($wait / 60) ?> minute(s).</p>
  <?php elseif ($error): ?>
    <p class="bad"><?= e($error) ?></p>
  <?php endif; ?>
  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="username" required autofocus>
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button type="submit" <?= $wait ? 'disabled' : '' ?>>Sign in</button>
</form>
