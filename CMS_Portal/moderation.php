<?php
/** CMS: moderation queue (post-moderation — content is live, hide/restore/remove). */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_superuser();
$pdo = db();

$types = ['recipes' => 'Recipes', 'comments' => 'Comments', 'wall' => 'Wall posts', 'topics' => 'Forum topics'];
$type = $_GET['type'] ?? 'recipes';
if (!isset($types[$type])) {
    $type = 'recipes';
}

/** Permanently delete a piece of content + its dependants. */
function cms_delete_content(string $type, int $id): void
{
    $pdo = db();
    if ($type === 'recipes') {
        $st = $pdo->prepare('SELECT image FROM recipes WHERE id = ?');
        $st->execute([$id]);
        if ($img = $st->fetchColumn()) { delete_upload($img); }
        foreach ($pdo->query('SELECT media FROM recipe_steps WHERE recipe_id = ' . $id) as $row) { delete_upload($row['media']); }
        $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$id]);
        $pdo->prepare("DELETE FROM comments WHERE target_type='recipe' AND target_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM reactions WHERE target_type='recipe' AND target_id=?")->execute([$id]);
        $pdo->prepare('UPDATE wall_posts SET shared_recipe_id=NULL WHERE shared_recipe_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM recipes WHERE id=?')->execute([$id]);
    } elseif ($type === 'comments') {
        $pdo->prepare("DELETE FROM reactions WHERE target_type='comment' AND target_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM comments WHERE id=?')->execute([$id]);
    } elseif ($type === 'wall') {
        $st = $pdo->prepare('SELECT image FROM wall_posts WHERE id = ?');
        $st->execute([$id]);
        if ($img = $st->fetchColumn()) { delete_upload($img); }
        $pdo->prepare("DELETE FROM comments WHERE target_type='wall' AND target_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM reactions WHERE target_type='wall' AND target_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM wall_posts WHERE id=?')->execute([$id]);
    } elseif ($type === 'topics') {
        $pdo->prepare("DELETE FROM comments WHERE target_type='topic' AND target_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM reactions WHERE target_type='topic' AND target_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM forum_topics WHERE id=?')->execute([$id]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $t  = $_POST['type'] ?? $type;
    if (!isset($types[$t])) { $t = 'recipes'; }

    $tableMap  = ['recipes' => 'recipes', 'comments' => 'comments', 'wall' => 'wall_posts', 'topics' => 'forum_topics'];
    $liveMap   = ['recipes' => 'published', 'comments' => 'visible', 'wall' => 'visible', 'topics' => 'visible'];
    $table = $tableMap[$t];

    if ($id && in_array($action, ['hide', 'restore'], true)) {
        $newStatus = $action === 'hide' ? 'removed' : $liveMap[$t];
        $pdo->prepare("UPDATE `$table` SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        cms_log_action((int)$admin['id'], $t, $id, $action);
        flash('success', ucfirst($action) . 'd.');
    } elseif ($id && $action === 'delete') {
        cms_delete_content($t, $id);
        cms_log_action((int)$admin['id'], $t, $id, 'delete');
        flash('success', 'Permanently deleted.');
    }
    cms_redirect('moderation.php?type=' . $t);
}

// counts per tab (live items)
$counts = [
    'recipes'  => (int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE status='published'")->fetchColumn(),
    'comments' => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE status='visible'")->fetchColumn(),
    'wall'     => (int)$pdo->query("SELECT COUNT(*) FROM wall_posts WHERE status='visible'")->fetchColumn(),
    'topics'   => (int)$pdo->query("SELECT COUNT(*) FROM forum_topics WHERE status='visible'")->fetchColumn(),
];

// rows for the active tab
$rows = [];
if ($type === 'recipes') {
    $rows = $pdo->query(
        "SELECT r.id, r.title AS label, r.status, r.created_at, u.username, u.display_name, NULL AS extra
           FROM recipes r JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC LIMIT 200"
    )->fetchAll();
} elseif ($type === 'comments') {
    $rows = $pdo->query(
        "SELECT c.id, c.body AS label, c.status, c.created_at, u.username, u.display_name, c.target_type AS extra
           FROM comments c JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC LIMIT 200"
    )->fetchAll();
} elseif ($type === 'wall') {
    $rows = $pdo->query(
        "SELECT w.id, w.body AS label, w.status, w.created_at, u.username, u.display_name, NULL AS extra
           FROM wall_posts w JOIN users u ON u.id = w.user_id ORDER BY w.created_at DESC LIMIT 200"
    )->fetchAll();
} elseif ($type === 'topics') {
    $rows = $pdo->query(
        "SELECT t.id, t.title AS label, t.status, t.created_at, u.username, u.display_name, NULL AS extra
           FROM forum_topics t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 200"
    )->fetchAll();
}

function cms_snippet(?string $s, int $len = 90): string
{
    $s = trim(preg_replace('/\s+/', ' ', strip_tags((string)$s)));
    return $s === '' ? '—' : mb_strimwidth($s, 0, $len, '…');
}

$cmsPageTitle = 'Moderation';
$cmsActive = 'moderation';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-page-head">
  <div>
    <h1>Moderation</h1>
    <p class="cms-sub">Posts go live immediately. Review them here — <strong>Hide</strong> takes something off the site (reversible), <strong>Delete</strong> removes it for good. The main-site Admin panel still works too.</p>
  </div>
</div>

<div class="cms-tabs">
  <?php foreach ($types as $k => $lbl): ?>
    <a href="<?= e(cms_url('moderation.php?type=' . $k)) ?>" class="<?= $k === $type ? 'active' : '' ?>">
      <?= e($lbl) ?><span class="count"><?= $counts[$k] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="panel"><p class="cms-sub" style="margin:0">Nothing to show here.</p></div>
<?php else: ?>
  <table class="cms-table">
    <tr><th>Content</th><th>Author</th><th>Posted</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): $hidden = $r['status'] === 'removed'; ?>
      <tr>
        <td>
          <?php if ($type === 'comments' && $r['extra']): ?><span class="cms-pill grey"><?= e($r['extra']) ?></span> <?php endif; ?>
          <?= e(cms_snippet($r['label'])) ?>
        </td>
        <td class="cms-sub">@<?= e($r['username']) ?></td>
        <td class="cms-sub"><?= e(time_ago($r['created_at'])) ?></td>
        <td><span class="cms-pill <?= $hidden ? 'red' : 'green' ?>"><?= $hidden ? 'Hidden' : 'Live' ?></span></td>
        <td>
          <div class="cms-actions">
            <?php if ($hidden): ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline">Restore</button></form>
            <?php else: ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-ghost">Hide</button></form>
            <?php endif; ?>
            <form method="post" style="display:inline" data-confirm="Permanently delete this? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
