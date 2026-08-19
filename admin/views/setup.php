<form method="post" class="auth-box">
  <?= csrf_field() ?>
  <img src="/assets/img/site/logo.png" alt="<?= SITE_NAME ?>" width="150" height="40">
  <h1>Create the admin account</h1>
  <p class="muted">This runs once. The account is stored in <code>storage/users.php</code>, which the server does not serve.</p>
  <?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
  <label for="name">Your name</label>
  <input id="name" name="name" type="text" value="<?= e($_POST['name'] ?? '') ?>">
  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" required>
  <label for="password">Password (10 characters or more)</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required>
  <label for="password2">Repeat password</label>
  <input id="password2" name="password2" type="password" autocomplete="new-password" required>
  <button type="submit">Create account</button>
</form>
