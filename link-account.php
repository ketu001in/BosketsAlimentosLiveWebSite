<?php
/**
 * "An account with this email already exists — link it?" confirmation.
 *
 * Reached only mid-OAuth, when the social email matches an existing local
 * account. To link (and avoid account takeover) the visitor must prove they own
 * that account by entering its password.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';
ensure_oauth_table();

$pending = $_SESSION['oauth_pending'] ?? null;
if (!$pending) {
    redirect('login.php');
}

$st = db()->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$pending['user_id']]);
$user = $st->fetch();
if (!$user) {
    unset($_SESSION['oauth_pending']);
    redirect('login.php');
}

$providerLabel = ucfirst($pending['provider']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['cancel'])) {
        unset($_SESSION['oauth_pending']);
        flash('info', 'No account was linked. You can sign in with your password instead.');
        redirect('login.php');
    }

    $password = $_POST['password'] ?? '';
    if ((int)$user['is_banned'] === 1) {
        unset($_SESSION['oauth_pending']);
        flash('error', 'This account has been suspended.');
        redirect('login.php');
    } elseif (password_verify($password, $user['password_hash'])) {
        oauth_link((int)$user['id'], $pending['provider'], ['id' => $pending['id'], 'email' => $pending['email']]);
        unset($_SESSION['oauth_pending']);
        oauth_login_user((int)$user['id']);
    } else {
        $error = 'That password is incorrect. Please try again, or cancel.';
    }
}

$pageTitle = 'Link your account';
$noIndex = true;
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="panel">
    <h2>Link your <?= e($providerLabel) ?> sign-in</h2>
    <p class="muted small">
      You signed in with <?= e($providerLabel) ?> using <strong><?= e($pending['email']) ?></strong>,
      which already belongs to an account here
      (<strong><?= e($user['display_name'] ?: $user['username']) ?></strong>).
      Enter that account's password once to link them — then next time, one tap with
      <?= e($providerLabel) ?> signs you straight in.
    </p>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid" style="margin-top:16px">
      <?= csrf_field() ?>
      <label class="field">Password for <?= e($user['email']) ?>
        <input type="password" name="password" required autofocus>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Link &amp; sign in</button>
      <button class="btn btn-ghost btn-block" type="submit" name="cancel" value="1">Cancel — don't link</button>
    </form>
    <p class="auth-alt" style="margin-bottom:0"><a href="<?= e(url('forgot-password.php')) ?>">Forgot that password?</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
