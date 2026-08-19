<div class="bar-row">
  <span class="muted"><?= count($posts) ?> posts</span>
  <a class="btn" href="/admin/posts/new">+ Write a post</a>
</div>

<div class="card">
  <table class="grid">
    <thead><tr><th></th><th>Title</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
        <tr>
          <td class="thumb">
            <?php if (!empty($p['image'])): ?>
              <img src="/<?= e($p['image']) ?>" alt="" width="46" height="36" loading="lazy">
            <?php endif; ?>
          </td>
          <td>
            <a href="/admin/posts/<?= e(rawurlencode($p['slug'])) ?>"><b><?= e($p['title']) ?></b></a>
            <small>/<?= e($p['slug']) ?>/</small>
          </td>
          <td><?= e(format_date($p['date'])) ?></td>
          <td class="right"><a class="ghost" href="/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">View &#8599;</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
