<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensure_announcements_tables();

$me  = require_login();
$uid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        // Mark all announcements as read
        $anns = db()->query("SELECT id FROM announcements WHERE is_deleted = 0")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($anns as $aid) {
            db()->prepare(
                "INSERT IGNORE INTO announcement_reads (user_id, announcement_id, is_dismissed, created_at) VALUES (?, ?, 0, NOW())"
            )->execute([$uid, $aid]);
        }
        flash('success', 'All notifications marked as read.');
    }

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    }

    if ($action === 'mark_ann_read') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare(
            "INSERT IGNORE INTO announcement_reads (user_id, announcement_id, is_dismissed, created_at) VALUES (?, ?, 0, NOW())"
        )->execute([$uid, $id]);
    }

    if ($action === 'dismiss_ann') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare(
            "INSERT INTO announcement_reads (user_id, announcement_id, is_dismissed, created_at) VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE is_dismissed = 1"
        )->execute([$uid, $id]);
    }

    redirect('notifications.php');
}

// Fetch active announcements with read status
$announcements = db()->query(
    "SELECT a.id, a.title, a.body, a.created_at,
            COALESCE(r.is_dismissed, 0) AS is_dismissed,
            CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END AS is_read
       FROM announcements a
       LEFT JOIN announcement_reads r ON r.announcement_id = a.id AND r.user_id = $uid
      WHERE a.is_deleted = 0
      ORDER BY a.created_at DESC"
)->fetchAll();

$notifs = db()->query(
    "SELECT n.*, u.username, u.display_name, u.avatar
       FROM notifications n LEFT JOIN users u ON u.id = n.actor_id
      WHERE n.user_id = $uid
      ORDER BY n.created_at DESC LIMIT 80"
)->fetchAll();

// Mark regular notifs as read on page visit
db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);

$pageTitle = 'Notifications';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:780px">
  <div class="section-head">
    <h2>🔔 Notifications</h2>
    <form method="post">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-ghost" name="action" value="mark_all_read"
              title="Mark everything as read" style="display:flex;align-items:center;gap:6px">
        <span style="font-size:16px">✓✓</span> Mark all read
      </button>
    </form>
  </div>

  <?php
  $unreadAnns = array_filter($announcements, fn($a) => !$a['is_dismissed'] && !$a['is_read']);
  if ($unreadAnns):
  ?>
  <h3 style="margin:0 0 10px;font-size:15px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em">📢 Announcements</h3>
  <?php foreach ($unreadAnns as $a): ?>
    <div class="ann-card" style="border-left:3px solid var(--green-600)">
      <div class="ann-card-head">
        <div>
          <div class="ann-title"><?= e($a['title']) ?></div>
          <div class="ann-meta"><?= e(time_ago($a['created_at'])) ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_ann_read">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-sm btn-ghost" title="Mark as read">✓ Read</button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="dismiss_ann">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-sm btn-ghost" title="Dismiss" style="color:var(--danger)">✕</button>
          </form>
        </div>
      </div>
      <?php if (trim($a['body'])): ?>
        <div class="ann-body"><?= nl2br(e($a['body'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <hr style="border:0;border-top:1px solid var(--line);margin:24px 0 18px">
  <?php endif; ?>

  <h3 style="margin:0 0 10px;font-size:15px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em">Activity</h3>

  <?php if (!$notifs): ?>
    <div class="empty"><span class="big">🕊️</span>Nothing yet — when buddies react, comment or send requests, it shows up here.</div>
  <?php endif; ?>

  <div class="panel" style="padding:0;overflow:hidden">
  <?php foreach ($notifs as $n): ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
      <?php if ($n['username']): ?><?= avatar_html($n, 40) ?><?php else: ?><span style="font-size:24px">🌿</span><?php endif; ?>
      <div>
        <a href="<?= e(url(notification_url($n))) ?>" style="color:inherit;text-decoration:none"><?= e($n['message']) ?></a>
        <div><time style="font-size:12px;color:var(--ink-soft)"><?= e(time_ago($n['created_at'])) ?></time></div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
