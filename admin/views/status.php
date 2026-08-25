<?php
/**
 * System status — what the server offers, what is writable, what is still
 * unset. Read before going live and whenever something stops working.
 *
 * @var array $groups
 */
$icon = [
    'ok'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M4 12.5l5 5L20 6.5"/></svg>',
    'warn' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3l9.5 17H2.5z"/><path d="M12 10v4.5M12 17.2v.1"/></svg>',
    'bad'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6L6 18"/></svg>',
];

$tally = ['ok' => 0, 'warn' => 0, 'bad' => 0];
foreach ($groups as $rows) {
    foreach ($rows as $row) $tally[$row['state']]++;
}
?>

<div class="stats">
  <div class="stat"><span><?= $tally['ok'] ?></span>Fine</div>
  <div class="stat <?= $tally['warn'] ? 'hot' : '' ?>"><span><?= $tally['warn'] ?></span>Worth a look</div>
  <div class="stat <?= $tally['bad'] ? 'hot' : '' ?>"><span><?= $tally['bad'] ?></span>Needs fixing</div>
</div>

<?php if ($tally['bad'] === 0 && $tally['warn'] === 0): ?>
  <div class="flash">Everything checked out. The site is ready to go live.</div>
<?php elseif ($tally['bad'] === 0): ?>
  <div class="flash">Nothing is broken. The items below would be worth setting before launch.</div>
<?php else: ?>
  <div class="flash bad">Some things need fixing before this goes live — see the crosses below.</div>
<?php endif; ?>

<?php foreach ($groups as $title => $rows): ?>
  <div class="card">
    <div class="card-hd"><h2><?= e($title) ?></h2></div>
    <table class="grid status">
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="mark <?= e($row['state']) ?>"><?= $icon[$row['state']] ?></td>
            <td><b><?= e($row['label']) ?></b></td>
            <td><?= $row['value'] ?></td>
            <td class="muted opt"><?= e($row['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>

<div class="card pad-card">
  <h2>Going live</h2>
  <ol class="steps">
    <li>Upload everything except <code>.data/</code>, <code>.git/</code>, <code>storage/</code> and the design concept files — see the README for the exact list.</li>
    <li>Make <code>data/</code>, <code>storage/</code> and <code>assets/img/</code> writable by the web server.</li>
    <li>Open <code>/admin/</code> and create the account. It is written to <code>storage/users.php</code>, which the server refuses to serve.</li>
    <li>Fill in SMTP under <a href="/admin/settings/emails">Settings → Emails</a> and send yourself the test.</li>
    <li>Add the Cloudflare Turnstile keys under <a href="/admin/security">Security</a> so the forms are protected.</li>
    <li>Come back to this page and confirm every line is a tick.</li>
    <li>Point the domain here, then re-submit <code>/sitemap.xml</code> in Search Console.</li>
  </ol>
</div>
