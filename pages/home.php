<?php
declare(strict_types=1);

set_page([
    'title'       => 'Arg Flex Ltd — Industrial Hoses, Fittings & Fluid Transfer Solutions',
    'description' => 'Arg Flex Ltd supplies rubber hoses, PVC/PU hoses and hose couplings for fuel, oil, gas, water, chemical and abrasive transfer. UK stock, cut to length, trade pricing.',
    'body_class'  => 'is-home',
    'preload'     => '/assets/img/site/hero-1.webp',
    'schema'      => [[
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => SITE_NAME,
        'url'      => SITE_URL . '/',
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => SITE_URL . '/shop/?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ]],
]);

$tops     = top_categories();
$featured = featured_products(12);
$posts    = array_slice(all_posts(), 0, 3);

require ROOT_DIR . '/inc/header.php';
?>

<section class="hero" style="padding:0">
  <img class="hero-bg" src="/assets/img/site/hero-1.webp" alt="" width="1600" height="900" fetchpriority="high" decoding="async">
  <div class="wrap">
    <div class="hero-in">
      <span class="eyebrow">Fluid transfer &amp; industrial applications</span>
      <h1>Concentrated range of <em>rubber &amp; plastic</em> hose products</h1>
      <p>Over 35 stocked hose lines for fuel, oil, gas, water, chemicals and abrasive media — cut to length from 1 m to 50 m, with couplings and clamps to match. Shipped from the UK.</p>
      <div class="hero-cta">
        <a class="btn btn-primary" href="/shop/">Browse the catalogue
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="btn btn-ghost" href="/contacts/">Request a quote</a>
      </div>
      <div class="hero-tags">
        <span>SAE J30 R6 / R10</span><span>DIN 73379</span><span>SAE J20 R3</span>
        <span>Cut to length</span><span>Trade pricing</span>
      </div>
    </div>
  </div>
</section>

<div class="trust">
  <div class="wrap" style="padding:0 20px">
    <div class="grid">
      <div class="c">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3l7.5 3v5.2c0 4.6-3.1 8.3-7.5 9.8-4.4-1.5-7.5-5.2-7.5-9.8V6z"/><path d="M9 12l2 2 4-4"/></svg>
        <div><b>Certified standards</b><small>SAE, DIN &amp; EN specifications on every technical datasheet.</small></div>
      </div>
      <div class="c">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.7"/><circle cx="17.5" cy="18" r="1.7"/></svg>
        <div><b>Dispatched from the UK</b><small>Same-day pick &amp; pack on stocked lines ordered before 14:00.</small></div>
      </div>
      <div class="c">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 18V8m5 10V5m5 13v-7m5 7V9"/></svg>
        <div><b>Cut to your length</b><small>Order 1 m, 5 m, 20 m or a full 50 m coil — you pay per metre.</small></div>
      </div>
      <div class="c">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 15a2 2 0 0 1-2 2H8l-4 3V6a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/></svg>
        <div><b>Technical support</b><small>Talk to someone who knows bore sizes, pressure and compatibility.</small></div>
      </div>
    </div>
  </div>
</div>

<div class="tick" aria-hidden="true">
  <div class="row">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <i>Fuel &amp; oil</i><i>Gas &amp; welding</i><i>Water &amp; irrigation</i><i>Chemicals</i><i>Compressed air</i><i>Ventilation</i><i>Abrasive media</i><i>Cooling systems</i><i>Hose couplings</i>
    <?php endfor; ?>
  </div>
</div>

<section>
  <div class="wrap">
    <div class="sec-head">
      <div>
        <span class="eyebrow">Shop by category</span>
        <h2 style="margin-top:12px">Three product families, one supplier</h2>
        <p>Rubber, PVC/PU and the couplings that join them — every line held in stock and priced per metre.</p>
      </div>
      <a class="link-more" href="/shop/">All products
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>

    <div class="cats">
      <?php foreach ($tops as $c):
          $kids  = child_categories($c['slug']);
          $items = products_in_category($c['slug']);
          $img   = $items[0]['images'][0] ?? null;
      ?>
      <article class="cat">
        <div class="ph">
          <?php if ($img): ?><img src="/<?= e($img) ?>" alt="<?= e($c['name']) ?>" loading="lazy" width="480" height="300"><?php endif; ?>
        </div>
        <div class="bd">
          <span class="cnt"><?= count($items) ?> products</span>
          <h3><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a></h3>
          <?php if ($kids): ?>
          <ul>
            <?php foreach ($kids as $k): ?>
              <li><a href="<?= e(category_url($k)) ?>"><?= e($k['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="go" href="<?= e(category_url($c)) ?>">Explore range
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section style="background:var(--bg-soft);padding-top:70px">
  <div class="wrap">
    <div class="sec-head">
      <div>
        <span class="eyebrow">Best sellers</span>
        <h2 style="margin-top:12px">Featured products</h2>
        <p>Priced per metre, excluding VAT. Coils available up to 50 m.</p>
      </div>
      <a class="link-more" href="/shop/">View shop
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>

    <div class="filters" data-filter-for="#home-products">
      <button class="on" type="button" data-f="all">All</button>
      <?php foreach ($tops as $c): ?>
        <button type="button" data-f="<?= e($c['slug']) ?>"><?= e($c['name']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="prods" id="home-products">
      <?php foreach ($featured as $i => $p) {
          $badge = $i === 0 ? 'Best seller' : '';
          include ROOT_DIR . '/partials/product-card.php';
      } ?>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="sec-head">
      <div>
        <span class="eyebrow">Where our hose works</span>
        <h2 style="margin-top:12px">Industries we supply</h2>
        <p>From a workshop bench to a tank farm — the same catalogue covers all six.</p>
      </div>
    </div>
    <div class="inds">
      <?php
      $inds = [
          ['/product-category/rubber-hoses/oil-products/', 'Fuel &amp; petroleum', 'Fuel/Oil Products', '<path d="M12 3s5 5.4 5 9a5 5 0 0 1-10 0c0-3.6 5-9 5-9z"/>'],
          ['/product-category/rubber-hoses/gas/', 'Welding &amp; gas', 'Gas', '<path d="M6 20h12M9 20V9l6-5v16"/><path d="M15 12h4v8"/>'],
          ['/product-category/pvcpu-hoses/', 'Construction', 'PVC,PU hoses', '<path d="M3 21h18M6 21V8l6-5 6 5v13"/><path d="M10 21v-6h4v6"/>'],
          ['/product-category/rubber-hoses/water/', 'Agriculture', 'Water', '<path d="M4 18c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2"/><path d="M4 12c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2"/>'],
          ['/product-category/rubber-hoses/chemicals/', 'Chemical transfer', 'Chemicals', '<path d="M9 3v6.5L4.5 18A2 2 0 0 0 6.3 21h11.4a2 2 0 0 0 1.8-3L15 9.5V3"/><path d="M8 3h8"/>'],
          ['/product-category/rubber-hoses/cooling-system/', 'Automotive', 'Cooling system', '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3"/>'],
      ];
      foreach ($inds as [$url, $label, $catName, $icon]):
          $slug  = basename(rtrim($url, '/'));
          $count = count(products_in_category($slug));
      ?>
        <a class="ind" href="<?= e($url) ?>">
          <svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $icon ?></svg>
          <b><?= $label ?></b>
          <small><?= $count ?> product<?= $count === 1 ? '' : 's' ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section style="background:var(--bg-soft);border-top:1px solid var(--line)">
  <div class="wrap">
    <div class="split">
      <div>
        <span class="eyebrow">About <?= SITE_NAME ?></span>
        <h2>Hoses chosen on specification, not on guesswork</h2>
        <p>We supply high-quality solutions for fluid transfer and industrial applications. Every hose we stock is listed with its tube compound, reinforcement, cover, working pressure and temperature range — so you can match the product to the job before you order.</p>
        <p>Whether you are transferring petroleum, chemicals, compressed air or potable water, our range is built for flexibility, durability and chemical resistance.</p>
        <ul class="checks">
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Full technical datasheet on every product page</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Bore sizes from 3.2 mm to 100 mm in stock</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Trade accounts and volume pricing available</span></li>
        </ul>
        <a class="btn btn-primary" href="/about-us/">More about us</a>
      </div>
      <div class="ph"><img src="/assets/img/site/about-1.jpg" alt="Arg Flex hose stock" loading="lazy" width="640" height="480"></div>
    </div>
  </div>
</section>

<section class="stats">
  <div class="wrap">
    <div class="grid">
      <div><div class="n" data-to="15">15</div><div class="l">Years of professional experience</div></div>
      <div><div class="n"><span data-to="98">98</span>%</div><div class="l">Satisfied clients</div></div>
      <div><div class="n" data-to="24">24</div><div class="l">Export countries</div></div>
      <div><div class="n" data-to="<?= count(all_products()) ?>"><?= count(all_products()) ?></div><div class="l">Products in the catalogue</div></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="sec-head">
      <div>
        <span class="eyebrow">Knowledge base</span>
        <h2 style="margin-top:12px">From the blog</h2>
        <p>How our hose products solve real challenges across industries — from construction to chemical transport.</p>
      </div>
      <a class="link-more" href="/blog/">All articles
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
    <div class="posts">
      <?php foreach ($posts as $post): include ROOT_DIR . '/partials/post-card.php'; endforeach; ?>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="cta">
      <div>
        <h2>Can&rsquo;t find what you are looking for?</h2>
        <p>Send us the bore size, the medium and the working pressure — we will come back with the right hose and a price.</p>
      </div>
      <a class="btn" href="/contacts/">Request a quote
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
