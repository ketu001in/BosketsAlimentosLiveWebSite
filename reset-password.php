<?php
/** Set a new password using an emailed reset token. */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('settings.php');
}

ensure_password_resets_table();

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = null;

$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db()->prepare(
        'SELECT pr.*, u.username, u.display_name
           FROM password_resets pr JOIN users u ON u.id = pr.user_id AND u.is_banned = 0
          WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()'
    );
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch();
}

if (!$row) {
    flash('error', 'That reset link is invalid or has expired. Please request a new one.');
    redirect('forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = 'The two passwords do not match.';
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass1, PASSWORD_DEFAULT), $row['user_id']]);
        db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);
        flash('success', 'Password updated — you can sign in with it now. 🎉');
        redirect('login.php');
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="panel">
    <h2>Choose a new password</h2>
    <p class="muted small">Hi <?= e($row['display_name'] ?: $row['username']) ?> — set a new password for your account below.</p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid" style="margin-top:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label class="field">New password <small>at least 8 characters</small>
        <input type="password" name="password" minlength="8" required autofocus>
      </label>
      <label class="field">Repeat new password
        <input type="password" name="password2" minlength="8" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Save new password</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
