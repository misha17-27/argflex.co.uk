<?php
/**
 * Email notifications, who sends them, how they are sent and how they look.
 *
 * @var array $values
 */

// A stand-in order so the preview shows a real layout rather than an empty shell.
$sample = [
    'items' => [
        ['title' => 'Oil resistant hose SAE J30 R6', 'option' => 'Inner Diameter: 10mm, Length: 20m',
         'qty' => 2, 'price' => 4250, 'line' => 8500],
        ['title' => 'ASFA clamps', 'option' => '', 'qty' => 10, 'price' => 160, 'line' => 1600],
    ],
    'subtotal' => 10100, 'shipping' => 1200, 'shipping_title' => 'Standard delivery',
    'vat' => tax_on(11300), 'total' => 11300 + tax_on(11300),
];
$previewBody = '<p style="margin:0 0 12px">Hello Jane,</p>'
    . '<p style="margin:0 0 14px">Thank you &mdash; we have your order and will confirm stock and cut '
    . 'lengths shortly. Your reference is <b>260819-4F2A9C</b>.</p>'
    . email_order_table($sample);
?>

<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card">
    <div class="card-hd"><h2>Email notifications</h2></div>
    <table class="grid mails">
      <thead>
        <tr><th>On</th><th>Email</th><th>Recipient</th><th>Subject</th><th>Heading</th></tr>
      </thead>
      <tbody>
        <?php foreach (EMAIL_KINDS as $kind => $meta): $conf = email_conf($kind); ?>
          <tr>
            <td>
              <input type="checkbox" name="email[<?= e($kind) ?>][enabled]"
                     aria-label="Send the <?= e($meta['label']) ?> email"
                     <?= !empty($conf['enabled']) ? 'checked' : '' ?>>
            </td>
            <td>
              <b><?= e($meta['label']) ?></b>
              <small><?= e($meta['when']) ?></small>
            </td>
            <td>
              <input type="text" name="email[<?= e($kind) ?>][to]" value="<?= e($conf['to']) ?>"
                     placeholder="<?= $meta['to'] === 'shop' ? e((string) $values['mail_to']) : 'the customer' ?>"
                     aria-label="Recipient">
              <small><?= $meta['to'] === 'shop' ? 'Blank uses the shop address below. Separate several with commas.' : 'Blank sends it to the customer.' ?></small>
            </td>
            <td><input type="text" name="email[<?= e($kind) ?>][subject]" value="<?= e($conf['subject']) ?>" aria-label="Subject"></td>
            <td><input type="text" name="email[<?= e($kind) ?>][heading]" value="<?= e($conf['heading']) ?>" aria-label="Heading"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="pad">
      <p class="hint">Subjects and headings understand <code>{site}</code>, <code>{reference}</code>, <code>{name}</code>, <code>{total}</code> and <code>{status}</code>.</p>
    </div>
  </div>

  <div class="card pad-card">
    <h2>Sender</h2>
    <div class="pair">
      <div>
        <label for="mail_from_name">"From" name</label>
        <input id="mail_from_name" name="mail_from_name" type="text" value="<?= e($values['mail_from_name']) ?>">
      </div>
      <div>
        <label for="mail_from">"From" address</label>
        <input id="mail_from" name="mail_from" type="email" value="<?= e($values['mail_from']) ?>">
        <p class="hint">Use an address on your own domain or the message will be filtered.</p>
      </div>
    </div>
    <label for="mail_to">Shop address</label>
    <input id="mail_to" name="mail_to" type="email" value="<?= e($values['mail_to']) ?>">
    <p class="hint">Where orders and enquiries land unless a notification above names someone else.</p>
  </div>

  <div class="card pad-card" id="smtp">
    <h2>Sending mail (SMTP)
      <span class="badge <?= $values['smtp_host'] !== '' ? 'ok' : 'warn' ?>">
        now: <?= $values['smtp_host'] !== '' ? 'SMTP' : "the server's own mail" ?>
      </span>
    </h2>
    <p class="hint">Leave the host blank to use the server's own mail. Fill it in to send through a
      mailbox, which is far more likely to reach an inbox than to be dropped as forged.</p>

    <label for="smtp_host">SMTP host</label>
    <input id="smtp_host" name="smtp_host" type="text" value="<?= e($values['smtp_host']) ?>" placeholder="smtp.your-provider.com">

    <div class="triple">
      <div>
        <label for="smtp_port">Port</label>
        <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?= (int) $values['smtp_port'] ?>">
      </div>
      <div>
        <label for="smtp_secure">Encryption</label>
        <select id="smtp_secure" name="smtp_secure">
          <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'None'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $values['smtp_secure'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="smtp_user">Username (the full email address)</label>
        <input id="smtp_user" name="smtp_user" type="text" value="<?= e($values['smtp_user']) ?>" autocomplete="off">
      </div>
    </div>

    <?php /* The password used to be echoed back into value="", so it stood in the
             page in plain text for anyone at the screen, any extension, and every
             "save page" and screenshot. It is write-only now. */ ?>
    <label for="smtp_pass">Password</label>
    <input id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password"
           placeholder="<?= $values['smtp_pass'] !== '' ? 'saved — leave blank to keep it' : 'the password for that mailbox' ?>">
    <?php if ($values['smtp_pass'] !== ''): ?>
      <label class="check" style="margin-top:8px">
        <input type="checkbox" name="smtp_pass_clear" value="1">
        Forget the saved password and go back to the server's own mail
      </label>
    <?php endif; ?>
    <p class="hint">Stored in <code>storage/settings.php</code>, which the server refuses to serve and
      git ignores. It is never shown again once saved.</p>

    <details class="hint" style="margin-top:12px">
      <summary>Where these come from</summary>
      <p style="margin:8px 0 0">In cPanel go to <b>Email Accounts</b>, and on the mailbox you want press
        <b>Connect Devices</b>. That page lists the host, the port and the encryption. The username is
        the full address, and the password is that mailbox's own.</p>
    </details>
  </div>

  <div class="card pad-card">
    <h2>Template</h2>

    <label for="email_logo">Logo</label>
    <div id="logo-row" class="row-line">
      <?php if ($values['email_logo'] !== '' && is_file(ROOT_DIR . '/' . $values['email_logo'])): ?>
        <img class="mini" src="/<?= e($values['email_logo']) ?>" alt="" width="40" height="32">
      <?php endif; ?>
      <input id="email_logo" name="email_logo" type="text" value="<?= e($values['email_logo']) ?>"
             placeholder="assets/img/site/logo.png">
      <button type="button" class="ghost small" data-pick-image="#logo-row">Choose</button>
    </div>
    <p class="hint">Leave blank for the heading on its own.</p>

    <div class="colours">
      <?php foreach (['email_accent' => 'Accent', 'email_bg' => 'Page background',
                      'email_body_bg' => 'Card background', 'email_text' => 'Text'] as $key => $label): ?>
        <div>
          <label for="<?= e($key) ?>"><?= e($label) ?></label>
          <div class="row-line">
            <input type="color" value="<?= e($values[$key]) ?>" data-syncs="#<?= e($key) ?>" aria-label="<?= e($label) ?> colour">
            <input id="<?= e($key) ?>" name="<?= e($key) ?>" type="text" maxlength="7" value="<?= e($values[$key]) ?>">
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <label for="email_footer">Footer</label>
    <textarea id="email_footer" name="email_footer" rows="3"><?= e($values['email_footer']) ?></textarea>
    <p class="hint">Understands <code>{site}</code> and <code>{year}</code>.</p>
  </div>

  <div class="savebar">
    <button type="submit" name="act" value="save">Save changes</button>
    <button type="submit" name="act" value="test" class="ghost"
            data-confirm="Save these settings and send a test message to <?= e($values['mail_to']) ?>?">Save and send a test</button>
  </div>
</form>

<div class="card">
  <div class="card-hd">
    <h2>Preview</h2>
    <span class="muted">Order received, as the customer sees it</span>
  </div>
  <div class="mail-preview">
    <iframe title="Email preview" loading="lazy"
            srcdoc="<?= e(email_html(email_tokens((string) email_conf('order_placed')['heading'],
                                                  ['site' => SITE_NAME, 'name' => 'Jane']),
                                     $previewBody)) ?>"></iframe>
  </div>
</div>

<?php require __DIR__ . '/../picker.php'; ?>
