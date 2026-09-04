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
          <h2><?= page_text('/cart/', 'empty_title', 'Your cart is empty') ?></h2>
          <p><?= page_text('/cart/', 'empty_text', 'Nothing added yet. Browse the catalogue and add the lengths you need.') ?></p>
          <a class="btn btn-primary" href="/shop/"><?= page_text('/cart/', 'empty_btn', 'Return to shop') ?></a>
        </div>
        <table class="cart-table" data-cart-table hidden>
          <thead><tr><th colspan="2">Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
          <tbody data-cart-rows></tbody>
        </table>
      </div>
      <aside class="cart-side" data-cart-side hidden>
        <h2>Order summary</h2>
        <?php /* The basket lives in this browser and can outlast the shelf: a
                 length added last week may have sold out since. The server
                 re-prices every line and refuses what it cannot sell, and it
                 used to do so in silence — the line stayed in the table, the
                 total quietly stopped agreeing with it. */ ?>
        <p class="cart-dropped" data-cart-dropped hidden></p>
        <div class="row"><span>Subtotal<?= price_suffix() !== '' ? ' (' . e(price_suffix()) . ')' : '' ?></span><b data-cart-subtotal>&pound;0.00</b></div>
        <?php if (tax_enabled()): ?>
          <div class="row"><span><?= e(tax_label()) ?> at <?= (int) tax_rate() ?>%</span><b data-cart-vat><?= e(money(0)) ?></b></div>
        <?php endif; ?>
        <div class="row disc" data-discount-row hidden>
          <span data-discount-label>Discount</span><b data-cart-discount><?= e(money(0)) ?></b>
        </div>
        <div class="row"><span>Delivery</span><b data-cart-ship>&mdash;</b></div>
        <div class="row total"><span>Total</span><b data-cart-total>&pound;0.00</b></div>
        <?php if (coupons_enabled()): ?>
          <form class="coupon" data-coupon>
            <label for="coupon-code">Discount code</label>
            <div class="coupon-row">
              <input id="coupon-code" name="code" type="text" autocomplete="off"
                     spellcheck="false" placeholder="Enter a code">
              <button class="btn btn-dark" type="submit">Apply</button>
            </div>
            <p class="coupon-msg" data-coupon-msg hidden></p>
            <p class="coupon-on" data-coupon-on hidden>
              <b data-coupon-code></b><span data-coupon-title></span>
              <button type="button" data-coupon-remove>Remove</button>
            </p>
          </form>
        <?php endif; ?>

        <a class="btn btn-primary" href="/checkout/" style="width:100%;justify-content:center">Proceed to checkout</a>
        <a class="btn btn-out" href="/shop/" style="width:100%;justify-content:center;margin-top:10px">Continue shopping</a>
      </aside>
    </div>

    <section class="cross" data-cross hidden>
      <div class="sec-head">
        <div>
          <span class="eyebrow">Goes with this</span>
          <h2 style="margin-top:12px"><?= page_text('/cart/', 'cross_title', 'Often needed alongside') ?></h2>
        </div>
      </div>
      <div class="prods" data-cross-grid></div>
    </section>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
