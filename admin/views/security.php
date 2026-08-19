<form method="post" class="two-col">
  <?= csrf_field() ?>
  <div>
    <div class="card pad-card">
      <h2>Cloudflare Turnstile</h2>
      <p class="muted">
        A free anti-spam check that does not ask people to pick out traffic lights. Create a widget at
        <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com</a>
        and paste the two keys here. Leave them blank and the checks stay off.
      </p>

      <label for="ts_site">Site key</label>
      <input id="ts_site" name="turnstile_site" type="text" value="<?= e($values['turnstile_site']) ?>">

      <label for="ts_secret">Secret key</label>
      <input id="ts_secret" name="turnstile_secret" type="password" value="<?= e($values['turnstile_secret']) ?>" autocomplete="new-password">

      <p class="hint">Applied to the enquiry form, the checkout and this panel's sign-in page.</p>
      <button type="submit">Save</button>
    </div>
  </div>

  <aside>
    <div class="card pad-card">
      <h2>Status</h2>
      <p class="<?= turnstile_enabled() ? 'ok-line' : 'muted' ?>">
        <?= turnstile_enabled()
              ? 'Turnstile is on.'
              : 'Turnstile is off — forms rely on the hidden honeypot field only.' ?>
      </p>
    </div>

    <div class="card pad-card">
      <h2>Already in place</h2>
      <ul class="plain">
        <li>Passwords stored as bcrypt hashes, never in the clear.</li>
        <li>Eight failed sign-ins lock an address out for 15 minutes.</li>
        <li>Sessions are HttpOnly, SameSite=Strict and rotate every 30 minutes.</li>
        <li>Every form in this panel carries a CSRF token.</li>
        <li>Uploads are checked by reading the image, not by its file name.</li>
        <li>Data files refuse to serve themselves if requested directly.</li>
      </ul>
    </div>
  </aside>
</form>
