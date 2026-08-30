<?php
/**
 * Product card.
 * @var array $p       product
 * @var string $badge  optional badge text
 * @var bool $eager    load the image eagerly (above the fold)
 */
declare(strict_types=1);
$badge = $badge ?? '';
$eager = $eager ?? false;
$img   = $p['images'][0] ?? null;
?>
<article class="card" data-cats="<?= e(implode(' ', $p['cats'])) ?>" data-price="<?= (int) $p['price_min'] ?>" data-name="<?= e(strtolower($p['name'])) ?>">
  <a class="ph" href="<?= e(product_url($p)) ?>" tabindex="-1" aria-hidden="true">
    <?php if (!product_in_stock($p)): ?><span class="tag out">Out of stock</span>
    <?php elseif ($badge): ?><span class="tag o"><?= e($badge) ?></span><?php endif; ?>
    <?php if ($img): ?>
      <img src="/<?= e($img) ?>" alt="<?= e($p['name']) ?>" width="400" height="300"
           <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
      <?php /* The second photograph, shown on hover. Pure CSS — a card that
               needs JavaScript to change a picture is a card that flickers on
               a slow connection. Only rendered when there IS a second one, so
               a product with a single photo behaves exactly as before rather
               than fading into a copy of itself.

               Nothing in the catalogue carries a second image yet; add one
               under Images on the product and it starts working there. */ ?>
      <?php if (($alt = $p['images'][1] ?? '') !== ''): ?>
        <img class="ph-alt" src="/<?= e($alt) ?>" alt="" width="400" height="300"
             loading="lazy" aria-hidden="true">
      <?php endif; ?>
    <?php endif; ?>
  </a>
  <?php if (on_sale($p)): ?><span class="flash-sale"><?= (int) sale_percent($p) ?>% off</span><?php endif; ?>
  <?php $stockNow = stock_state($p); ?>
  <?php if ($stockNow['state'] === 'low'): ?><span class="flash-low"><?= e($stockNow['label']) ?></span><?php endif; ?>
  <div class="bd">
    <span class="cat-l"><?= e(product_cat_label($p)) ?></span>
    <h3><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></h3>
    <?php if (reviews_enabled() && ($cardRating = rating_summary($p['slug']))): ?>
      <span class="card-rating"><?= stars((float) $cardRating['average'], 13) ?>
        <em>(<?= (int) $cardRating['count'] ?>)</em></span>
    <?php endif; ?>
    <div class="price">
      <?php if (($was = was_label($p)) !== ''): ?><s><?= e($was) ?></s> <?php endif; ?>
      <?= e(price_label($p)) ?>
      <small><?= $p['price_min'] > 0 ? e(price_suffix() !== '' ? ucfirst(price_suffix()) : '') : 'Contact us for a quote' ?><?= $p['variants'] ? ' · ' . count($p['variants']) . ' options' : '' ?></small>
    </div>
    <?php /* The heart sits beside the button rather than floating over the
             photo: on a touch screen an overlay is either too small to hit or
             large enough to be pressed on the way to opening the product. */ ?>
    <div class="card-buy">
      <?php if (!product_in_stock($p)): ?>
        <a class="btn btn-dark add" href="/contacts/?product=<?= e($p['slug']) ?>">Ask when it is back</a>
      <?php elseif ($p['variants']): ?>
        <a class="btn btn-dark add" href="<?= e(product_url($p)) ?>">Select options</a>
      <?php elseif ($p['price_min'] > 0): ?>
        <button class="btn btn-dark add" type="button"
                data-add-to-cart
                data-slug="<?= e($p['slug']) ?>"
                data-title="<?= e($p['name']) ?>"
                data-price="<?= (int) effective_min($p) ?>"
                data-max="<?= (int) stock_ceiling($p) ?>"
                data-image="/<?= e($img ?? '') ?>">Add to cart</button>
      <?php else: ?>
        <a class="btn btn-dark add" href="/contacts/?product=<?= e($p['slug']) ?>">Request price</a>
      <?php endif; ?>
      <?php /* A look at the specification without leaving the listing. These
               hoses are told apart by a standard and a bore range, not by the
               photograph, so comparing them means reading — and opening each
               one to read six lines is the slow way to do it. */ ?>
      <button class="card-heart" type="button" data-quick-view data-slug="<?= e($p['slug']) ?>"
              title="Quick view" aria-label="Quick view: <?= e($p['name']) ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M1.8 12S5.6 5.5 12 5.5 22.2 12 22.2 12 18.4 18.5 12 18.5 1.8 12 1.8 12z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
      <button class="card-heart" type="button" data-wishlist data-slug="<?= e($p['slug']) ?>"
              title="Add to wishlist" aria-label="Add <?= e($p['name']) ?> to your wishlist">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 20s-7.5-4.6-7.5-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7.5 3C19.5 15.4 12 20 12 20z"/></svg>
      </button>
    </div>
  </div>
</article>
