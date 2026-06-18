<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$login, $login]);
    $user = $st->fetch();

    if ($user && (int)$user['is_banned'] === 1) {
        $error = 'This account has been suspended. Please contact the site administrator.';
    } elseif ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        session_regenerate_id(true);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $dest = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        flash('success', 'Welcome back, ' . ($user['display_name'] ?: $user['username']) . '! 👋');
        redirect($dest !== '' && !preg_match('~^https?://~', $dest) ? ltrim($dest, '/') : 'index.php');
    } else {
        $error = 'Wrong username/email or password. Please try again.';
    }
}

$pageTitle = 'Sign In';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="panel">
    <h2>Welcome back 👋</h2>
    <p class="muted small">Sign in to post recipes, react, comment and catch up with your buddies.</p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid" style="margin-top:18px">
      <?= csrf_field() ?>
      <label class="field">Username or email
        <input type="text" name="login" required autofocus>
      </label>
      <label class="field">Password
        <input type="password" name="password" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Sign In</button>
    </form>
    <?= oauth_buttons_html() ?>
    <p class="auth-alt" style="margin-bottom:0;margin-top:18px"><a href="<?= e(url('forgot-password.php')) ?>">Forgot your password?</a></p>
  </div>
  <p class="auth-alt">New here? <a href="<?= e(url('register.php')) ?>"><strong>Create a free account</strong></a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
