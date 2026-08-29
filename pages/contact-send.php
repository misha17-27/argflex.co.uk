<?php
/**
 * Handles the enquiry form. Saves the message so it can never be lost to a
 * mail problem, then tries to send it on. The visitor is redirected either
 * way — a failed send is the shop's problem, not theirs.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/store.php';      // add_submission()
require_once ROOT_DIR . '/inc/turnstile.php';
require_once ROOT_DIR . '/inc/security.php';   // the form token and the counters
require_once ROOT_DIR . '/inc/mail.php';

$back = '/contacts/';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $back);
    exit;
}

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$product = trim((string) ($_POST['product'] ?? ''));
$trap    = trim((string) ($_POST['website'] ?? ''));   // honeypot, hidden from people

$query = $product !== '' ? '?product=' . urlencode($product) . '&' : '?';

/* The honeypot and the captcha stop a robot filling this in. Neither stops
   another site posting it with a real person's cookies attached, which is
   what the token is for. */
if (!form_token_ok('enquiry')) {
    header('Location: ' . $back . $query . 'error=stale#form');
    exit;
}

/* Nobody sends nine enquiries in an hour. A form that mails the shop on every
   POST is otherwise a way to fill an inbox, one request at a time. */
if (rate_limited('enquiry', 8, 3600)) {
    header('Location: ' . $back . $query . 'error=toomany#form');
    exit;
}

// a filled honeypot is a bot; accept it silently so it learns nothing
if ($trap !== '') {
    header('Location: ' . $back . $query . 'sent=1#form');
    exit;
}

if ($name === '' || $message === '' || !usable_email($email)) {
    header('Location: ' . $back . $query . 'error=fields#form');
    exit;
}

rate_hit('enquiry');

if (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
    header('Location: ' . $back . $query . 'error=captcha#form');
    exit;
}

/* Capped before anything is stored or sent. The name and phone end up in a
   mail header, where a newline would start a header of its own — that is how
   a contact form becomes somebody else's relay. */
$name    = header_safe($name, 80);
$phone   = header_safe($phone, 40);
$message = clip($message, 5000);

$id = add_submission([
    'source'  => $product !== '' ? 'product' : 'contact',
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'message' => $message,
    'product' => $product,
]);

$productLine = '';
if ($product !== '' && ($p = find_product($product))) {
    $productLine = $p['name'] . ' - ' . SITE_URL . product_url($p);
}

// the enquiry is already stored, so a mail failure is logged rather than shown
send_enquiry_emails([
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'message' => $message,
    'product' => $productLine,
]);

header('Location: ' . $back . $query . 'sent=1#form');
exit;
