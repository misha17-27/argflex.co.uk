<?php
/**
 * The customer's own account.
 *
 * Ordering never needs one — the checkout stays open to anybody — but placing
 * an order opens one anyway (see open_account_for_order), so most people
 * arrive here already having an account and a posted password. The job of
 * this page is therefore to be worth the visit: what you ordered, where it is
 * going, and how to change the password you were sent.
 *
 * Laid out as sections rather than one long page, because the orders list is
 * what people come back for and it should not be below a form they filled in
 * once. Each section is a path — /my-account/orders/ — so it can be
 * bookmarked and read out.
 *
 * @var string $section  the part after /my-account/
 * @var string $ref      an order reference, for /my-account/orders/<ref>/
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/store.php';
require_once ROOT_DIR . '/inc/accounts.php';
require_once ROOT_DIR . '/inc/security.php';   // the form token and the counters
require_once ROOT_DIR . '/inc/mail.php';

$customer = current_customer();
$errors   = [];
$section  = $section ?? '';
$ref      = $ref ?? '';
$view     = (string) ($_GET['do'] ?? '');

/** Back to a section with a word about what happened. */
function account_done(string $state, string $where = ''): never
{
    header('Location: /my-account/' . ($where !== '' ? $where . '/' : '')
         . '?done=' . urlencode($state));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['act'] ?? '');

    /* Every action here is signed for itself, so a token minted for signing
       in cannot be replayed against "change my password" — which is the
       whole point of this on a page that both anonymous and signed-in people
       post to. */
    if ($stale = require_form_token('account-' . $act, 'return')) {
        $errors[] = $stale;
        $act      = '';
    }

    if ($act === 'login') {
        /* Ten wrong passwords an hour from one address. The comparison is
           already constant-time; this stops the guessing rather than the
           timing. */
        if (rate_limited('signin', 10, 3600)) {
            $errors[] = 'Too many sign-in attempts. Wait a few minutes, or reset your password.';
        } elseif (customer_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            rate_clear('signin');
            account_done('welcome');
        } else {
            rate_hit('signin');
            $errors[] = 'Those details were not recognised.';
        }

    } elseif ($act === 'register') {
        if (rate_limited('register', 5, 3600)) {
            $errors[] = 'Too many accounts opened from here. Try again later.';
        } else {
            rate_hit('register');
            $problem = create_account((string) ($_POST['email'] ?? ''),
                                      (string) ($_POST['password'] ?? ''),
                                      (string) ($_POST['name'] ?? ''));
            if ($problem === '') {
                customer_login((string) $_POST['email'], (string) $_POST['password']);
                account_done('registered');
            }
            $errors[] = $problem;
        }
        $view = 'register';

    } elseif ($act === 'details' && $customer) {
        update_account($customer['email'], [
            'name'    => clip(trim((string) ($_POST['name'] ?? '')), 60),
            'company' => clip(trim((string) ($_POST['company'] ?? '')), 60),
            'phone'   => clip(trim((string) ($_POST['phone'] ?? '')), 40),
        ]);
        account_done('saved', 'details');

    } elseif ($act === 'address' && $customer) {
        $country = strtoupper(trim((string) ($_POST['country'] ?? '')));
        update_account($customer['email'], [
            'address'  => clip(trim((string) ($_POST['address'] ?? '')), 120),
            'city'     => clip(trim((string) ($_POST['city'] ?? '')), 60),
            'postcode' => clip(trim((string) ($_POST['postcode'] ?? '')), 16),
            'country'  => isset(COUNTRIES[$country]) ? $country : $customer['country'],
        ]);
        account_done('address', 'addresses');

    } elseif ($act === 'password' && $customer) {
        $now = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify($now, $customer['password'])) {
            $errors[] = 'That is not your current password.';
        } elseif (strlen($new) < 10) {
            $errors[] = 'Use a password of at least ten characters.';
        } elseif ($new !== (string) ($_POST['confirm'] ?? $new)) {
            $errors[] = 'The two new passwords are not the same.';
        } else {
            update_account($customer['email'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
            account_done('password', 'details');
        }
        $section = 'details';

    } elseif ($act === 'forgot') {
        $email = trim((string) ($_POST['email'] ?? ''));

        /* This mails whoever is named, so left open it is a way to post a
           stranger a reset link every few seconds. Counted before the token
           is minted, and the answer below is the same either way. */
        if (rate_limited('forgot', 5, 3600)) account_done('reset-sent');
        rate_hit('forgot');

        $token = start_password_reset($email);
        if ($token !== '') {
            $account = find_account($email);
            $link    = SITE_URL . '/my-account/?do=reset&email=' . urlencode($account['email'])
                     . '&token=' . urlencode($token);
            mail_notify('password_reset', $account['email'],
                ['site' => SITE_NAME, 'name' => $account['name']],
                '<p style="margin:0 0 14px">Somebody asked to reset the password on this '
              . 'account. If it was not you, ignore this — nothing has changed.</p>'
              . '<p style="margin:0 0 20px"><a href="' . e($link) . '" style="display:inline-block;'
              . 'background:' . e((string) setting('email_accent')) . ';color:#fff;padding:11px 22px;'
              . 'border-radius:8px;text-decoration:none;font-weight:700">Choose a new password</a></p>'
              . '<p style="margin:0;color:#5b6880;font-size:13px">The link works for one hour.</p>');
        }
        // the same answer either way, so this cannot be used to find out who has an account
        account_done('reset-sent');

    } elseif ($act === 'reset') {
        $email = (string) ($_POST['email'] ?? '');
        $token = (string) ($_POST['token'] ?? '');
        $new   = (string) ($_POST['password'] ?? '');
        if (!reset_token_valid($email, $token)) {
            $errors[] = 'That link has expired. Ask for another.';
            $view = 'forgot';
        } elseif (strlen($new) < 10) {
            $errors[] = 'Use a password of at least ten characters.';
            $view = 'reset';
        } else {
            $account = find_account($email);
            update_account($account['email'], [
                'password' => password_hash($new, PASSWORD_DEFAULT),
                'reset' => '', 'reset_at' => '',
            ]);
            customer_login($account['email'], $new);
            account_done('password');
        }

    } elseif ($act === 'logout') {
        customer_logout();
        account_done('bye');
    }
}

$customer = current_customer();
$said     = (string) ($_GET['done'] ?? '');
$notes    = [
    'welcome'    => 'Signed in.',
    'registered' => 'Your account is ready.',
    'saved'      => 'Saved.',
    'address'    => 'Your address is saved. The checkout will fill it in from now on.',
    'password'   => 'Your password has been changed.',
    'bye'        => 'Signed out.',
    'reset-sent' => 'If there is an account on that address, a reset link is on its way. It works for one hour.',
];

/* Sections a signed-in customer can be on. Anything else falls back to the
   dashboard rather than 404ing — the URL is guessable and a dead end here
   helps nobody. */
$sections = [
    ''          => ['Dashboard',       'grid'],
    'orders'    => ['Orders',          'orders'],
    'addresses' => ['Address',         'pin'],
    'details'   => ['Account details', 'user'],
    'wishlist'  => ['Wishlist',        'heart'],
];
if (!isset($sections[$section])) $section = '';

$icons = [
    'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'orders' => '<path d="M4 5h2l2.2 10.4a2 2 0 0 0 2 1.6h6.9a2 2 0 0 0 2-1.55L21 8H6.5"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/>',
    'pin'    => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
    'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
    'heart'  => '<path d="M12 20s-7.5-4.6-7.5-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7.5 3C19.5 15.4 12 20 12 20z"/>',
    'out'    => '<path d="M9 20H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4"/><path d="M16 16l4-4-4-4"/><path d="M20 12H9"/>',
];

$orders = $customer ? account_orders($customer['email']) : [];

/* One order, when the URL names one — and only if it is theirs. Matching on
   the address the order was placed with is what makes this safe: a reference
   is short enough to guess at, so it must not be the only thing standing
   between a stranger and somebody's delivery address. */
$oneOrder = null;
if ($customer && $section === 'orders' && $ref !== '') {
    foreach ($orders as $o) {
        if (strcasecmp((string) $o['reference'], $ref) === 0) { $oneOrder = $o; break; }
    }
}

set_page([
    'title'       => 'My account — ' . SITE_NAME,
    'description' => 'Sign in to your Arg Flex account to see your orders and save your delivery details.',
    'crumbs'      => [['label' => 'My account']],
    'robots'      => 'noindex, follow',
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow"><?= $customer ? 'Your account' : 'Account' ?></span>
    <h1><?= $customer ? e($sections[$section][0]) : 'My account' ?></h1>
    <p><?= $customer
        ? 'Your orders, your details, and the address the checkout fills in for you.'
        : 'Sign in to see your orders. You never need an account to place one.' ?></p>
  </div>
</section>

<section style="padding-top:36px">
  <div class="wrap">

    <?php if (isset($notes[$said])): ?>
      <p class="acc-note"><?= e($notes[$said]) ?></p>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="form-error">
        <b>That did not work</b>
        <ul><?php foreach ($errors as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($customer): ?>

      <div class="acc-layout">
        <nav class="acc-nav" aria-label="Your account">
          <?php foreach ($sections as $slug => [$label, $icon]): ?>
            <a href="/my-account/<?= $slug !== '' ? e($slug) . '/' : '' ?>"
               class="<?= $section === $slug ? 'on' : '' ?>"
               <?= $section === $slug ? 'aria-current="page"' : '' ?>>
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><?= $icons[$icon] ?></svg>
              <?= e($label) ?>
              <?php if ($slug === 'orders' && $orders): ?><i class="tally"><?= count($orders) ?></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
          <?php /* Signing out changes something, so it is a button that posts a
                   token, not a link another site could put in an <img>. */ ?>
          <form method="post" class="acc-out">
            <input type="hidden" name="act" value="logout">
            <?= form_field('account-logout') ?>
            <button type="submit">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><?= $icons['out'] ?></svg>
              Sign out
            </button>
          </form>
        </nav>

        <div class="acc-body">

        <?php /* ------------------------------------------------ dashboard */ ?>
        <?php if ($section === ''): ?>

          <div class="acc-box">
            <h2>Hello <?= e($customer['name'] !== '' ? $customer['name'] : $customer['email']) ?></h2>
            <p class="c-note">From here you can look back at what you have ordered, keep the
              address the checkout fills in for you, and change your password. If we opened this
              account when you ordered, changing the password we posted you is the first thing
              worth doing.</p>
          </div>

          <div class="acc-tiles">
            <?php
              $recent = $orders[0] ?? null;
              $tiles = [
                ['/my-account/orders/', 'orders', 'Orders',
                 $orders ? count($orders) . ' order' . (count($orders) === 1 ? '' : 's')
                         : 'Nothing yet'],
                ['/my-account/addresses/', 'pin', 'Address',
                 $customer['address'] !== '' ? e($customer['city'] !== '' ? $customer['city'] : $customer['postcode'])
                                             : 'Not set yet'],
                ['/my-account/details/', 'user', 'Account details', 'Name, phone and password'],
                ['/wishlist/', 'heart', 'Wishlist', 'Everything you have saved'],
              ];
            ?>
            <?php foreach ($tiles as [$href, $icon, $label, $sub]): ?>
              <a class="acc-tile" href="<?= e($href) ?>">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><?= $icons[$icon] ?></svg>
                <b><?= e($label) ?></b>
                <span><?= $sub ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($recent): ?>
            <div class="acc-box">
              <h2>Your last order</h2>
              <ul class="acc-orders">
                <?php $o = $recent; require ROOT_DIR . '/partials/account-order-row.php'; ?>
              </ul>
            </div>
          <?php endif; ?>

        <?php /* --------------------------------------------------- orders */ ?>
        <?php elseif ($section === 'orders' && $oneOrder): ?>

          <?php $o = $oneOrder; $ord = $o['order']; $c = $o['customer']; ?>
          <div class="acc-box">
            <p class="c-note"><a href="/my-account/orders/">&larr; All orders</a></p>
            <h2>Order <?= e($o['reference']) ?></h2>
            <p class="c-note">Placed <?= e(date('j F Y', strtotime($o['placed_at']))) ?> ·
              <span class="acc-status <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></p>

            <table class="acc-lines">
              <thead><tr><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
              <tbody>
                <?php foreach ($ord['items'] as $item): ?>
                  <tr>
                    <td><b><?= e($item['title']) ?></b>
                      <?php if (($item['option'] ?? '') !== ''): ?><small><?= e($item['option']) ?></small><?php endif; ?>
                    </td>
                    <td><?= (int) $item['qty'] ?></td>
                    <td><?= e(money((int) $item['line'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr><th colspan="2">Goods</th><td><?= e(money((int) $ord['subtotal'])) ?></td></tr>
                <?php if ((int) ($ord['discount'] ?? 0) > 0): ?>
                  <tr><th colspan="2">Discount</th><td>&minus;<?= e(money((int) $ord['discount'])) ?></td></tr>
                <?php endif; ?>
                <tr><th colspan="2">Delivery</th><td><?= e(money((int) $ord['shipping'])) ?></td></tr>
                <?php if ((int) ($ord['vat'] ?? 0) > 0): ?>
                  <tr><th colspan="2"><?= e(tax_label()) ?></th><td><?= e(money((int) $ord['vat'])) ?></td></tr>
                <?php endif; ?>
                <tr class="tot"><th colspan="2">Total</th><td><?= e(money((int) $ord['total'])) ?></td></tr>
              </tfoot>
            </table>

            <h3>Delivered to</h3>
            <p class="acc-addr"><?= nl2br(e(trim(implode("\n", array_filter([
                $c['name'], $c['company'], $c['address'],
                trim($c['city'] . ' ' . $c['postcode']), $c['country'],
            ]))))) ?></p>
            <p class="c-note">Need an invoice or a copy of anything?
              <a href="/contacts/?ref=<?= e($o['reference']) ?>">Ask us</a> quoting <?= e($o['reference']) ?>.</p>
          </div>

        <?php elseif ($section === 'orders'): ?>

          <div class="acc-box">
            <h2>Your orders</h2>
            <?php if (!$orders): ?>
              <p class="c-note">Nothing yet. Once you order, it appears here with its reference,
                what it cost and where it got to.</p>
              <a class="btn btn-primary" href="/shop/">Browse the catalogue</a>
            <?php else: ?>
              <ul class="acc-orders">
                <?php foreach ($orders as $o): require ROOT_DIR . '/partials/account-order-row.php'; endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

        <?php /* ------------------------------------------------ addresses */ ?>
        <?php elseif ($section === 'addresses'): ?>

          <div class="acc-box">
            <h2>Delivery address</h2>
            <p class="c-note">The checkout fills this in for you. It is the only address we keep —
              we do not hold a separate billing one, because everything here is delivered to the
              address it is invoiced to.</p>
            <form method="post">
              <input type="hidden" name="act" value="address">
              <?= form_field('account-address') ?>
              <div class="fld"><label for="a-address">Address</label>
                <input id="a-address" name="address" type="text" value="<?= e($customer['address']) ?>" autocomplete="street-address"></div>
              <div class="two">
                <div class="fld"><label for="a-city">Town or city</label>
                  <input id="a-city" name="city" type="text" value="<?= e($customer['city']) ?>" autocomplete="address-level2"></div>
                <div class="fld"><label for="a-postcode">Postcode</label>
                  <input id="a-postcode" name="postcode" type="text" value="<?= e($customer['postcode']) ?>" autocomplete="postal-code"></div>
              </div>
              <div class="fld"><label for="a-country">Country</label>
                <select id="a-country" name="country" autocomplete="country">
                  <?php foreach (delivery_countries() as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $customer['country'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <button class="btn btn-primary" type="submit">Save my address</button>
            </form>
          </div>

        <?php /* -------------------------------------------------- details */ ?>
        <?php elseif ($section === 'details'): ?>

          <div class="acc-box">
            <h2>Account details</h2>
            <form method="post">
              <input type="hidden" name="act" value="details">
              <?= form_field('account-details') ?>
              <div class="two">
                <div class="fld"><label for="a-name">Name</label>
                  <input id="a-name" name="name" type="text" value="<?= e($customer['name']) ?>" autocomplete="name"></div>
                <div class="fld"><label for="a-company">Company</label>
                  <input id="a-company" name="company" type="text" value="<?= e($customer['company']) ?>" autocomplete="organization"></div>
              </div>
              <div class="fld"><label for="a-phone">Phone</label>
                <input id="a-phone" name="phone" type="tel" value="<?= e($customer['phone']) ?>" autocomplete="tel"></div>
              <div class="fld"><label for="a-email">Email address</label>
                <input id="a-email" type="email" value="<?= e($customer['email']) ?>" disabled>
                <p class="fld-note">Your orders are found by this address, so changing it would
                  hide them. <a href="/contacts/">Ask us</a> and we will move them across.</p></div>
              <button class="btn btn-primary" type="submit">Save changes</button>
            </form>
          </div>

          <div class="acc-box alt">
            <h2>Password</h2>
            <p class="c-note">If we opened this account when you ordered, the password in that
              email was made by us and posted over email. Change it to something only you know.</p>
            <form method="post">
              <input type="hidden" name="act" value="password">
              <?= form_field('account-password') ?>
              <div class="fld"><label for="a-cur">Current password</label>
                <input id="a-cur" name="current" type="password" autocomplete="current-password" required></div>
              <div class="two">
                <div class="fld"><label for="a-new">New password</label>
                  <input id="a-new" name="password" type="password" autocomplete="new-password" minlength="10" required>
                  <p class="fld-note">Ten characters or more.</p></div>
                <div class="fld"><label for="a-new2">Repeat it</label>
                  <input id="a-new2" name="confirm" type="password" autocomplete="new-password" minlength="10" required></div>
              </div>
              <button class="btn btn-out" type="submit">Change my password</button>
            </form>
          </div>

        <?php /* ------------------------------------------------- wishlist */ ?>
        <?php elseif ($section === 'wishlist'): ?>

          <div class="acc-box">
            <h2>Wishlist</h2>
            <p class="c-note">What you have saved is kept in this browser rather than on the
              account, so it is there without signing in — and it does not follow you to another
              computer.</p>
            <a class="btn btn-primary" href="/wishlist/">Open my wishlist</a>
          </div>

        <?php endif; ?>

        </div>
      </div>

    <?php /* --------------------------------------------- not signed in */ ?>
    <?php elseif ($view === 'reset' || (isset($_GET['token']) && $view !== 'forgot')): ?>
      <div class="account-grid one">
        <form class="acc-box" method="post">
          <input type="hidden" name="act" value="reset">
          <?= form_field('account-reset') ?>
          <input type="hidden" name="email" value="<?= e((string) ($_GET['email'] ?? $_POST['email'] ?? '')) ?>">
          <input type="hidden" name="token" value="<?= e((string) ($_GET['token'] ?? $_POST['token'] ?? '')) ?>">
          <h2>Choose a new password</h2>
          <div class="fld"><label for="r-pw">New password</label>
            <input id="r-pw" name="password" type="password" autocomplete="new-password" minlength="10" required autofocus>
            <p class="fld-note">Ten characters or more.</p></div>
          <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Save it and sign in</button>
        </form>
      </div>

    <?php elseif ($view === 'forgot'): ?>
      <div class="account-grid one">
        <form class="acc-box" method="post">
          <input type="hidden" name="act" value="forgot">
          <?= form_field('account-forgot') ?>
          <h2>Forgotten password</h2>
          <p class="c-note">Tell us the address on the account and we will send a link to set a new password.</p>
          <div class="fld"><label for="f-email">Email address</label>
            <input id="f-email" name="email" type="email" autocomplete="username" required autofocus></div>
          <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Send the link</button>
          <p class="c-note"><a href="/my-account/">Back to signing in</a></p>
        </form>
      </div>

    <?php else: ?>
      <div class="account-grid">
        <form class="acc-box" method="post">
          <input type="hidden" name="act" value="login">
          <?= form_field('account-login') ?>
          <h2>Sign in</h2>
          <p class="c-note">If you have ordered from us, you already have an account — the
            password was in an email headed &ldquo;<?= e(email_conf('account_opened')['subject'] !== ''
              ? email_conf('account_opened')['subject'] : 'Your account at ' . SITE_NAME) ?>&rdquo;.</p>
          <div class="fld"><label for="acc-u">Email address</label>
            <input id="acc-u" name="email" type="email" autocomplete="username" required></div>
          <div class="fld"><label for="acc-pw">Password</label>
            <input id="acc-pw" name="password" type="password" autocomplete="current-password" required></div>
          <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Sign in</button>
          <p class="c-note"><a href="/my-account/?do=forgot">Forgotten your password?</a></p>
        </form>

        <form class="acc-box alt" method="post">
          <input type="hidden" name="act" value="register">
          <?= form_field('account-register') ?>
          <h2>Open an account</h2>
          <p>It saves retyping your delivery details and keeps your orders in one place.
            You can order perfectly well without one.</p>
          <div class="fld"><label for="reg-name">Your name</label>
            <input id="reg-name" name="name" type="text" autocomplete="name" required></div>
          <div class="fld"><label for="reg-email">Email address</label>
            <input id="reg-email" name="email" type="email" autocomplete="username" required></div>
          <div class="fld"><label for="reg-pw">Password</label>
            <input id="reg-pw" name="password" type="password" autocomplete="new-password" minlength="10" required>
            <p class="fld-note">Ten characters or more.</p></div>
          <button class="btn btn-dark" type="submit" style="width:100%;justify-content:center">Create the account</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
