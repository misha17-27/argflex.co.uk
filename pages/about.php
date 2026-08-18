<?php
declare(strict_types=1);

set_page([
    'title'       => 'About us — ' . SITE_NAME,
    'description' => 'Arg Flex Ltd is dedicated to providing high-quality solutions for fluid transfer and industrial applications, with a focus on innovation, reliability and customer satisfaction.',
    'crumbs'      => [['label' => 'About us']],
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow">About company</span>
    <h1>Hoses chosen on specification, not on guesswork</h1>
    <p>At Arg Flex Ltd we are dedicated to providing high-quality solutions for fluid transfer and industrial applications. With a focus on innovation, reliability and customer satisfaction, we offer a comprehensive range of products tailored to meet the diverse needs of our clients.</p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <div class="split">
      <div>
        <span class="eyebrow">What we do</span>
        <h2>A concentrated range, held in stock and understood</h2>
        <p>Our hoses are engineered for flexibility, durability and resistance to a wide range of chemicals, making them suitable for a wide variety of industrial applications. Whether you need to transfer petroleum, chemicals or other fluids, our range ensures safe and efficient handling.</p>
        <p>Rather than listing thousands of lines we cannot supply, we hold a focused catalogue and know every product in it — which is why every product page carries the tube compound, the reinforcement, the cover, the working temperature and the standard it is built to.</p>
        <ul class="checks">
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Full technical datasheet on every product page</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Bore sizes from 3.2 mm to 100 mm in stock</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Cut to length from 1 m — no forced full coils</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Matching clamps and couplings for every bore we sell</span></li>
        </ul>
        <a class="btn btn-primary" href="/shop/">Browse the catalogue</a>
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
        <span class="eyebrow">How we work</span>
        <h2 style="margin-top:12px">From enquiry to dispatch</h2>
        <p>Four steps, and a person on the end of the phone at each of them.</p>
      </div>
    </div>
    <div class="steps">
      <div class="step"><span class="num">01</span><h3>Tell us the duty</h3><p>The medium, the bore size, the working pressure and the temperature range. That is enough to narrow the catalogue to one or two lines.</p></div>
      <div class="step"><span class="num">02</span><h3>We confirm the spec</h3><p>We check the tube compound and cover against the medium, and confirm the standard the hose is built to.</p></div>
      <div class="step"><span class="num">03</span><h3>Cut to length</h3><p>Order 1 m or a full 50 m coil. Cut lengths are prepared to order with the matching clamps.</p></div>
      <div class="step"><span class="num">04</span><h3>Dispatched from the UK</h3><p>Stocked lines ordered before 14:00 on a working day are picked and packed the same day.</p></div>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="cta">
      <div>
        <h2>Talk to someone who knows the range</h2>
        <p>We answer technical questions the same working day.</p>
      </div>
      <a class="btn" href="/contacts/">Contact us</a>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
