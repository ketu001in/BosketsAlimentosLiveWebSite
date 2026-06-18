<?php
/** CMS forgot-password — emails the admin a reset link (admin accounts only). */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_cms_logged_in()) {
    cms_redirect('index.php');
}

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    ensure_password_resets_table();
    $email = trim($_POST['email'] ?? '');

    $now = time();
    $_SESSION['cms_pw_times'] = array_filter($_SESSION['cms_pw_times'] ?? [], fn($t) => $t > $now - 900);
    if (count($_SESSION['cms_pw_times']) >= 3) {
        flash('error', 'Too many reset requests — please wait a few minutes and try again.');
        cms_redirect('forgot-password.php');
    }
    $_SESSION['cms_pw_times'][] = $now;

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $st = db()->prepare('SELECT id, email, display_name, username FROM users WHERE email = ? AND is_admin = 1 AND is_banned = 0');
        $st->execute([$email]);
        if ($user = $st->fetch()) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);
            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())'
            )->execute([$user['id'], hash('sha256', $token)]);

            $link = cms_url('reset-password.php?token=' . $token);
            $name = $user['display_name'] ?: $user['username'];
            send_mail(
                $user['email'],
                'Reset your ' . SITE_NAME . ' CMS / admin password',
                "Hi $name,\n\n"
                . "A password reset was requested for the " . SITE_NAME . " admin (CMS) account.\n\n"
                . "Open this link within 1 hour to set a new password:\n$link\n\n"
                . "If you didn't request this, ignore this email — your password stays unchanged.\n\n"
                . SITE_NAME . " · CMS Portal"
            );
        }
    }
    $sent = true;
}

$cmsPageTitle = 'Forgot password';
$cmsBare = true;
include __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <h2 style="margin-top:0">Reset admin password 🔑</h2>
  <?php if ($sent): ?>
    <div class="flash flash-success">If that email belongs to an administrator account, a reset link is on its way. It works for 1 hour — check spam too.</div>
    <p class="auth-alt" style="margin-top:18px"><a href="<?= e(cms_url('login.php')) ?>">&larr; Back to sign in</a></p>
  <?php else: ?>
    <p class="muted small">Enter the admin account's email and we'll send a link to choose a new password. This resets the same account you use on the main site.</p>
    <form method="post" class="form-grid" style="margin-top:16px">
      <?= csrf_field() ?>
      <label class="field">Admin email
        <input type="email" name="email" required autofocus>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    </form>
    <p class="auth-alt" style="margin-bottom:0"><a href="<?= e(cms_url('login.php')) ?>">&larr; Back to sign in</a></p>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
