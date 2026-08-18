<?php
declare(strict_types=1);

$posts = all_posts();
$lead  = array_shift($posts);

set_page([
    'title'       => 'Blog — ' . SITE_NAME,
    'description' => 'Technical guides on industrial hose: standards, materials, applications and how to pick the right line for the job.',
    'crumbs'      => [['label' => 'Blog']],
]);

require ROOT_DIR . '/inc/header.php';
?>

<section class="pg-head">
  <div class="wrap">
    <span class="eyebrow">Knowledge base</span>
    <h1>Blog</h1>
    <p>How our hose products solve real challenges across industries — from construction to chemical transport. <?= count(all_posts()) ?> articles.</p>
  </div>
</section>

<?php if ($lead): ?>
<section style="padding-bottom:40px">
  <div class="wrap">
    <a class="lead-post" href="<?= e(post_url($lead)) ?>">
      <?php if ($lead['image']): ?>
        <div class="ph"><img src="/<?= e($lead['image']) ?>" alt="<?= e($lead['title']) ?>" width="768" height="432" fetchpriority="high"></div>
      <?php endif; ?>
      <div class="bd">
        <span class="d">Latest · <?= e(format_date($lead['date'])) ?></span>
        <h2><?= e($lead['title']) ?></h2>
        <p><?= e($lead['excerpt']) ?></p>
        <span class="r">Continue reading
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
      </div>
    </a>
  </div>
</section>
<?php endif; ?>

<section style="padding-top:0">
  <div class="wrap">
    <div class="posts cols-3">
      <?php foreach ($posts as $post): include ROOT_DIR . '/partials/post-card.php'; endforeach; ?>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="cta">
      <div>
        <h2>Still not sure which hose you need?</h2>
        <p>Our team answers technical questions the same working day.</p>
      </div>
      <a class="btn" href="/contacts/">Ask a question</a>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
