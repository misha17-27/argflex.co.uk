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
  </div>
</article>
