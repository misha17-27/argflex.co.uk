<?php
/**
 * Checkout. The basket lives in the visitor's browser, so it is posted back
 * with the form and re-priced here from the catalogue — the browser never
 * gets to decide what anything costs.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/turnstile.php';
require_once ROOT_DIR . '/inc/mail.php';

$errors = [];
$done   = trim((string) ($_GET['ok'] ?? ''));
$old    = [];

/** Rebuild the order from the posted lines, using our own prices. */
function price_order(array $lines): array
{
    $items = [];
    foreach ($lines as $line) {
        $slug = (string) ($line['slug'] ?? '');
        $p    = find_product($slug);
        if (!$p) continue;

        $qty    = max(1, min(999, (int) ($line['qty'] ?? 1)));
        $option = (string) ($line['option'] ?? '');
        $price  = null;

        if ($p['variants']) {
            foreach ($p['variants'] as $v) {
                if ($v['label'] === $option) { $price = (int) $v['price']; break; }
            }
        } elseif ($p['price_min'] > 0) {
            $price = (int) $p['price_min'];
        }
        if ($price === null) continue;   // unknown option or price-on-request

        $items[] = [
            'slug'  => $p['slug'],
            'title' => $p['name'],
            'option' => $option,
            'qty'   => $qty,
            'price' => $price,
            'line'  => $price * $qty,
        ];
    }

    $subtotal = array_sum(array_column($items, 'line'));
    $freeOver = (int) setting('free_shipping');
    $flat     = (int) setting('shipping_flat');
    $shipping = ($subtotal >= $freeOver || $subtotal === 0) ? 0 : $flat;
    $vat      = (int) round(($subtotal + $shipping) * (setting('vat_rate') / 100));

    return [
        'items'    => $items,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'vat'      => $vat,
        'total'    => $subtotal + $shipping + $vat,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old   = $_POST;
    $lines = json_decode((string) ($_POST['cart'] ?? '[]'), true);
    $order = price_order(is_array($lines) ? $lines : []);

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
    if (!$order['items']) {
        $errors['cart'] = 'Your basket is empty, so there is nothing to order yet.';
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
                'country'  => trim((string) ($_POST['country'] ?? 'United Kingdom')),
                'notes'    => trim((string) ($_POST['notes'] ?? '')),
            ],
            'order' => $order,
        ];

        $dir = ROOT_DIR . '/storage/orders';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents("{$dir}/{$ref}.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // tell the shop about it; the order file is already written either way
        $lines = '';
        foreach ($order['items'] as $item) {
            $lines .= '  ' . $item['qty'] . ' x ' . $item['title']
                   . ($item['option'] !== '' ? ' (' . $item['option'] . ')' : '')
                   . ' — ' . money((int) $item['line']) . "
";
        }
        $c    = $record['customer'];
        $body = "New order {$ref}

{$lines}
"
              . 'Subtotal: ' . money((int) $order['subtotal']) . "
"
              . 'Delivery: ' . ($order['shipping'] ? money((int) $order['shipping']) : 'Free') . "
"
              . 'VAT:      ' . money((int) $order['vat']) . "
"
              . 'Total:    ' . money((int) $order['total']) . "

"
              . "Customer
"
              . "  {$c['name']}" . ($c['company'] !== '' ? " ({$c['company']})" : '') . "
"
              . "  {$c['email']}
  {$c['phone']}
"
              . "  {$c['address']}, {$c['city']}, {$c['postcode']}, {$c['country']}
"
              . ($c['notes'] !== '' ? "
Notes:
  {$c['notes']}
" : '');

        $mailError = '';
        if (!send_mail((string) setting('mail_to'), 'New order ' . $ref, $body, $c['email'], $mailError)) {
            @file_put_contents(ROOT_DIR . '/storage/mail-errors.log',
                date('c') . "  {$ref}  {$mailError}
", FILE_APPEND | LOCK_EX);
        }

        header('Location: /checkout/?ok=' . urlencode($ref));
        exit;
    }
}

set_page([
    'title'       => ($done !== '' ? 'Order received' : 'Checkout') . ' — ' . SITE_NAME,
    'description' => 'Complete your Arg Flex order. Prices exclude VAT; delivery is free on orders over £250.',
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
              <input id="co-country" name="country" type="text" autocomplete="country-name" value="<?= e($old['country'] ?? 'United Kingdom') ?>">
            </div>
            <div class="fld">
              <label for="co-notes">Order notes (optional)</label>
              <textarea id="co-notes" name="notes" rows="3" placeholder="Delivery instructions, required cut lengths, purchase order number…"><?= e($old['notes'] ?? '') ?></textarea>
            </div>
          </fieldset>

          <div class="co-box co-pay">
            <h3>Payment</h3>
            <p>We do not take card details here. Once we have confirmed stock and cut lengths we send a proforma invoice with bank transfer details, or a secure payment link if you prefer to pay by card.</p>
            <p class="muted">Trade accounts can pay on their usual terms — mention your account number in the notes above.</p>
          </div>
        </div>

        <aside class="co-side">
          <h3>Your order</h3>
          <div class="co-empty" data-co-empty>
            <p>Your basket is empty.</p>
            <a class="btn btn-out" href="/shop/" style="width:100%;justify-content:center">Browse the catalogue</a>
          </div>
          <div data-co-summary hidden>
            <ul class="co-lines" data-co-lines></ul>
            <div class="row"><span>Subtotal</span><b data-co-subtotal>&pound;0.00</b></div>
            <div class="row"><span>Delivery</span><b data-co-ship>&mdash;</b></div>
            <div class="row"><span>VAT at <?= (int) setting('vat_rate') ?>%</span><b data-co-vat>&pound;0.00</b></div>
            <div class="row total"><span>Total</span><b data-co-total>&pound;0.00</b></div>
            <div class="hp" aria-hidden="true">
              <label for="co-website">Leave this field empty</label>
              <input id="co-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <?= turnstile_widget() ?>
            <p class="hint">Free UK delivery on orders over <?= e(money((int) setting('free_shipping'))) ?> excl. VAT.</p>
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Place order</button>
            <a class="btn btn-out" href="/cart/" style="width:100%;justify-content:center;margin-top:10px">Back to cart</a>
          </div>
        </aside>
      </form>
    </div>
  </section>

<?php endif; ?>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
