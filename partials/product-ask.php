<?php
/**
 * The enquiry form, on the product page itself.
 *
 * WHY IT IS HERE. The submersible SAE J30 R10 hose draws 79% of everything
 * this shop earns from search — 49 clicks in 28 days on one URL — and it is
 * out of stock. Until now that visit ended at a line of text and a link to
 * another page. A visitor who has already found the right hose should not have
 * to go and find a form; the ask belongs where the answer isn't.
 *
 * The same applies to the eight products sold on quotation rather than at a
 * price. Nine pages in total, all of them reachable from search, none of them
 * able to take an order.
 *
 * Posts to the same handler as the contacts page, so the form token, the rate
 * limit, the honeypot, Turnstile and the spam scorer all apply unchanged, and
 * the enquiry lands in the same admin list.
 *
 * @var array $p the product being asked about
 */

/* Required here, not assumed. The product page does not load either of these
   for itself, and this partial fatals without them — which is how it first
   shipped in a local test. */
require_once ROOT_DIR . '/inc/security.php';   // form_field()
require_once ROOT_DIR . '/inc/turnstile.php';  // turnstile_widget()

$askSent   = isset($_GET['sent']);
$askFailed = (string) ($_GET['error'] ?? '');
$askWhy    = product_in_stock($p)
    ? 'This one is priced to order.'
    : 'This one is out of stock at the moment.';
?>
<section class="p-ask" id="form">
  <div class="wrap">
    <form class="c-form" method="post" action="/contact-send/" novalidate>
      <?= form_field('enquiry') ?>
      <input type="hidden" name="product" value="<?= e($p['slug']) ?>">
      <input type="hidden" name="from" value="product">

      <h2>Ask about this hose</h2>

      <?php if ($askSent): ?>
        <p class="c-ok">Thank you — your enquiry is with us and we will reply within one
          working day, with a price and a lead time for this hose.</p>
      <?php else: ?>
        <?php if ($askFailed === 'fields'): ?>
          <p class="c-bad">Please give us your name, a valid email address and a message.</p>
        <?php elseif ($askFailed === 'captcha'): ?>
          <p class="c-bad">The anti-spam check did not pass. Please try once more.</p>
        <?php elseif ($askFailed === 'stale'): ?>
          <p class="c-bad">This page had been open a while, so we could not tell the message
            came from us. Reload it and send once more, or call <?= SITE_PHONE ?>.</p>
        <?php elseif ($askFailed === 'toomany'): ?>
          <p class="c-bad">That is several enquiries in a short time. Give it a few minutes,
            or call us on <?= SITE_PHONE ?> — we would rather talk anyway.</p>
        <?php endif; ?>

        <p class="c-about"><?= e($askWhy) ?> Tell us the length and the bore you need and we
          will come back with a price and when we can have it with you.</p>

        <div class="two">
          <div class="fld"><label for="ask-name">Your name</label>
            <input id="ask-name" name="name" type="text" placeholder="John Smith" required></div>
          <div class="fld"><label for="ask-phone">Phone number</label>
            <input id="ask-phone" name="phone" type="tel" placeholder="+44 …"></div>
        </div>
        <div class="fld"><label for="ask-email">Your email</label>
          <input id="ask-email" name="email" type="email" placeholder="you@company.co.uk" required></div>
        <div class="fld">
          <label for="ask-msg">What do you need?</label>
          <textarea id="ask-msg" name="message" rows="4"
            placeholder="e.g. 25 m, 16 mm bore, and when could you deliver?"><?= e('I would like a price and a lead time for ' . $p['name'] . '.') ?></textarea>
        </div>
        <div class="hp" aria-hidden="true">
          <label for="ask-website">Leave this field empty</label>
          <input id="ask-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <?= turnstile_widget() ?>
        <button class="btn btn-primary" type="submit"
                style="width:100%;justify-content:center">Send the enquiry</button>
        <p class="c-note">We reply to technical enquiries within one working day. Your details
          are used only to answer this enquiry.</p>
      <?php endif; ?>
    </form>
  </div>
</section>
