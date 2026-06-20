<?php
/** Admin: unified content moderation with tabbed sections. */
require_once __DIR__ . '/_admin.php';
ensure_star_recipe_table();
ensure_recipe_pending_status();

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $pdo    = db();

    switch ($action) {

        // ── Pending recipe: approve ───────────────────────────────────────────
        case 'approve_recipe':
            $pdo->prepare("UPDATE recipes SET status='published' WHERE id=?")->execute([$id]);
            // notify author
            $st = $pdo->prepare("SELECT user_id, title FROM recipes WHERE id=?");
            $st->execute([$id]);
            $rec = $st->fetch();
            if ($rec) {
                $pdo->prepare(
                    "INSERT INTO notifications (user_id, actor_id, type, target_type, target_id, message, created_at)
                     VALUES (?, ?, 'recipe_approved', 'recipe', ?, ?, NOW())"
                )->execute([$rec['user_id'], $admin['id'], $id,
                    'Your recipe "' . mb_strimwidth($rec['title'], 0, 60, '…') . '" has been approved and is now live! 🎉']);
                send_recipe_notification_emails($id, (int)$rec['user_id']);
            }
            flash('success', 'Recipe approved and live.');
            break;

        // ── Pending recipe: reject ────────────────────────────────────────────
        case 'reject_recipe':
            $reason = trim($_POST['reason'] ?? '');
            $pdo->prepare("UPDATE recipes SET status='removed' WHERE id=?")->execute([$id]);
            $st = $pdo->prepare("SELECT user_id, title FROM recipes WHERE id=?");
            $st->execute([$id]);
            $rec = $st->fetch();
            if ($rec) {
                $msg = 'Your recipe "' . mb_strimwidth($rec['title'], 0, 60, '…') . '" was not approved.';
                if ($reason) $msg .= ' Reason: ' . $reason;
                $pdo->prepare(
                    "INSERT INTO notifications (user_id, actor_id, type, target_type, target_id, message, created_at)
                     VALUES (?, ?, 'recipe_rejected', 'recipe', ?, ?, NOW())"
                )->execute([$rec['user_id'], $admin['id'], $id, $msg]);
            }
            flash('info', 'Recipe rejected and author notified.');
            break;

        // ── Star Recipe settings ──────────────────────────────────────────────
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

        // ── Recipe moderation ─────────────────────────────────────────────────
        case 'feature':
        case 'unfeature':
            $pdo->prepare('UPDATE recipes SET is_featured=? WHERE id=?')
                ->execute([$action === 'feature' ? 1 : 0, $id]);
            flash('success', $action === 'feature' ? 'Recipe featured.' : 'Recipe un-featured.');
            break;
        case 'remove_recipe':
            $pdo->prepare("UPDATE recipes SET status='removed' WHERE id=?")->execute([$id]);
            flash('success', 'Recipe hidden.');
            break;
        case 'restore_recipe':
            $pdo->prepare("UPDATE recipes SET status='published' WHERE id=?")->execute([$id]);
            flash('success', 'Recipe restored.');
            break;
        case 'delete_recipe':
            $st = $pdo->prepare('SELECT image FROM recipes WHERE id=?');
            $st->execute([$id]);
            if ($img = $st->fetchColumn()) delete_upload($img);
            foreach ($pdo->query("SELECT media FROM recipe_steps WHERE recipe_id=$id") as $r) delete_upload($r['media']);
            $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id=?')->execute([$id]);
            $pdo->prepare("DELETE FROM comments WHERE target_type='recipe' AND target_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM reactions WHERE target_type='recipe' AND target_id=?")->execute([$id]);
            $pdo->prepare('UPDATE wall_posts SET shared_recipe_id=NULL WHERE shared_recipe_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM recipes WHERE id=?')->execute([$id]);
            flash('success', 'Recipe permanently deleted.');
            break;

        // ── Comment moderation ────────────────────────────────────────────────
        case 'remove_comment':
            $pdo->prepare("UPDATE comments SET status='removed' WHERE id=?")->execute([$id]);
            flash('success', 'Comment hidden.');
            break;
        case 'restore_comment':
            $pdo->prepare("UPDATE comments SET status='visible' WHERE id=?")->execute([$id]);
            flash('success', 'Comment restored.');
            break;
        case 'delete_comment':
            $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
            flash('success', 'Comment deleted.');
            break;

        // ── Forum topic moderation ────────────────────────────────────────────
        case 'remove_topic':
            $pdo->prepare("UPDATE forum_topics SET status='removed' WHERE id=?")->execute([$id]);
            flash('success', 'Topic hidden.');
            break;
        case 'restore_topic':
            $pdo->prepare("UPDATE forum_topics SET status='visible' WHERE id=?")->execute([$id]);
            flash('success', 'Topic restored.');
            break;
        case 'delete_topic':
            $pdo->prepare("DELETE FROM comments WHERE target_type='topic' AND target_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM forum_topics WHERE id=?")->execute([$id]);
            flash('success', 'Topic deleted.');
            break;

        // ── Wall post moderation ──────────────────────────────────────────────
        case 'remove_wall':
            $pdo->prepare("UPDATE wall_posts SET status='removed' WHERE id=?")->execute([$id]);
            flash('success', 'Wall post hidden.');
            break;
        case 'restore_wall':
            $pdo->prepare("UPDATE wall_posts SET status='visible' WHERE id=?")->execute([$id]);
            flash('success', 'Wall post restored.');
            break;
        case 'delete_wall':
            $pdo->prepare("DELETE FROM comments WHERE target_type='wall' AND target_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM wall_posts WHERE id=?")->execute([$id]);
            flash('success', 'Wall post deleted.');
            break;
    }

    $tab = $_POST['_tab'] ?? 'pending';
    redirect('admin/content.php?tab=' . urlencode($tab));
}

// ── Data queries ──────────────────────────────────────────────────────────────
$starCfg      = db()->query("SELECT * FROM star_recipe WHERE id=1")->fetch();
$starRecipeId = (int)($starCfg['recipe_id'] ?? 0);
$activeTab    = $_GET['tab'] ?? 'pending';

$pending = db()->query(
    "SELECT r.*, u.username, u.display_name, u.avatar,
            c.name AS category_name, cu.name AS cuisine_name
       FROM recipes r JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c  ON c.id  = r.category_id
  LEFT JOIN cuisines   cu ON cu.id = r.cuisine_id
      WHERE r.status = 'pending' ORDER BY r.created_at ASC"
)->fetchAll();

$recipes = db()->query(
    "SELECT r.id, r.title, r.status, r.is_featured, r.views, r.created_at, u.username
       FROM recipes r JOIN users u ON u.id = r.user_id
      WHERE r.status != 'pending' ORDER BY r.created_at DESC LIMIT 150"
)->fetchAll();

$comments = db()->query(
    "SELECT c.id, c.body, c.target_type, c.target_id, c.status, c.created_at, u.username
       FROM comments c JOIN users u ON u.id = c.user_id
      ORDER BY c.created_at DESC LIMIT 100"
)->fetchAll();

$forumTopics = db()->query(
    "SELECT t.id, t.title, t.status, t.views, t.created_at, u.username,
            (SELECT COUNT(*) FROM comments c WHERE c.target_type='topic' AND c.target_id=t.id) replies
       FROM forum_topics t JOIN users u ON u.id = t.user_id
      ORDER BY t.created_at DESC LIMIT 80"
)->fetchAll();

$wallPosts = db()->query(
    "SELECT w.id, w.body, w.status, w.created_at, u.username,
            (SELECT COUNT(*) FROM comments c WHERE c.target_type='wall' AND c.target_id=w.id) replies
       FROM wall_posts w JOIN users u ON u.id = w.user_id
      ORDER BY w.created_at DESC LIMIT 80"
)->fetchAll();

$pageTitle = 'Admin · Content';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>🍲 Content Moderation</h1>
  <?php admin_nav('content'); ?>

  <!-- Star Recipe Settings -->
  <div class="panel" style="border-left:4px solid #ffd700;margin-bottom:20px">
    <h3>⭐ Star Recipe Settings</h3>
    <form method="post" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?><input type="hidden" name="_tab" value="recipes">
      <input type="hidden" name="action" value="star_settings">
      <label class="field" style="min-width:200px;margin:0">Label text
        <input type="text" name="star_label" maxlength="60" value="<?= e($starCfg['label'] ?? 'Star Recipe') ?>">
      </label>
      <label class="field" style="margin:0">Mode
        <select name="star_mode">
          <option value="auto"   <?= ($starCfg['mode'] ?? 'auto') === 'auto'   ? 'selected' : '' ?>>Auto (highest score this week)</option>
          <option value="manual" <?= ($starCfg['mode'] ?? 'auto') === 'manual' ? 'selected' : '' ?>>Manual (pick below)</option>
        </select>
      </label>
      <button class="btn btn-primary btn-sm" type="submit">Save</button>
    </form>
  </div>

  <!-- Tabs -->
  <div class="mod-tabs">
    <?php
    $tabs = [
      'pending' => ['Pending Approval', count($pending)],
      'recipes' => ['Recipes', null],
      'comments'=> ['Comments', null],
      'forum'   => ['Forum Topics', null],
      'wall'    => ['Wall Posts', null],
    ];
    foreach ($tabs as $key => [$label, $badge]): ?>
      <a href="?tab=<?= $key ?>" class="mod-tab<?= $activeTab === $key ? ' active' : '' ?>">
        <?= e($label) ?>
        <?php if ($badge !== null && $badge > 0): ?><span class="mod-badge"><?= $badge ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ══ PENDING ══════════════════════════════════════════════════════════════ -->
  <?php if ($activeTab === 'pending'): ?>
  <div class="panel">
    <?php if (!$pending): ?>
      <div class="empty" style="padding:30px 0"><span class="big">✅</span>No recipes pending approval.</div>
    <?php else: ?>
      <?php foreach ($pending as $r): ?>
        <div class="pending-card">
          <div class="pending-thumb">
            <?php if ($r['image']): ?>
              <img src="<?= e(url($r['image'])) ?>" alt="">
            <?php else: ?>
              <span style="font-size:32px">🍳</span>
            <?php endif; ?>
          </div>
          <div class="pending-info">
            <div class="pending-title"><?= e($r['title']) ?></div>
            <div class="pending-meta">
              by <strong>@<?= e($r['username']) ?></strong>
              <?php if ($r['category_name']): ?> · <?= e($r['category_name']) ?><?php endif; ?>
              <?php if ($r['cuisine_name']): ?> · <?= e($r['cuisine_name']) ?><?php endif; ?>
              · submitted <?= e(time_ago($r['created_at'])) ?>
            </div>
            <?php if (trim($r['story'] ?? '')): ?>
              <p class="pending-story"><?= e(mb_strimwidth(trim($r['story']), 0, 160, '…')) ?></p>
            <?php endif; ?>
          </div>
          <div class="pending-actions">
            <a class="btn btn-sm btn-ghost" href="<?= e(url('recipe.php?id=' . (int)$r['id'] . '&preview=1')) ?>" target="_blank">Preview</a>
            <form method="post" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="_tab" value="pending">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-primary" name="action" value="approve_recipe">✅ Approve</button>
            </form>
            <button class="btn btn-sm btn-danger" onclick="toggleReject(<?= (int)$r['id'] ?>)">❌ Reject</button>
            <div id="reject-<?= (int)$r['id'] ?>" style="display:none;margin-top:10px">
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="_tab" value="pending">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="reject_recipe">
                <textarea name="reason" placeholder="Reason for rejection (optional — sent to author)" style="width:100%;min-height:70px;border-radius:6px;padding:8px;border:1px solid var(--line);font-size:13px;resize:vertical;background:var(--surface)"></textarea>
                <button class="btn btn-sm btn-danger" type="submit" style="margin-top:6px">Confirm Rejection</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ══ RECIPES ══════════════════════════════════════════════════════════════ -->
  <?php elseif ($activeTab === 'recipes'): ?>
  <div class="panel">
    <div class="table-wrap">
    <table class="data">
      <tr><th>Recipe</th><th>Author</th><th>Views</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($recipes as $r): ?>
        <tr>
          <td>
            <a href="<?= e(url('recipe.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a>
            <?php if ($r['is_featured']): ?><span class="pill pill-orange">★</span><?php endif; ?>
            <?php if ((int)$r['id'] === $starRecipeId): ?><span class="pill" style="background:#ffd700;color:#5a4000">⭐</span><?php endif; ?>
          </td>
          <td class="small">@<?= e($r['username']) ?></td>
          <td><?= (int)$r['views'] ?></td>
          <td><?= $r['status'] === 'published' ? '<span class="pill pill-green">live</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post" style="display:flex;gap:5px;flex-wrap:wrap">
              <?= csrf_field() ?><input type="hidden" name="_tab" value="recipes">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <?php if ($r['is_featured']): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="unfeature">Un-feature</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="feature">Feature</button>
              <?php endif; ?>
              <?php if ((int)$r['id'] !== $starRecipeId): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="set_star" style="color:#b08000;border-color:#e0c040">⭐ Star</button>
              <?php endif; ?>
              <?php if ($r['status'] === 'published'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="remove_recipe">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_recipe">Restore</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" name="action" value="delete_recipe" onclick="return confirm('Permanently delete this recipe?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <!-- ══ COMMENTS ═════════════════════════════════════════════════════════════ -->
  <?php elseif ($activeTab === 'comments'): ?>
  <div class="panel">
    <div class="table-wrap">
    <table class="data">
      <tr><th>Comment</th><th>Author</th><th>On</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($comments as $c): ?>
        <tr>
          <td class="small"><?= e(mb_strimwidth($c['body'], 0, 100, '…')) ?></td>
          <td class="small">@<?= e($c['username']) ?></td>
          <td class="small"><?= e(ucfirst($c['target_type'])) ?> #<?= (int)$c['target_id'] ?></td>
          <td><?= $c['status'] === 'visible' ? '<span class="pill pill-green">visible</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post" style="display:flex;gap:5px">
              <?= csrf_field() ?><input type="hidden" name="_tab" value="comments">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <?php if ($c['status'] === 'visible'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="remove_comment">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_comment">Restore</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" name="action" value="delete_comment" onclick="return confirm('Delete this comment?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <!-- ══ FORUM ════════════════════════════════════════════════════════════════ -->
  <?php elseif ($activeTab === 'forum'): ?>
  <div class="panel">
    <div class="table-wrap">
    <table class="data">
      <tr><th>Topic</th><th>Author</th><th>Views</th><th>Replies</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($forumTopics as $t): ?>
        <tr>
          <td><a href="<?= e(url('forum-topic.php?id=' . (int)$t['id'])) ?>"><?= e(mb_strimwidth($t['title'], 0, 70, '…')) ?></a></td>
          <td class="small">@<?= e($t['username']) ?></td>
          <td><?= (int)$t['views'] ?></td>
          <td><?= (int)$t['replies'] ?></td>
          <td><?= $t['status'] === 'visible' ? '<span class="pill pill-green">visible</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post" style="display:flex;gap:5px">
              <?= csrf_field() ?><input type="hidden" name="_tab" value="forum">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <?php if ($t['status'] === 'visible'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="remove_topic">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_topic">Restore</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" name="action" value="delete_topic" onclick="return confirm('Delete this topic and all its replies?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <!-- ══ WALL ═════════════════════════════════════════════════════════════════ -->
  <?php elseif ($activeTab === 'wall'): ?>
  <div class="panel">
    <div class="table-wrap">
    <table class="data">
      <tr><th>Post</th><th>Author</th><th>Comments</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($wallPosts as $w): ?>
        <tr>
          <td class="small"><?= e(mb_strimwidth(strip_tags($w['body'] ?? ''), 0, 100, '…')) ?></td>
          <td class="small">@<?= e($w['username']) ?></td>
          <td><?= (int)$w['replies'] ?></td>
          <td><?= $w['status'] === 'visible' ? '<span class="pill pill-green">visible</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post" style="display:flex;gap:5px">
              <?= csrf_field() ?><input type="hidden" name="_tab" value="wall">
              <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
              <?php if ($w['status'] === 'visible'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="remove_wall">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_wall">Restore</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" name="action" value="delete_wall" onclick="return confirm('Delete this wall post and its comments?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
  <?php endif; ?>

</div>
<script>
function toggleReject(id) {
  var el = document.getElementById('reject-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
