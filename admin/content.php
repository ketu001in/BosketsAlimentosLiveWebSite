<?php
/** Admin: recipes (feature/remove/delete), wall posts and comments moderation. */
require_once __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $pdo = db();

    switch ($action) {
        case 'feature':
        case 'unfeature':
            $pdo->prepare('UPDATE recipes SET is_featured = ? WHERE id = ?')
                ->execute([$action === 'feature' ? 1 : 0, $id]);
            flash('success', $action === 'feature' ? 'Recipe featured on the homepage. ⭐' : 'Recipe un-featured.');
            break;
        case 'remove_recipe':
        case 'restore_recipe':
            $pdo->prepare('UPDATE recipes SET status = ? WHERE id = ?')
                ->execute([$action === 'remove_recipe' ? 'removed' : 'published', $id]);
            flash('success', $action === 'remove_recipe' ? 'Recipe hidden from the site.' : 'Recipe restored.');
            break;
        case 'delete_recipe':
            $st = $pdo->prepare('SELECT image FROM recipes WHERE id = ?');
            $st->execute([$id]);
            if ($img = $st->fetchColumn()) delete_upload($img);
            foreach ($pdo->query("SELECT media FROM recipe_steps WHERE recipe_id = $id") as $r) delete_upload($r['media']);
            $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$id]);
            $pdo->prepare("DELETE FROM comments WHERE target_type='recipe' AND target_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM reactions WHERE target_type='recipe' AND target_id = ?")->execute([$id]);
            $pdo->prepare('UPDATE wall_posts SET shared_recipe_id = NULL WHERE shared_recipe_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
            flash('success', 'Recipe permanently deleted.');
            break;
        case 'remove_wall':
            $pdo->prepare("UPDATE wall_posts SET status = 'removed' WHERE id = ?")->execute([$id]);
            flash('success', 'Wall post hidden.');
            break;
        case 'remove_comment':
            $pdo->prepare("UPDATE comments SET status = 'removed' WHERE id = ?")->execute([$id]);
            flash('success', 'Comment hidden.');
            break;
        case 'restore_comment':
            $pdo->prepare("UPDATE comments SET status = 'visible' WHERE id = ?")->execute([$id]);
            flash('success', 'Comment restored.');
            break;
    }
    redirect('admin/content.php');
}

$recipes = db()->query(
    "SELECT r.id, r.title, r.status, r.is_featured, r.views, r.created_at, u.username
       FROM recipes r JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC LIMIT 150"
)->fetchAll();

$comments = db()->query(
    "SELECT c.id, c.body, c.target_type, c.target_id, c.status, c.created_at, u.username
       FROM comments c JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC LIMIT 60"
)->fetchAll();

$pageTitle = 'Admin · Content';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>🍲 Content Moderation</h1>
  <?php admin_nav('content'); ?>

  <div class="panel">
    <h3>Recipes</h3>
    <div class="table-wrap">
    <table class="data">
      <tr><th>Recipe</th><th>Author</th><th>Views</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($recipes as $r): ?>
        <tr>
          <td><a href="<?= e(url('recipe.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a>
              <?php if ($r['is_featured']): ?><span class="pill pill-orange">★ featured</span><?php endif; ?></td>
          <td class="small">@<?= e($r['username']) ?></td>
          <td><?= (int)$r['views'] ?></td>
          <td><?= $r['status'] === 'published' ? '<span class="pill pill-green">live</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <?php if ($r['is_featured']): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="unfeature">Un-feature</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="feature">⭐ Feature</button>
              <?php endif; ?>
              <?php if ($r['status'] === 'published'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="remove_recipe">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_recipe">Restore</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" name="action" value="delete_recipe"
                      onclick="return confirm('Permanently delete this recipe?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <div class="panel">
    <h3>Recent comments &amp; forum replies</h3>
    <div class="table-wrap">
    <table class="data">
      <tr><th>Comment</th><th>By</th><th>On</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($comments as $c): ?>
        <tr>
          <td class="small"><?= e(mb_strimwidth($c['body'], 0, 90, '…')) ?></td>
          <td class="small">@<?= e($c['username']) ?></td>
          <td class="small"><?= e($c['target_type']) ?> #<?= (int)$c['target_id'] ?></td>
          <td><?= $c['status'] === 'visible' ? '<span class="pill pill-green">visible</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <?php if ($c['status'] === 'visible'): ?>
                <button class="btn btn-sm btn-danger" name="action" value="remove_comment">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_comment">Restore</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
