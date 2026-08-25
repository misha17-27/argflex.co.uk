<?php
/**
 * The reviews panel on a product page: the summary, what people wrote, and
 * the form to add one.
 *
 * @var array $p  the product being viewed
 */
$summary = rating_summary($p['slug']);
$rows    = product_reviews($p['slug']);
$said    = (string) ($_GET['review'] ?? '');

$notes = [
    'thanks'     => ['ok',  'Thank you — your review is on the page.'],
    'pending'    => ['ok',  'Thank you. We read every review before it appears, so give us a day.'],
    'incomplete' => ['bad', 'Please add your name, a real email address and a few words.'],
    'captcha'    => ['bad', 'The anti-spam check did not pass. Please try once more.'],
    'unverified' => ['bad', 'Reviews are open to customers who have ordered this — use the email address on your order.'],
    'closed'     => ['bad', 'Reviews are closed at the moment.'],
];
?>

<div class="rv" id="reviews">

  <?php if (isset($notes[$said])): ?>
    <p class="rv-note <?= e($notes[$said][0]) ?>"><?= e($notes[$said][1]) ?></p>
  <?php endif; ?>

  <?php if ($summary): ?>
    <div class="rv-top">
      <div class="rv-score">
        <b><?= e(rtrim(rtrim(number_format($summary['average'], 1), '0'), '.')) ?></b>
        <?= stars($summary['average'], 18) ?>
        <span><?= (int) $summary['count'] ?> review<?= $summary['count'] === 1 ? '' : 's' ?></span>
      </div>
      <div class="rv-spread">
        <?php foreach ($summary['spread'] as $starCount => $howMany): ?>
          <div class="rv-bar">
            <span><?= (int) $starCount ?>★</span>
            <i><b style="width:<?= $summary['count'] ? round($howMany / $summary['count'] * 100) : 0 ?>%"></b></i>
            <em><?= (int) $howMany ?></em>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <ul class="rv-list">
      <?php foreach ($rows as $r): ?>
        <li>
          <div class="rv-head">
            <?= stars((float) $r['rating']) ?>
            <b><?= e($r['author']) ?></b>
            <?php if (!empty($r['verified'])): ?>
              <span class="rv-verified" title="This customer ordered it">Verified purchase</span>
            <?php endif; ?>
            <time datetime="<?= e($r['created']) ?>"><?= e(date('j M Y', strtotime($r['created']))) ?></time>
          </div>
          <p><?= nl2br(e($r['body'])) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="rv-none"><?= page_text('/product/', 'reviews_none',
        'No reviews yet. If you have used this, we would like to hear how it went.') ?></p>
  <?php endif; ?>

  <form class="rv-form" method="post" action="/review-send" novalidate>
    <h3><?= $summary ? 'Add your review' : 'Be the first to review this' ?></h3>
    <input type="hidden" name="product" value="<?= e($p['slug']) ?>">

    <div class="rv-stars">
      <span class="rv-label">Your rating</span>
      <div class="rv-pick" data-rating>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" id="rv-<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
          <label for="rv-<?= $i ?>" title="<?= $i ?> out of 5">
            <svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5-5.9-3.1-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z"/>
            </svg>
            <span class="sr"><?= $i ?> out of 5</span>
          </label>
        <?php endfor; ?>
      </div>
    </div>

    <div class="two">
      <div class="fld">
        <label for="rv-author">Your name *</label>
        <input id="rv-author" name="author" type="text" maxlength="60" autocomplete="name" required>
      </div>
      <div class="fld">
        <label for="rv-email">Email *</label>
        <input id="rv-email" name="email" type="email" autocomplete="email" required>
        <p class="fld-note">Never published<?= setting('review_verified')
            ? ' — we check it against your order' : '' ?>.</p>
      </div>
    </div>

    <div class="fld">
      <label for="rv-body">Your review *</label>
      <textarea id="rv-body" name="body" rows="4" maxlength="2000" required
                placeholder="How did it hold up? What did you use it for?"></textarea>
    </div>

    <div class="hp" aria-hidden="true">
      <label for="rv-website">Leave this field empty</label>
      <input id="rv-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <?= turnstile_widget() ?>
    <button class="btn btn-primary" type="submit">Submit review</button>
  </form>
</div>
