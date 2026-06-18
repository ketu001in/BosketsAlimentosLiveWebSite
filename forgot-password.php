<?php
/** Request a password-reset link by email. */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('settings.php');
}

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    ensure_password_resets_table();
    $email = trim($_POST['email'] ?? '');

    // Light rate limit: max 3 requests per session per 15 minutes.
    $now = time();
    $_SESSION['pw_reset_times'] = array_filter($_SESSION['pw_reset_times'] ?? [], fn($t) => $t > $now - 900);
    if (count($_SESSION['pw_reset_times']) >= 3) {
        flash('error', 'Too many reset requests — please wait a few minutes and try again.');
        redirect('forgot-password.php');
    }
    $_SESSION['pw_reset_times'][] = $now;

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $st = db()->prepare('SELECT id, email, display_name, username FROM users WHERE email = ? AND is_banned = 0');
        $st->execute([$email]);
        if ($user = $st->fetch()) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())'
            )->execute([$user['id'], hash('sha256', $token)]);

            $link = url('reset-password.php?token=' . $token);
            $name = $user['display_name'] ?: $user['username'];
            send_mail(
                $user['email'],
                'Reset your ' . SITE_NAME . ' password',
                "Hi $name,\n\n"
                . "Someone (hopefully you) asked to reset the password for your " . SITE_NAME . " account.\n\n"
                . "Open this link to choose a new password (valid for 1 hour):\n$link\n\n"
                . "If you didn't ask for this, you can safely ignore this email — your password stays unchanged.\n\n"
                . "Happy cooking!\n" . SITE_NAME . ' · ' . SITE_TAGLINE
            );
        }
    }
    // Always show the same message so the form can't be used to probe which emails exist.
    $sent = true;
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="panel">
    <h2>Forgot your password? 🔑</h2>
    <?php if ($sent): ?>
      <div class="flash flash-success">If an account exists for that email, a reset link is on its way.
      Check your inbox (and the spam folder) — the link works for 1 hour.</div>
      <p class="auth-alt" style="margin-top:18px"><a href="<?= e(url('login.php')) ?>">&larr; Back to sign in</a></p>
    <?php else: ?>
      <p class="muted small">Enter the email you registered with and we'll send you a link to set a new password.</p>
      <form method="post" class="form-grid" style="margin-top:18px">
        <?= csrf_field() ?>
        <label class="field">Email address
          <input type="email" name="email" required autofocus>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!$sent): ?>
    <p class="auth-alt">Remembered it after all? <a href="<?= e(url('login.php')) ?>"><strong>Sign in</strong></a></p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
