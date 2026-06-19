<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensure_email_notify_columns();

$token = trim($_GET['token'] ?? '');
$done  = false;
$error = '';

if ($token !== '') {
    $st = db()->prepare("SELECT id, display_name FROM users WHERE email_token = ?");
    $st->execute([$token]);
    $user = $st->fetch();
    if ($user) {
        db()->prepare("UPDATE users SET email_notify = 0 WHERE id = ?")->execute([$user['id']]);
        $done = true;
    } else {
        $error = 'Invalid or already-used unsubscribe link.';
    }
} else {
    $error = 'No token provided.';
}

$pageTitle = 'Unsubscribe';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:540px;text-align:center;padding:60px 20px">
  <?php if ($done): ?>
    <div style="font-size:48px;margin-bottom:16px">💚</div>
    <h2>You've been unsubscribed</h2>
    <p class="muted">You won't receive recipe notification emails from <?= e(SITE_NAME) ?> anymore.</p>
    <p class="muted">Changed your mind? You can re-enable notifications in <a href="<?= e(url('settings.php')) ?>">Account Settings</a>.</p>
  <?php else: ?>
    <div style="font-size:48px;margin-bottom:16px">❌</div>
    <h2>Unsubscribe failed</h2>
    <p class="muted"><?= e($error) ?></p>
    <p class="muted">You can manage notifications in <a href="<?= e(url('settings.php')) ?>">Account Settings</a> when signed in.</p>
  <?php endif; ?>
  <a class="btn btn-outline" href="<?= e(url('index.php')) ?>" style="margin-top:20px">Back to Homepage</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
