<?php if ($result !== null): ?>
  <div class="flash <?= $result['ok'] ? 'ok' : 'bad' ?>"><?= e($result['message']) ?></div>
<?php endif; ?>

<form method="post" class="two-col">
  <?= csrf_field() ?>
  <div>
    <div class="card pad-card">
      <h2>Where messages go</h2>
      <label for="mail_to">Send enquiries and orders to</label>
      <input id="mail_to" name="mail_to" type="email" value="<?= e($values['mail_to']) ?>">

      <div class="pair">
        <div>
          <label for="mail_from">From address</label>
          <input id="mail_from" name="mail_from" type="email" value="<?= e($values['mail_from']) ?>">
          <p class="hint">Use an address on your own domain, or the mail will be treated as spam.</p>
        </div>
        <div>
          <label for="mail_from_name">From name</label>
          <input id="mail_from_name" name="mail_from_name" type="text" value="<?= e($values['mail_from_name']) ?>">
        </div>
      </div>
    </div>

    <div class="card pad-card">
      <h2>SMTP</h2>
      <p class="hint">Leave the host blank to use the server's own <code>mail()</code>. Filling it in is far more reliable.</p>

      <div class="pair">
        <div>
          <label for="smtp_host">Host</label>
          <input id="smtp_host" name="smtp_host" type="text" value="<?= e($values['smtp_host']) ?>" placeholder="smtp.yourhost.com">
        </div>
        <div>
          <label for="smtp_port">Port</label>
          <input id="smtp_port" name="smtp_port" type="number" value="<?= (int) $values['smtp_port'] ?>">
        </div>
      </div>

      <div class="pair">
        <div>
          <label for="smtp_user">Username</label>
          <input id="smtp_user" name="smtp_user" type="text" value="<?= e($values['smtp_user']) ?>" autocomplete="off">
        </div>
        <div>
          <label for="smtp_secure">Encryption</label>
          <select id="smtp_secure" name="smtp_secure">
            <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'] as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $values['smtp_secure'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label for="smtp_pass">Password</label>
      <input id="smtp_pass" name="smtp_pass" type="password" value="<?= e($values['smtp_pass']) ?>" autocomplete="new-password">
    </div>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Save</h2>
      <button type="submit" name="act" value="save">Save mail settings</button>
    </div>

    <div class="card pad-card">
      <h2>Test it</h2>
      <p class="hint">Sends one message to the address above, using the settings as they are saved now.</p>
      <button class="ghost block" type="submit" name="act" value="test">Send a test email</button>
    </div>

    <?php if (is_file(ROOT_DIR . '/storage/mail-errors.log')): ?>
      <div class="card pad-card">
        <h2>Recent failures</h2>
        <pre class="log"><?= e(implode("\n", array_slice(file(ROOT_DIR . '/storage/mail-errors.log', FILE_IGNORE_NEW_LINES) ?: [], -6))) ?></pre>
        <p class="hint">Enquiries are saved before the mail is attempted, so nothing listed here was lost.</p>
      </div>
    <?php endif; ?>
  </aside>
</form>
