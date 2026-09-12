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
 * The eight prices ARE editable here, and the shipping classes. What is not
 * is the four LENGTH BANDS: the boundaries, the rules that apply them and the
 * two consignment packages all name each other by rate id, and getting one
 * wrong silently reprices every order that lands on it. A price is a number
 * on its own; a boundary is a change to three lists at once, and belongs in
 * data/shipping.php with .data/test_shipping.php in front of you.
 *
 * A model that costs more to send than its length suggests still names its
 * own price on its own Shipping card — a price belongs with the thing it
 * prices, and the table here is only the default.
 *
 * @var array $values
 */

/* shipping_all_rates(), NOT shipping_rates(). The second one answers "what
   does the checkout offer today", and since the offer ticks below started
   working it leaves out every rate the shop has switched off — so the price
   table beneath read a missing key for those, rendered £0.00 with a blank
   name, and printed two PHP warnings per cell. The card's own promise is that
   unticking hides a method "without losing its price"; the screen has to show
   the price it kept. This table is the eight carried rates, on or off. */
$common = shipping_all_rates();
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

<form method="post" class="setform">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="shipping">

  <div class="card pad-card">
    <h2>What the shop charges</h2>
    <p class="hint">The default for every product. Change a price here and it applies to
      everything that does not name its own below. Prices exclude
      <?= e(tax_label()) ?>, which is not charged on delivery.</p>

    <div class="table-scroll">
    <table class="grid rates">
      <thead>
        <tr><th>Length</th><th>1-2 days</th><th>3-4 days</th></tr>
      </thead>
      <tbody>
        <?php foreach ($bands as $band => [$fast, $slow]): ?>
          <tr>
            <td class="band"><b><?= e($band) ?></b></td>
            <?php foreach ([$fast, $slow] as $id): $rate = $common[$id]; ?>
              <td>
                <div class="money-in">
                  <span><?= e(currency_symbol()) ?></span>
                  <input type="number" step="0.01" min="0" max="9999"
                         name="rate[<?= (int) $id ?>][cost]"
                         value="<?= e(number_format($rate['cost'] / 100, 2, '.', '')) ?>"
                         aria-label="<?= e($band) ?>, <?= e($rate['title']) ?>, price">
                </div>
                <input type="text" class="rate-title" maxlength="60"
                       name="rate[<?= (int) $id ?>][title]"
                       value="<?= e($rate['title']) ?>"
                       aria-label="<?= e($band) ?>, name shown at the checkout">
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <p class="hint">The second box is the name the customer sees at the checkout. The four
      LENGTH BANDS are not editable here on purpose: the boundaries, the rules that apply
      them and the two consignment packages all name each other by rate id, so a boundary is
      a change to three lists at once. They are in <code>data/shipping.php</code>, and
      <code>.data/test_shipping.php</code> checks all four, one metre at a time.</p>
  </div>

  <?php /* Which of them the checkout offers, and the one thing most shops
           want to add. Separate from the table above on purpose: that one is
           the prices carried over from WooCommerce and is a record of what the
           old shop charged. What to offer today is a decision, and it is kept
           in the settings so that file stays traceable to the dump. */ ?>
  <?php $free = shipping_free(); ?>
  <div class="card pad-card">
    <h2>What the checkout offers</h2>
    <input type="hidden" name="offer_form" value="1">

    <table class="grid rates">
      <thead><tr><th>Offer it</th><th>Name</th><th>Price</th></tr></thead>
      <tbody>
        <?php foreach (shipping_all_rates() as $id => $r): if ($r['mine']) continue; ?>
          <tr>
            <td>
              <label class="check tight">
                <input type="checkbox" name="offer[]" value="<?= (int) $id ?>" <?= $r['on'] ? 'checked' : '' ?>>
                <span class="sr-only">Offer <?= e($r['title']) ?></span>
              </label>
            </td>
            <td><?= e($r['title']) ?></td>
            <td><?= e(money($r['cost'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p class="hint">Unticking hides a method from the checkout without losing its price.
      Tick it again and exactly what was there comes back.</p>

    <h3>Free delivery</h3>

    <label class="check">
      <input type="checkbox" name="free_on" data-toggle-block="#free-fields" <?= $free['on'] ? 'checked' : '' ?>>
      Offer free delivery
    </label>

    <div id="free-fields" <?= $free['on'] ? '' : 'hidden' ?>>
      <div class="two">
        <div>
          <label for="free_min">On orders from</label>
          <div class="with-unit">
            <span><?= e(currency_symbol()) ?></span>
            <?php /* No min/max/step, and the form does not validate it. The box
                   is deliberately left enabled while the block is hidden so an
                   untick keeps the figure — but HTML5 validation still applies
                   to a field hidden this way, and the browser cannot focus it
                   to complain. A bad figure plus an untick made "Save changes"
                   do nothing at all: no navigation, no message, and the price
                   and shipping-class edits made in the same visit lost. The
                   server clamps it anyway — money_in() takes the number out of
                   whatever was typed and caps it. */ ?>
          <input id="free_min" name="free_min" type="text" inputmode="decimal"
                   value="<?= $free['min_goods'] ? e(number_format($free['min_goods'] / 100, 2, '.', '')) : '' ?>"
                   placeholder="any">
          </div>
          <p class="hint">Blank offers it on every order. The figure is the goods,
            before <?= e(tax_label()) ?> and before delivery.</p>
        </div>
        <div>
          <label for="free_title">Called</label>
          <input id="free_title" name="free_title" type="text" maxlength="60"
                 value="<?= e($free['title']) ?>" placeholder="Free delivery">
          <p class="hint">What the customer sees beside it at the checkout.</p>
        </div>
      </div>
    </div>

    <p class="hint">Measured against the whole basket, not each parcel — an order that
      splits into two was still paid for once, and charging for how we pack it would be
      a rule about us rather than about the order. It is offered whatever the length,
      because the four length bands and the rules behind them are written around the
      eight above.</p>
  </div>

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
            $items = price_basket_lines($lines);
            if (!$items) continue;
            /* shipping_quote(), not shipping_packages(): the quote is where a
               method with a minimum order value is dropped for a basket that
               does not reach it, and where free delivery is moved to the front.
               Reading the packages directly showed free delivery against every
               example here, including the ones the checkout would charge for —
               a preview that disagrees with the till is worse than none. */
            $quote    = shipping_quote($items, 'GB');
            $packages = $quote['packages'];
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
