<?php
/**
 * Checkout. The basket lives in the visitor's browser, so it is posted back
 * with the form and re-priced here from the catalogue — the browser never
 * gets to decide what anything costs.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/turnstile.php';
require_once ROOT_DIR . '/inc/mail.php';
require_once ROOT_DIR . '/inc/store.php';     // save_order(), record_coupon_use()

$errors = [];
$done   = trim((string) ($_GET['ok'] ?? ''));
$old    = [];

// Somebody signed in should not retype what we already hold. Anything they
// change here is used for this order and does not overwrite the account.
if ($signedIn = current_customer()) {
    $old = [
        'name'     => $signedIn['name'],     'company'  => $signedIn['company'],
        'email'    => $signedIn['email'],    'phone'    => $signedIn['phone'],
        'address'  => $signedIn['address'],  'city'     => $signedIn['city'],
        'postcode' => $signedIn['postcode'], 'country'  => $signedIn['country'],
    ];
}

/**
 * Rebuild the order from the posted lines, using our own prices.
 *
 * $picked maps a consignment's position to the rate the customer chose for
 * it. A basket can travel as two consignments — see inc/shipping.php — and
 * each is charged separately, so the delivery figure is the sum of them.
 */
function price_order(array $lines, string $country = '', string $code = '', array $picked = []): array
{
    $items    = price_basket_lines($lines);
    $subtotal = array_sum(array_column($items, 'line'));

    // A code is re-checked here rather than trusted; the browser only ever
    // says which one to try.
    $coupon   = $code !== '' ? coupon_apply($code, $items, $subtotal) : ['ok' => false];
    $discount = !empty($coupon['ok']) ? (int) $coupon['discount'] : 0;

    $quote = shipping_quote($items, $country, $picked);
    $free  = !empty($coupon['ok']) && !empty($coupon['free_shipping']);
    $ship  = $free ? 0 : (int) $quote['cost'];

    // VAT goes on the goods alone. The live shop's one tax rate has its
    // shipping flag off, and every one of the shipping lines in the order
    // archive carries no tax — so putting delivery in the base here would
    // have raised every order by a fifth of its carriage.
    $tax  = tax_for($country);
    $base = $subtotal - $discount + (tax_on_shipping() ? $ship : 0);
    $vat  = (int) round($base * $tax['rate'] / 100);

    return [
        'items'          => $items,
        'subtotal'       => $subtotal,
        'coupon'         => !empty($coupon['ok']) ? $coupon['code'] : '',
        'coupon_title'   => !empty($coupon['ok']) ? $coupon['title'] : '',
        'discount'       => $discount,
        'shipping'       => $ship,
        'shipping_title' => $free ? 'Free delivery with ' . $coupon['code'] : (string) $quote['title'],
        'shipping_zone'  => shipping_zone($country)['name'] ?? '',
        'packages'       => $quote['packages'],
        'deliverable'    => (bool) $quote['deliverable'],
        'undeliverable_because' => (string) $quote['why'],
        'ship_surcharge' => 0,
        'ship_because'   => '',
        'delivery_in'    => '',
        'vat'            => $vat,
        'tax_label'      => $tax['label'],
        'tax_rate'       => $tax['rate'],
        'tax_note'       => $tax['note'],
        'total'          => $subtotal - $discount + $ship + $vat,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old   = $_POST;
    $lines = json_decode((string) ($_POST['cart'] ?? '[]'), true);
    $country = strtoupper(trim((string) ($_POST['country'] ?? '')));
    if (!isset(COUNTRIES[$country])) $country = (string) setting('default_country');
    // one rate per consignment; anything the browser sends that is no longer
    // on offer falls back to the first, which is the shop's own order
    $pickedRates = [];
    foreach ((array) ($_POST['ship'] ?? []) as $i => $rateId) $pickedRates[(int) $i] = (int) $rateId;

    $order   = price_order(is_array($lines) ? $lines : [], $country,
                           trim((string) ($_POST['coupon'] ?? '')), $pickedRates);

    $required = [
        'name'     => 'your name',
        'email'    => 'an email address',
        'phone'    => 'a phone number',
        'address'  => 'a delivery address',
        'city'     => 'a town or city',
        'postcode' => 'a postcode',
    ];
    foreach ($required as $field => $label) {
        if (trim((string) ($_POST[$field] ?? '')) === '') $errors[$field] = "Please enter {$label}.";
    }
    if (!isset($errors['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address does not look right.';
    }
    $payment = find_payment_method((string) ($_POST['payment'] ?? '')) ?? default_payment_method();
    if (empty($payment['enabled']) && payment_methods()) $payment = default_payment_method();

    if (!$order['items']) {
        $errors['cart'] = 'Your basket is empty, so there is nothing to order yet.';
    } elseif (!$order['deliverable']) {
        $errors['country'] = $order['undeliverable_because'];
    }
    if (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
        $errors['captcha'] = 'The anti-spam check did not pass. Please try once more.';
    }
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $errors['captcha'] = 'The anti-spam check did not pass. Please try once more.';
    }

    if (!$errors) {
        $ref = date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $record = [
            'reference' => $ref,
            'placed_at' => date('c'),
            'customer'  => [
                'name'     => trim((string) $_POST['name']),
                'company'  => trim((string) ($_POST['company'] ?? '')),
                'email'    => trim((string) $_POST['email']),
                'phone'    => trim((string) $_POST['phone']),
                'address'  => trim((string) $_POST['address']),
                'city'     => trim((string) $_POST['city']),
                'postcode' => trim((string) $_POST['postcode']),
                'country'      => COUNTRIES[$country] ?? 'United Kingdom',
                'country_code' => $country,
                'notes'    => trim((string) ($_POST['notes'] ?? '')),
            ],
            'order'   => $order,
            'payment' => $payment,
        ];

        $dir = ROOT_DIR . '/storage/orders';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents("{$dir}/{$ref}.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // The order file is on disk either way, so a mail failure is logged
        // rather than shown — the customer has their reference regardless.
        if ($order['coupon'] !== '') record_coupon_use($order['coupon']);

        send_order_emails($record);

        header('Location: /checkout/?ok=' . urlencode($ref));
        exit;
    }
}

set_page([
    'title'       => ($done !== '' ? 'Order received' : 'Checkout') . ' — ' . SITE_NAME,
    'description' => 'Complete your Arg Flex order.'
        . (price_suffix() !== '' ? ' Prices ' . price_suffix() . '.' : '')
        . ' Delivery within the United Kingdom.',
    'crumbs'      => [['label' => 'Cart', 'url' => '/cart/'], ['label' => 'Checkout']],
]);

require ROOT_DIR . '/inc/header.php';
?>

<?php if ($done !== ''): ?>

  <section class="pg-head">
    <div class="wrap narrow">
      <span class="eyebrow">Thank you</span>
      <h1>Order <?= e($done) ?> received</h1>
      <p>We have your order. Nothing has been charged yet — we confirm stock and cut lengths first, then send a proforma invoice with payment details.</p>
    </div>
  </section>

  <section style="padding-top:32px" data-order-done>
    <div class="wrap narrow">
      <ol class="next-steps">
        <li><b>We check the order</b><span>Stock, cut lengths and the couplings that go with them, usually within one working day.</span></li>
        <li><b>You get a proforma invoice</b><span>With the final total including VAT and delivery, and how to pay.</span></li>
        <li><b>We dispatch</b><span>Stocked lines leave the same working day once payment clears.</span></li>
      </ol>

      <div class="done-box">
        <p>Keep reference <b><?= e($done) ?></b> to hand if you need to call us about this order.</p>
        <div class="done-actions">
          <a class="btn btn-primary" href="/shop/">Continue shopping</a>
          <a class="btn btn-out" href="tel:<?= SITE_PHONE_HREF ?>">Call <?= SITE_PHONE ?></a>
        </div>
      </div>
    </div>
  </section>

<?php else: ?>

  <section class="pg-head">
    <div class="wrap">
      <span class="eyebrow">Checkout</span>
      <h1>Delivery details</h1>
      <p>All prices exclude VAT. We confirm stock and send a proforma invoice before taking payment — no card details are collected on this page.</p>
    </div>
  </section>

  <section style="padding-top:36px">
    <div class="wrap">
      <?php if ($errors): ?>
        <div class="form-error">
          <b>Please check the form</b>
          <ul>
            <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form class="checkout-grid" method="post" action="/checkout/" data-checkout novalidate>
        <input type="hidden" name="cart" value="" data-cart-field>

        <div class="co-main">
          <fieldset class="co-box">
            <legend>Your details</legend>
            <div class="two">
              <div class="fld<?= isset($errors['name']) ? ' bad' : '' ?>">
                <label for="co-name">Full name *</label>
                <input id="co-name" name="name" type="text" autocomplete="name" value="<?= e($old['name'] ?? '') ?>" required>
              </div>
              <div class="fld">
                <label for="co-company">Company (optional)</label>
                <input id="co-company" name="company" type="text" autocomplete="organization" value="<?= e($old['company'] ?? '') ?>">
              </div>
            </div>
            <div class="two">
              <div class="fld<?= isset($errors['email']) ? ' bad' : '' ?>">
                <label for="co-email">Email *</label>
                <input id="co-email" name="email" type="email" autocomplete="email" value="<?= e($old['email'] ?? '') ?>" required>
              </div>
              <div class="fld<?= isset($errors['phone']) ? ' bad' : '' ?>">
                <label for="co-phone">Phone *</label>
                <input id="co-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>" required>
              </div>
            </div>
          </fieldset>

          <fieldset class="co-box">
            <legend>Delivery address</legend>
            <div class="fld<?= isset($errors['address']) ? ' bad' : '' ?>">
              <label for="co-address">Street address *</label>
              <input id="co-address" name="address" type="text" autocomplete="street-address" value="<?= e($old['address'] ?? '') ?>" required>
            </div>
            <div class="two">
              <div class="fld<?= isset($errors['city']) ? ' bad' : '' ?>">
                <label for="co-city">Town / city *</label>
                <input id="co-city" name="city" type="text" autocomplete="address-level2" value="<?= e($old['city'] ?? '') ?>" required>
              </div>
              <div class="fld<?= isset($errors['postcode']) ? ' bad' : '' ?>">
                <label for="co-postcode">Postcode *</label>
                <input id="co-postcode" name="postcode" type="text" autocomplete="postal-code" value="<?= e($old['postcode'] ?? '') ?>" required>
              </div>
            </div>
            <div class="fld">
              <label for="co-country">Country</label>
              <select id="co-country" name="country" autocomplete="country" data-co-country>
                <?php $picked = strtoupper((string) ($old['country'] ?? '')) ?: (string) setting('default_country'); ?>
                <?php foreach (delivery_countries() as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= $picked === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="fld-note">Delivery zone: <b data-co-zone><?= e(shipping_zone($picked)['name'] ?? '') ?></b></p>
            </div>
            <div class="fld">
              <label for="co-notes">Order notes (optional)</label>
              <textarea id="co-notes" name="notes" rows="3" placeholder="Delivery instructions, required cut lengths, purchase order number…"><?= e($old['notes'] ?? '') ?></textarea>
            </div>
          </fieldset>

          <!-- Filled in by the server: a basket can travel as two
               consignments, each charged on its own. -->
          <div class="co-box" data-ship-choice hidden></div>

          <div class="co-box co-pay">
            <h2>Payment</h2>
            <?php $methods = payment_methods(); $chosen = (string) ($old['payment'] ?? ($methods[0]['id'] ?? '')); ?>
            <?php if (count($methods) > 1): ?>
              <ul class="pay-list">
                <?php foreach ($methods as $m): ?>
                  <li>
                    <label class="pay-opt">
                      <input type="radio" name="payment" value="<?= e($m['id']) ?>" <?= $chosen === $m['id'] ? 'checked' : '' ?>>
                      <span>
                        <b><?= e($m['title']) ?></b>
                        <?php if ($m['description'] !== ''): ?><em><?= e($m['description']) ?></em><?php endif; ?>
                      </span>
                    </label>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php elseif ($methods): ?>
              <input type="hidden" name="payment" value="<?= e($methods[0]['id']) ?>">
              <p><?= e($methods[0]['description']) ?></p>
              <?php if ($methods[0]['instructions'] !== ''): ?>
                <p class="muted"><?= e($methods[0]['instructions']) ?></p>
              <?php endif; ?>
            <?php else: ?>
              <p>We will be in touch to arrange payment once the order is confirmed.</p>
            <?php endif; ?>
          </div>
        </div>

        <aside class="co-side">
          <h2>Your order</h2>
          <div class="co-empty" data-co-empty>
            <p>Your basket is empty.</p>
            <a class="btn btn-out" href="/shop/" style="width:100%;justify-content:center">Browse the catalogue</a>
          </div>
          <div data-co-summary hidden>
            <ul class="co-lines" data-co-lines></ul>
            <div class="row"><span>Subtotal</span><b data-co-subtotal>&pound;0.00</b></div>
            <div class="row disc" data-discount-row hidden>
              <span data-discount-label>Discount</span><b data-co-discount><?= e(money(0)) ?></b>
            </div>
            <div class="row"><span>Delivery</span><b data-co-ship>&mdash;</b></div>
            <?php if (tax_enabled()): ?>
              <div class="row"><span><?= e(tax_label()) ?> at <?= (int) tax_rate() ?>%</span><b data-co-vat><?= e(money(0)) ?></b></div>
            <?php endif; ?>
            <div class="row total"><span>Total</span><b data-co-total>&pound;0.00</b></div>
            <input type="hidden" name="coupon" value="<?= e((string) ($old['coupon'] ?? '')) ?>" data-coupon-field>
            <div class="hp" aria-hidden="true">
              <label for="co-website">Leave this field empty</label>
              <input id="co-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <?= turnstile_widget() ?>
            <?php if (($terms = (string) setting('terms_path')) !== ''): ?>
              <p class="hint">By placing this order you accept our
                <a href="<?= e($terms) ?>">terms and returns policy</a>.</p>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Place order</button>
            <a class="btn btn-out" href="/cart/" style="width:100%;justify-content:center;margin-top:10px">Back to cart</a>
          </div>
        </aside>
      </form>
    </div>
  </section>

<?php endif; ?>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
