<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';
ensure_phone_columns();

if (is_logged_in()) {
    redirect('index.php');
}

$error          = null;
$unverifiedUser = null; // set when login credentials are correct but email not verified

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Resend verification email action
    if (!empty($_POST['resend_verify'])) {
        $uid   = (int)($_POST['resend_uid'] ?? 0);
        $email = trim($_POST['resend_email'] ?? '');
        if ($uid && $email) {
            $st = db()->prepare("SELECT id, email, display_name FROM users WHERE id = ? AND email = ? AND email_verified_at IS NULL");
            $st->execute([$uid, $email]);
            $u = $st->fetch();
            if ($u) {
                $token = generate_verify_token($uid);
                send_verification_email($u['email'], $u['display_name'], $token);
                flash('success', 'Verification email resent! Please check your inbox.');
            }
        }
        redirect('login.php');
    }

    // Normal login
    $login    = trim($_POST['login']    ?? '');
    $password = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$login, $login]);
    $user = $st->fetch();

    if ($user && (int)$user['is_banned'] === 1) {
        $error = 'This account has been suspended. Please contact the site administrator.';
    } elseif ($user && password_verify($password, $user['password_hash'])) {
        // Check email verification
        if (empty($user['email_verified_at'])) {
            // Credentials correct but not verified — show resend prompt
            $unverifiedUser = $user;
        } else {
            // All good — log in
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
        }
    } else {
        $error = 'Wrong username/email or password. Please try again.';
    }
}

// Helper to mask email for display: user@example.com → u***@e***.com
function mask_email(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(2, strlen($local) - 1));
    $domainParts = explode('.', $domain);
    $maskedDomain = substr($domainParts[0], 0, 1) . str_repeat('*', max(2, strlen($domainParts[0]) - 1));
    return $maskedLocal . '@' . $maskedDomain . '.' . implode('.', array_slice($domainParts, 1));
}

$pageTitle = 'Sign In';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="panel">
    <h2>Welcome back 👋</h2>
    <p class="muted small">Sign in to post recipes, react, comment and catch up with your buddies.</p>

    <?php if ($unverifiedUser): ?>
      <!-- ── Unverified account prompt ─────────────────────────────── -->
      <div style="background:#fff8e1;border:1.5px solid #f0c040;border-radius:14px;padding:22px;margin-top:12px;text-align:center">
        <div style="font-size:40px;margin-bottom:10px">📧</div>
        <h3 style="margin:0 0 8px;color:#7a5c00">Please verify your email first</h3>
        <p style="color:#7a5c00;font-size:14px;margin:0 0 6px">
          Your account is not yet activated. We sent a verification email to:
        </p>
        <p style="font-weight:700;color:#5a4000;font-size:15px;margin:0 0 16px">
          <?= e(mask_email($unverifiedUser['email'])) ?>
        </p>
        <p style="color:#7a5c00;font-size:13px;margin:0 0 18px">
          Click the link in that email to activate your account.<br>
          <em>Check your spam/junk folder if you don't see it.</em>
        </p>

        <!-- Resend form -->
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="resend_verify" value="1">
          <input type="hidden" name="resend_uid"   value="<?= (int)$unverifiedUser['id'] ?>">
          <input type="hidden" name="resend_email" value="<?= e($unverifiedUser['email']) ?>">
          <button type="submit" class="btn btn-primary" style="width:100%">
            📨 Resend verification email
          </button>
        </form>

        <p style="margin-top:14px;margin-bottom:0;font-size:13px;color:#999">
          Wrong account? <a href="<?= e(url('login.php')) ?>" style="color:#b07a00">Try a different login</a>
        </p>
      </div>

    <?php else: ?>
      <!-- ── Normal login form ──────────────────────────────────────── -->
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form-grid" style="margin-top:18px">
        <?= csrf_field() ?>
        <label class="field">Username or email
          <input type="text" name="login" required autofocus value="<?= e($_POST['login'] ?? '') ?>">
        </label>
        <label class="field">Password
          <input type="password" name="password" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Sign In</button>
      </form>
      <?= oauth_buttons_html() ?>
      <p class="auth-alt" style="margin-bottom:0;margin-top:18px">
        <a href="<?= e(url('forgot-password.php')) ?>">Forgot your password?</a>
      </p>
    <?php endif; ?>
  </div>
  <p class="auth-alt">New here? <a href="<?= e(url('register.php')) ?>"><strong>Create a free account</strong></a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
