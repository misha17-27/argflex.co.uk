<?php
/**
 * An attribute term's archive — every product made in one bore size, or
 * available in one length.
 *
 * These are not decoration. Thirty-five of them are indexed on the live site
 * as /inner-diameter/8mm/ and /length/50m/, and dropping them at migration
 * would be the classic way to lose rankings. The title is copied from the
 * live page exactly: the term, a hyphen, and the domain — which is neither
 * the separator nor the site name every other page here uses. Live sets no
 * meta description on them, so neither do we.
 *
 * @var array $term       the term, with its attribute name and slug
 */
declare(strict_types=1);

$items = products_with_term($term['attribute_slug'], $term['slug']);

$sort  = (string) ($_GET['sort'] ?? 'default');
$items = sort_products($items, $sort);

$paged = paginate($items, (int) ($_GET['page'] ?? 1));
$items = $paged['items'];

/* WHAT THESE PAGES USED TO SAY, AND WHY IT CHANGED.
   The title was the bare term and the domain — "5m - argflex.co.uk" — copied
   from WordPress, and the meta description was deliberately empty because
   WordPress set none. Keeping both identical was the right call at migration:
   it is how you carry rankings across. Six weeks on, Search Console shows what
   it bought. These 23 pages are indexed and earn nothing measurable, because a
   title that is only a number matches no commercial search, and an empty
   description lets Google quote the navigation.

   So the term still leads the title — whatever these pages match on, they go
   on matching — and the words a buyer actually types follow it. "Cut to
   length" and "per metre" appear in no other title on the site, and cutting to
   length is the business: carriage here is priced by the metre.

   The description and the intro are built from THIS term's own product set, so
   two sizes never read alike. A blurb written per axis would stamp the same
   sentence on 25 pages and make the duplication worse, not better. */
$axis     = lower($term['attribute']);
$isLength = $term['attribute_slug'] === 'length';
$headline = $isLength
    ? $term['name'] . ' hose, cut to length'
    : $term['name'] . ' bore hose, cut to length';

/* What is genuinely true of this size, read off the products in it. */
$standards = [];
foreach ($items as $p) {
    if (preg_match_all('~\b(SAE\s?J\d+\s?R\d+|DIN\s?\d{4,5}(?:\s?[AB])?)\b~i',
                       (string) ($p['name'] ?? '') . ' ' . strip_tags((string) ($p['short'] ?? '')), $m)) {
        foreach ($m[1] as $s) $standards[strtoupper(preg_replace('~\s+~', ' ', $s))] = true;
    }
}
$standards = array_slice(array_keys($standards), 0, 3);

$cheapest = null;
foreach ($items as $p) {
    $v = (int) ($p['price_min'] ?? 0);
    if ($v > 0 && ($cheapest === null || $v < $cheapest)) $cheapest = $v;
}

/* A length archive that quotes a per-metre price is the same page at every
   length — which is why /length/5m/ and /length/30m/ list the same three
   hoses and read identically, and why Google indexes one and drops the other.
   The number that actually differs is what a coil of THAT length costs, so
   that is the number these pages carry. It is also the query: people search
   "30m fuel hose price", not "fuel hose per metre". */
$metres  = $isLength && preg_match('~([\d.]+)\s*m~i', $term['name'], $m) ? (float) $m[1] : 0.0;
$atThis  = ($metres > 0 && $cheapest) ? (int) round($cheapest * $metres) : null;

$blurb = $paged['total'] . ' hose' . ($paged['total'] === 1 ? '' : 's')
       . ' with ' . $axis . ' ' . $term['name']
       . ($standards ? ', including ' . natural_list($standards) : '')
       . ($atThis    ? ', from ' . money($atThis) . ' for the ' . $term['name'] . ' run'
                     : ($cheapest ? ', from ' . money($cheapest) . ' per metre' : ''))
       . '. Cut to length and shipped from the UK.';

set_page([
    'title'       => $headline . ' - argflex.co.uk',
    'description' => clip($blurb, 158),
    'canonical'   => attribute_term_url($term['attribute_slug'], $term['slug']),
    'crumbs'      => [
        ['label' => 'Shop', 'url' => '/shop/'],
        ['label' => $term['attribute']],
        ['label' => $term['name']],
    ],
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow"><?= e($term['attribute']) ?></span>
    <h1><?= e($headline) ?></h1>
    <p><?= (int) $paged['total'] ?> product<?= $paged['total'] === 1 ? '' : 's' ?>
       in the catalogue with <?= e(lower($term['attribute'])) ?> <?= e($term['name']) ?>,
       priced per metre<?= price_suffix() !== '' ? ' ' . e(price_suffix()) : '' ?>.</p>
    <?php
      /* The only genuinely per-term prose on the page. Everything in it is
         read off the products listed below, so no two sizes say the same
         thing — which is the whole point. A sentence written once and stamped
         on 25 archives would deepen the duplication it is meant to cure. */
    ?>
    <p class="pg-lede">
      <?php if ($standards): ?>
        Made to <?= e(natural_list($standards)) ?>.
      <?php endif; ?>
      <?php if ($isLength && $atThis): ?>
        A <?= e($term['name']) ?> run starts at <?= e(money($atThis)) ?><?= price_suffix() !== '' ? ' ' . e(price_suffix()) : '' ?>,
        cut to that length before it ships.
      <?php elseif ($isLength): ?>
        Ordered at <?= e($term['name']) ?>, cut to that length before it ships.
      <?php else: ?>
        <?= $cheapest ? 'From ' . e(money($cheapest)) . ' per metre. ' : '' ?>Every hose here
        measures <?= e($term['name']) ?> on the inside, so a coupling or clamp for that bore
        will fit any of them.
      <?php endif; ?>
      Cut lengths are prepared to order and dispatched from the UK.
    </p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <div class="toolbar">
      <span><?= count($items) ?> product<?= count($items) === 1 ? '' : 's' ?></span>
      <form method="get" class="sorter">
        <label for="sort">Sort</label>
        <select id="sort" name="sort" onchange="this.form.submit()">
          <option value="default"    <?= $sort === 'default'    ? 'selected' : '' ?>>Default</option>
          <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Name A–Z</option>
          <option value="price-asc"  <?= $sort === 'price-asc'  ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Price: high to low</option>
        </select>
      </form>
    </div>

    <?php if ($items): ?>
      <h2 class="sr">Products with <?= e(lower($term['attribute'])) ?> <?= e($term['name']) ?></h2>
      <div class="prods">
        <?php foreach ($items as $p): ?>
          <?php include ROOT_DIR . '/partials/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <?php $query = array_diff_key($_GET, ['page' => 1]);
            $base  = attribute_term_url($term['attribute_slug'], $term['slug']);
            require ROOT_DIR . '/partials/pager.php'; ?>
    <?php else: ?>
      <p>Nothing is listed in this size yet.
         <a href="/contacts/">Ask us</a> — we cut to order.</p>
    <?php endif; ?>

    <?php
      /* The other sizes on this axis, so a visitor who landed here from a
         search can move sideways rather than back out to the shop. */
      $sibling = find_attribute($term['attribute_slug']);
    ?>
    <?php if ($sibling && count($sibling['terms']) > 1): ?>
      <h2 class="sr">Other <?= e(lower($term['attribute'])) ?> sizes</h2>
      <div class="subcats" style="margin-top:34px">
        <?php foreach ($sibling['terms'] as $t): ?>
          <?php if ($t['slug'] === $term['slug']) continue; ?>
          <a href="<?= e(attribute_term_url($term['attribute_slug'], $t['slug'])) ?>">
            <b><?= e($t['name']) ?></b>
            <span><?= count(products_with_term($term['attribute_slug'], $t['slug'])) ?> products</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
