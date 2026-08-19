<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>

<div class="card">
  <div class="card-hd"><h2>Accounts</h2><span class="muted"><?= count($list) ?> people</span></div>
  <table class="grid">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Added</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($list as $u): ?>
        <tr>
          <td><b><?= e($u['name']) ?></b><?= $u['email'] === current_user()['email'] ? ' <small>that is you</small>' : '' ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="pill <?= ($u['role'] ?? 'admin') === 'admin' ? 'confirmed' : '' ?>"><?= e(ROLES[$u['role'] ?? 'admin'] ?? 'Editor') ?></span></td>
          <td class="muted"><?= e(substr((string) ($u['created'] ?? ''), 0, 10)) ?></td>
          <td class="right">
            <?php if ($u['email'] !== current_user()['email']): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($u['email']) ?>">
                <button class="x" type="submit" name="act" value="delete"
                        data-confirm="Remove <?= e($u['email']) ?>?" aria-label="Remove">&times;</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" class="card pad-card narrow-card">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="save">
  <h2>Add someone, or change a password</h2>
  <p class="muted">Using an email that already exists updates that account.</p>

  <label for="u-name">Name</label>
  <input id="u-name" name="name" type="text">

  <label for="u-email">Email</label>
  <input id="u-email" name="email" type="email" required>

  <label for="u-role">Role</label>
  <select id="u-role" name="role">
    <?php foreach (ROLES as $key => $label): ?>
      <option value="<?= e($key) ?>"><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <p class="hint">Editors can work on content. Users, mail, security and settings stay with administrators.</p>

  <label for="u-pass">Password</label>
  <input id="u-pass" name="password" type="password" autocomplete="new-password">
  <p class="hint">At least 10 characters. Leave blank when only changing the name or role.</p>

  <button type="submit">Save account</button>
</form>
