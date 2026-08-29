<?php
/**
 * The customer's own account: sign in, register, order history, details.
 *
 * Ordering never needs one — the checkout stays open to anybody — so this
 * page has to earn its keep by saving typing and showing what was ordered.
 */
declare(strict_types=1);

require_once ROOT_DIR . '/inc/store.php';
require_once ROOT_DIR . '/inc/accounts.php';
require_once ROOT_DIR . '/inc/mail.php';

$customer = current_customer();
$errors   = [];
$done     = '';
$view     = (string) ($_GET['do'] ?? '');

/** Back to this page with a word about what happened. */
function account_done(string $state, string $extra = ''): never
{
    header('Location: /my-account/?done=' . urlencode($state) . ($extra !== '' ? '&' . $extra : ''));
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
        $country = strtoupper(trim((string) ($_POST['country'] ?? '')));
        update_account($customer['email'], [
            'name'     => clip(trim((string) ($_POST['name'] ?? '')), 60),
            'company'  => clip(trim((string) ($_POST['company'] ?? '')), 60),
            'phone'    => clip(trim((string) ($_POST['phone'] ?? '')), 40),
            'address'  => clip(trim((string) ($_POST['address'] ?? '')), 120),
            'city'     => clip(trim((string) ($_POST['city'] ?? '')), 60),
            'postcode' => clip(trim((string) ($_POST['postcode'] ?? '')), 16),
            'country'  => isset(COUNTRIES[$country]) ? $country : $customer['country'],
        ]);
        account_done('saved');

    } elseif ($act === 'password' && $customer) {
        $now = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify($now, $customer['password'])) {
            $errors[] = 'That is not your current password.';
        } elseif (strlen($new) < 10) {
            $errors[] = 'Use a password of at least ten characters.';
        } else {
            update_account($customer['email'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
            account_done('password');
        }

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
    }
}

if (($_GET['logout'] ?? '') !== '') {
    customer_logout();
    account_done('bye');
}

$customer = current_customer();
$said     = (string) ($_GET['done'] ?? '');
$notes    = [
    'welcome'    => 'Signed in.',
    'registered' => 'Your account is ready.',
    'saved'      => 'Your details are saved. The checkout will fill them in from now on.',
    'password'   => 'Your password has been changed.',
    'bye'        => 'Signed out.',
    'reset-sent' => 'If there is an account on that address, a reset link is on its way. It works for one hour.',
];

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
    <h1><?= $customer ? e($customer['name'] !== '' ? $customer['name'] : $customer['email']) : 'My account' ?></h1>
    <p><?= $customer
        ? 'Your orders, your details, and the address the checkout fills in for you.'
        : 'Sign in to see your orders. You never need an account to place one.' ?></p>
  </div>
</section>

<section style="padding-top:40px">
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
      <?php $orders = account_orders($customer['email']); ?>

      <div class="acc-bar">
        <span><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></span>
        <a href="/my-account/?logout=1">Sign out</a>
      </div>

      <div class="account-grid">
        <div class="acc-box">
          <h2>Your orders</h2>
          <?php if (!$orders): ?>
            <p class="c-note">Nothing yet. Once you order, it appears here with its reference and status.</p>
            <a class="btn btn-primary" href="/shop/">Browse the catalogue</a>
          <?php else: ?>
            <ul class="acc-orders">
              <?php foreach ($orders as $o): ?>
                <li>
                  <div>
                    <b><?= e($o['reference']) ?></b>
                    <span><?= e(date('j M Y', strtotime($o['placed_at']))) ?> ·
                      <?= count($o['order']['items']) ?> item<?= count($o['order']['items']) === 1 ? '' : 's' ?></span>
                  </div>
                  <div class="acc-order-right">
                    <b><?= e(money((int) $o['order']['total'])) ?></b>
                    <span class="acc-status <?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="c-note">Need a copy of an invoice? <a href="/contacts/">Ask us</a> quoting the reference.</p>
          <?php endif; ?>
        </div>

        <div class="acc-box alt">
          <h2>Your details</h2>
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
            <button class="btn btn-primary" type="submit">Save my details</button>
            <p class="c-note">The checkout fills these in for you next time.</p>
          </form>

          <form method="post" class="acc-pw">
            <input type="hidden" name="act" value="password">
          <?= form_field('account-password') ?>
            <h3>Change your password</h3>
            <div class="fld"><label for="a-cur">Current password</label>
              <input id="a-cur" name="current" type="password" autocomplete="current-password" required></div>
            <div class="fld"><label for="a-new">New password</label>
              <input id="a-new" name="password" type="password" autocomplete="new-password" minlength="10" required>
              <p class="fld-note">Ten characters or more.</p></div>
            <button class="btn btn-out" type="submit">Change it</button>
          </form>
        </div>
      </div>

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
