<?php
/**
 * Terms — the conditions an order is taken on.
 *
 * Every clause here describes something the shop actually does and the code
 * can be checked against: prices quoted excluding tax, hose cut to order,
 * delivery to the United Kingdom only, payment taken by Stripe or PayPal or
 * against a proforma invoice, stock confirmed before dispatch.
 */
declare(strict_types=1);

set_page([
    'title'       => 'Terms and conditions — ' . SITE_NAME,
    'description' => 'The conditions ' . SITE_NAME . ' sells on: prices and ' . tax_label()
                   . ', cut lengths, payment, delivery within the UK, and what happens if '
                   . 'something is out of stock.',
    'crumbs'      => [['label' => 'Terms and conditions']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap narrow">
    <span class="eyebrow">Terms</span>
    <h1>Terms and conditions</h1>
    <p>Last updated <?= date('j F Y') ?>. These are the conditions we sell on. Nothing here
      takes away the rights you have as a consumer.</p>
  </div>
</section>

<section style="padding-top:36px">
  <div class="wrap narrow">
    <div class="rich">
      <h2>Who you are buying from</h2>
      <p><?= e(SITE_NAME) ?><?= ($cn = trim((string) setting('company_number'))) !== ''
          ? ', registered in England and Wales, company number ' . e($cn) : '' ?>.
        <?= e((string) setting('address')) ?>.
        <?php if (($vat = trim((string) setting('vat_number'))) !== ''): ?>
          <?= e(tax_label()) ?> number <?= e($vat) ?>.
        <?php endif; ?>
        Email <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>,
        telephone <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a>.</p>

      <h2>Prices</h2>
      <p>Hose is priced <b>per metre</b>. Catalogue prices are shown
        <?= e(price_suffix() !== '' ? price_suffix() : 'excluding ' . tax_label()) ?>;
        <?= e(tax_label()) ?> is added at the checkout, where you see the total you will pay
        before you pay it. <?= e(tax_label()) ?> is not charged on delivery.</p>
      <p>We may change prices at any time, but never on an order already placed.</p>

      <h2>Cut lengths</h2>
      <p>Most of what we sell is cut from a coil to the length you choose. That makes it
        made to your order: <b>hose cut to length cannot be returned</b> because it is not
        in a condition anybody else can buy, unless it is faulty or not what you ordered —
        and then of course it can. Full, uncut coils in their original packaging are
        covered by our <a href="/refund_returns/">returns policy</a> in the ordinary way.</p>

      <h2>Your order</h2>
      <p>Placing an order is an offer to buy. We accept it when we confirm the order by
        email. We check stock and cut lengths before dispatch, and if something cannot be
        supplied we will tell you and refund it rather than send a short order.</p>
      <p>Where a listing shows "price on request", no price has been agreed until we quote
        one and you accept it.</p>

      <h2>Paying</h2>
      <p>You can pay by card or through PayPal at the checkout, or ask for a proforma
        invoice and pay by transfer — in which case the goods are dispatched once the
        payment has cleared. Card payments are handled by Stripe and PayPal payments by
        PayPal; we never see your card number.</p>

      <h2>Delivery</h2>
      <p>We deliver within the <b>United Kingdom only</b>. Carriage is worked out on the
        metres in your basket and shown before you pay. Full details, including the bands
        and the same-day cut-off, are on the <a href="/delivery/">delivery page</a>.</p>
      <p>Delivery dates are the courier's estimate and not a guarantee. Risk in the goods
        passes to you when they are delivered.</p>

      <h2>Fitness for purpose</h2>
      <p>Bore size, working pressure, temperature range and the standards each hose is made
        to are stated on its page. <b>Choosing the right hose for your application is
        yours</b>, and we are glad to help: tell us the medium, the bore and the working
        pressure and we will say what we think fits. That advice is given in good faith and
        does not replace your own assessment, particularly where fuel, gas or chemicals are
        involved.</p>

      <h2>If something goes wrong</h2>
      <p>Call <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a> or email
        <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a> with your order reference.
        Our <a href="/refund_returns/">refunds and returns policy</a> sets out how returns
        work, and your statutory rights are unaffected by anything on this page.</p>

      <h2>Law</h2>
      <p>These terms are governed by the law of England and Wales.</p>
    </div>
  </div>
</section>
