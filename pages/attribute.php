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

set_page([
    'title'       => $term['name'] . ' - argflex.co.uk',
    'description' => '',
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
    <h1><?= e($term['name']) ?></h1>
    <p><?= (int) $paged['total'] ?> product<?= $paged['total'] === 1 ? '' : 's' ?>
       in the catalogue with <?= e(lower($term['attribute'])) ?> <?= e($term['name']) ?>,
       priced per metre<?= price_suffix() !== '' ? ' ' . e(price_suffix()) : '' ?>.</p>
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
