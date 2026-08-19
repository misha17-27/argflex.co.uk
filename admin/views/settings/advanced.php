<?php
/**
 * Page setup, legacy URLs and the asset cache.
 *
 * @var array $values
 */

$pages = [
    ['Shop',        '/shop/',           'The full catalogue.'],
    ['Cart',        '/cart/',           'Where the basket icon goes.'],
    ['Checkout',    '/checkout/',       'Where an order is placed and stored.'],
    ['My account',  '/my-account/',     'Sign-in and the trade account pitch.'],
    ['Wishlist',    '/wishlist/',       'Saved products.'],
    ['Compare',     '/compare/',        'Two products side by side.'],
    ['Contact',     '/contacts/',       'The enquiry form.'],
];

$policyPages = [
    '/refund_returns/' => 'Refunds and returns',
    '/about-us/'       => 'About us',
    '/contacts/'       => 'Contacts',
    ''                 => 'Do not link to anything',
];

$redirects = (array) $values['redirects'];

$redirectRow = function (string $key, string $from, string $to): void { ?>
  <div class="row-line" data-row>
    <input type="text" name="redir[<?= e($key) ?>][from]" value="<?= e($from) ?>" placeholder="/old-url/" aria-label="Old URL">
    <span class="arrow">&rarr;</span>
    <input type="text" name="redir[<?= e($key) ?>][to]" value="<?= e($to) ?>" placeholder="/new-url/" aria-label="New URL">
    <button type="button" class="x" data-remove-row aria-label="Remove">&times;</button>
  </div>
<?php }; ?>

<div class="card pad-card">
  <h2>Page setup</h2>
  <p class="hint">These addresses are fixed: they match the WordPress site this one replaced, and the search rankings the shop already holds are attached to them. Changing one would mean a redirect and a re-crawl, so they are shown here rather than made editable.</p>
  <table class="grid">
    <thead><tr><th>Page</th><th>URL</th><th>What it does</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pages as [$label, $url, $what]): ?>
        <tr>
          <td><b><?= e($label) ?></b></td>
          <td><code><?= e($url) ?></code></td>
          <td class="muted"><?= e($what) ?></td>
          <td class="right"><a href="<?= e($url) ?>" target="_blank" rel="noopener">View ↗</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" class="setform">
  <?= csrf_field() ?>

  <div class="card pad-card">
    <h2>Terms and conditions</h2>
    <label for="terms_path">Page linked from the checkout</label>
    <select id="terms_path" name="terms_path">
      <?php foreach ($policyPages as $path => $label): ?>
        <option value="<?= e($path) ?>" <?= $values['terms_path'] === $path ? 'selected' : '' ?>>
          <?= e($label) ?><?= $path !== '' ? ' — ' . e($path) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p class="hint">Shown above the Place order button as "By placing this order you accept our …".</p>
  </div>

  <div class="card pad-card">
    <h2>Legacy URLs</h2>
    <p class="hint">Addresses the old WordPress site answered that this one names differently. Each is a permanent redirect, so an inbound link or a ranking on the old URL carries over instead of hitting a 404.</p>
    <div class="rows" id="redir-rows">
      <?php $i = 0; foreach ($redirects as $from => $to) $redirectRow((string) $i++, (string) $from, (string) $to); ?>
      <template id="redir-tpl"><?php $redirectRow('__r__', '', ''); ?></template>
    </div>
    <button type="button" class="ghost small" data-add-row="#redir-tpl">+ Add a redirect</button>
    <p class="hint">Both sides need the leading and trailing slash. A row with either side blank is dropped.</p>
  </div>

  <div class="card pad-card">
    <h2>Asset cache</h2>
    <p class="hint">CSS and JS are served with a year-long cache and a <code>?v=</code> stamp. Saving anything in Settings bumps the stamp; this is here for when a template changed and nothing else did.</p>
    <p>Current version: <b>v<?= e((string) $values['asset_ver']) ?></b></p>
    <label class="check">
      <input type="checkbox" name="bust" value="1">
      Bump it again when I save
    </label>
  </div>

  <div class="savebar">
    <button type="submit">Save changes</button>
  </div>
</form>

<div class="card pad-card">
  <h2>Where things live</h2>
  <table class="grid">
    <thead><tr><th>Path</th><th>What is in it</th><th>Served over HTTP</th></tr></thead>
    <tbody>
      <?php foreach ([
        ['data/',    'Products, categories, attributes, posts, page copy and SEO', false],
        ['storage/', 'Orders, enquiries, settings and the admin accounts',         false],
        ['assets/',  'The stylesheet, the script and every image',                 true],
        ['.data/',   'The import and verification scripts — not needed on a server', false],
      ] as [$path, $what, $public]): ?>
        <tr>
          <td><code><?= e($path) ?></code></td>
          <td class="muted"><?= e($what) ?></td>
          <td><?= $public ? 'Yes' : '<b>Denied</b>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
