<?php
/**
 * Single blog article.
 * @var array $post
 */
declare(strict_types=1);

$all  = all_posts();
$idx  = array_search($post['slug'], array_column($all, 'slug'), true);
$prev = $idx !== false && $idx > 0 ? $all[$idx - 1] : null;
$next = $idx !== false && $idx < count($all) - 1 ? $all[$idx + 1] : null;
$more = array_values(array_filter($all, fn($x) => $x['slug'] !== $post['slug']));
$more = array_slice($more, 0, 3);

set_page([
    'title'       => $post['title'] . ' — ' . SITE_NAME,
    'description' => clip($post['excerpt'], 160),
    'crumbs'      => [['label' => 'Blog', 'url' => '/blog/'], ['label' => $post['title']]],
    'preload'     => $post['image'] ? '/' . $post['image'] : null,
    'image'       => $post['image'] ? SITE_URL . '/' . $post['image'] : null,
    'og_type'     => 'article',
    'schema'      => [array_filter([
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => $post['title'],
        'description'   => $post['excerpt'],
        'datePublished' => $post['date'],
        'image'         => $post['image'] ? SITE_URL . '/' . $post['image'] : null,
        'mainEntityOfPage' => SITE_URL . post_url($post),
        'author'        => ['@type' => 'Organization', 'name' => SITE_NAME],
        'publisher'     => ['@type' => 'Organization', 'name' => SITE_NAME,
                            'logo' => ['@type' => 'ImageObject', 'url' => SITE_URL . '/assets/img/site/logo.png']],
    ])],
]);

require ROOT_DIR . '/inc/header.php';
?>

<article class="article">
  <div class="wrap narrow">
    <span class="eyebrow"><?= e(format_date($post['date'])) ?></span>
    <h1><?= e($post['title']) ?></h1>
    <p class="lede"><?= e($post['excerpt']) ?></p>
  </div>

  <?php if ($post['image']): ?>
    <div class="wrap narrow">
      <div class="article-hero">
        <img src="/<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>" width="820" height="461" fetchpriority="high">
      </div>
    </div>
  <?php endif; ?>

  <div class="wrap narrow">
    <div class="rich article-body"><?= $post['content'] ?></div>

    <div class="share">
      <span>Share</span>
      <a href="https://www.facebook.com/sharer/sharer.php?u=https://argflex.co.uk<?= e(post_url($post)) ?>" rel="noopener" target="_blank" aria-label="Share on Facebook">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.25-1.5 1.55-1.5h1.65V4.6c-.3-.04-1.3-.13-2.45-.13-2.42 0-4.08 1.48-4.08 4.2v2.23H7.5V14h2.67v8z"/></svg>
      </a>
      <a href="https://www.linkedin.com/shareArticle?mini=true&url=https://argflex.co.uk<?= e(post_url($post)) ?>" rel="noopener" target="_blank" aria-label="Share on LinkedIn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-.95 1.83-1.95 3.76-1.95 4.02 0 4.76 2.5 4.76 5.76V21h-4v-5.5c0-1.31-.02-3-1.9-3-1.9 0-2.19 1.42-2.19 2.9V21H9z"/></svg>
      </a>
      <a href="https://wa.me/?text=https://argflex.co.uk<?= e(post_url($post)) ?>" rel="noopener" target="_blank" aria-label="Share on WhatsApp">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3-.8-2.5-1.1-4.1-3.7-4.2-3.9-.1-.2-1-1.4-1-2.6s.6-1.8.9-2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.5c-.1.2-.3.3-.1.6.2.3.7 1.2 1.6 1.9 1.1.9 1.5 1 1.7 1.1.2.1.4.1.5-.1l.7-.8c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.1.1.5-.1 1.1z"/></svg>
      </a>
    </div>

    <nav class="pager">
      <?php if ($prev): ?>
        <a class="p-prev" href="<?= e(post_url($prev)) ?>"><span>Newer article</span><b><?= e($prev['title']) ?></b></a>
      <?php else: ?><span></span><?php endif; ?>
      <?php if ($next): ?>
        <a class="p-next" href="<?= e(post_url($next)) ?>"><span>Older article</span><b><?= e($next['title']) ?></b></a>
      <?php endif; ?>
    </nav>
  </div>
</article>

<section style="background:var(--bg-soft);border-top:1px solid var(--line)">
  <div class="wrap">
    <div class="sec-head">
      <div>
        <span class="eyebrow">Keep reading</span>
        <h2 style="margin-top:12px">More from the blog</h2>
      </div>
      <a class="link-more" href="/blog/">All articles
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
    <div class="posts">
      <?php foreach ($more as $post): include ROOT_DIR . '/partials/post-card.php'; endforeach; ?>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
