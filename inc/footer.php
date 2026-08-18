<?php declare(strict_types=1); ?>
<footer class="ftr">
  <div class="wrap">
    <div class="cols">
      <div>
        <img src="/assets/img/site/logo.png" alt="<?= SITE_NAME ?>" width="140" height="38" loading="lazy">
        <p style="font-size:14.5px;max-width:34ch"><?= SITE_TAG ?>. Rubber and plastic hose products supplied across the UK and Europe.</p>
        <div class="soc" style="margin-top:22px">
          <a href="https://www.facebook.com/" aria-label="Facebook"><svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.25-1.5 1.55-1.5h1.65V4.6c-.3-.04-1.3-.13-2.45-.13-2.42 0-4.08 1.48-4.08 4.2v2.23H7.5V14h2.67v8z"/></svg></a>
          <a href="https://www.instagram.com/" aria-label="Instagram"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg></a>
          <a href="https://wa.me/<?= SITE_PHONE_HREF ?>" aria-label="WhatsApp"><svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3-.8-2.5-1.1-4.1-3.7-4.2-3.9-.1-.2-1-1.4-1-2.6s.6-1.8.9-2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.5c-.1.2-.3.3-.1.6.2.3.7 1.2 1.6 1.9 1.1.9 1.5 1 1.7 1.1.2.1.4.1.5-.1l.7-.8c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.1.1.5-.1 1.1z"/></svg></a>
        </div>
      </div>
      <div>
        <h4>Products</h4>
        <ul>
          <?php foreach (top_categories() as $ftCat): ?>
            <li><a href="<?= e(category_url($ftCat)) ?>"><?= e($ftCat['name']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="/product-category/rubber-hoses/oil-products/">Fuel &amp; oil</a></li>
          <li><a href="/product-category/rubber-hoses/gas/">Gas &amp; welding</a></li>
          <li><a href="/shop/">All products</a></li>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="/about-us/">About us</a></li>
          <li><a href="/blog/">Blog</a></li>
          <li><a href="/contacts/">Contact</a></li>
          <li><a href="/refund_returns/">Refunds &amp; returns</a></li>
          <li><a href="/wishlist/">Wishlist</a></li>
          <li><a href="/checkout/">Checkout</a></li>
          <li><a href="/my-account/">My account</a></li>
        </ul>
      </div>
      <div>
        <h4>Get in touch</h4>
        <div class="c-row">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 16.5v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.7 2 2 0 0 1 3.1 1.5h3a2 2 0 0 1 2 1.7c.1 1 .35 1.9.7 2.8a2 2 0 0 1-.45 2.1L7.1 9.4a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.35 1.85.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
          <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a>
        </div>
        <div class="c-row">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
          <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>
        </div>
        <div class="c-row">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
          <span><?= SITE_ADDR ?></span>
        </div>
        <div class="c-row">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg>
          <span><?= SITE_HOURS_WEEK ?><br><?= SITE_HOURS_WEEKEND ?></span>
        </div>
      </div>
    </div>
    <div class="bot">
      <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
      <span>Prices shown exclude VAT.</span>
    </div>
  </div>
</footer>

<div class="drawer" id="dr">
  <div class="sc" data-close></div>
  <div class="pn" role="dialog" aria-label="Menu">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <img src="/assets/img/site/logo.png" alt="<?= SITE_NAME ?>" style="height:32px" loading="lazy">
      <button class="x" type="button" data-close aria-label="Close menu">&times;</button>
    </div>
    <form class="dr-search" action="/shop/" method="get">
      <input type="search" name="q" placeholder="Search products…" aria-label="Search products">
    </form>
    <nav>
      <a href="/">Home</a>
      <a href="/shop/">Shop</a>
      <?php foreach (top_categories() as $drCat): ?>
        <a href="<?= e(category_url($drCat)) ?>"><?= e($drCat['name']) ?></a>
      <?php endforeach; ?>
      <a href="/about-us/">About us</a>
      <a href="/blog/">Blog</a>
      <a href="/contacts/">Contacts</a>
      <a href="/wishlist/">Wishlist</a>
      <a href="/my-account/">My account</a>
    </nav>
    <a class="btn btn-primary" href="/contacts/" style="width:100%;justify-content:center;margin-top:24px">Request a quote</a>
  </div>
</div>

<div class="mini" id="mini" hidden>
  <div class="mini-sc" data-mini-close></div>
  <aside class="mini-pn" role="dialog" aria-modal="true" aria-labelledby="mini-h">
    <header class="mini-hd">
      <h2 id="mini-h">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>
        <span data-mini-title>Added to cart</span>
      </h2>
      <button type="button" class="mini-x" data-mini-close aria-label="Close">&times;</button>
    </header>

    <div class="mini-body">
      <ul class="mini-rows" data-mini-rows></ul>
      <p class="mini-none" data-mini-none hidden>Your cart is empty.</p>
    </div>

    <footer class="mini-ft">
      <div class="mini-sum"><span>Subtotal</span><b data-mini-subtotal>&pound;0.00</b></div>
      <p class="mini-note">Excluding VAT and delivery.</p>
      <a class="btn btn-primary" href="/checkout/" style="width:100%;justify-content:center">Checkout</a>
      <a class="btn btn-out" href="/cart/" style="width:100%;justify-content:center;margin-top:10px">View cart</a>
      <button type="button" class="mini-cont" data-mini-close>Continue shopping</button>
    </footer>
  </aside>
</div>

<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
