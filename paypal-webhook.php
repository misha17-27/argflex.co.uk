<?php
/**
 * PayPal telling us it captured a payment.
 *
 * Worth being precise about why this exists, because it is NOT the reason the
 * WooCommerce plugin on the old site has one.
 *
 * We create the PayPal order with intent CAPTURE and take the money ourselves,
 * from the server, in payment.php. A customer who approves in PayPal and then
 * closes the tab leaves an approved-but-uncaptured order: it expires and
 * nothing is taken. That case needs no webhook and never did.
 *
 * The case that does: our capture call SUCCEEDS at PayPal and we lose the
 * answer — a timeout, a dropped connection, the process restarted between
 * PayPal taking the money and place_order() being reached. The money is gone,
 * the basket is still frozen in storage/pending, and no order exists. Rare,
 * and the worst kind of rare: the customer has paid and the shop cannot see
 * it. PayPal sends PAYMENT.CAPTURE.COMPLETED regardless, and this turns it
 * into the order that should have been written.
 *
 * Every request is verified with PayPal before it is believed. Their scheme
 * is not a local HMAC — the signature is over a certificate chain and the
 * supported check is to hand it back to PayPal — so verification is a round
 * trip, and a shop with no webhook id configured cannot verify and therefore
 * refuses everything. Without that, this address would let anyone on the
 * internet conjure paid orders.
 *
 * In PayPal: Developer dashboard → your app → Webhooks → add this URL and
 * subscribe to PAYMENT.CAPTURE.COMPLETED. Paste the webhook ID it gives back
 * into Settings → Payments.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once ROOT_DIR . '/inc/mail.php';
require_once ROOT_DIR . '/inc/store.php';
require_once ROOT_DIR . '/inc/gateways.php';    // verification and the webhook id
require_once ROOT_DIR . '/inc/pending.php';     // the frozen basket

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

/** Stop, saying why, without telling a caller more than they should know. */
function done(int $status, string $note): never
{
    http_response_code($status);
    exit($note);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') done(405, 'post only');

if (paypal_webhook_id() === '') done(503, 'no webhook id configured');

$raw = (string) file_get_contents('php://input');
if ($raw === '' || strlen($raw) > 200000) done(400, 'unreadable');

/* PHP hands headers over as HTTP_PAYPAL_TRANSMISSION_ID and friends; PayPal
   names them with dashes. Normalised here so inc/gateways.php can ask for the
   name PayPal documents. */
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
    }
}

if (!paypal_verify_webhook($headers, $raw)) done(400, 'not verified');

$event = json_decode($raw, true);
if (!is_array($event)) done(400, 'unreadable');

/* Anything else is acknowledged and ignored. Refusing it would make PayPal
   retry a message we were never going to act on, for days. */
if (($event['event_type'] ?? '') !== 'PAYMENT.CAPTURE.COMPLETED') done(200, 'ignored');

$resource = (array) ($event['resource'] ?? []);

/* custom_id is our own order reference, put there by paypal_create_order().
   Nothing else in the message is ours, so nothing else is trusted to identify
   the basket. */
$reference = trim((string) ($resource['custom_id'] ?? ''));
if ($reference === '') done(200, 'no reference');

if (find_order($reference)) done(200, 'already recorded');

$frozen = pending_read($reference);
if (!$frozen) done(200, 'nothing pending');
if (($frozen['method'] ?? '') !== 'ppcp') done(200, 'not a paypal basket');

/* Verification proves PayPal sent this. It does not prove the figure is the
   one we asked for, so that is still checked — against the basket frozen at
   the moment the payment started, not against today's prices. */
$paid = (int) round(((float) ($resource['amount']['value'] ?? 0)) * 100);
if ($paid !== (int) $frozen['order']['total']) done(200, 'amount does not match');

$currency = strtoupper((string) ($resource['amount']['currency_code'] ?? ''));
if ($currency !== '' && $currency !== strtoupper((string) setting('currency'))) {
    done(200, 'currency does not match');
}

$claimed = pending_claim($reference);
if (!$claimed) done(200, 'somebody else got there first');

$payment = find_payment_method('ppcp') ?? ['id' => 'ppcp', 'title' => 'PayPal'];
$label   = paypal_payer_label((array) ($resource['payer'] ?? []));

place_order([
    'reference' => $reference,
    'placed_at' => date('c'),
    'customer'  => $claimed['customer'],
    'order'     => $claimed['order'],
    'payment'   => ['id' => 'ppcp', 'title' => $label] + (array) $payment,
    'paid'      => ['gateway' => 'ppcp', 'id' => (string) ($resource['id'] ?? ''),
                    'amount'  => $paid, 'at' => date('c'), 'via' => 'webhook'],
]);

done(200, 'recorded');
