<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(page('title')) ?></title>
<meta name="description" content="<?= e(page('description')) ?>">
<link rel="canonical" href="<?= e(canonical_url()) ?>">
<?php /* Search Console and Meta domain verification. Inert strings that set
         no cookie, so they are not behind the consent banner — gating them
         would mean the domain never verifies. See inc/tracking.php. */ ?>
<?= tracking_head() ?>
<?php if (page('robots')): ?>
<meta name="robots" content="<?= e(page('robots')) ?>">
<?php endif; ?>

<meta property="og:type" content="<?= e(page('og_type') ?: 'website') ?>">
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta property="og:locale" content="en_GB">
<meta property="og:url" content="<?= e(canonical_url()) ?>">
<meta property="og:title" content="<?= e(page('title')) ?>">
<meta property="og:description" content="<?= e(page('description')) ?>">
<meta property="og:image" content="<?= e(page_og_image()) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e(page('title')) ?>">
<meta name="twitter:description" content="<?= e(page('description')) ?>">
<meta name="twitter:image" content="<?= e(page_og_image()) ?>">

<?php if (page('preload')): ?>
<link rel="preload" as="image" href="<?= e(page('preload')) ?>" fetchpriority="high">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="icon" href="/assets/img/favicon/fav-32x32.png" sizes="32x32">
<link rel="icon" href="/assets/img/favicon/fav-192x192.png" sizes="192x192">
<link rel="apple-touch-icon" href="/assets/img/favicon/fav-180x180.png">
<meta name="msapplication-TileImage" content="/assets/img/favicon/fav-270x270.png">
<meta name="theme-color" content="#0b1220">

<?php foreach (page_schema_blocks() as $ldBlock): ?>
<script type="application/ld+json"><?= json_encode($ldBlock, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endforeach; ?>
</head>
<?php /* The token for the discount-code endpoint. Only when codes are switched
         on: a page carrying a token for a feature that is off is a page saying
         the feature exists. */ ?>
<body class="<?= e(page('body_class')) ?>"<?= coupons_enabled()
    ? ' data-coupon-token="' . e(form_token('coupon')) . '"' : '' ?>>
<a class="skip" href="#content">Skip to the content</a>

<div class="topbar">
  <div class="wrap">
    <div class="tb-l">
      <span>UK delivery from &pound;3.20, priced by length</span>
      <span><?= SITE_HOURS_WEEK ?></span>
    </div>
    <div class="tb-r">
      <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a>
      <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>
    </div>
  </div>
</div>

<header class="hdr">
  <div class="wrap">
    <a class="logo" href="/" aria-label="<?= SITE_NAME ?> — home">
      <img src="/assets/img/site/logo.png" alt="<?= SITE_NAME ?>" width="140" height="40">
    </a>
    <?php /* Enter still goes to /shop/?q=, with or without JavaScript. The
             dropdown below is a shortcut past that page, never the only way
             in — combobox roles so a screen reader is told there is a list
             underneath and which row is on. */ ?>
    <form class="search" role="search" action="/shop/" method="get" data-search>
      <svg class="mag" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>"
             placeholder="Search by product, standard or bore size…" aria-label="Search products"
             autocomplete="off" role="combobox" aria-expanded="false"
             aria-controls="search-drop" aria-autocomplete="list">
      <button type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <span>Search</span>
      </button>
      <div class="search-drop" id="search-drop" role="listbox" aria-label="Search suggestions" hidden></div>
    </form>
    <div class="hdr-act">
      <a class="icon-btn" href="/my-account/" aria-label="Account">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>
      </a>
      <a class="icon-btn" href="/wishlist/" aria-label="Wishlist">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-7.5-4.6-7.5-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7.5 3C19.5 15.4 12 20 12 20z"/></svg>
        <span class="badge" data-count="wishlist" hidden>0</span>
      </a>
      <a class="icon-btn" href="/cart/" aria-label="Cart">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h2l2.2 10.4a2 2 0 0 0 2 1.6h6.9a2 2 0 0 0 2-1.55L21 8H6.5"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>
        <span class="badge" data-count="cart">0</span>
      </a>
      <button class="burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="dr">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- Everything above is chrome. A keyboard reaches the content in one press
     rather than tabbing through the whole menu; the link only shows when it
     has focus, which is the point of it. -->
<main id="content" tabindex="-1">

<?php $navHere = strtok($_SERVER['REQUEST_URI'] ?? '/', '?'); ?>
<nav class="nav" aria-label="Main">
  <div class="wrap">
    <a href="/" <?= $navHere === '/' ? 'class="on" aria-current="page"' : '' ?>>Home</a>
    <a href="/shop/" <?= $navHere === '/shop/' ? 'class="on" aria-current="page"' : '' ?>>Shop</a>
    <?php foreach (top_categories() as $navCat): ?>
      <a href="<?= e(category_url($navCat)) ?>" <?= str_starts_with($navHere, category_url($navCat)) ? 'class="on"' : '' ?>><?= e($navCat['name']) ?></a>
    <?php endforeach; ?>
    <a href="/about-us/" <?= $navHere === '/about-us/' ? 'class="on" aria-current="page"' : '' ?>>About us</a>
    <a href="/blog/" <?= $navHere === '/blog/' ? 'class="on" aria-current="page"' : '' ?>>Blog</a>
    <a href="/contacts/" <?= $navHere === '/contacts/' ? 'class="on" aria-current="page"' : '' ?>>Contacts</a>
  </div>
</nav>

<?php if (page('crumbs')): ?>
<nav class="crumbs" aria-label="Breadcrumb">
  <div class="wrap">
    <ol>
      <li><a href="/">Home</a></li>
      <?php foreach (page('crumbs') as $crumbIndex => $crumbItem): ?>
        <li<?= $crumbIndex === count(page('crumbs')) - 1 ? ' aria-current="page"' : '' ?>>
          <?php if (!empty($crumbItem['url'])): ?><a href="<?= e($crumbItem['url']) ?>"><?= e($crumbItem['label']) ?></a>
          <?php else: ?><?= e($crumbItem['label']) ?><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</nav>
<?php endif; ?>
