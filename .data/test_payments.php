<?php
/**
 * The parts of taking a payment that can be tested without a gateway.
 *
 * No keys are needed here and no money moves. What is checked is the
 * machinery around the payment: that a forged webhook is refused, that a
 * frozen basket can only be turned into an order once, and that a gateway
 * without keys never appears at the checkout.
 *
 * Those are the three places where a mistake costs real money — an order
 * conjured out of nothing, an invoice sent twice, or a card form that
 * cannot charge anything taking somebody's order.
 *
 *   php .data/test_payments.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/inc/config.php';
require_once ROOT_DIR . '/inc/store.php';

$failed = 0;
$ran    = 0;

function check(string $label, $got, $want): void
{
    global $failed, $ran;
    $ran++;
    $ok = $got === $want;
    if (!$ok) $failed++;
    printf("  %-60s %s\n", $label, $ok ? 'OK' : 'FAILED');
    if (!$ok) {
        echo '        wanted: ' . json_encode($want) . "\n";
        echo '        got:    ' . json_encode($got) . "\n";
    }
}

/* ------------------------------------------------ the webhook's signature */

// The same check the webhook uses, lifted so it can be exercised directly.
require_once ROOT_DIR . '/inc/config.php';
$verify = function (string $payload, string $header, string $secret): bool {
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
    foreach ($signatures as $given) if (hash_equals($expected, $given)) return true;
    return false;
};

echo "A WEBHOOK CLAIMING TO BE STRIPE\n";

$secret  = 'whsec_this_is_only_a_test_secret';
$payload = '{"type":"payment_intent.succeeded","data":{"object":{"id":"pi_test"}}}';
$now     = time();
$sign    = fn(int $t, string $body, string $key) => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $key);

check('a genuine one is accepted',          $verify($payload, $sign($now, $payload, $secret), $secret), true);
check('a forged signature is refused',      $verify($payload, 't=' . $now . ',v1=deadbeef', $secret), false);
check('the wrong secret is refused',        $verify($payload, $sign($now, $payload, 'whsec_someone_elses'), $secret), false);
check('a tampered body is refused',         $verify($payload . ' ', $sign($now, $payload, $secret), $secret), false);
check('an old but genuine one is refused',  $verify($payload, $sign($now - 600, $payload, $secret), $secret), false);
check('one from the future is refused',     $verify($payload, $sign($now + 600, $payload, $secret), $secret), false);
check('no signature at all is refused',     $verify($payload, '', $secret), false);
check('a missing timestamp is refused',     $verify($payload, 'v1=' . hash_hmac('sha256', $now . '.' . $payload, $secret), $secret), false);

/* ------------------------------------------------------ the frozen basket */

echo "\nA BASKET FROZEN BEFORE PAYING\n";

$ref   = 'TEST00-' . strtoupper(bin2hex(random_bytes(3)));
$order = ['total' => 1234, 'items' => [], 'coupon' => ''];
$who   = ['name' => 'Rita Cheng', 'email' => 'rita@example.com'];

check('it saves',            pending_save($ref, $order, $who, 'stripe'), true);
check('and reads back',      pending_read($ref)['order']['total'] ?? null, 1234);
check('with the customer',   pending_read($ref)['customer']['name'] ?? null, 'Rita Cheng');

$first  = pending_claim($ref);
$second = pending_claim($ref);
check('the first claim gets it',       $first['reference'] ?? null, $ref);
check('the second gets nothing',       $second, null);
check('and it is gone afterwards',     pending_read($ref), null);

// A reference is a filename, and it arrives from the far side of the wire.
check('a reference cannot escape its folder', pending_save('../../evil', $order, $who, 'stripe'), false);
check('nor can an empty one',                 pending_save('', $order, $who, 'stripe'), false);
check('nothing was written outside storage',  is_file(ROOT_DIR . '/evil.json'), false);

/* ---------------------------------------------------- what is on offer */

echo "\nWHAT THE CHECKOUT MAY OFFER\n";

check('a gateway with no keys is not ready',  gateway_ready('stripe'), false);
check('nor is PayPal',                        gateway_ready('ppcp'), false);
check('the invoice route needs none',         gateway_ready('proforma'), true);

$offered = array_column(usable_payment_methods(), 'id');
check('so the shop falls back to the invoice', $offered, ['proforma']);
check('and never offers a gateway it cannot charge on',
      array_intersect($offered, ['stripe', 'ppcp']), []);

/* ------------------------------------------------- placing one only once */

echo "\nAN ORDER IS WRITTEN DOWN ONCE\n";

$ref2 = 'TEST01-' . strtoupper(bin2hex(random_bytes(3)));
$record = [
    'reference' => $ref2,
    'placed_at' => date('c'),
    'customer'  => ['name' => 'Rita Cheng', 'email' => '', 'country_code' => 'GB'],
    'order'     => ['total' => 1234, 'items' => [], 'coupon' => '', 'subtotal' => 1234,
                    'shipping' => 0, 'vat' => 0, 'discount' => 0],
    'payment'   => ['id' => 'stripe', 'title' => 'Credit / Debit Card'],
];
check('it is placed',                 place_order($record), true);
check('and is on file',               (bool) find_order($ref2), true);

$before = filemtime(ROOT_DIR . '/storage/orders/' . $ref2 . '.json');
sleep(1);
check('placing it again is a no-op',  place_order($record), true);
check('the file was not rewritten',   filemtime(ROOT_DIR . '/storage/orders/' . $ref2 . '.json'), $before);

delete_order($ref2);
check('tidied up',                    find_order($ref2), null);

echo "\n";
printf("%d checks, %s\n", $ran, $failed ? "$failed FAILED" : 'all passing');
exit($failed ? 1 : 0);
