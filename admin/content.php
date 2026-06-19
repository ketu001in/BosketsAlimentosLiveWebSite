<?php
/** Admin: recipes (feature/remove/delete), wall posts and comments moderation. */
require_once __DIR__ . '/_admin.php';

ensure_star_recipe_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $pdo = db();

    switch ($action) {
        case 'set_star':
            $pdo->prepare("UPDATE star_recipe SET recipe_id=?, mode='manual', updated_at=NOW(), updated_by=? WHERE id=1")
                ->execute([$id, $admin['id']]);
            flash('success', '⭐ Star Recipe updated.');
            break;
        case 'star_settings':
            $label = mb_substr(trim($_POST['star_label'] ?? 'Star Recipe'), 0, 60) ?: 'Star Recipe';
            $mode  = ($_POST['star_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
            $pdo->prepare("UPDATE star_recipe SET label=?, mode=?, updated_at=NOW(), updated_by=? WHERE id=1")
                ->execute([$label, $mode, $admin['id']]);
            flash('success', 'Star Recipe settings saved.');
            break;
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

$starCfg  = db()->query("SELECT * FROM star_recipe WHERE id = 1")->fetch();
$starRecipeId = (int)($starCfg['recipe_id'] ?? 0);

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

  <!-- Star Recipe Settings -->
  <div class="panel" style="border-left:4px solid #ffd700">
    <h3>⭐ Star Recipe Settings</h3>
    <form method="post" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="star_settings">
      <label class="field" style="min-width:200px;margin:0">Label text
        <input type="text" name="star_label" maxlength="60" value="<?= e($starCfg['label'] ?? 'Star Recipe') ?>" placeholder="e.g. Recipe of the Week">
      </label>
      <label class="field" style="margin:0">Mode
        <select name="star_mode">
          <option value="auto"   <?= ($starCfg['mode'] ?? 'auto') === 'auto'   ? 'selected' : '' ?>>Auto (highest score this week)</option>
          <option value="manual" <?= ($starCfg['mode'] ?? 'auto') === 'manual' ? 'selected' : '' ?>>Manual (I pick it below)</option>
        </select>
      </label>
      <button class="btn btn-primary btn-sm" type="submit">Save Settings</button>
    </form>
    <?php if ($starRecipeId): ?>
      <p class="muted small" style="margin-top:10px">Currently set: recipe #<?= $starRecipeId ?>
        <?php
          $curStar = db()->prepare("SELECT title FROM recipes WHERE id = ?");
          $curStar->execute([$starRecipeId]);
          $curStarTitle = $curStar->fetchColumn();
          if ($curStarTitle) echo '— <strong>' . e($curStarTitle) . '</strong>';
        ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>Recipes</h3>
    <div class="table-wrap">
    <table class="data">
      <tr><th>Recipe</th><th>Author</th><th>Views</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($recipes as $r): ?>
        <tr>
          <td><a href="<?= e(url('recipe.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a>
              <?php if ($r['is_featured']): ?><span class="pill pill-orange">★ featured</span><?php endif; ?>
              <?php if ((int)$r['id'] === $starRecipeId): ?><span class="pill" style="background:#ffd700;color:#5a4000">⭐ Star</span><?php endif; ?></td>
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
                <button class="btn btn-sm btn-ghost" name="action" value="feature">Feature</button>
              <?php endif; ?>
              <?php if ((int)$r['id'] !== $starRecipeId): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="set_star" style="color:#b08000;border-color:#e0c040">⭐ Set Star</button>
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
