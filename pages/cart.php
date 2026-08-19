<?php
declare(strict_types=1);

set_page([
    'title'       => 'Shopping cart — ' . SITE_NAME,
    'description' => 'Your Arg Flex shopping cart.',
    'crumbs'      => [['label' => 'Cart']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow"><?= page_text('/cart/', 'eyebrow', 'Your order') ?></span>
    <h1><?= page_text('/cart/', 'title', 'Shopping cart') ?></h1>
    <p><?= page_text('/cart/', 'intro', 'All prices exclude VAT. Cut lengths are prepared to order.') ?></p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <div class="cart-grid" data-cart-page>
      <div class="cart-main">
        <div class="cart-empty" data-cart-empty>
          <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 5h2l2.2 10.4a2 2 0 0 0 2 1.6h6.9a2 2 0 0 0 2-1.55L21 8H6.5"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>
          <h3><?= page_text('/cart/', 'empty_title', 'Your cart is empty') ?></h3>
          <p><?= page_text('/cart/', 'empty_text', 'Nothing added yet. Browse the catalogue and add the lengths you need.') ?></p>
          <a class="btn btn-primary" href="/shop/"><?= page_text('/cart/', 'empty_btn', 'Return to shop') ?></a>
        </div>
        <table class="cart-table" data-cart-table hidden>
          <thead><tr><th colspan="2">Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
          <tbody data-cart-rows></tbody>
        </table>
      </div>
      <aside class="cart-side" data-cart-side hidden>
        <h3>Order summary</h3>
        <div class="row"><span>Subtotal (excl. VAT)</span><b data-cart-subtotal>&pound;0.00</b></div>
        <div class="row"><span>VAT at <?= (int) setting('vat_rate') ?>%</span><b data-cart-vat>&pound;0.00</b></div>
        <div class="row"><span>Delivery</span><b data-cart-ship>&mdash;</b></div>
        <div class="row total"><span>Total</span><b data-cart-total>&pound;0.00</b></div>
        <p class="hint">Free UK delivery on orders over <?= e(money((int) setting('free_shipping'))) ?> excl. VAT.</p>
        <a class="btn btn-primary" href="/checkout/" style="width:100%;justify-content:center">Proceed to checkout</a>
        <a class="btn btn-out" href="/shop/" style="width:100%;justify-content:center;margin-top:10px">Continue shopping</a>
      </aside>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
