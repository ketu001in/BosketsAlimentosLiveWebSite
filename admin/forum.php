<?php
/** Admin: forum moderation — topics and boards. */
require_once __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $pdo = db();

    switch ($action) {
        case 'hide_topic':
        case 'restore_topic':
            $pdo->prepare('UPDATE forum_topics SET status = ? WHERE id = ?')
                ->execute([$action === 'hide_topic' ? 'removed' : 'visible', $id]);
            flash('success', $action === 'hide_topic' ? 'Topic hidden.' : 'Topic restored.');
            break;
        case 'delete_topic':
            $pdo->prepare("DELETE FROM comments WHERE target_type='topic' AND target_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM reactions WHERE target_type='topic' AND target_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM forum_topics WHERE id = ?')->execute([$id]);
            flash('success', 'Topic permanently deleted.');
            break;
        case 'add_board':
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if ($name !== '') {
                $bid = find_or_create('forum_categories', $name, (int)$admin['id']);
                if ($bid && $desc !== '') {
                    $pdo->prepare('UPDATE forum_categories SET description = ? WHERE id = ?')->execute([$desc, $bid]);
                }
                flash('success', 'Board saved.');
            }
            break;
        case 'delete_board':
            $st = $pdo->prepare('SELECT COUNT(*) FROM forum_topics WHERE category_id = ?');
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) {
                flash('error', 'That board still has topics — move or delete them first.');
            } else {
                $pdo->prepare('DELETE FROM forum_categories WHERE id = ?')->execute([$id]);
                flash('success', 'Board deleted.');
            }
            break;
    }
    redirect('admin/forum.php');
}

$topics = db()->query(
    "SELECT t.id, t.title, t.status, t.views, t.created_at, u.username, fc.name AS cat_name,
            (SELECT COUNT(*) FROM comments c WHERE c.target_type='topic' AND c.target_id = t.id AND c.status='visible') replies
       FROM forum_topics t JOIN users u ON u.id = t.user_id JOIN forum_categories fc ON fc.id = t.category_id
   ORDER BY t.created_at DESC LIMIT 150"
)->fetchAll();

$boards = db()->query(
    'SELECT fc.*, (SELECT COUNT(*) FROM forum_topics t WHERE t.category_id = fc.id) topic_count
       FROM forum_categories fc ORDER BY fc.name'
)->fetchAll();

$pageTitle = 'Admin · Forum';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>💬 Forum Moderation</h1>
  <?php admin_nav('forum'); ?>

  <div class="panel">
    <h3>Boards</h3>
    <form method="post" class="filter-bar" style="margin-bottom:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_board">
      <input type="text" name="name" required maxlength="100" placeholder="New board name…" style="flex:1;margin-top:0">
      <input type="text" name="description" maxlength="200" placeholder="Short description (optional)" style="flex:2;margin-top:0">
      <button class="btn btn-primary btn-sm" type="submit">Add board</button>
    </form>
    <div class="table-wrap">
    <table class="data">
      <tr><th>Board</th><th>Description</th><th>Topics</th><th></th></tr>
      <?php foreach ($boards as $b): ?>
        <tr>
          <td><strong><?= e($b['name']) ?></strong></td>
          <td class="small"><?= e($b['description'] ?? '') ?></td>
          <td><?= (int)$b['topic_count'] ?></td>
          <td>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button class="btn btn-sm btn-danger" name="action" value="delete_board"
                      onclick="return confirm('Delete this board?')" <?= $b['topic_count'] > 0 ? 'disabled title="Board has topics"' : '' ?>>Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <div class="panel">
    <h3>Topics</h3>
    <div class="table-wrap">
    <table class="data">
      <tr><th>Topic</th><th>Board</th><th>By</th><th>Replies</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($topics as $t): ?>
        <tr>
          <td><a href="<?= e(url('forum-topic.php?id=' . (int)$t['id'])) ?>"><?= e(mb_strimwidth($t['title'], 0, 60, '…')) ?></a></td>
          <td class="small"><?= e($t['cat_name']) ?></td>
          <td class="small">@<?= e($t['username']) ?></td>
          <td><?= (int)$t['replies'] ?></td>
          <td><?= $t['status'] === 'visible' ? '<span class="pill pill-green">live</span>' : '<span class="pill pill-red">hidden</span>' ?></td>
          <td>
            <form method="post" style="display:flex;gap:6px">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <?php if ($t['status'] === 'visible'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="hide_topic">Hide</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore_topic">Restore</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" name="action" value="delete_topic"
                      onclick="return confirm('Permanently delete this topic and its replies?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
