<?php
/**
 * Talking to Stripe and PayPal.
 *
 * The live shop takes card payments through Stripe and PayPal through
 * PayPal Payments, in that order, with PayPal preselected. This is the
 * server half of that: creating the charge, and — the part that matters —
 * checking afterwards that what was actually paid is what we asked for.
 *
 * Two rules run through all of it.
 *
 * The browser is never believed about money. It says which basket and which
 * options; the amount is worked out here, from the catalogue, every time.
 * A page that could name its own price would be a shop anyone could rob.
 *
 * Nothing is treated as paid until the gateway says so in a reply we
 * fetched ourselves. A redirect back from PayPal proves the customer
 * pressed a button, not that the money moved.
 *
 * Keys live in storage/settings.php, which git ignores and the server
 * refuses to serve. They are entered by the shop's owner in the admin and
 * appear nowhere else — not in this repository, not in a log, and not in
 * anything the browser receives except the publishable key, which is meant
 * to be public.
 */
declare(strict_types=1);

/* ---------------------------------------------------------------- config */

function gateway_settings(string $id): array
{
    $all = (array) setting('gateways');
    return (array) ($all[$id] ?? []);
}

function stripe_test_mode(): bool
{
    return (bool) (gateway_settings('stripe')['test_mode'] ?? true);
}

function paypal_sandbox(): bool
{
    return (bool) (gateway_settings('paypal')['sandbox'] ?? true);
}

/** The key the browser is allowed to see. */
function stripe_publishable_key(): string
{
    $s = gateway_settings('stripe');
    return trim((string) ($s[stripe_test_mode() ? 'test_publishable' : 'live_publishable'] ?? ''));
}

/** The key that must never leave this server. */
function stripe_secret_key(): string
{
    $s = gateway_settings('stripe');
    return trim((string) ($s[stripe_test_mode() ? 'test_secret' : 'live_secret'] ?? ''));
}

function paypal_client_id(): string
{
    $s = gateway_settings('paypal');
    return trim((string) ($s[paypal_sandbox() ? 'sandbox_client_id' : 'live_client_id'] ?? ''));
}

function paypal_secret(): string
{
    $s = gateway_settings('paypal');
    return trim((string) ($s[paypal_sandbox() ? 'sandbox_secret' : 'live_secret'] ?? ''));
}

/**
 * Is a gateway actually able to take money?
 *
 * A method the owner switched on but never gave keys to must not appear at
 * the checkout. Showing a card form that cannot charge anything is worse
 * than showing nothing: the customer believes they have paid.
 */
function gateway_ready(string $id): bool
{
    /* Keys are not enough: without curl this server cannot talk to either
       gateway at all, and offering a card button that can only fail is worse
       than falling back to the invoice route. The invoice routes need
       nothing and stay available. */
    $canReach = function_exists('curl_init');

    return match ($id) {
        'stripe' => $canReach && stripe_publishable_key() !== '' && stripe_secret_key() !== '',
        'ppcp'   => $canReach && paypal_client_id() !== '' && paypal_secret() !== '',
        default  => true,       // the invoice routes need nothing
    };
}

/**
 * Which payment methods the checkout may offer, given what is configured.
 *
 * Until the keys are in, neither gateway can charge anything, and offering
 * them would take orders nobody has paid for. So the shop falls back to the
 * invoice route, which needs no keys and is how it has worked all along.
 * The moment a gateway is configured the fallback disappears and the
 * checkout reads as the live one does: PayPal, then the card.
 */
function usable_payment_methods(): array
{
    $ready = array_values(array_filter(payment_methods(),
        fn($m) => gateway_ready((string) ($m['id'] ?? ''))));
    if ($ready) return $ready;

    foreach (payment_methods(false) as $m) {
        if (($m['id'] ?? '') === 'proforma') return [['enabled' => true] + $m];
    }
    return [];
}

/* ------------------------------------------------------------------- http */

/**
 * One request to a gateway.
 *
 * Returns [status, decoded body]. Network failures come back as status 0
 * with an 'error' key, so every caller has one shape to handle and none of
 * them can mistake a timeout for a refusal — or, far worse, for a success.
 */
function gateway_http(string $method, string $url, array $headers, $body = null): array
{
    /* Some hosts ship PHP without curl, and this used to call curl_init()
       regardless — a fatal, mid-payment, printing a stack trace with the
       server's absolute path into whatever was listening. The documented
       shape of this function is [0, error] for anything that did not
       complete, and a missing extension is exactly that. */
    if (!function_exists('curl_init')) {
        return [0, ['error' => 'This server has no curl extension, so it cannot reach a payment gateway.']];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) return [0, ['error' => $err ?: 'the request did not complete']];

    $decoded = json_decode((string) $raw, true);
    return [$status, is_array($decoded) ? $decoded : ['error' => 'unreadable reply']];
}

/* ----------------------------------------------------------------- stripe */

function stripe_call(string $method, string $path, array $params = []): array
{
    $key = stripe_secret_key();
    if ($key === '') return [0, ['error' => 'Stripe is not configured']];

    return gateway_http($method, 'https://api.stripe.com/v1/' . ltrim($path, '/'), [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/x-www-form-urlencoded',
        'Stripe-Version: 2024-06-20',
    ], $params ? http_build_query($params) : null);
}

/**
 * Ask Stripe for an intent to charge this much.
 *
 * The amount comes from price_order(); the browser only ever said which
 * products. `reference` is our own order number, so a payment can always be
 * traced back to a basket even if the customer closes the tab.
 */
function stripe_create_intent(int $pence, string $reference, string $email, array $meta = []): array
{
    [$status, $body] = stripe_call('POST', 'payment_intents', array_filter([
        'amount'                    => $pence,
        'currency'                  => strtolower((string) setting('currency')),
        'receipt_email'             => $email ?: null,
        'description'               => 'Order ' . $reference,
        'metadata[reference]'       => $reference,
        'metadata[site]'            => SITE_NAME,
        'automatic_payment_methods[enabled]' => 'true',
    ] + $meta, fn($v) => $v !== null && $v !== ''));

    return $status === 200 ? ['ok' => true, 'intent' => $body]
                           : ['ok' => false, 'error' => stripe_message($body)];
}

/**
 * Has this intent really been paid, and for the right amount?
 *
 * Called after the browser says it succeeded. The browser is not the
 * authority — Stripe is — and the amount is checked as well as the status,
 * because an intent can be created for one figure and confirmed against a
 * basket that has since changed.
 */
function stripe_confirm_paid(string $intentId, int $expectedPence): array
{
    [$status, $body] = stripe_call('GET', 'payment_intents/' . rawurlencode($intentId));
    if ($status !== 200) return ['ok' => false, 'error' => stripe_message($body)];

    if (($body['status'] ?? '') !== 'succeeded') {
        return ['ok' => false, 'error' => 'That payment has not completed.',
                'status' => (string) ($body['status'] ?? '')];
    }
    if ((int) ($body['amount_received'] ?? 0) !== $expectedPence) {
        return ['ok' => false, 'error' => 'The amount paid does not match the order.',
                'paid' => (int) ($body['amount_received'] ?? 0), 'expected' => $expectedPence];
    }
    return ['ok' => true, 'intent' => $body];
}

/** Stripe's own wording where there is one, rather than a code. */
function stripe_message(array $body): string
{
    return (string) ($body['error']['message'] ?? $body['error'] ?? 'Stripe would not complete that.');
}

/* ----------------------------------------------------------------- paypal */

function paypal_base(): string
{
    return paypal_sandbox() ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
}

/**
 * An access token, kept for as long as PayPal says it is good for.
 *
 * Held only for this request. Caching it across requests would mean writing
 * a credential to disk, which is not worth one extra call per order.
 */
function paypal_token(): string
{
    static $token = null;
    if ($token !== null) return $token;

    $id = paypal_client_id();
    $secret = paypal_secret();
    if ($id === '' || $secret === '') return $token = '';

    [$status, $body] = gateway_http('POST', paypal_base() . '/v1/oauth2/token', [
        'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        'Content-Type: application/x-www-form-urlencoded',
    ], 'grant_type=client_credentials');

    return $token = $status === 200 ? (string) ($body['access_token'] ?? '') : '';
}

function paypal_call(string $method, string $path, ?array $body = null): array
{
    $token = paypal_token();
    if ($token === '') return [0, ['error' => 'PayPal would not authorise this site']];

    return gateway_http($method, paypal_base() . $path, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : null);
}

/** Open a PayPal order for an amount we worked out ourselves. */
function paypal_create_order(int $pence, string $reference): array
{
    [$status, $body] = paypal_call('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id'  => $reference,
            'custom_id'     => $reference,
            'description'   => 'Order ' . $reference,
            'amount' => [
                'currency_code' => strtoupper((string) setting('currency')),
                'value'         => number_format($pence / 100, 2, '.', ''),
            ],
        ]],
        'payment_source' => ['paypal' => ['experience_context' => [
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'PAY_NOW',
            'brand_name'          => SITE_NAME,
        ]]],
    ]);

    return in_array($status, [200, 201], true)
        ? ['ok' => true, 'id' => (string) ($body['id'] ?? '')]
        : ['ok' => false, 'error' => paypal_message($body)];
}

/**
 * Take the money, and check that we took the right amount.
 *
 * PayPal reports the figure it actually captured. Comparing it against the
 * order rather than trusting the approval is what stops a basket edited
 * between approval and capture from being paid at the old price.
 */
function paypal_capture(string $orderId, int $expectedPence): array
{
    [$status, $body] = paypal_call('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture');

    if (!in_array($status, [200, 201], true)) {
        return ['ok' => false, 'error' => paypal_message($body)];
    }
    if (($body['status'] ?? '') !== 'COMPLETED') {
        return ['ok' => false, 'error' => 'PayPal did not complete that payment.',
                'status' => (string) ($body['status'] ?? '')];
    }

    $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
    $paid    = (int) round(((float) ($capture['amount']['value'] ?? 0)) * 100);
    if ($paid !== $expectedPence) {
        return ['ok' => false, 'error' => 'The amount paid does not match the order.',
                'paid' => $paid, 'expected' => $expectedPence];
    }

    return ['ok' => true, 'capture' => $capture, 'payer' => $body['payer'] ?? []];
}

/** The webhook id PayPal issued for this shop's endpoint, or ''. */
function paypal_webhook_id(): string
{
    $s = gateway_settings('paypal');
    return trim((string) ($s[paypal_sandbox() ? 'sandbox_webhook_id' : 'live_webhook_id'] ?? ''));
}

/**
 * Ask PayPal whether a webhook really came from PayPal.
 *
 * Their scheme is not a local HMAC like Stripe's — the signature is over a
 * certificate chain, and the supported way to check it is to hand the headers
 * and the body back and let PayPal answer. That means a round trip on every
 * webhook, which is PayPal's design rather than a choice made here.
 *
 * Verifying needs the webhook id, so an unconfigured shop cannot verify and
 * must refuse: an endpoint that accepts unverified messages is a way for
 * anyone on the internet to conjure paid orders.
 */
function paypal_verify_webhook(array $headers, string $rawBody): bool
{
    $id = paypal_webhook_id();
    if ($id === '') return false;

    $need = ['transmission_id'   => 'PAYPAL-TRANSMISSION-ID',
             'transmission_time' => 'PAYPAL-TRANSMISSION-TIME',
             'transmission_sig'  => 'PAYPAL-TRANSMISSION-SIG',
             'cert_url'          => 'PAYPAL-CERT-URL',
             'auth_algo'         => 'PAYPAL-AUTH-ALGO'];

    $ask = ['webhook_id' => $id];
    foreach ($need as $field => $header) {
        $value = trim((string) ($headers[$header] ?? ''));
        if ($value === '') return false;
        $ask[$field] = $value;
    }

    /* The certificate must be PayPal's. Without this check the caller could
       name a host of their own, sign the body with a key they hold, and have
       the verification pass against their own certificate. */
    $host = strtolower((string) parse_url($ask['cert_url'], PHP_URL_HOST));
    if ($host !== 'api.paypal.com' && !str_ends_with($host, '.paypal.com')) return false;

    $event = json_decode($rawBody, true);
    if (!is_array($event)) return false;
    $ask['webhook_event'] = $event;

    [$status, $body] = paypal_call('POST', '/v1/notifications/verify-webhook-signature', $ask);

    return in_array($status, [200, 201], true)
        && ($body['verification_status'] ?? '') === 'SUCCESS';
}

function paypal_message(array $body): string
{
    $detail = $body['details'][0]['description'] ?? null;
    return (string) ($detail ?? $body['message'] ?? $body['error'] ?? 'PayPal would not complete that.');
}

/**
 * How the live shop titles a PayPal order: the method, then who paid.
 * Thirty-six historic orders are written that way.
 */
function paypal_payer_label(array $payer): string
{
    $email = trim((string) ($payer['email_address'] ?? ''));
    return $email !== '' ? 'PayPal - ' . $email : 'PayPal';
}
