<?php
/** Admin dashboard — site health at a glance. */
require_once __DIR__ . '/_admin.php';

$pdo = db();
$stats = [
    'Members'        => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Recipes'        => $pdo->query("SELECT COUNT(*) FROM recipes WHERE status = 'published'")->fetchColumn(),
    'Forum topics'   => $pdo->query("SELECT COUNT(*) FROM forum_topics WHERE status = 'visible'")->fetchColumn(),
    'Comments'       => $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'visible'")->fetchColumn(),
    'Reactions'      => $pdo->query('SELECT COUNT(*) FROM reactions')->fetchColumn(),
    'Buddy pairs'    => $pdo->query("SELECT COUNT(*) FROM buddies WHERE status = 'accepted'")->fetchColumn(),
    'Wall posts'     => $pdo->query("SELECT COUNT(*) FROM wall_posts WHERE status = 'visible'")->fetchColumn(),
    'Ingredients'    => $pdo->query('SELECT COUNT(*) FROM ingredients')->fetchColumn(),
];

$newUsers = $pdo->query('SELECT username, display_name, avatar, created_at FROM users ORDER BY created_at DESC LIMIT 8')->fetchAll();
$newRecipes = $pdo->query(
    "SELECT r.id, r.title, r.created_at, u.username FROM recipes r JOIN users u ON u.id = r.user_id
     WHERE r.status='published' ORDER BY r.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>🛠️ Admin Panel</h1>
  <?php admin_nav('index'); ?>

  <div class="stat-grid">
    <?php foreach ($stats as $label => $n): ?>
      <div class="stat-card"><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
  </div>

  <div class="profile-cols" style="grid-template-columns:1fr 1fr">
    <div class="panel">
      <h3>Newest members</h3>
      <?php foreach ($newUsers as $u): ?>
        <div class="buddy-mini">
          <?= avatar_html($u, 36) ?>
          <a href="<?= e(url('profile.php?u=' . urlencode($u['username']))) ?>" style="flex:1"><?= e($u['display_name'] ?: $u['username']) ?></a>
          <span class="muted small"><?= e(time_ago($u['created_at'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <h3>Latest recipes</h3>
      <?php foreach ($newRecipes as $r): ?>
        <div class="buddy-mini">
          <a href="<?= e(url('recipe.php?id=' . (int)$r['id'])) ?>" style="flex:1"><?= e($r['title']) ?></a>
          <span class="muted small">@<?= e($r['username']) ?> · <?= e(time_ago($r['created_at'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
