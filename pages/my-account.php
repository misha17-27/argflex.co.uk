<?php
declare(strict_types=1);

set_page([
    'title'       => 'My account — ' . SITE_NAME,
    'description' => 'Sign in to your Arg Flex trade account.',
    'crumbs'      => [['label' => 'My account']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow">Trade accounts</span>
    <h1>My account</h1>
    <p>Trade customers get volume pricing, saved delivery addresses and a full order history.</p>
  </div>
</section>

<section style="padding-top:40px">
  <div class="wrap">
    <div class="account-grid">
      <form class="acc-box" method="post" action="/my-account/">
        <h2>Sign in</h2>
        <div class="fld"><label for="acc-u">Email address</label><input id="acc-u" name="email" type="email" autocomplete="username" required></div>
        <div class="fld"><label for="acc-pw">Password</label><input id="acc-pw" name="password" type="password" autocomplete="current-password" required></div>
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Sign in</button>
        <p class="c-note">Password reset and order history become available once the store back end is connected.</p>
      </form>
      <div class="acc-box alt">
        <h2>No account yet?</h2>
        <p>Open a trade account to see your negotiated pricing across the catalogue, reorder previous cut lengths in one click and pay on account.</p>
        <ul class="checks">
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Volume pricing on every line</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Saved delivery addresses</span></li>
          <li><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg><span>Full order and invoice history</span></li>
        </ul>
        <a class="btn btn-dark" href="/contacts/">Apply for an account</a>
      </div>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
