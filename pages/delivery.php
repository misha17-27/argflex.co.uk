<?php
/**
 * Delivery — what it costs and when it arrives.
 *
 * Built from the LIVE shipping configuration rather than written out, because
 * a page of prices typed by hand is a page that disagrees with the checkout
 * the first time a rate changes. Everything here comes from shipping_all_rates()
 * and shipping_free(), which is what the basket is quoted from.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/shipping.php';   // the rates this page is built from

$rates = shipping_all_rates();
$free  = shipping_free();

$bands = [
    'Up to 5 metres'  => [11, 14],
    '5 to 10 metres'  => [17, 18],
    '10 to 25 metres' => [12, 15],
    '25 to 50 metres' => [13, 16],
];

set_page([
    'title'       => 'Delivery — ' . SITE_NAME,
    'description' => 'UK delivery on every hose, priced on the metres in your basket rather than the '
                   . 'order value. Cut lengths are prepared to order. ' . tax_label() . ' is not charged on delivery.',
    'crumbs'      => [['label' => 'Delivery']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap narrow">
    <span class="eyebrow">Delivery</span>
    <h1>Delivery</h1>
    <p>We deliver within the United Kingdom only. Carriage is worked out on the
      <b>metres in your basket</b>, not on what the order is worth — a coil takes more
      room than an offcut whatever it costs.</p>
  </div>
</section>

<section style="padding-top:36px">
  <div class="wrap narrow">
    <div class="rich">
      <h2>What it costs</h2>
      <p>Prices exclude <?= e(tax_label()) ?>. <b><?= e(tax_label()) ?> is not charged on delivery.</b></p>

      <div class="table-scroll">
        <table class="grid">
          <thead>
            <tr><th>Length in the basket</th><th>1–2 days</th><th>3–4 days</th></tr>
          </thead>
          <tbody>
            <?php foreach ($bands as $band => [$fast, $slow]): ?>
              <?php if (!isset($rates[$fast]) && !isset($rates[$slow])) continue; ?>
              <tr>
                <td><b><?= e($band) ?></b></td>
                <td><?= isset($rates[$fast]) ? e(money((int) $rates[$fast]['cost'])) : '—' ?></td>
                <td><?= isset($rates[$slow]) ? e(money((int) $rates[$slow]['cost'])) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (!empty($free['on'])): ?>
        <p><b><?= e($free['title']) ?></b>
          <?php if ((int) $free['min_goods'] > 0): ?>
            on orders of <?= e(money((int) $free['min_goods'])) ?> or more, before
            <?= e(tax_label()) ?> and before delivery.
          <?php else: ?>
            on every order.
          <?php endif; ?>
          It is offered at the checkout alongside the speeds above.</p>
      <?php endif; ?>

      <p>A few of the heavier lines — ducting and the widest bores — carry their own
        carriage price, which the basket shows you before you pay. Nothing is added
        afterwards.</p>

      <h2>When it arrives</h2>
      <p>Stocked lines ordered before <b>14:00</b> on a working day are picked and packed the
        same day. The two speeds above are the courier's working days after that, not
        counting weekends or bank holidays.</p>
      <p><b>Cut lengths are prepared to order.</b> Hose is cut from the coil for you, so a
        cut order can take an extra working day before it leaves us — we will tell you if
        it will.</p>

      <h2>More than one parcel</h2>
      <p>A large order can be sent as two consignments, each charged on its own. You are
        shown one delivery figure at the checkout and the breakdown appears on your order.
        Where a basket splits, a free delivery threshold is measured against the
        <b>whole order</b>, not each parcel — you spent the money once.</p>

      <h2>Where we deliver</h2>
      <p>The United Kingdom. Anywhere else has no rate at all and the checkout says so
        rather than taking an order nobody can fulfil. If you are outside the UK and want
        a quotation, <a href="/contacts/">ask us</a> — we can price carriage by hand.</p>

      <h2>Something wrong with it</h2>
      <p>If a parcel arrives damaged or the wrong length, call
        <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a> or email
        <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a> with your order reference and
        we will put it right. See also our
        <a href="/refund_returns/">refunds and returns policy</a>.</p>
    </div>
  </div>
</section>
