<?php
/**
 * Blog card.
 * @var array $post
 */
declare(strict_types=1);
?>
<a class="post" href="<?= e(post_url($post)) ?>">
  <?php if (!empty($post['image'])): ?>
    <div class="ph"><img src="/<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>" loading="lazy" width="768" height="432"></div>
  <?php endif; ?>
  <div class="bd">
    <span class="d"><?= e(format_date($post['date'])) ?></span>
    <h3><?= e($post['title']) ?></h3>
    <p><?= e($post['excerpt']) ?></p>
    <span class="r">Continue reading
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </span>
  </div>
</a>
