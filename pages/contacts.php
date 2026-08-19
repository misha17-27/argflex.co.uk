<?php
declare(strict_types=1);

$about = trim((string) ($_GET['product'] ?? ''));
$prod  = $about !== '' ? find_product($about) : null;

set_page([
    'title'       => 'Contacts — ' . SITE_NAME,
    'description' => 'Contact Arg Flex Ltd: ' . SITE_PHONE . ', ' . SITE_EMAIL . '. ' . SITE_ADDR . '. We answer technical enquiries the same working day.',
    'crumbs'      => [['label' => 'Contacts']],
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow"><?= page_text('/contacts/', 'eyebrow', 'Get in touch') ?></span>
    <h1><?= page_text('/contacts/', 'title', 'We would love to speak with you') ?></h1>
    <p><?= page_text('/contacts/', 'intro', 'Send the medium, the bore size and the working pressure and we will confirm the right hose, the coupling to match it and a price — usually the same working day.') ?></p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <div class="contact-grid">
      <div class="c-cards">
        <a class="c-card" href="tel:<?= SITE_PHONE_HREF ?>">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 16.5v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.7 2 2 0 0 1 3.1 1.5h3a2 2 0 0 1 2 1.7c.1 1 .35 1.9.7 2.8a2 2 0 0 1-.45 2.1L7.1 9.4a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.35 1.85.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
          <b>Phone</b><span><?= SITE_PHONE ?></span>
        </a>
        <a class="c-card" href="mailto:<?= SITE_EMAIL ?>">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
          <b>Email</b><span><?= SITE_EMAIL ?></span>
        </a>
        <div class="c-card">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
          <b>Location</b><span><?= SITE_ADDR ?></span>
        </div>
        <div class="c-card">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg>
          <b>Hours</b><span><?= SITE_HOURS_WEEK ?><br><?= SITE_HOURS_WEEKEND ?></span>
        </div>
      </div>

      <form class="c-form" method="post" action="/contacts/" novalidate>
        <h2><?= page_text('/contacts/', 'form_title', 'Fill out the form and we will contact you') ?></h2>
        <?php if ($prod): ?>
          <p class="c-about">Enquiry about: <b><?= e($prod['name']) ?></b> <a href="/contacts/">clear</a></p>
          <input type="hidden" name="product" value="<?= e($prod['slug']) ?>">
        <?php endif; ?>
        <div class="two">
          <div class="fld"><label for="name">Your name</label><input id="name" name="name" type="text" placeholder="John Smith" required></div>
          <div class="fld"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" placeholder="+44 …"></div>
        </div>
        <div class="fld"><label for="email">Your email</label><input id="email" name="email" type="email" placeholder="you@company.co.uk" required></div>
        <div class="fld">
          <label for="msg">Message</label>
          <textarea id="msg" name="message" rows="5" placeholder="e.g. 25 m of 16 mm fuel hose, SAE J30 R6, plus clamps"><?= $prod ? e('I would like a price for ' . $prod['name'] . '.') : '' ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center"><?= page_text('/contacts/', 'form_btn', 'Send') ?></button>
        <p class="c-note"><?= page_text('/contacts/', 'form_note', 'We reply to technical enquiries within one working day. Your details are used only to answer this enquiry.') ?></p>
      </form>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <?php $mapQuery = rawurlencode('107 George Lane, South Woodford, London, E18 1AN'); ?>
    <div class="map-box">
      <iframe
        title="<?= SITE_NAME ?> on Google Maps"
        src="https://www.google.com/maps?q=<?= $mapQuery ?>&amp;z=16&amp;hl=en&amp;output=embed"
        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        allowfullscreen></iframe>
      <a class="map-link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= $mapQuery ?>" target="_blank" rel="noopener">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
        Open in Google Maps
      </a>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
