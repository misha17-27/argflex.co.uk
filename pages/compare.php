<?php
declare(strict_types=1);

$a = find_product((string) ($_GET['a'] ?? ''));
$b = find_product((string) ($_GET['b'] ?? ''));

set_page([
    'title'       => 'Compare products — ' . SITE_NAME,
    'description' => 'Compare hose specifications side by side.',
    'crumbs'      => [['label' => 'Compare']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow"><?= page_text('/compare/', 'eyebrow', 'Side by side') ?></span>
    <h1><?= page_text('/compare/', 'title', 'Compare products') ?></h1>
    <p><?= page_text('/compare/', 'intro', 'Pick any two lines from the catalogue and see their specifications next to each other.') ?></p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <form class="compare-pick" method="get">
      <?php foreach (['a' => 'A', 'b' => 'B'] as $key => $label): ?>
        <div class="fld">
          <label for="cmp-<?= $key ?>">Product <?= $label ?></label>
          <select id="cmp-<?= $key ?>" name="<?= $key ?>" onchange="this.form.submit()">
            <option value="">Select a product…</option>
            <?php foreach (all_products() as $item): ?>
              <option value="<?= e($item['slug']) ?>" <?= ($_GET[$key] ?? '') === $item['slug'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endforeach; ?>
    </form>

    <?php if ($a || $b):
        $rows = [];
        foreach ([$a, $b] as $side => $prod) {
            if (!$prod) continue;
            foreach (parse_specs($prod['short']) as $s) {
                $rows[$s['label']] = $rows[$s['label']] ?? ['', ''];
                $rows[$s['label']][$side] = $s['value'];
            }
            foreach ($prod['attrs'] as $at) {
                $rows[$at['name']] = $rows[$at['name']] ?? ['', ''];
                $rows[$at['name']][$side] = implode(', ', array_column($at['terms'], 'name'));
            }
            $rows['Price'] = $rows['Price'] ?? ['', ''];
            $rows['Price'][$side] = price_label($prod);
        }
    ?>
      <div class="table-scroll">
        <table class="spec-table compare-table">
          <thead>
            <tr>
              <th></th>
              <th><?php if ($a): ?><a href="<?= e(product_url($a)) ?>"><?= e($a['name']) ?></a><?php else: ?>&mdash;<?php endif; ?></th>
              <th><?php if ($b): ?><a href="<?= e(product_url($b)) ?>"><?= e($b['name']) ?></a><?php else: ?>&mdash;<?php endif; ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $label => $vals): ?>
              <tr>
                <th><?= e((string) $label) ?></th>
                <td><?= e($vals[0] !== '' ? $vals[0] : '—') ?></td>
                <td><?= e($vals[1] !== '' ? $vals[1] : '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty">
        <h2>Choose two products above</h2>
        <p>Their tube, reinforcement, cover, temperature range and price will line up side by side.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
