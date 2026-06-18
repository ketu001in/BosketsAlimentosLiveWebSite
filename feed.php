<?php
/** Buddy feed: wall posts + new recipes from you and your buddies (Facebook-style). */
require_once __DIR__ . '/includes/bootstrap.php';

$me  = require_login();
$uid = (int)$me['id'];

$ids   = buddy_ids($uid);
$ids[] = $uid;
$in    = implode(',', array_fill(0, count($ids), '?'));

// wall posts from the circle
$st = db()->prepare(
    "SELECT 'wall' AS kind, w.id, w.user_id, w.body, w.image, w.shared_recipe_id, w.created_at,
            u.username, u.display_name, u.avatar,
            r.title AS r_title, r.image AS r_image, r.id AS r_id
       FROM wall_posts w
       JOIN users u ON u.id = w.user_id
  LEFT JOIN recipes r ON r.id = w.shared_recipe_id AND r.status = 'published'
      WHERE w.user_id IN ($in) AND w.status = 'visible'
   ORDER BY w.created_at DESC LIMIT 40"
);
$st->execute($ids);
$wallItems = $st->fetchAll();

// recipes from the circle
$st = db()->prepare(
    "SELECT 'recipe' AS kind, r.id, r.user_id, r.title, r.image, r.story, r.created_at,
            u.username, u.display_name, u.avatar
       FROM recipes r JOIN users u ON u.id = r.user_id
      WHERE r.user_id IN ($in) AND r.status = 'published'
   ORDER BY r.created_at DESC LIMIT 40"
);
$st->execute($ids);
$recipeItems = $st->fetchAll();

$feed = array_merge($wallItems, $recipeItems);
usort($feed, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$feed = array_slice($feed, 0, 50);

$pageTitle = 'My Feed';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:720px">
  <h1>📰 My Feed</h1>
  <p class="muted">The latest recipes and food stories from you and your <?= count($ids) - 1 ?> buddies.</p>

  <?php if (count($ids) === 1): ?>
    <div class="panel" style="text-align:center">
      <h3>Your feed is hungry for buddies 🍽️</h3>
      <p class="muted">Add buddies to fill this feed with their recipes and food stories.</p>
      <a class="btn btn-primary" href="<?= e(url('buddies.php')) ?>">Find buddies</a>
    </div>
  <?php elseif (!$feed): ?>
    <div class="empty"><span class="big">🍃</span>Quiet in the kitchen… no posts from your circle yet.</div>
  <?php endif; ?>

  <?php foreach ($feed as $item): ?>
    <article class="post">
      <div class="post-head">
        <?= avatar_html($item, 44) ?>
        <div class="who">
          <a href="<?= e(url('profile.php?u=' . urlencode($item['username']))) ?>"><?= e($item['display_name'] ?: $item['username']) ?></a>
          <?= $item['kind'] === 'recipe' ? ' posted a new recipe' : '' ?>
          <time><?= e(time_ago($item['created_at'])) ?></time>
        </div>
      </div>

      <?php if ($item['kind'] === 'recipe'): ?>
        <a class="shared-recipe" href="<?= e(url('recipe.php?id=' . (int)$item['id'])) ?>">
          <?php if ($item['image']): ?><img src="<?= e(url($item['image'])) ?>" alt=""><?php endif; ?>
          <div><strong><?= e($item['title']) ?></strong>
          <?php if (!empty($item['story'])): ?><br><span class="muted small"><?= e(mb_strimwidth($item['story'], 0, 120, '…')) ?></span><?php endif; ?>
          </div>
        </a>
        <div class="post-actions"><?= reaction_bar('recipe', (int)$item['id']) ?>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('recipe.php?id=' . (int)$item['id'])) ?>">💬 Comment</a>
        </div>
      <?php else: ?>
        <?php if ($item['body'] !== ''): ?><?= nl2p($item['body']) ?><?php endif; ?>
        <?php if ($item['image']): ?><img class="post-img" src="<?= e(url($item['image'])) ?>" alt=""><?php endif; ?>
        <?php if ($item['r_id']): ?>
          <a class="shared-recipe" href="<?= e(url('recipe.php?id=' . (int)$item['r_id'])) ?>">
            <?php if ($item['r_image']): ?><img src="<?= e(url($item['r_image'])) ?>" alt=""><?php endif; ?>
            <div><span class="muted small">Shared recipe</span><br><strong><?= e($item['r_title']) ?></strong></div>
          </a>
        <?php endif; ?>
        <div class="post-actions"><?= reaction_bar('wall', (int)$item['id']) ?>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('profile.php?u=' . urlencode($item['username']) . '#wall-comments-' . (int)$item['id'])) ?>">💬 Comment</a>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
