<?php
/**
 * Taking a card or a PayPal payment.
 *
 * Two steps, because that is how both gateways work.
 *
 *   start   the form is checked, the basket is priced here, an order
 *           reference is minted and the whole thing is frozen to disk.
 *           Only then does the gateway hear an amount. What comes back to
 *           the browser is a token that lets it settle up — never a price
 *           it could argue with.
 *
 *   finish  the browser says it is done. We ask the gateway ourselves
 *           whether that is true and whether the figure matches, and only
 *           if both hold does the order get written down.
 *
 * The frozen basket is what makes this safe to interrupt. If the customer
 * closes the tab between paying and coming back, the gateway's webhook
 * finds the same file and finishes the job. Whichever arrives second is
 * told the order already exists and does nothing.
 *
 * Nothing here trusts a number from the browser. The request names products,
 * options and quantities; every price is worked out from the catalogue.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once ROOT_DIR . '/inc/turnstile.php';
require_once ROOT_DIR . '/inc/mail.php';
require_once ROOT_DIR . '/inc/store.php';
require_once ROOT_DIR . '/inc/order-form.php';
require_once ROOT_DIR . '/inc/gateways.php';    // talking to Stripe and PayPal
require_once ROOT_DIR . '/inc/pending.php';     // the frozen basket

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Post to this address.']));
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

$action = (string) ($body['action'] ?? '');
$method = (string) ($body['payment'] ?? '');

/** Answer and stop. */
function reply(array $data, int $status = 200): never
{
    http_response_code($status);
    exit(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/* ------------------------------------------------------------------ start */

if ($action === 'start') {
    if (!in_array($method, ['stripe', 'ppcp'], true) || !gateway_ready($method)) {
        reply(['ok' => false, 'error' => 'That way of paying is not available.'], 400);
    }

    $lines   = is_array($body['cart'] ?? null) ? $body['cart'] : [];
    $country = posted_country($body);
    $order   = price_order($lines, $country, trim((string) ($body['coupon'] ?? '')), posted_rates($body));

    $errors = check_order_form($body, $order);
    if ($errors) reply(['ok' => false, 'errors' => $errors], 422);

    if ($order['total'] <= 0) {
        reply(['ok' => false, 'error' => 'There is nothing to pay for.'], 422);
    }

    $payment = find_payment_method($method) ?? default_payment_method();
    $ref     = new_order_reference();
    $record  = build_order_record($ref, $body, $order, $payment);

    // Frozen before a penny moves, so that whatever happens next — the
    // browser coming back or the webhook arriving first — there is one
    // agreed version of what was bought and what it cost.
    if (!pending_save($ref, $order, $record['customer'], $method)) {
        reply(['ok' => false, 'error' => 'Could not start the payment. Please try again.'], 500);
    }

    if ($method === 'stripe') {
        $made = stripe_create_intent($order['total'], $ref, $record['customer']['email']);
        if (empty($made['ok'])) {
            pending_forget($ref);
            reply(['ok' => false, 'error' => $made['error']], 502);
        }
        reply(['ok' => true, 'reference' => $ref, 'total' => $order['total'],
               'client_secret' => (string) ($made['intent']['client_secret'] ?? ''),
               'publishable'   => stripe_publishable_key()]);
    }

    $made = paypal_create_order($order['total'], $ref);
    if (empty($made['ok'])) {
        pending_forget($ref);
        reply(['ok' => false, 'error' => $made['error']], 502);
    }
    reply(['ok' => true, 'reference' => $ref, 'total' => $order['total'],
           'paypal_order' => $made['id']]);
}

/* ----------------------------------------------------------------- finish */

if ($action === 'finish') {
    $ref = (string) ($body['reference'] ?? '');

    $frozen = pending_read($ref);
    if (!$frozen) {
        // Already turned into an order — by the webhook, or by this same
        // browser retrying. That is a success, not a failure.
        if (find_order($ref)) reply(['ok' => true, 'reference' => $ref, 'already' => true]);
        reply(['ok' => false, 'error' => 'That payment has expired. Nothing has been charged.'], 410);
    }

    $expected = (int) $frozen['order']['total'];

    if ($frozen['method'] === 'stripe') {
        $check = stripe_confirm_paid((string) ($body['intent'] ?? ''), $expected);
        $label = 'Credit / Debit Card';
        $paid  = $check['intent']['id'] ?? '';
    } else {
        $check = paypal_capture((string) ($body['paypal_order'] ?? ''), $expected);
        $label = !empty($check['ok']) ? paypal_payer_label((array) ($check['payer'] ?? [])) : 'PayPal';
        $paid  = $check['capture']['id'] ?? '';
    }

    if (empty($check['ok'])) {
        // The basket stays frozen: the customer may well try again, and
        // throwing it away here would lose the address they typed.
        reply(['ok' => false, 'error' => $check['error']], 402);
    }

    $claimed = pending_claim($ref);
    if (!$claimed) {
        // The webhook got there first, which is exactly what it is for.
        reply(['ok' => true, 'reference' => $ref, 'already' => true]);
    }

    $payment = find_payment_method($frozen['method']) ?? default_payment_method();
    $record  = [
        'reference' => $ref,
        'placed_at' => date('c'),
        'customer'  => $claimed['customer'],
        'order'     => $claimed['order'],
        'payment'   => ['id' => $frozen['method'], 'title' => $label] + (array) $payment,
        'paid'      => ['gateway' => $frozen['method'], 'id' => (string) $paid,
                        'amount'  => $expected, 'at' => date('c')],
    ];

    reply(place_order($record)
        ? ['ok' => true, 'reference' => $ref]
        : ['ok' => false, 'error' => 'The payment went through but the order could not be saved. '
                                   . 'Please contact us quoting ' . $ref . '.'], 200);
}

reply(['ok' => false, 'error' => 'Unknown action.'], 400);
