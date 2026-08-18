<?php
declare(strict_types=1);

set_page([
    'title'       => 'Wishlist — ' . SITE_NAME,
    'description' => 'Products you have saved for later.',
    'crumbs'      => [['label' => 'Wishlist']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow">Saved for later</span>
    <h1>Wishlist</h1>
    <p>Products you have saved while browsing. Stored in this browser only.</p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <div class="cart-empty" data-wishlist-empty>
      <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20s-7.5-4.6-7.5-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7.5 3C19.5 15.4 12 20 12 20z"/></svg>
      <h3>Your wishlist is empty</h3>
      <p>Tap the heart on any product page to keep it here for later.</p>
      <a class="btn btn-primary" href="/shop/">Browse products</a>
    </div>
    <div class="prods" data-wishlist-grid hidden></div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
