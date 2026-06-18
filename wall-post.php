<?php
/** Single wall post (notification landing page). Buddies-only, like the wall itself. */
require_once __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare(
    "SELECT w.*, u.username, u.display_name, u.avatar,
            r.title AS r_title, r.image AS r_image, r.id AS r_id
       FROM wall_posts w
       JOIN users u ON u.id = w.user_id AND u.is_banned = 0
  LEFT JOIN recipes r ON r.id = w.shared_recipe_id AND r.status = 'published'
      WHERE w.id = ? AND w.status = 'visible'"
);
$st->execute([$id]);
$p = $st->fetch();

if (!$p) {
    flash('error', 'That wall post no longer exists.');
    redirect('feed.php');
}

$isOwn = (int)$p['user_id'] === (int)$me['id'];
if (!$isOwn && !is_admin() && !are_buddies((int)$me['id'], (int)$p['user_id'])) {
    flash('info', 'Wall posts are visible to buddies only. Send a buddy request to see them.');
    redirect('profile.php?u=' . urlencode($p['username']));
}

$authorName = $p['display_name'] ?: $p['username'];
$pageTitle = 'Wall post by ' . $authorName;
include __DIR__ . '/includes/header.php';
?>
<div class="container section section-narrow">

  <p><a class="muted small" href="<?= e(url('profile.php?u=' . urlencode($p['username']) . '&tab=wall')) ?>">&larr; <?= e($authorName) ?>'s wall</a></p>

  <article class="post">
    <div class="post-head">
      <?= avatar_html($p, 44) ?>
      <div class="who">
        <a href="<?= e(url('profile.php?u=' . urlencode($p['username']))) ?>"><?= e($authorName) ?></a>
        <time><?= e(time_ago($p['created_at'])) ?></time>
      </div>
      <?php if ($isOwn || is_admin()): ?>
        <button class="btn btn-sm btn-danger" style="margin-left:auto"
                onclick="if(confirm('Delete this post?'))fetch(BOSKETS.base+'/api/wall.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':BOSKETS.csrf},body:JSON.stringify({action:'delete',post_id:<?= (int)$p['id'] ?>})}).then(r=>r.json()).then(()=>location.href=BOSKETS.base+'/feed.php')">🗑</button>
      <?php endif; ?>
    </div>
    <?php if ($p['body'] !== ''): ?><?= nl2p($p['body']) ?><?php endif; ?>
    <?php if ($p['image']): ?><img class="post-img" src="<?= e(url($p['image'])) ?>" alt=""><?php endif; ?>
    <?php if ($p['r_id']): ?>
      <a class="shared-recipe" href="<?= e(url('recipe.php?id=' . (int)$p['r_id'])) ?>">
        <?php if ($p['r_image']): ?><img src="<?= e(url($p['r_image'])) ?>" alt=""><?php endif; ?>
        <div><span class="muted small">Shared recipe</span><br><strong><?= e($p['r_title']) ?></strong></div>
      </a>
    <?php endif; ?>
    <div class="post-actions"><?= reaction_bar('wall', (int)$p['id']) ?></div>
    <?php $cs = comments_for('wall', (int)$p['id']); ?>
    <div id="wall-comments-<?= (int)$p['id'] ?>">
      <?php foreach ($cs as $c): ?>
        <div class="comment">
          <?= avatar_html($c, 36) ?>
          <div class="comment-body">
            <div class="comment-head">
              <a href="<?= e(url('profile.php?u=' . urlencode($c['username']))) ?>"><?= e($c['display_name'] ?: $c['username']) ?></a>
              <time><?= e(time_ago($c['created_at'])) ?></time>
            </div>
            <div><?= nl2br(e($c['body'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <form class="comment-form" data-type="wall" data-id="<?= (int)$p['id'] ?>" data-list="#wall-comments-<?= (int)$p['id'] ?>">
      <textarea placeholder="Write a comment…" maxlength="3000"></textarea>
      <button class="btn btn-sm btn-primary" type="submit">Comment</button>
    </form>
  </article>

</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
