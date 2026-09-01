<?php
/**
 * Google and Meta.
 *
 * Everything here is blank until an id is pasted in, and a blank id loads
 * nothing at all — the shop works exactly the same with this screen empty.
 *
 * @var array $values
 */

$ids   = tracking_ids();
$live  = array_filter([
    'Analytics'  => $ids['ga4'],
    'Tag Manager'=> $ids['gtm'],
    'Google Ads' => $ids['ads'],
    'Meta Pixel' => $ids['pixel'],
]);
?>

<div class="card pad-card">
  <h2>What is switched on
    <span class="badge <?= $live ? 'ok' : 'warn' ?>">
      <?= $live ? e(implode(', ', array_keys($live))) : 'nothing yet' ?>
    </span>
  </h2>
  <p class="hint">An empty box loads nothing. With every box empty the site never
    contacts Google or Meta at all, and the cookie banner does not appear — there is
    nothing to ask about.</p>
</div>

<form method="post" class="setform">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="tracking">

  <div class="card pad-card">
    <h2>Consent</h2>
    <p class="hint">This shop sells into the UK, where PECR wants consent <b>before</b> a
      non-essential cookie is set, and the ICO is explicit that analytics is not exempt.
      With this on, nothing is fetched from Google or Meta until somebody presses Allow —
      not loaded-and-throttled, not fetched. Open the network panel before answering and
      there is nothing there.</p>

    <label class="check">
      <input type="checkbox" name="consent_required" value="1"
             <?= !empty($values['consent_required']) ? 'checked' : '' ?>>
      Ask before loading Analytics and the Pixel
    </label>
    <p class="hint">Turning this off means every visitor is measured from the first page.
      Only do that on legal advice.</p>
  </div>

  <div class="card pad-card">
    <h2>Google</h2>

    <div class="pair">
      <div>
        <label for="ga4_id">Analytics 4 measurement ID</label>
        <input id="ga4_id" name="ga4_id" type="text" value="<?= e($values['ga4_id']) ?>"
               placeholder="G-XXXXXXXXXX">
        <p class="hint">Admin → Data streams → your web stream, top right.</p>
      </div>
      <div>
        <label for="gtm_id">Tag Manager container</label>
        <input id="gtm_id" name="gtm_id" type="text" value="<?= e($values['gtm_id']) ?>"
               placeholder="GTM-XXXXXXX">
        <p class="hint">An <b>alternative</b> to the box on the left, not an addition. Fill
          this in and the container is loaded instead, and it decides what else runs.</p>
      </div>
    </div>

    <div class="pair">
      <div>
        <label for="google_ads_id">Ads conversion ID</label>
        <input id="google_ads_id" name="google_ads_id" type="text"
               value="<?= e($values['google_ads_id']) ?>" placeholder="AW-XXXXXXXXX">
      </div>
      <div>
        <label for="google_ads_label">Ads conversion label</label>
        <input id="google_ads_label" name="google_ads_label" type="text"
               value="<?= e($values['google_ads_label']) ?>" placeholder="AbC-D_efG-h12_34-567">
        <p class="hint">Both are needed, and both come from the one conversion action in
          Google Ads. A completed order fires it, with the order value and reference.</p>
      </div>
    </div>

    <label for="google_verify">Search Console verification</label>
    <input id="google_verify" name="google_verify" type="text"
           value="<?= e($values['google_verify']) ?>" placeholder="the content= value only">
    <p class="hint">Paste only what is inside <code>content="…"</code>, not the whole tag.
      It sets no cookie, so it goes on every page whether or not anybody consents —
      otherwise the domain would never verify.</p>
  </div>

  <div class="card pad-card">
    <h2>Meta</h2>

    <label for="meta_pixel_id">Pixel ID</label>
    <input id="meta_pixel_id" name="meta_pixel_id" type="text"
           value="<?= e($values['meta_pixel_id']) ?>" placeholder="1234567890123456">
    <p class="hint">Events Manager → Data sources. Fifteen or sixteen digits.</p>

    <label for="meta_verify">Domain verification</label>
    <input id="meta_verify" name="meta_verify" type="text"
           value="<?= e($values['meta_verify']) ?>" placeholder="the content= value only">
    <p class="hint">Business settings → Brand safety → Domains. Like the Google one, it is
      an inert string and is not behind the banner.</p>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
  </div>
</form>

<div class="card pad-card">
  <h2>What gets reported</h2>
  <p class="hint">The shop describes what happened once; both vendors are told in their own
    vocabulary. Nothing is sent before consent, and nothing that happened before consent is
    replayed afterwards.</p>

  <div class="table-scroll">
    <table class="grid">
      <thead><tr><th>When</th><th>Google</th><th>Meta</th><th>Carries</th></tr></thead>
      <tbody>
        <?php foreach ([
          ['A product page is opened', 'view_item', 'ViewContent', 'the product and its lowest price'],
          ['Something goes in the basket', 'add_to_cart', 'AddToCart', 'the option chosen, the quantity, the line total'],
          ['The checkout is reached', 'begin_checkout', 'InitiateCheckout', 'the whole basket and its total'],
          ['An order is placed', 'purchase', 'Purchase', 'the reference, the total, the tax, the carriage, every line'],
          ['The search box is used', 'search', 'Search', 'what was typed'],
        ] as [$when, $g, $m, $carries]): ?>
          <tr>
            <td><b><?= e($when) ?></b></td>
            <td><code><?= e($g) ?></code></td>
            <td><code><?= e($m) ?></code></td>
            <td><?= e($carries) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="hint">A purchase also fires the Google Ads conversion when both Ads boxes above
    are filled in. Checking it: open the site, press Allow, and look for
    <code>gtag/js</code> and <code>fbevents.js</code> in the network panel — before Allow
    neither should be there.</p>
</div>
