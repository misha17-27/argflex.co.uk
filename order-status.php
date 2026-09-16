<?php
/**
 * Has this order been written down yet, and what was in it?
 *
 * Asked by the thank-you page, and only in one situation: the customer paid by
 * card, the bank sent them away to authenticate, and they came back before the
 * gateway's webhook had turned the payment into an order. The page then has a
 * reference and nothing behind it, says "confirming your payment" — and used
 * to send NOTHING to analytics, ever, with no retry and nothing to retry from.
 *
 * Those are disproportionately the larger orders: a bank challenges the ones
 * worth challenging. So the shop's own record of what it sells would have been
 * quietly biased towards the small ones, and nothing on the screen would have
 * said so.
 *
 * WHO MAY ASK. Only the browser that placed the order: receipt_allowed()
 * checks a cookie signed with the site's own key and naming this reference,
 * set when the payment finished. A reference on its own opens nothing. That
 * matters more here than on the thank-you page itself, because this answers in
 * JSON and JSON is what somebody would write a script against.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once ROOT_DIR . '/inc/security.php';
require_once ROOT_DIR . '/inc/store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$ref = trim((string) ($_GET['ref'] ?? ''));

if ($ref === '' || !receipt_allowed($ref)) {
    // Deliberately the same answer as "not written down yet". Telling a
    // stranger that a reference exists is telling them something.
    exit(json_encode(['placed' => false]));
}

$order = find_order($ref);
if (!$order) exit(json_encode(['placed' => false]));

$o     = (array) ($order['order'] ?? []);
$lines = [];
foreach ((array) ($o['items'] ?? []) as $line) {
    $lines[] = [
        'item_id'      => (string) ($line['slug'] ?? ''),
        'item_name'    => (string) ($line['title'] ?? ''),
        'item_variant' => (string) ($line['option'] ?? ''),
        'price'        => round(((int) ($line['price'] ?? 0)) / 100, 2),
        'quantity'     => (int) ($line['qty'] ?? 1),
    ];
}

/* The same shape pages/checkout.php sends when the order IS on file, so one
   purchase looks identical whichever way it arrived. */
exit(json_encode([
    'placed'   => true,
    'purchase' => [
        'transaction_id' => (string) $order['reference'],
        'value'          => round(((int) ($o['total'] ?? 0)) / 100, 2),
        'tax'            => round(((int) ($o['vat'] ?? 0)) / 100, 2),
        'shipping'       => round(((int) ($o['shipping'] ?? 0)) / 100, 2),
        'currency'       => (string) setting('currency'),
        'items'          => $lines,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
