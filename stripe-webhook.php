<?php
/**
 * Stripe telling us a card payment went through.
 *
 * This exists because a card is confirmed in the customer's browser. If that
 * browser dies in the half-second between Stripe taking the money and our
 * own page hearing about it, the charge is real and the order does not
 * exist. Stripe tells us anyway, and this turns that into an order.
 *
 * PayPal needs no equivalent: there we capture the money ourselves, from the
 * server, so a browser that vanishes first leaves an authorisation that
 * simply expires. Nothing is taken.
 *
 * Every request is checked against the signing secret before it is believed.
 * Without that, this address would be a way for anyone on the internet to
 * conjure paid orders out of nothing.
 *
 * In Stripe: Developers → Webhooks → add /stripe-webhook.php and subscribe
 * to payment_intent.succeeded. Paste the whsec_ secret into
 * Settings → Payments.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once ROOT_DIR . '/inc/mail.php';
require_once ROOT_DIR . '/inc/store.php';
require_once ROOT_DIR . '/inc/gateways.php';    // the signing secret
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

$secret = trim((string) (gateway_settings('stripe')['webhook_secret'] ?? ''));
if ($secret === '') done(503, 'no signing secret configured');

$payload   = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

/**
 * Is this really from Stripe, and recent?
 *
 * The signature covers the timestamp and the body together, so replaying an
 * old-but-genuine message fails on the age check rather than the hash. Five
 * minutes is Stripe's own tolerance.
 */
function stripe_signature_ok(string $payload, string $header, string $secret): bool
{
    $timestamp = '';
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't')  $timestamp = $value;
        if ($key === 'v1') $signatures[] = $value;
    }
    if ($timestamp === '' || !$signatures) return false;
    if (abs(time() - (int) $timestamp) > 300) return false;

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $given) {
        if (hash_equals($expected, $given)) return true;   // constant time, deliberately
    }
    return false;
}

if (!stripe_signature_ok($payload, $signature, $secret)) done(400, 'bad signature');

$event = json_decode($payload, true);
if (!is_array($event)) done(400, 'unreadable');

// Anything else is acknowledged and ignored — refusing it would make Stripe
// retry a message we were never going to act on.
if (($event['type'] ?? '') !== 'payment_intent.succeeded') done(200, 'ignored');

$intent    = (array) ($event['data']['object'] ?? []);
$reference = (string) ($intent['metadata']['reference'] ?? '');
$paid      = (int) ($intent['amount_received'] ?? 0);

if ($reference === '') done(200, 'no reference');
if (find_order($reference)) done(200, 'already recorded');

$frozen = pending_read($reference);
if (!$frozen) done(200, 'nothing pending');

// The signature proves Stripe sent this; it does not prove the amount is
// the one we asked for, so that is still checked.
if ($paid !== (int) $frozen['order']['total']) done(200, 'amount does not match');

$claimed = pending_claim($reference);
if (!$claimed) done(200, 'somebody else got there first');

$payment = find_payment_method('stripe') ?? ['id' => 'stripe', 'title' => 'Credit / Debit Card'];

place_order([
    'reference' => $reference,
    'placed_at' => date('c'),
    'customer'  => $claimed['customer'],
    'order'     => $claimed['order'],
    'payment'   => ['id' => 'stripe', 'title' => 'Credit / Debit Card'] + (array) $payment,
    'paid'      => ['gateway' => 'stripe', 'id' => (string) ($intent['id'] ?? ''),
                    'amount'  => $paid, 'at' => date('c'), 'via' => 'webhook'],
]);

done(200, 'recorded');
