<?php
/**
 * Product category listing.
 * @var array $category
 */
declare(strict_types=1);

$items  = products_in_category($category['slug']);
$kids   = child_categories($category['slug']);
$parent = $category['parent'] !== '' ? find_category($category['parent']) : null;

$sort = (string) ($_GET['sort'] ?? 'default');
$items = sort_products($items, $sort);

$crumbs = [];
if ($parent) $crumbs[] = ['label' => $parent['name'], 'url' => category_url($parent)];
$crumbs[] = ['label' => $category['name']];

$blurbs = [
    'rubber-hoses'    => 'Synthetic rubber hose for fuel, oil, gas, coolant, water, chemicals and abrasive media, with textile or steel reinforcement.',
    'pvcpu-hoses'     => 'Lightweight, flexible PVC and polyurethane hose for liquids, compressed air, ventilation and irrigation.',
    'hose-couplings'  => 'Worm-drive clamps and couplings to match every bore size in the catalogue.',
    'oil-products'    => 'Fuel and oil transfer hose to SAE J30 R6, R10 and DIN 73379 specifications.',
    'gas'             => 'Oxygen, acetylene, LPG and twin line welding hose.',
    'water'           => 'Water delivery and irrigation hose for agriculture, construction and garden use.',
    'cooling-system'  => 'Coolant and heater hose resistant to glycol at temperatures up to +125 °C.',
    'chemicals'       => 'Silicone and chemical resistant hose for laboratory, pharmaceutical and industrial transfer.',
    'abrasive-materials' => 'Abrasion resistant hose for sandblasting and the conveying of abrasive solids.',
    'ventilation'     => 'Steel helix reinforced PVC ducting for conditioning and ventilation plants.',
    'flat-water'      => 'Flat and layflat hose for water supply and delivery.',
    'oil-products-pvcpu-hoses' => 'Transparent PVC tube for gasoline, diesel and oil transfer.',
];
$blurb = $category['description'] ?: ($blurbs[$category['slug']] ?? 'Products in the ' . $category['name'] . ' range, priced per metre and excluding VAT.');

$paged = paginate($items, (int) ($_GET['page'] ?? 1));
$items = $paged['items'];

set_page([
    'title'       => $category['name'] . ' — ' . SITE_NAME,
    'description' => clip($blurb, 160),
    'crumbs'      => $crumbs,
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow"><?= $parent ? e($parent['name']) : 'Category' ?></span>
    <h1><?= e($category['name']) ?></h1>
    <p><?= e($blurb) ?></p>
  </div>
</section>

<?php if ($kids): ?>
<section style="padding:0 0 8px">
  <div class="wrap">
    <div class="subcats">
      <?php foreach ($kids as $k): ?>
        <a href="<?= e(category_url($k)) ?>">
          <b><?= e($k['name']) ?></b>
          <span><?= count(products_in_category($k['slug'])) ?> products</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

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
      <div class="prods">
        <?php foreach ($items as $i => $p) { $eager = $i < 4; include ROOT_DIR . '/partials/product-card.php'; } ?>
      </div>
      <?php
      $query = ['sort' => $sort !== 'default' ? $sort : null];
      $base  = category_url($category);
      require ROOT_DIR . '/partials/pager.php';
      ?>
    <?php else: ?>
      <div class="empty">
        <h3>Nothing listed here yet</h3>
        <p>This category is in the catalogue but has no stocked lines at the moment. Tell us what you need and we will source it.</p>
        <a class="btn btn-primary" href="/contacts/">Request a quote</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="cta">
      <div>
        <h2>Not sure which one fits?</h2>
        <p>Send the medium, the bore size and the working pressure — we will confirm the right hose and the coupling to match it.</p>
      </div>
      <a class="btn" href="/contacts/">Ask a technical question</a>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
