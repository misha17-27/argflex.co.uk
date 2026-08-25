<?php
/**
 * A printable proforma invoice or delivery note.
 *
 * Rendered without the admin chrome — this is a document, so it gets its own
 * page and its own stylesheet, and prints straight from the browser.
 *
 * @var array  $order
 * @var string $kind    invoice | note
 * @var array  $values  settings
 */
$c       = $order['customer'];
$o       = $order['order'];
$invoice = $order['invoice'] ?? null;
$isNote  = $kind === 'note';

$title = $isNote ? 'Delivery note' : 'Proforma invoice';
$store = array_filter([
    $values['store_addr1'], $values['store_addr2'],
    trim($values['store_city'] . ' ' . $values['store_postcode']),
    COUNTRIES[$values['store_country']] ?? '',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title . ' ' . ($invoice['number'] ?? $order['reference'])) ?></title>
<link rel="icon" href="/assets/img/favicon/fav-32x32.png" sizes="32x32">
<link rel="stylesheet" href="/admin/assets/print.css?v=<?= e(ASSET_VER) ?>">
</head>
<body>

<div class="toolbar">
  <a href="/admin/orders/<?= e(rawurlencode($order['reference'])) ?>">&larr; Back to the order</a>
  <div>
    <?php if (!$isNote): ?>
      <a href="/admin/orders/<?= e(rawurlencode($order['reference'])) ?>/note">Delivery note</a>
    <?php else: ?>
      <a href="/admin/orders/<?= e(rawurlencode($order['reference'])) ?>/invoice">Proforma invoice</a>
    <?php endif; ?>
    <button type="button" onclick="window.print()">Print or save as PDF</button>
  </div>
</div>

<main class="sheet">

  <header class="doc-head">
    <div>
      <?php if (is_file(ROOT_DIR . '/assets/img/site/logo.png')): ?>
        <img class="logo" src="/assets/img/site/logo.png" alt="<?= e(SITE_NAME) ?>" width="150">
      <?php else: ?>
        <p class="brand"><?= e(SITE_NAME) ?></p>
      <?php endif; ?>
      <address>
        <?php foreach ($store as $line): ?><?= e($line) ?><br><?php endforeach; ?>
        <?= e(SITE_PHONE) ?><br>
        <?= e(SITE_EMAIL) ?>
      </address>
      <?php if ($values['company_number'] !== '' || $values['vat_number'] !== ''): ?>
        <p class="reg">
          <?php if ($values['company_number'] !== ''): ?>Company no. <?= e($values['company_number']) ?><?php endif; ?>
          <?php if ($values['vat_number'] !== ''): ?><br><?= e(tax_label()) ?> no. <?= e($values['vat_number']) ?><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="doc-meta">
      <h1><?= e($title) ?></h1>
      <dl>
        <?php if (!$isNote && $invoice): ?>
          <dt>Invoice number</dt><dd><b><?= e($invoice['number']) ?></b></dd>
          <dt>Issued</dt><dd><?= e(date('j F Y', strtotime($invoice['issued_at']))) ?></dd>
          <?php if (!empty($invoice['due_at'])): ?>
            <dt>Payment due</dt><dd><?= e(date('j F Y', strtotime($invoice['due_at']))) ?></dd>
          <?php endif; ?>
        <?php endif; ?>
        <dt>Order</dt><dd><?= e($order['reference']) ?></dd>
        <dt>Order date</dt><dd><?= e(date('j F Y', strtotime($order['placed_at']))) ?></dd>
        <?php if ($isNote): ?>
          <dt>Printed</dt><dd><?= e(date('j F Y')) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </header>

  <section class="parties">
    <div>
      <h2><?= $isNote ? 'Deliver to' : 'Invoice to' ?></h2>
      <address>
        <b><?= e($c['name']) ?></b><br>
        <?php if ($c['company'] !== ''): ?><?= e($c['company']) ?><br><?php endif; ?>
        <?= e($c['address']) ?><br>
        <?= e($c['city']) ?><?= $c['postcode'] !== '' ? ', ' . e($c['postcode']) : '' ?><br>
        <?= e($c['country']) ?>
      </address>
      <p class="reg"><?= e($c['email']) ?><br><?= e($c['phone']) ?></p>
    </div>
    <div>
      <h2>Delivery</h2>
      <p><?= e($o['shipping_title'] ?? 'Delivery') ?><?php if (!empty($o['shipping_zone'])): ?><br><span class="reg"><?= e($o['shipping_zone']) ?></span><?php endif; ?></p>
      <?php if (!empty($o['delivery_in'])): ?><p class="reg">Estimated <?= e($o['delivery_in']) ?></p><?php endif; ?>
      <?php if (!$isNote && !empty($order['payment']['title'])): ?>
        <h2 class="spaced">Payment</h2>
        <p><?= e($order['payment']['title']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <table class="lines">
    <thead>
      <tr>
        <th class="num">#</th>
        <th>Description</th>
        <th class="qty">Qty</th>
        <?php if (!$isNote): ?>
          <th class="money">Unit price</th>
          <th class="money">Amount</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($o['items'] as $i => $item): ?>
        <tr>
          <td class="num"><?= $i + 1 ?></td>
          <td>
            <b><?= e($item['title']) ?></b>
            <?php if ($item['option'] !== ''): ?><span class="opt"><?= e($item['option']) ?></span><?php endif; ?>
          </td>
          <td class="qty"><?= (int) $item['qty'] ?></td>
          <?php if (!$isNote): ?>
            <td class="money"><?= e(money((int) $item['price'])) ?></td>
            <td class="money"><?= e(money((int) $item['line'])) ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <?php if (!$isNote): ?>
      <tfoot>
        <tr><th colspan="4">Goods</th><td class="money"><?= e(money((int) $o['subtotal'])) ?></td></tr>
        <?php if (!empty($o['discount'])): ?>
          <tr><th colspan="4">Discount<?= !empty($o['coupon']) ? ' (' . e($o['coupon']) . ')' : '' ?></th>
              <td class="money">&minus;<?= e(money((int) $o['discount'])) ?></td></tr>
        <?php endif; ?>
        <tr><th colspan="4"><?= e($o['shipping_title'] ?? 'Delivery') ?></th>
            <td class="money"><?= $o['shipping'] ? e(money((int) $o['shipping'])) : 'Free' ?></td></tr>
        <?php if (!empty($o['vat'])): ?>
          <tr><th colspan="4"><?= e($o['tax_label'] ?? tax_label()) ?> at <?= (int) ($o['tax_rate'] ?? tax_rate()) ?>%</th>
              <td class="money"><?= e(money((int) $o['vat'])) ?></td></tr>
        <?php endif; ?>
        <tr class="total"><th colspan="4">Total due</th>
            <td class="money"><?= e(money((int) $o['total'])) ?></td></tr>
      </tfoot>
    <?php endif; ?>
  </table>

  <?php if ($c['notes'] !== ''): ?>
    <section class="block">
      <h2>Order notes</h2>
      <p><?= nl2br(e($c['notes'])) ?></p>
    </section>
  <?php endif; ?>

  <?php if (!$isNote): ?>
    <?php
    $bank = array_filter([
        'Account name' => $values['bank_name'],
        'Sort code'    => $values['bank_sort'],
        'Account'      => $values['bank_account'],
        'IBAN'         => $values['bank_iban'],
        'BIC'          => $values['bank_bic'],
    ]);
    ?>
    <div class="foot-cols">
    <?php if ($bank): ?>
      <section class="block bank">
        <h2>How to pay</h2>
        <dl>
          <?php foreach ($bank as $label => $value): ?>
            <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
          <?php endforeach; ?>
        </dl>
        <p class="reg">Please quote <b><?= e($invoice['number'] ?? $order['reference']) ?></b> with your payment.</p>
      </section>
    <?php endif; ?>

    <?php if ($values['invoice_terms'] !== ''): ?>
      <section class="block">
        <h2>Terms</h2>
        <p><?= nl2br(e($values['invoice_terms'])) ?></p>
      </section>
    <?php endif; ?>
    </div>
  <?php else: ?>
    <section class="block sign">
      <h2>Received in good condition</h2>
      <div class="sign-row">
        <div><span></span>Name</div>
        <div><span></span>Signature</div>
        <div><span></span>Date</div>
      </div>
    </section>
  <?php endif; ?>

  <footer class="doc-foot">
    <?= e(SITE_NAME) ?> &middot; <?= e(SITE_EMAIL) ?> &middot; <?= e(SITE_PHONE) ?>
    <?php if ($isNote): ?><br>This is a delivery note, not a request for payment.<?php endif; ?>
  </footer>
</main>

</body>
</html>
