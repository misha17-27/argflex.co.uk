<?php
/**
 * Single product: gallery, spec table, variant picker, description, related.
 * @var array $product
 */
declare(strict_types=1);

$p        = $product;
$specs    = parse_specs($p['short']);
$primary  = primary_category($p);
$parent   = $primary && $primary['parent'] !== '' ? find_category($primary['parent']) : null;
$related  = related_products($p, 4);
$img      = $p['images'][0] ?? null;

$crumbs = [];
if ($parent)  $crumbs[] = ['label' => $parent['name'],  'url' => category_url($parent)];
if ($primary) $crumbs[] = ['label' => $primary['name'], 'url' => category_url($primary)];
$crumbs[] = ['label' => $p['name']];

$metaBits = array_slice(array_map(fn($s) => $s['label'] . ': ' . $s['value'], $specs), 0, 2);

set_page([
    'title'       => $p['name'] . ' — ' . SITE_NAME,
    'description' => clip($metaBits ? implode('. ', $metaBits) : strip_tags($p['short']), 160),
    'crumbs'      => $crumbs,
    'preload'     => $img ? '/' . $img : null,
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="product">
  <div class="wrap">
    <div class="p-grid">
      <div class="p-media">
        <div class="p-main">
          <?php if ($img): ?>
            <img id="p-img" src="/<?= e($img) ?>" alt="<?= e($p['name']) ?>" width="756" height="588" fetchpriority="high">
          <?php endif; ?>
        </div>
        <?php if (count($p['images']) > 1): ?>
          <div class="p-thumbs">
            <?php foreach ($p['images'] as $i => $src): ?>
              <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="/<?= e($src) ?>" aria-label="View image <?= $i + 1 ?>">
                <img src="/<?= e($src) ?>" alt="" loading="lazy" width="90" height="68">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="p-info">
        <span class="eyebrow"><?= e(product_cat_label($p)) ?></span>
        <h1><?= e($p['name']) ?></h1>

        <div class="p-price">
          <b><?= e(price_label($p)) ?></b>
          <small><?= $p['price_min'] > 0 ? 'Per metre · excluding VAT' : 'Contact us for a quotation' ?></small>
        </div>

        <?php if ($specs): ?>
          <div class="p-facts">
            <?php foreach ($specs as $s): ?>
              <p><b><?= e($s['label']) ?>:</b> <?= e($s['value']) ?></p>
            <?php endforeach; ?>
          </div>
        <?php elseif ($p['short']): ?>
          <div class="p-facts p-short"><?= $p['short'] ?></div>
        <?php endif; ?>

        <?php if ($p['variants']):
            $variantMap = [];
            foreach ($p['variants'] as $v) {
                $variantMap[$v['key']] = ['price' => (int) $v['price'], 'label' => $v['label']];
            }
            $pickable = array_values(array_filter($p['attrs'], fn($a) => $a['variation'] && $a['terms']));
            // an attribute with a single option is fixed, so preselect it
        ?>
          <form class="p-buy" data-buy data-variants='<?= e(json_encode($variantMap, JSON_UNESCAPED_UNICODE)) ?>'>
            <div class="swatches">
              <?php foreach ($pickable as $a):
                  $single = count($a['terms']) === 1; ?>
                <div class="sw-row" data-attr="<?= e($a['name']) ?>">
                  <span class="sw-label"><?= e($a['name']) ?></span>
                  <div class="sw-opts">
                    <?php foreach ($a['terms'] as $t): ?>
                      <button type="button" class="sw<?= $single ? ' on' : '' ?>"
                              data-value="<?= e($t['slug']) ?>"
                              aria-pressed="<?= $single ? 'true' : 'false' ?>"><?= e($t['name']) ?></button>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              <button type="button" class="sw-clear" data-clear hidden>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                Clear
              </button>
            </div>
            <p class="sw-none" hidden>That combination is not stocked — <a href="/contacts/?product=<?= e($p['slug']) ?>">ask us about it</a>.</p>
            <div class="p-actions">
              <div class="qty">
                <button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                <input type="number" name="qty" value="1" min="1" max="999" aria-label="Quantity">
                <button type="button" data-step="1" aria-label="Increase quantity">+</button>
              </div>
              <button class="btn btn-primary" type="submit"
                      data-add-to-cart
                      data-slug="<?= e($p['slug']) ?>"
                      data-title="<?= e($p['name']) ?>"
                      data-image="/<?= e($img ?? '') ?>">Add to cart</button>
            </div>
            <p class="p-total" hidden>Total: <b>—</b></p>
          </form>
        <?php elseif ($p['price_min'] > 0): ?>
          <form class="p-buy" data-buy>
            <div class="p-actions">
              <div class="qty">
                <button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                <input type="number" name="qty" value="1" min="1" max="999" aria-label="Quantity">
                <button type="button" data-step="1" aria-label="Increase quantity">+</button>
              </div>
              <button class="btn btn-primary" type="submit"
                      data-add-to-cart
                      data-slug="<?= e($p['slug']) ?>"
                      data-title="<?= e($p['name']) ?>"
                      data-price="<?= (int) $p['price_min'] ?>"
                      data-image="/<?= e($img ?? '') ?>">Add to cart</button>
            </div>
          </form>
        <?php else: ?>
          <div class="p-actions">
            <a class="btn btn-primary" href="/contacts/?product=<?= e($p['slug']) ?>">Request a price</a>
          </div>
        <?php endif; ?>

        <div class="p-actions-2">
          <button class="linkish" type="button" data-wishlist data-slug="<?= e($p['slug']) ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 20s-7.5-4.6-7.5-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7.5 3C19.5 15.4 12 20 12 20z"/></svg>
            <span>Add to wishlist</span>
          </button>
          <a class="linkish" href="/contacts/?product=<?= e($p['slug']) ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 15a2 2 0 0 1-2 2H8l-4 3V6a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
            <span>Ask about this product</span>
          </a>
        </div>

        <ul class="p-meta">
          <li><span>SKU</span><b><?= e($p['sku'] !== '' ? $p['sku'] : 'N/A') ?></b></li>
          <li><span>Categories</span><b>
            <?php $names = [];
              foreach ($p['cats'] as $slug) { if ($c = find_category($slug)) $names[] = '<a href="' . e(category_url($c)) . '">' . e($c['name']) . '</a>'; }
              echo implode(', ', $names) ?: '—';
            ?>
          </b></li>
          <li><span>Delivery</span><b>Dispatched from the UK</b></li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="p-tabs">
  <div class="wrap">
    <div class="tabs-nav" role="tablist">
      <button class="on" type="button" role="tab" data-tab="desc">Description</button>
      <button type="button" role="tab" data-tab="spec">Additional information</button>
      <button type="button" role="tab" data-tab="ship">Delivery &amp; returns</button>
    </div>

    <div class="tab-panel on" data-panel="desc">
      <?php if ($p['desc']): ?>
        <div class="rich"><?= $p['desc'] ?></div>
      <?php else: ?>
        <div class="rich"><?= $p['short'] ?: '<p>Technical details on request.</p>' ?></div>
      <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="spec">
      <table class="spec-table">
        <tbody>
          <?php foreach ($specs as $s): ?>
            <tr><th><?= e($s['label']) ?></th><td><?= e($s['value']) ?></td></tr>
          <?php endforeach; ?>
          <?php foreach ($p['attrs'] as $a): ?>
            <tr><th><?= e($a['name']) ?></th><td><?= e(implode(', ', array_column($a['terms'], 'name'))) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$specs && !$p['attrs']): ?>
            <tr><th>Details</th><td>Available on request — <a href="/contacts/">contact us</a>.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($p['variants']): ?>
        <h3 class="pt-h">Price per option</h3>
        <table class="spec-table">
          <thead><tr><th>Option</th><th>Price (excl. VAT)</th></tr></thead>
          <tbody>
            <?php foreach ($p['variants'] as $v): ?>
              <tr><th><?= e($v['label']) ?></th><td><?= e(money((int) $v['price'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="ship">
      <div class="rich">
        <p>Stocked lines ordered before 14:00 on a working day are picked and packed the same day and dispatched from the UK. Cut lengths are prepared to order.</p>
        <p>Our refund and returns policy lasts 30 days from delivery. Items must be unused, in their original packaging and accompanied by proof of purchase. Cut-to-length hose prepared to your specification cannot be returned unless it is faulty.</p>
        <p>Full terms are on the <a href="/refund_returns/">refunds and returns</a> page, or call <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a>.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section style="background:var(--bg-soft);border-top:1px solid var(--line)">
  <div class="wrap">
    <div class="sec-head">
      <div>
        <span class="eyebrow">You may also need</span>
        <h2 style="margin-top:12px">Related products</h2>
      </div>
      <a class="link-more" href="<?= e($primary ? category_url($primary) : '/shop/') ?>">More from this category
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
    <div class="prods">
      <?php foreach ($related as $p) { include ROOT_DIR . '/partials/product-card.php'; } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
