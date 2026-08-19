<?php
/** @var string $viewName */
$user  = current_user();
$note  = flash();
$bare  = in_array($viewName, ['login', 'setup'], true);
$here  = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$isOn  = fn(string $prefix) => $prefix === '/admin/'
    ? $here === '/admin/' || $here === '/admin'
    : str_starts_with($here, $prefix);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? 'Admin') . ' — ' . SITE_NAME) ?></title>
<link rel="icon" href="/assets/img/favicon/fav-32x32.png" sizes="32x32">
<link rel="stylesheet" href="/admin/assets/admin.css?v=<?= e(ASSET_VER) ?>">
</head>
<body class="<?= $bare ? 'bare' : '' ?>">

<?php if ($bare): ?>

  <main class="auth">
    <?php require __DIR__ . '/' . $viewName . '.php'; ?>
  </main>

<?php else: ?>

  <div class="shell">
    <aside class="side">
      <a class="brand" href="/admin/">
        <img src="/assets/img/site/logo.png" alt="" width="120" height="32">
        <span>Admin</span>
      </a>
      <nav>
        <a href="/admin/" class="<?= $isOn('/admin/') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>Dashboard</a>
        <a href="/admin/orders" class="<?= $isOn('/admin/orders') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 5h2l2.2 10.4a2 2 0 0 0 2 1.6h6.9a2 2 0 0 0 2-1.55L21 8H6.5"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>Orders</a>
        <a href="/admin/products" class="<?= $isOn('/admin/products') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 7l9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/></svg>Products</a>
        <a href="/admin/categories" class="<?= $isOn('/admin/categories') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 6h16M4 12h16M4 18h10"/></svg>Categories</a>
        <a href="/admin/posts" class="<?= $isOn('/admin/posts') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>Blog</a>
        <a href="/admin/media" class="<?= $isOn('/admin/media') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-6 6"/></svg>Images</a>
        <a href="/admin/seo" class="<?= $isOn('/admin/seo') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>SEO</a>
        <a href="/admin/settings" class="<?= $isOn('/admin/settings') ? 'on' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.3 1a7 7 0 0 0-1.7-1L14.5 3h-4l-.4 2.6a7 7 0 0 0-1.7 1l-2.3-1-2 3.4L6 11a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 1.7 1l.4 2.6h4l.4-2.6a7 7 0 0 0 1.7-1l2.3 1 2-3.4-2-1.5c.06-.33.1-.66.1-1z"/></svg>Settings</a>
      </nav>
      <div class="side-foot">
        <a href="/" target="_blank" rel="noopener">View site ↗</a>
        <a href="/admin/account"><?= e($user['name'] ?? '') ?></a>
        <a href="/admin/logout" class="out">Sign out</a>
      </div>
    </aside>

    <main class="main">
      <header class="top">
        <h1><?= e($title ?? 'Admin') ?></h1>
        <?php if (!empty($actions)) echo $actions; ?>
      </header>

      <?php if ($note): ?>
        <div class="flash <?= e($note['kind']) ?>"><?= e($note['message']) ?></div>
      <?php endif; ?>

      <?php require __DIR__ . '/' . $viewName . '.php'; ?>
    </main>
  </div>

<?php endif; ?>

<script>
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});
document.addEventListener('click', function (e) {
  var add = e.target.closest('[data-add-row]');
  if (!add) return;
  var tpl = document.querySelector(add.dataset.addRow);
  var row = tpl.content.cloneNode(true);
  tpl.parentNode.insertBefore(row, tpl);
});
document.addEventListener('click', function (e) {
  var rm = e.target.closest('[data-remove-row]');
  if (rm) rm.closest('[data-row]').remove();
});
</script>
</body>
</html>
