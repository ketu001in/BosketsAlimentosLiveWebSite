<?php
/** Admin: push announcements to all registered users. */
require_once __DIR__ . '/_admin.php';
ensure_announcements_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body']  ?? '');
        if ($title === '') {
            flash('error', 'Title is required.');
        } else {
            db()->prepare(
                "INSERT INTO announcements (title, body, created_by, created_at) VALUES (?, ?, ?, NOW())"
            )->execute([$title, $body, $admin['id']]);
            flash('success', 'Announcement sent to all registered users.');
        }
    }

    if ($action === 'hard_delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM announcement_reads WHERE announcement_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        flash('success', 'Announcement permanently deleted.');
    }

    redirect('admin/announcements.php');
}

// Stats per announcement
$announcements = db()->query(
    "SELECT a.*,
            u.username AS author,
            (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id) AS read_count,
            (SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id AND r.is_dismissed = 1) AS dismissed_count,
            (SELECT COUNT(*) FROM users WHERE is_banned = 0) AS total_users
       FROM announcements a
       JOIN users u ON u.id = a.created_by
      WHERE a.is_deleted = 0
      ORDER BY a.created_at DESC LIMIT 100"
)->fetchAll();

$pageTitle = 'Admin · Announcements';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>📢 Push Announcements</h1>
  <?php admin_nav('announcements'); ?>

  <div class="panel" style="margin-bottom:28px">
    <h3>Send new announcement</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label class="field">Title <span class="req">*</span>
        <input type="text" name="title" maxlength="200" placeholder="e.g. Welcome to our new gallery section!" required>
      </label>
      <label class="field">Message body <small>Shown when user clicks the announcement</small>
        <textarea name="body" maxlength="3000" rows="4" placeholder="Full details of the announcement…"></textarea>
      </label>
      <div><button class="btn btn-primary" type="submit">📢 Send to all users</button></div>
    </form>
  </div>

  <h3 style="margin-bottom:14px">Announcement history (<?= count($announcements) ?>)</h3>

  <?php if (!$announcements): ?>
    <div class="empty"><span class="big">📭</span>No announcements sent yet.</div>
  <?php endif; ?>

  <?php foreach ($announcements as $a): ?>
    <div class="ann-card">
      <div class="ann-card-head">
        <div>
          <div class="ann-title"><?= e($a['title']) ?></div>
          <div class="ann-meta">
            Sent <?= e(time_ago($a['created_at'])) ?> by @<?= e($a['author']) ?>
            &nbsp;·&nbsp; <?= (int)$a['read_count'] ?> / <?= (int)$a['total_users'] ?> users read
            &nbsp;·&nbsp; <?= (int)$a['dismissed_count'] ?> dismissed
          </div>
        </div>
        <form method="post" style="flex-shrink:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="hard_delete">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit"
                  onclick="return confirm('Permanently delete this announcement from the database?')">
            🗑 Hard Delete
          </button>
        </form>
      </div>
      <?php if (trim($a['body'])): ?>
        <div class="ann-body"><?= e($a['body']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
