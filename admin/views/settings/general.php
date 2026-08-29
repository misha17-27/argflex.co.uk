<?php /** @var array $values */ ?>
<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>Store details</h2>
    <p class="hint">These appear in the header, the footer, the contacts page and the structured data.</p>

    <div class="pair">
      <div>
        <label for="site_name">Site name</label>
        <input id="site_name" name="site_name" type="text" value="<?= e($values['site_name']) ?>">
      </div>
      <div>
        <label for="site_tag">Strapline</label>
        <input id="site_tag" name="site_tag" type="text" value="<?= e($values['site_tag']) ?>">
      </div>
    </div>

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

    <label for="whatsapp">WhatsApp number</label>
    <input id="whatsapp" name="whatsapp" type="text" value="<?= e($values['whatsapp']) ?>"
           placeholder="447717217388">
    <p class="hint">Digits only, with the country code and no <code>+</code>. Every product gets an
      <b>Order on WhatsApp</b> button that opens a chat with the product, the option chosen and the
      quantity already written out. Leave blank to hide the button.</p>

    <div class="pair">
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($values['email']) ?>">
      </div>
      <div>
        <label for="address">Address, as shown on the site</label>
        <input id="address" name="address" type="text" value="<?= e($values['address']) ?>">
      </div>
    </div>

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
    <h2>Store address</h2>
    <p class="hint">Where the business is. Delivery zones are matched against the customer's country, not this one — this is the address that goes on invoices and in the order emails.</p>

    <div class="pair">
      <div>
        <label for="store_addr1">Address line 1</label>
        <input id="store_addr1" name="store_addr1" type="text" value="<?= e($values['store_addr1']) ?>">
      </div>
      <div>
        <label for="store_addr2">Address line 2</label>
        <input id="store_addr2" name="store_addr2" type="text" value="<?= e($values['store_addr2']) ?>">
      </div>
    </div>
    <div class="pair">
      <div>
        <label for="store_city">City</label>
        <input id="store_city" name="store_city" type="text" value="<?= e($values['store_city']) ?>">
      </div>
      <div>
        <label for="store_postcode">Postcode</label>
        <input id="store_postcode" name="store_postcode" type="text" value="<?= e($values['store_postcode']) ?>">
      </div>
    </div>
    <div class="pair">
      <div>
        <label for="company_number">Company number</label>
        <input id="company_number" name="company_number" type="text" value="<?= e($values['company_number']) ?>">
      </div>
      <div>
        <label for="vat_number"><?= e($values['tax_label']) ?> number</label>
        <input id="vat_number" name="vat_number" type="text" value="<?= e($values['vat_number']) ?>">
      </div>
    </div>
    <p class="hint">Both are printed on the invoice. Leave either blank to leave it off.</p>

    <label for="store_country">Country</label>
    <select id="store_country" name="store_country">
      <?php foreach (COUNTRIES as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= $values['store_country'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="card pad-card">
    <h2>Selling and delivery locations</h2>

    <div class="pair">
      <div>
        <label for="sell_to">Selling locations</label>
        <select id="sell_to" name="sell_to" data-toggle="#sell-list">
          <option value="all"      <?= $values['sell_to'] === 'all'      ? 'selected' : '' ?>>Sell to all countries</option>
          <option value="selected" <?= $values['sell_to'] === 'selected' ? 'selected' : '' ?>>Sell to selected countries only</option>
        </select>
      </div>
      <div>
        <label for="ship_to">Delivery locations</label>
        <select id="ship_to" name="ship_to" data-toggle="#ship-list">
          <option value="sell"     <?= $values['ship_to'] === 'sell'     ? 'selected' : '' ?>>Deliver anywhere we sell</option>
          <option value="selected" <?= $values['ship_to'] === 'selected' ? 'selected' : '' ?>>Deliver to selected countries only</option>
          <option value="none"     <?= $values['ship_to'] === 'none'     ? 'selected' : '' ?>>Collection only — no delivery</option>
        </select>
      </div>
    </div>

    <div class="pair">
      <div id="sell-list" <?= $values['sell_to'] === 'selected' ? '' : 'hidden' ?>>
        <label for="sell_countries">Countries we sell to</label>
        <select id="sell_countries" name="sell_countries[]" multiple size="9">
          <?php foreach (COUNTRIES as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= in_array($code, (array) $values['sell_countries'], true) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Ctrl or ⌘ to pick several.</p>
      </div>
      <div id="ship-list" <?= $values['ship_to'] === 'selected' ? '' : 'hidden' ?>>
        <label for="ship_countries">Countries we deliver to</label>
        <select id="ship_countries" name="ship_countries[]" multiple size="9">
          <?php foreach (COUNTRIES as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= in_array($code, (array) $values['ship_countries'], true) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">This is the list the checkout offers.</p>
      </div>
    </div>

    <label for="default_country">Country the checkout starts on</label>
    <select id="default_country" name="default_country">
      <?php foreach (COUNTRIES as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= $values['default_country'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="hint">The basket quotes delivery for this country until the visitor picks another at checkout.</p>
  </div>

  <div class="card pad-card">
    <h2>Taxes and discount codes</h2>

    <label class="check">
      <input type="checkbox" name="enable_taxes" <?= !empty($values['enable_taxes']) ? 'checked' : '' ?>>
      Add <?= e($values['tax_label']) ?> to the cart and the checkout
    </label>
    <p class="hint">The rate and the wording are on the <a href="/admin/settings/tax">Tax</a> tab.</p>

    <label class="check">
      <input type="checkbox" name="enable_coupons" <?= !empty($values['enable_coupons']) ? 'checked' : '' ?>>
      Accept discount codes
    </label>
    <p class="hint">Shows a code box on the cart and the checkout. The codes themselves live under <a href="/admin/coupons">Discount codes</a><?php
      $live = count(array_filter(all_coupons(), fn($c) => !empty($c['enabled'])));
      echo $live ? ' — ' . $live . ' of ' . count(all_coupons()) . ' switched on' : ', where there are none yet'; ?>.</p>
  </div>

  <div class="card pad-card">
    <h2>Currency</h2>
    <p class="hint">Prices are stored as whole pence, so this only changes how they are printed — nothing is converted.</p>

    <div class="pair">
      <div>
        <label for="currency">Currency</label>
        <select id="currency" name="currency">
          <?php foreach (CURRENCIES as $code => [$name, $symbol]): ?>
            <option value="<?= e($code) ?>" <?= $values['currency'] === $code ? 'selected' : '' ?>>
              <?= e($name) ?> (<?= e($symbol) ?>) — <?= e($code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="currency_pos">Symbol position</label>
        <select id="currency_pos" name="currency_pos">
          <?php foreach (CURRENCY_POSITIONS as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $values['currency_pos'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="triple">
      <div>
        <label for="thousand_sep">Thousand separator</label>
        <input id="thousand_sep" name="thousand_sep" type="text" maxlength="2" value="<?= e($values['thousand_sep']) ?>">
      </div>
      <div>
        <label for="decimal_sep">Decimal separator</label>
        <input id="decimal_sep" name="decimal_sep" type="text" maxlength="2" value="<?= e($values['decimal_sep']) ?>">
      </div>
      <div>
        <label for="decimals">Decimal places</label>
        <input id="decimals" name="decimals" type="number" min="0" max="4" value="<?= (int) $values['decimals'] ?>">
      </div>
    </div>
    <p class="hint">Prices currently read <b><?= e(money(123456)) ?></b> and <b><?= e(money(950)) ?></b>.</p>
  </div>

  <div class="card pad-card">
    <h2>Map and social links</h2>

    <label for="map_url">Google Maps embed URL</label>
    <input id="map_url" name="map_url" type="url" value="<?= e($values['map_url']) ?>">
    <p class="hint">On Google Maps: Share → Embed a map → copy the <code>src</code> from the iframe.</p>

    <?php for ($i = 1; $i <= 4; $i++): ?>
      <div class="pair">
        <div>
          <label for="soc<?= $i ?>_name">Network <?= $i ?></label>
          <input id="soc<?= $i ?>_name" name="soc<?= $i ?>_name" type="text"
                 value="<?= e($values['soc' . $i . '_name']) ?>" placeholder="Facebook">
        </div>
        <div>
          <label for="soc<?= $i ?>_url">Link</label>
          <input id="soc<?= $i ?>_url" name="soc<?= $i ?>_url" type="url" value="<?= e($values['soc' . $i . '_url']) ?>">
        </div>
      </div>
    <?php endfor; ?>
    <p class="hint">Leave a name blank to hide that one from the footer.</p>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
    <span class="hint">Saving also bumps the asset version, so visitors see the change instead of a cached copy.</span>
  </div>
</form>
