<?php
/**
 * What sits under the Add to cart button: the carriage for what is currently
 * chosen, and a way to order the same thing on WhatsApp.
 *
 * Delivery is shown here rather than only at the checkout because carriage on
 * this shop is priced on the METRES, not the money — a 50 m coil and a 1 m
 * offcut of the same hose are charged very differently, and a customer who
 * only finds that out three pages later has every right to be annoyed.
 *
 * The figures are not worked out here. assets/js/site.js asks
 * /delivery-quote.php for the line that is currently selected, so the price
 * shown is the one the checkout will charge, from the same code. Before the
 * answer arrives this block is empty and hidden, so it never shows a guess.
 *
 * Included inside the <form class="p-buy"> on pages/product.php.
 *
 * @var array $p the product
 */

$waMessage = 'Hello, I would like to order: ' . $p['name']
           . ' — ' . SITE_URL . product_url($p);
$waHref    = whatsapp_link($waMessage);
?>

<div class="p-ship" data-ship-estimate hidden>
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg>
  <div>
    <b>Delivery</b>
    <div class="p-ship-opts" data-ship-lines></div>
  </div>
</div>

<?php /* Only once a speed has been chosen. Until then the Total above is the
         goods alone, which is what it says — a figure that quietly included a
         carriage nobody picked would be the shop deciding for them. */ ?>
<div class="p-ship-sum" data-ship-sum hidden>
  <p><span>With delivery</span> <span data-ship-sum-figure></span></p>
  <p class="p-ship-vat"><?= e(tax_label()) ?> is added at the checkout; it is not charged on delivery.</p>
  <button class="btn btn-primary" type="button" data-ship-go>Checkout</button>
</div>

<?php if ($waHref !== ''): ?>
  <a class="btn btn-wa" href="<?= e($waHref) ?>" target="_blank" rel="noopener"
     data-wa data-number="<?= e(whatsapp_number()) ?>" data-base="<?= e($waMessage) ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.6 2 2.2 6.4 2.2 11.84c0 1.9.5 3.7 1.44 5.3L2 22l5-1.6a9.8 9.8 0 0 0 4.87 1.28h.01c5.44 0 9.85-4.4 9.85-9.84C21.73 6.4 17.48 2 12.04 2zm0 17.9h-.01a8.2 8.2 0 0 1-4.16-1.14l-.3-.18-3.1.99.99-3.02-.2-.31a8.13 8.13 0 0 1-1.25-4.36c0-4.5 3.67-8.16 8.18-8.16 2.18 0 4.23.85 5.77 2.4a8.1 8.1 0 0 1 2.4 5.77c0 4.5-3.67 8.16-8.17 8.16zm4.49-6.11c-.25-.13-1.46-.72-1.68-.8-.23-.09-.39-.13-.56.12-.16.25-.64.8-.78.97-.15.16-.29.18-.53.06-.25-.13-1.04-.39-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.15-.25-.02-.39.11-.51.11-.11.25-.29.37-.44.13-.15.17-.25.25-.41.09-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.48c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.2 3.72.59.25 1.05.4 1.4.52.59.18 1.13.16 1.55.1.47-.07 1.46-.6 1.66-1.18.21-.57.21-1.06.15-1.16-.06-.11-.23-.17-.48-.29z"/></svg>
    Order on WhatsApp
  </a>
  <p class="p-wa-note">Opens a chat with the hose, the size and the quantity already written out.</p>
<?php endif; ?>
