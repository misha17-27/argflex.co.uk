<?php
/**
 * Delivery, as it actually works.
 *
 * This screen used to edit zones and methods, and wrote a setting nothing
 * read any more: carriage is priced on the METRES in the basket, not on the
 * order value, and a table of flat prices per zone could not express that
 * without lying about it. It also called shipping_quote() with the old
 * arguments, which is why it was throwing a fatal.
 *
 * What is editable here is the shipping classes. The bands and their prices
 * live in data/shipping.php, and a model that charges its own price says so
 * on its own Shipping card — a price belongs with the thing it prices.
 *
 * @var array $values
 */

$common = shipping_rates();
$bands  = [
    'Up to 5 metres'  => [11, 14],
    '5 to 10 metres'  => [17, 18],
    '10 to 25 metres' => [12, 15],
    '25 to 50 metres' => [13, 16],
];

/* Which models carry a price of their own, so the shop can see them in one
   place without opening every product. */
$ownPrices = [];
foreach (all_products(true) as $p) {
    if (!empty($p['delivery'])) {
        $ownPrices[] = ['name' => $p['name'], 'slug' => $p['slug'],
                        'where' => 'the product', 'bands' => count($p['delivery'])];
    }
    $options = 0;
    $howMany = 0;
    foreach ((array) $p['variants'] as $v) {
        if (empty($v['delivery'])) continue;
        $options++;
        $howMany = max($howMany, count($v['delivery']));
    }
    if ($options) {
        $ownPrices[] = ['name' => $p['name'], 'slug' => $p['slug'],
                        'where' => $options . ' option' . ($options === 1 ? '' : 's'),
                        'bands' => $howMany];
    }
}

/* Which classes are actually in use, on a product or on one of its options. */
$used = [];
foreach (all_products(true) as $p) {
    foreach (array_merge([$p], (array) $p['variants']) as $holder) {
        $class = trim((string) ($holder['shipping_class'] ?? ''));
        if ($class !== '') $used[$class] = ($used[$class] ?? 0) + 1;
    }
}
?>

<div class="card pad-card">
  <h2>How delivery is worked out</h2>
  <p class="hint">Carriage is priced on the <b>metres in the basket</b>, not on what it costs.
    A variation's weight is its metre count — one metre of 3 mm tube and one metre of
    32 mm sandblast hose both weigh 1.</p>
  <p class="hint">The basket falls into one band and is offered that band's two speeds.
    A basket can also split into two consignments, each charged on its own; the customer
    sees one Delivery figure and the order screen shows the breakdown.</p>
  <p class="hint">Deliveries go to the United Kingdom only. Anywhere else has no rate at
    all, and the checkout says so rather than taking an order nobody can fulfil.
    <b><?= e(tax_label()) ?> is not charged on delivery.</b></p>
</div>

<div class="card pad-card">
  <h2>What the shop charges</h2>
  <p class="hint">These are in <code>data/shipping.php</code>, with the rules that decide
    which band a basket falls into. They are not edited here because getting a boundary
    wrong silently reprices every order that lands on it — <code>.data/test_shipping.php</code>
    checks all four, one metre at a time.</p>

  <table class="grid">
    <thead><tr><th>Length</th><th>1-2 days</th><th>3-4 days</th></tr></thead>
    <tbody>
      <?php foreach ($bands as $band => [$fast, $slow]): ?>
        <tr>
          <td><b><?= e($band) ?></b></td>
          <td><?= e(money($common[$fast]['cost'])) ?><small><?= e($common[$fast]['title']) ?></small></td>
          <td><?= e(money($common[$slow]['cost'])) ?><small><?= e($common[$slow]['title']) ?></small></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card pad-card">
  <h2>Models that charge their own</h2>
  <?php if (!$ownPrices): ?>
    <p class="hint">None yet. A model that costs more to send than its length suggests —
      ducting, the widest bores — names its price on its own <b>Shipping</b> card, and a
      variable product can set one per option.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th>Product</th><th>Set on</th><th>Bands</th></tr></thead>
      <tbody>
        <?php foreach ($ownPrices as $row): ?>
          <tr>
            <td><a href="/admin/products/<?= e($row['slug']) ?>"><b><?= e($row['name']) ?></b></a></td>
            <td><?= e($row['where']) ?></td>
            <td><?= (int) $row['bands'] ?> of 8</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint">Anything not listed uses the table above.</p>
  <?php endif; ?>
</div>

<form method="post" class="setform">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="shipping">

  <div class="card pad-card">
    <h2>Shipping classes</h2>
    <p class="hint">A name you can put on a product or on one of its options. Nothing prices
      on one today — it is carried because the order archive refers to it.</p>

    <label for="shipping_classes">One per line</label>
    <textarea id="shipping_classes" name="shipping_classes" rows="5"><?= e(implode("\n", shipping_classes())) ?></textarea>

    <?php if ($used): ?>
      <p class="hint">In use:
        <?php $bits = [];
              foreach ($used as $class => $n) $bits[] = '<b>' . e($class) . '</b> on ' . (int) $n;
              echo implode(', ', $bits); ?>.
      </p>
    <?php else: ?>
      <p class="hint">No product carries one yet — set it on a product's Shipping card.</p>
    <?php endif; ?>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
  </div>
</form>

<div class="card pad-card">
  <h2>What this quotes today</h2>
  <p class="hint">Real baskets, priced by the same code the checkout uses.</p>
  <table class="grid">
    <thead><tr><th>Basket</th><th>Metres</th><th>Consignments</th><th>Delivery</th></tr></thead>
    <tbody>
      <?php
        $examples = [
            'One metre of acetylene hose'      => [['acetylene-hose', '8mm|1m', 1]],
            'A fifty-metre coil'               => [['acetylene-hose', '8mm|50m', 1]],
            'A 25 m coil and a 15 m offcut'    => [['acetylene-hose', '8mm|50m', 1],
                                                   ['oxygen-hose-agoma', '6-3mm|15m', 1]],
            'One metre of small-bore PVC tube' => [['pvc-tube-for-petroleum-products-2', '5mm|1m', 1]],
        ];
        foreach ($examples as $label => $rows):
            $lines = [];
            foreach ($rows as [$slug, $option, $qty]) {
                if (!find_product($slug)) continue;
                $lines[] = ['slug' => $slug, 'option' => $option, 'qty' => $qty];
            }
            if (!$lines) continue;
            $items    = price_basket_lines($lines);
            $packages = shipping_packages($items, 'GB');
            if (!$items) continue;
      ?>
        <tr>
          <td><b><?= e($label) ?></b></td>
          <td><?= (int) lines_weight($items) ?></td>
          <td><?= count($packages) ?></td>
          <td>
            <?php foreach ($packages as $pkg): ?>
              <?= e(implode(' / ', array_map(fn($r) => money($r['cost']), $pkg['rates']))) ?>
              <small><?= e($pkg['name']) ?></small>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
