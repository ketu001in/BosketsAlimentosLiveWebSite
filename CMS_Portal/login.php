<?php
/** CMS SuperUser login (admin credentials, separate CMS session). */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_cms_logged_in()) {
    cms_redirect('index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_admin = 1 LIMIT 1');
    $st->execute([$login, $login]);
    $u = $st->fetch();

    if ($u && (int)$u['is_banned'] === 1) {
        $error = 'This administrator account is suspended.';
    } elseif ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['cms_admin_id'] = (int)$u['id'];
        session_regenerate_id(true);
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        $dest = $_SESSION['cms_after_login'] ?? '';
        unset($_SESSION['cms_after_login']);
        if ($dest !== '' && str_starts_with($dest, '/') && !str_starts_with($dest, '//')) {
            header('Location: ' . $dest);
            exit;
        }
        cms_redirect('index.php');
    } else {
        $error = 'Wrong credentials, or that account is not an administrator. The CMS only accepts admin logins.';
    }
}

$cmsPageTitle = 'Sign in';
$cmsBare = true;
include __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <h2 style="margin-top:0">SuperUser sign in</h2>
  <p class="muted small">The CMS portal is for the site administrator only. Sign in with your <strong>Bosket's Alimentos admin</strong> username/email and password.</p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form-grid" style="margin-top:16px">
    <?= csrf_field() ?>
    <label class="field">Admin username or email
      <input type="text" name="login" required autofocus>
    </label>
    <label class="field">Password
      <input type="password" name="password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Sign in to CMS</button>
  </form>
  <p class="auth-alt" style="margin-bottom:0"><a href="<?= e(cms_url('forgot-password.php')) ?>">Forgot your password?</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
