<?php
require_once __DIR__ . '/includes/bootstrap.php';

$me  = require_login();
$uid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$st = db()->prepare(
    'SELECT n.*, u.username, u.display_name, u.avatar
       FROM notifications n LEFT JOIN users u ON u.id = n.actor_id
      WHERE n.user_id = ?
   ORDER BY n.created_at DESC LIMIT 80'
);
$st->execute([$uid]);
$notifs = $st->fetchAll();

$pageTitle = 'Notifications';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:760px">
  <div class="section-head">
    <h2 style="font-size:30px">🔔 Notifications</h2>
    <?php if ($notifs): ?>
      <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Mark all read</button></form>
    <?php endif; ?>
  </div>

  <?php if (!$notifs): ?>
    <div class="empty"><span class="big">🕊️</span>Nothing yet — when buddies react, comment or send requests, it shows up here.</div>
  <?php endif; ?>

  <?php foreach ($notifs as $n): ?>
    <a class="notif <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= e(url(notification_url($n))) ?>">
      <?php if ($n['username']): ?><?= avatar_html($n, 40) ?><?php else: ?><span style="font-size:24px">🌿</span><?php endif; ?>
      <div><?= e($n['message']) ?></div>
      <time><?= e(time_ago($n['created_at'])) ?></time>
    </a>
  <?php endforeach; ?>
</div>
<?php
// visiting the page marks everything read
db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
include __DIR__ . '/includes/footer.php';
?>
