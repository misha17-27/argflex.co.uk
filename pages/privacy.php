<?php
/**
 * Privacy — what the site collects and why.
 *
 * Written from what the code actually does, not from a template: the checkout
 * collects a name, an email, a telephone number and a delivery address, the
 * gateways see the payment, the basket lives in the browser, and nothing
 * reaches Google or Meta until the visitor has agreed. Every claim on this
 * page is one this site can be checked against.
 *
 * It exists because there was nothing: the checkout has been collecting names
 * and addresses with no privacy notice anywhere on the site, and the consent
 * banner offers a link that has to lead somewhere.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/tracking.php';   // tracking_wanted(), for the analytics section

set_page([
    'title'       => 'Privacy — ' . SITE_NAME,
    'description' => 'What ' . SITE_NAME . ' collects when you order or get in touch, who it is '
                   . 'shared with, how long it is kept and how to ask for a copy or its deletion.',
    'crumbs'      => [['label' => 'Privacy']],
    'robots'      => 'index, follow',
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap narrow">
    <span class="eyebrow">Privacy</span>
    <h1>Privacy</h1>
    <p>Last updated <?= date('j F Y') ?>. In short: we collect what is needed to send you
      hose and to answer you, we do not sell it, and you can ask us for a copy or to
      delete it.</p>
  </div>
</section>

<section style="padding-top:36px">
  <div class="wrap narrow">
    <div class="rich">
      <h2>Who we are</h2>
      <p><?= e(SITE_NAME) ?><?= ($cn = trim((string) setting('company_number'))) !== ''
          ? ', registered in England and Wales, company number ' . e($cn) : '' ?>.
        <?= e((string) setting('address')) ?>.
        Email <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>,
        telephone <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a>.
        We are the data controller for everything described here.</p>

      <h2>What we collect, and why</h2>
      <p><b>When you place an order</b> — your name, email address, telephone number,
        delivery address and any order notes you write. We need these to take the order,
        send the goods and talk to you about them. Without them there is no order.</p>
      <p><b>When you pay by card or PayPal</b> — the payment itself is handled by Stripe or
        PayPal. <b>We never see or store your card number.</b> What we keep is the
        gateway's own reference for the payment, the amount and the date, so the order can
        be matched to the money and refunded if it needs to be.</p>
      <p><b>When you contact us</b> — your name, email address, telephone number and the
        message, so we can reply.</p>
      <p><b>When you leave a review</b> — the name you give and what you wrote, which is
        published on the product page.</p>
      <p><b>If an account is opened for you</b> — ordering can open an account so you can
        see the order later and the checkout can fill your details in next time. It holds
        the same details as the order.</p>

      <h2>What is in your browser rather than ours</h2>
      <p>Your <b>basket</b> is kept in your own browser, not on our server, until you place
        the order. So are your wishlist and comparison list. Clearing your browser's data
        clears them and we never see them.</p>
      <p>We set a small number of cookies that the site cannot work without: one that
        proves a form came from this site, and one that lets you see the order you have
        just placed. These need no consent because without them the checkout does not
        function.</p>

      <h2>Analytics, and your choice</h2>
      <?php if (tracking_wanted()): ?>
        <p>We would like to use <?= trim((string) setting('ga4_id')) !== '' ? 'Google Analytics' : '' ?><?=
             trim((string) setting('ga4_id')) !== '' && trim((string) setting('meta_pixel_id')) !== '' ? ' and ' : '' ?><?=
             trim((string) setting('meta_pixel_id')) !== '' ? 'the Meta pixel' : '' ?> to see which
          pages and products people actually use.</p>
        <p><b>Nothing is loaded until you agree.</b> If you decline, or simply never answer,
          the analytics scripts are never fetched at all — not fetched with tracking
          switched off, not fetched. You can check that yourself in your browser's network
          panel. Your choice is remembered in your browser and you can change it at any
          time.</p>
      <?php else: ?>
        <p>We run no analytics and no advertising trackers on this site at all.</p>
      <?php endif; ?>

      <h2>Who else sees it</h2>
      <ul>
        <li><b>The courier</b>, so the parcel can be delivered — your name, address and
          telephone number.</li>
        <li><b>Stripe and PayPal</b>, for payments you make through them.</li>
        <li><b>Our email provider</b>, to deliver the messages we send you.</li>
        <li><b>Our hosting provider</b>, which holds the site and its files.</li>
      </ul>
      <p>We do not sell your details and we do not pass them to anybody for their own
        marketing.</p>

      <h2>How long we keep it</h2>
      <p>Orders and invoices are kept for <b>six years</b> after the end of the tax year
        they fall in, which is what HMRC requires of us. Enquiries are kept while they are
        useful to answer and then deleted. Published reviews stay until you ask us to
        remove yours.</p>

      <h2>What you can ask for</h2>
      <p>You can ask us for a copy of what we hold about you, for it to be corrected, or
        for it to be deleted where we are not required to keep it. You can object to how we
        use it and ask us to restrict that use. Write to
        <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a> and we will answer within
        one month.</p>
      <p>If you are not satisfied with our answer you can complain to the Information
        Commissioner's Office at
        <a href="https://ico.org.uk/make-a-complaint/" rel="noopener" target="_blank">ico.org.uk</a>.</p>
    </div>
  </div>
</section>
