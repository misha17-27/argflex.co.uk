<?php
/**
 * The chat widget's one request: a name, a telephone number and what they are
 * after, saved here and answered in WhatsApp.
 *
 * SAVED BEFORE THE HANDOVER, deliberately. The widget ends by sending the
 * visitor to wa.me with the message already written, and a good share of
 * people never press send once they get there — the app opens, the phone
 * rings, the moment passes. If the lead only existed in that unsent message
 * it would be gone. It is stored first; WhatsApp is the convenience on top.
 *
 * Answers JSON because the widget never leaves the page.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/store.php';      // add_submission()
require_once ROOT_DIR . '/inc/security.php';   // the token, the counters, the scorer
require_once ROOT_DIR . '/inc/commerce.php';   // whatsapp_link()
require_once ROOT_DIR . '/inc/mail.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

/** Every exit goes through here, so the widget always gets JSON to read. */
function chat_say(array $body, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    chat_say(['ok' => false, 'error' => 'post only'], 405);
}

/* Without the token any page anywhere could post these with a visitor's
   cookies attached — the same reason the enquiry form carries one. */
if (!form_token_ok('chat')) {
    chat_say(['ok' => false, 'say' => 'This page had been open a while. Reload it and try again.'], 419);
}

/* Nobody asks six times in an hour. A widget on every page of the shop is
   otherwise an open pipe into the enquiry list. */
if (rate_limited('chat', 6, 3600)) {
    chat_say(['ok' => false, 'say' => 'That is several messages in a short time. Give it a few minutes, or call ' . SITE_PHONE . '.'], 429);
}

// hidden from people; anything in it came from a machine
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    chat_say(['ok' => true, 'wa' => '', 'say' => 'Thank you.']);   // tell a bot nothing
}

$product = header_safe(trim((string) ($_POST['product'] ?? '')), 120);
$name    = header_safe(trim((string) ($_POST['name'] ?? '')), 80);
$phone   = header_safe(trim((string) ($_POST['phone'] ?? '')), 40);

if ($name === '') {
    chat_say(['ok' => false, 'say' => 'Please tell us your name first.']);
}

/* Enough digits to be a number somebody could ring. Deliberately loose about
   the rest: +44, 0044, spaces, brackets and dashes are all how people write
   their own number, and refusing a real one to enforce a format loses a
   customer over punctuation. */
$digits = preg_replace('/\D+/', '', $phone) ?? '';
if (strlen($digits) < 7 || strlen($digits) > 15) {
    chat_say(['ok' => false, 'say' => 'That does not look like a phone number — could you check it?']);
}

rate_hit('chat');

/* The same scorer the enquiry form uses. There is no free prose here beyond
   the product line, so this almost never fires — but the widget is on every
   page and a form that is everywhere gets found. */
$judged = spam_score($name, '', $phone, $product);
$isSpam = $judged['score'] >= 4;

$message = 'Asked in the chat about: ' . ($product !== '' ? $product : '(not said)');

add_submission([
    'source'   => 'chat',
    'name'     => $name,
    'email'    => '',
    'phone'    => $phone,
    'message'  => $message,
    'product'  => '',
    'spam'     => $isSpam,
    'spam_why' => $isSpam ? implode('; ', $judged['why']) : '',
    'is_read'  => $isSpam,
]);

/* The visitor is handed on either way — telling a robot it was caught is how
   it learns to write the next one — but a held message is not mailed on. */
if (!$isSpam) {
    send_enquiry_emails([
        'name'    => $name,
        'email'   => '',
        'phone'   => $phone,
        'message' => $message,
        'product' => '',
    ]);
}

$wa = whatsapp_link(
    'Hello Arg Flex. I am ' . $name . '.'
    . ($product !== '' ? ' I am looking for: ' . $product . '.' : '')
    . ' You can reach me on ' . $phone . '.'
);

chat_say(['ok' => true, 'wa' => $wa]);
