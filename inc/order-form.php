<?php
/**
 * The checkout, apart from its appearance.
 *
 * Pricing an order, checking the form, and turning the two into the record
 * that gets written down. It lives here because three different things need
 * it and they must not disagree: the ordinary form post, the endpoint that
 * hands a basket to Stripe or PayPal, and the webhook that hears back from
 * them. A second copy of "is this address complete" or "what does this
 * cost" would drift, and the copy that was wrong would be the one taking
 * somebody's money.
 *
 * The basket lives in the visitor's browser, so it is posted back and
 * re-priced here from the catalogue. The browser never decides what
 * anything costs.
 */
declare(strict_types=1);

/**
 * Rebuild the order from the posted lines, using our own prices.
 *
 * $picked maps a consignment's position to the rate the customer chose for
 * it. A basket can travel as two consignments — see inc/shipping.php — and
 * each is charged separately, so the delivery figure is the sum of them.
 */
function price_order(array $lines, string $country = '', string $code = '', array $picked = []): array
{
    $items    = price_basket_lines($lines);
    $subtotal = array_sum(array_column($items, 'line'));

    // A code is re-checked here rather than trusted; the browser only ever
    // says which one to try.
    $coupon   = $code !== '' ? coupon_apply($code, $items, $subtotal) : ['ok' => false];
    $discount = !empty($coupon['ok']) ? (int) $coupon['discount'] : 0;

    $quote = shipping_quote($items, $country, $picked);
    $free  = !empty($coupon['ok']) && !empty($coupon['free_shipping']);
    $ship  = $free ? 0 : (int) $quote['cost'];

    // VAT goes on the goods alone. The live shop's one tax rate has its
    // shipping flag off, and every one of the shipping lines in the order
    // archive carries no tax — so putting delivery in the base here would
    // have raised every order by a fifth of its carriage.
    $tax  = tax_for($country);
    $base = $subtotal - $discount + (tax_on_shipping() ? $ship : 0);
    $vat  = (int) round($base * $tax['rate'] / 100);

    return [
        'items'          => $items,
        'subtotal'       => $subtotal,
        'coupon'         => !empty($coupon['ok']) ? $coupon['code'] : '',
        'coupon_title'   => !empty($coupon['ok']) ? $coupon['title'] : '',
        'discount'       => $discount,
        'shipping'       => $ship,
        'shipping_title' => $free ? 'Free delivery with ' . $coupon['code'] : (string) $quote['title'],
        'shipping_zone'  => shipping_zone($country)['name'] ?? '',
        'packages'       => $quote['packages'],
        'deliverable'    => (bool) $quote['deliverable'],
        'undeliverable_because' => (string) $quote['why'],
        'ship_surcharge' => 0,
        'ship_because'   => '',
        'delivery_in'    => '',
        'vat'            => $vat,
        'tax_label'      => $tax['label'],
        'tax_rate'       => $tax['rate'],
        'tax_note'       => $tax['note'],
        'total'          => $subtotal - $discount + $ship + $vat,
    ];
}

/** The country an order is priced for, taken from what was posted. */
function posted_country(array $post): string
{
    $country = strtoupper(trim((string) ($post['country'] ?? '')));
    return isset(COUNTRIES[$country]) ? $country : (string) setting('default_country');
}

/** Which rate the customer picked for each consignment. */
function posted_rates(array $post): array
{
    $picked = [];
    foreach ((array) ($post['ship'] ?? []) as $i => $rateId) $picked[(int) $i] = (int) $rateId;
    return $picked;
}

/**
 * What is wrong with this form, as field => message.
 *
 * An empty result means the order can be taken. Delivery is checked here
 * too: a basket we cannot send must not become an order, whichever way it
 * arrived.
 */
function check_order_form(array $post, array $order): array
{
    $errors = [];

    $required = [
        'name'     => 'your name',
        'email'    => 'an email address',
        'phone'    => 'a phone number',
        'address'  => 'a delivery address',
        'city'     => 'a town or city',
        'postcode' => 'a postcode',
    ];
    foreach ($required as $field => $label) {
        if (trim((string) ($post[$field] ?? '')) === '') $errors[$field] = "Please enter {$label}.";
    }
    if (!isset($errors['email']) && !filter_var((string) ($post['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address does not look right.';
    }

    if (!$order['items']) {
        $errors['cart'] = 'Your basket is empty, so there is nothing to order yet.';
    } elseif (!$order['deliverable']) {
        $errors['country'] = $order['undeliverable_because'];
    }

    /* The form's own token. Checked here rather than in the page so the card
       and PayPal route through payment.php gets it too — that reads a JSON
       body, so it passes what the body held. Without it another site could
       place an order in a signed-in customer's name, to their address, and
       the first they would know is the invoice. */
    if (!form_token_ok('checkout', isset($post['_form']) ? (string) $post['_form'] : null)) {
        $errors['captcha'] = ($_COOKIE[FORM_COOKIE] ?? '') === ''
            ? 'Your browser did not keep our cookie, so we could not check this came from us. Allow cookies for this site and try again.'
            : 'This page had been open a while and the form went stale. Reload it and send the order once more.';
    }

    // The honeypot, and the anti-spam check if one is configured.
    if (trim((string) ($post['website'] ?? '')) !== ''
        || !turnstile_verify($post['cf-turnstile-response'] ?? '')) {
        $errors['captcha'] = 'The anti-spam check did not pass. Please try once more.';
    }
    return $errors;
}

/** A fresh order reference, in the shape the shop has always used. */
function new_order_reference(): string
{
    return date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Everything about an order, ready to be written down. */
function build_order_record(string $reference, array $post, array $order, array $payment): array
{
    $country = posted_country($post);
    return [
        'reference' => $reference,
        'placed_at' => date('c'),
        /* Capped on the way in. These are shown on the order screen, put in
           the invoice and used to address a mail, and a field with no ceiling
           is a way to write a megabyte into storage/orders on every POST. The
           name and phone are header-safe because they reach a mail header. */
        'customer'  => [
            'name'     => header_safe((string) ($post['name'] ?? ''), 80),
            'company'  => header_safe((string) ($post['company'] ?? ''), 80),
            'email'    => clip(trim((string) ($post['email'] ?? '')), 190),
            'phone'    => header_safe((string) ($post['phone'] ?? ''), 40),
            'address'  => clip(trim((string) ($post['address'] ?? '')), 200),
            'city'     => clip(trim((string) ($post['city'] ?? '')), 80),
            'postcode' => clip(trim((string) ($post['postcode'] ?? '')), 16),
            'country'      => COUNTRIES[$country] ?? 'United Kingdom',
            'country_code' => $country,
            'notes'    => clip(trim((string) ($post['notes'] ?? '')), 2000),
        ],
        'order'   => $order,
        'payment' => $payment,
    ];
}
