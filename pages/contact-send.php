<?php
/**
 * Handles the enquiry form. Saves the message so it can never be lost to a
 * mail problem, then tries to send it on. The visitor is redirected either
 * way — a failed send is the shop's problem, not theirs.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/store.php';      // add_submission()
require_once ROOT_DIR . '/inc/turnstile.php';
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

// a filled honeypot is a bot; accept it silently so it learns nothing
if ($trap !== '') {
    header('Location: ' . $back . $query . 'sent=1#form');
    exit;
}

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $back . $query . 'error=fields#form');
    exit;
}

if (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
    header('Location: ' . $back . $query . 'error=captcha#form');
    exit;
}

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
    $productLine = "About: {$p['name']}\nURL: " . SITE_URL . product_url($p) . "\n";
}

$body = "New enquiry from the website\n\n"
      . "Name:    {$name}\n"
      . "Email:   {$email}\n"
      . "Phone:   " . ($phone !== '' ? $phone : '—') . "\n"
      . $productLine
      . "\nMessage:\n{$message}\n\n"
      . "Reference: {$id}\n"
      . "Received:  " . date('j M Y H:i') . "\n";

$error = '';
if (!send_mail((string) setting('mail_to'), 'Website enquiry — ' . $name, $body, $email, $error)) {
    // the enquiry is already stored, so only note why the mail failed
    @file_put_contents(ROOT_DIR . '/storage/mail-errors.log',
        date('c') . "  {$id}  {$error}\n", FILE_APPEND | LOCK_EX);
}

header('Location: ' . $back . $query . 'sent=1#form');
exit;
