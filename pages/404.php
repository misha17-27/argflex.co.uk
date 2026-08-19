<?php
declare(strict_types=1);

set_page([
    'title'       => 'Page not found — ' . SITE_NAME,
    'description' => 'That page does not exist. Search the catalogue or browse by category.',
    'robots'      => 'noindex, follow',
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap narrow" style="text-align:center">
    <span class="eyebrow"><?= page_text('/404', 'eyebrow', 'Error 404') ?></span>
    <h1><?= page_text('/404', 'title', 'We couldn’t find that page') ?></h1>
    <p><?= page_text('/404', 'intro', 'The link may be out of date, or the product may have been renamed. Try searching the catalogue instead.') ?></p>
    <form class="search notfound-search" role="search" action="/shop/" method="get">
      <svg class="mag" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" placeholder="Search by product, standard or bore size…" aria-label="Search products">
      <button type="submit"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span>Search</span></button>
    </form>
  </div>
</section>

<section style="padding-top:20px">
  <div class="wrap">
    <div class="cats">
      <?php foreach (top_categories() as $c):
          $items = products_in_category($c['slug']);
          $img   = category_image($c);
      ?>
        <article class="cat">
          <div class="ph"><?php if ($img): ?><img src="/<?= e($img) ?>" alt="<?= e($c['name']) ?>" loading="lazy" width="480" height="300"><?php endif; ?></div>
          <div class="bd">
            <span class="cnt"><?= count($items) ?> products</span>
            <h3><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a></h3>
            <a class="go" href="<?= e(category_url($c)) ?>">Explore range
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
