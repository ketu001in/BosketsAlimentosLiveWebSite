<?php
/** Admin: contact-form messages inbox. */
require_once __DIR__ . '/_admin.php';

db()->exec(
    "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        name VARCHAR(80) NOT NULL,
        email VARCHAR(190) NOT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        KEY idx_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['msg_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        db()->prepare('UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        flash('success', 'Message deleted.');
    }
    redirect('admin/messages.php');
}

$messages = db()->query(
    'SELECT m.*, u.username FROM contact_messages m
  LEFT JOIN users u ON u.id = m.user_id
   ORDER BY m.is_read ASC, m.created_at DESC LIMIT 300'
)->fetchAll();

$pageTitle = 'Admin · Messages';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>Messages</h1>
  <?php admin_nav('messages'); ?>

  <?php if (!$messages): ?>
    <div class="empty"><span class="big">📭</span>No contact messages yet.</div>
  <?php endif; ?>

  <?php foreach ($messages as $m): ?>
    <div class="panel" style="margin-bottom:14px; <?= $m['is_read'] ? 'opacity:.75' : 'border-left:4px solid var(--green-600); border-radius:0 var(--radius) var(--radius) 0' ?>">
      <div style="display:flex; gap:12px; align-items:baseline; flex-wrap:wrap">
        <strong style="font-size:16px"><?= e($m['subject']) ?></strong>
        <?php if (!$m['is_read']): ?><span class="pill pill-green">new</span><?php endif; ?>
        <span class="muted small" style="margin-left:auto"><?= e(date('j M Y, H:i', strtotime($m['created_at']))) ?></span>
      </div>
      <div class="muted small" style="margin:4px 0 10px">
        From: <strong><?= e($m['name']) ?></strong> &lt;<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>&gt;
        <?php if ($m['username']): ?> · member <a href="<?= e(url('profile.php?u=' . urlencode($m['username']))) ?>">@<?= e($m['username']) ?></a><?php endif; ?>
      </div>
      <?= nl2p($m['message']) ?>
      <div style="display:flex; gap:8px; margin-top:12px">
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
          <button class="btn btn-sm btn-ghost" name="action" value="toggle"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?>
          <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
          <button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button>
        </form>
        <a class="btn btn-sm btn-outline" href="mailto:<?= e($m['email']) ?>?subject=Re: <?= e(rawurlencode($m['subject'])) ?>">Reply by email</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
