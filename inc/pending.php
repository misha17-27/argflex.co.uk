<?php
/**
 * Orders that have been priced but not yet paid for.
 *
 * A card payment happens in three steps: we quote a price, the customer's
 * browser settles with the gateway, and then somebody has to write the
 * order down. The third step is the one that goes wrong — the tab is
 * closed, the phone drops the connection, the browser crashes — and if the
 * only record lives in that browser, the money is taken and no order
 * exists.
 *
 * So the basket is written here first, priced and frozen, before the
 * customer is sent to pay. Whichever comes back first, the browser or the
 * gateway's webhook, finds the same file and turns it into a real order.
 * The other one finds it already done and leaves it alone.
 *
 * These files hold a customer's name, address and basket, so they live in
 * storage/, which git ignores and the server refuses to serve.
 */
declare(strict_types=1);

function pending_dir(): string
{
    $dir = ROOT_DIR . '/storage/pending';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function pending_path(string $reference): string
{
    // The reference is ours, but it arrives from the browser at the far end,
    // so it is never allowed to describe a path.
    $safe = preg_replace('/[^A-Za-z0-9-]/', '', $reference) ?? '';
    return pending_dir() . '/' . $safe . '.json';
}

/** Freeze an order before sending the customer off to pay for it. */
function pending_save(string $reference, array $order, array $customer, string $method): bool
{
    if (preg_replace('/[^A-Za-z0-9-]/', '', $reference) !== $reference || $reference === '') {
        return false;
    }
    return (bool) file_put_contents(pending_path($reference), json_encode([
        'reference' => $reference,
        'order'     => $order,
        'customer'  => $customer,
        'method'    => $method,
        'started'   => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

function pending_read(string $reference): ?array
{
    $file = pending_path($reference);
    if (!is_file($file)) return null;
    $row = json_decode((string) file_get_contents($file), true);
    return is_array($row) ? $row : null;
}

function pending_forget(string $reference): void
{
    @unlink(pending_path($reference));
}

/**
 * Claim a pending order, once.
 *
 * The browser and the webhook can both arrive with the same news, and an
 * order written twice is a real invoice sent twice. Renaming is atomic on
 * every filesystem we run on, so exactly one caller gets the file and the
 * other is told the work is already done.
 */
function pending_claim(string $reference): ?array
{
    $file = pending_path($reference);
    if (!is_file($file)) return null;

    $claim = $file . '.claimed';
    if (!@rename($file, $claim)) return null;      // somebody else got there first

    $row = json_decode((string) file_get_contents($claim), true);
    @unlink($claim);
    return is_array($row) ? $row : null;
}

/**
 * Throw away frozen baskets nobody came back for.
 *
 * A customer who changes their mind at the gateway leaves one behind. They
 * carry an address, so they should not sit there indefinitely.
 */
function pending_sweep(int $olderThanHours = 48): int
{
    $cutoff = time() - $olderThanHours * 3600;
    $gone   = 0;
    foreach (glob(pending_dir() . '/*.json') ?: [] as $file) {
        if (filemtime($file) < $cutoff && @unlink($file)) $gone++;
    }
    return $gone;
}
