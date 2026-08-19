<form method="post" class="two-col">
  <?= csrf_field() ?>

  <div>
    <div class="card pad-card">
      <h2>Contact details</h2>
      <p class="hint">These appear in the header, the footer, the contacts page and the structured data.</p>

      <label for="site_name">Site name</label>
      <input id="site_name" name="site_name" type="text" value="<?= e($values['site_name']) ?>">

      <label for="site_tag">Strapline</label>
      <input id="site_tag" name="site_tag" type="text" value="<?= e($values['site_tag']) ?>">

      <div class="pair">
        <div>
          <label for="phone">Phone (shown)</label>
          <input id="phone" name="phone" type="text" value="<?= e($values['phone']) ?>">
        </div>
        <div>
          <label for="phone_href">Phone (dialled)</label>
          <input id="phone_href" name="phone_href" type="text" value="<?= e($values['phone_href']) ?>">
          <p class="hint">Digits and + only — this is what <code>tel:</code> links use.</p>
        </div>
      </div>

      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= e($values['email']) ?>">

      <label for="address">Address</label>
      <input id="address" name="address" type="text" value="<?= e($values['address']) ?>">

      <div class="pair">
        <div>
          <label for="hours_week">Weekday hours</label>
          <input id="hours_week" name="hours_week" type="text" value="<?= e($values['hours_week']) ?>">
        </div>
        <div>
          <label for="hours_weekend">Weekend hours</label>
          <input id="hours_weekend" name="hours_weekend" type="text" value="<?= e($values['hours_weekend']) ?>">
        </div>
      </div>
    </div>

    <div class="card pad-card">
      <h2>Pricing and delivery</h2>

      <div class="pair">
        <div>
          <label for="free_shipping">Free delivery over (£, excl. VAT)</label>
          <input id="free_shipping" name="free_shipping" type="number" step="0.01" min="0"
                 value="<?= number_format($values['free_shipping'] / 100, 2, '.', '') ?>">
        </div>
        <div>
          <label for="shipping_flat">Delivery charge (£)</label>
          <input id="shipping_flat" name="shipping_flat" type="number" step="0.01" min="0"
                 value="<?= number_format($values['shipping_flat'] / 100, 2, '.', '') ?>">
        </div>
      </div>

      <label for="vat_rate">VAT rate (%)</label>
      <input id="vat_rate" name="vat_rate" type="number" min="0" max="100" value="<?= (int) $values['vat_rate'] ?>">
      <p class="hint">Used on the cart, the checkout summary and when an order is priced on the server.</p>
    </div>
  </div>

  <div class="card pad-card" style="grid-column:1">
      <h2>Map</h2>
      <label for="map_url">Google Maps embed URL</label>
      <input id="map_url" name="map_url" type="url" value="<?= e($values['map_url']) ?>">
      <p class="hint">On Google Maps: Share → Embed a map → copy the <code>src</code> of the iframe.</p>
    </div>

    <div class="card pad-card" style="grid-column:1">
      <h2>Social links</h2>
      <p class="hint">Shown in the footer. Leave a name blank to hide that one.</p>
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="pair">
          <div>
            <label for="soc<?= $i ?>_name">Network <?= $i ?></label>
            <input id="soc<?= $i ?>_name" name="soc<?= $i ?>_name" type="text" value="<?= e($values['soc' . $i . '_name']) ?>" placeholder="Facebook">
          </div>
          <div>
            <label for="soc<?= $i ?>_url">Link</label>
            <input id="soc<?= $i ?>_url" name="soc<?= $i ?>_url" type="url" value="<?= e($values['soc' . $i . '_url']) ?>">
          </div>
        </div>
      <?php endfor; ?>
    </div>

  <aside>
    <div class="card pad-card">
      <h2>Save</h2>
      <button type="submit">Save settings</button>
      <p class="hint">Saving also bumps the asset version, so visitors pick up the change straight away instead of a cached copy.</p>
    </div>

    <div class="card pad-card">
      <h2>Current asset version</h2>
      <p class="muted big"><code>v=<?= e(ASSET_VER) ?></code></p>
      <p class="hint">Appended to the CSS and JavaScript URLs so they can be cached for a year and still update on demand.</p>
    </div>
  </aside>
</form>
