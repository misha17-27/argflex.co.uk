<?php
/**
 * Takes a review from the product page.
 *
 * Stored first, moderated after — same principle as the enquiry form, so a
 * mail or spam-check wobble can never lose what somebody wrote. The visitor
 * always ends up back on the product with a message.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/store.php';
require_once ROOT_DIR . '/inc/turnstile.php';
require_once ROOT_DIR . '/inc/mail.php';

$slug    = trim((string) ($_POST['product'] ?? ''));
$product = find_product($slug);
$back    = $product ? product_url($product) : '/shop/';

/** Send them back to the reviews with a word about how it went. */
function finish(string $back, string $state): never
{
    header('Location: ' . $back . '?review=' . urlencode($state) . '#reviews');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$product || !reviews_enabled()) {
    finish($back, 'closed');
}

$author = trim((string) ($_POST['author'] ?? ''));
$email  = trim((string) ($_POST['email'] ?? ''));
$body   = trim((string) ($_POST['body'] ?? ''));
$rating = max(1, min(5, (int) ($_POST['rating'] ?? 0)));

// the honeypot is hidden from people; anything in it came from a machine
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    finish($back, 'thanks');            // say nothing useful to a bot
}

if ($author === '' || $body === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish($back, 'incomplete');
}
if (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
    finish($back, 'captcha');
}

$bought = has_bought($email, $product['slug']);
if (setting('review_verified') && !$bought) {
    finish($back, 'unverified');
}

$id = add_review([
    'product'  => $product['slug'],
    'author'   => clip($author, 60),
    'email'    => $email,
    'rating'   => $rating,
    'body'     => clip($body, 2000),
    'verified' => $bought,
]);

// tell the shop there is something to moderate, if it wants to know
if (setting('review_approval')) {
    mail_notify('review', (string) setting('mail_to'),
        ['site' => SITE_NAME, 'name' => $author, 'product' => $product['name']],
        '<p style="margin:0 0 12px"><b>' . e($author) . '</b> left '
      . (int) $rating . ' star' . ($rating === 1 ? '' : 's') . ' on '
      . '<a href="' . e(SITE_URL . product_url($product)) . '">' . e($product['name']) . '</a>'
      . ($bought ? ' — they have ordered it.' : '.') . '</p>'
      . '<p style="margin:0 0 16px;color:#5b6880">' . nl2br(e($body)) . '</p>'
      . '<p style="margin:0"><a href="' . e(SITE_URL . '/admin/reviews') . '">Approve or bin it</a></p>',
        $email);
}

finish($back, setting('review_approval') ? 'pending' : 'thanks');
