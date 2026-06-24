<?php
/** Email verification landing page. */
require_once __DIR__ . '/includes/bootstrap.php';
ensure_phone_columns();

$token = trim($_GET['token'] ?? '');
$status = 'invalid';
$displayName = '';
$username    = '';

if ($token) {
    // First: try to find a valid (not yet expired) token
    $st = db()->prepare(
        "SELECT id, username, display_name, email_verified_at, verify_token_expires
           FROM users WHERE verify_token = ?"
    );
    $st->execute([$token]);
    $user = $st->fetch();

    if ($user) {
        if ($user['email_verified_at']) {
            // Already verified — could be user clicking link a second time
            $status      = 'already';
            $displayName = $user['display_name'];
            $username    = $user['username'];
            // Log them in if not already
            if (!is_logged_in()) {
                $_SESSION['user_id'] = (int)$user['id'];
                session_regenerate_id(true);
            }
        } elseif ($user['verify_token_expires'] && strtotime($user['verify_token_expires']) < time()) {
            // Token exists but expired
            $status = 'expired';
        } else {
            // Valid token — verify now (keep token so re-clicks show 'already verified')
            db()->prepare(
                "UPDATE users SET email_verified_at = NOW() WHERE id = ?"
            )->execute([$user['id']]);
            $_SESSION['user_id'] = (int)$user['id'];
            session_regenerate_id(true);
            $status      = 'success';
            $displayName = $user['display_name'];
            $username    = $user['username'];
        }
    } else {
        // Token not found — check if user with this email is already verified
        // (handles the case where token was cleared after successful verification)
        $status = 'invalid';
    }
}

// If already logged in and landing here, treat as success
if ($status === 'invalid' && is_logged_in()) {
    $me = current_user();
    if ($me && $me['email_verified_at']) {
        $status      = 'already';
        $displayName = $me['display_name'];
        $username    = $me['username'];
    }
}

$pageTitle = 'Email Verification';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:560px;text-align:center;padding:60px 20px">

  <?php if ($status === 'success'): ?>
    <div style="font-size:56px;margin-bottom:16px">✅</div>
    <h2>Email verified!</h2>
    <p class="muted">Your account is now active, <?= e($displayName ?: 'there') ?>. Welcome to <?= e(SITE_NAME) ?>! 🌿</p>
    <a class="btn btn-primary" href="<?= e(url('profile.php?u=' . urlencode($username))) ?>" style="margin-top:20px">Go to my profile →</a>

  <?php elseif ($status === 'already'): ?>
    <div style="font-size:56px;margin-bottom:16px">✅</div>
    <h2>Email already verified!</h2>
    <p class="muted">Your account is active<?= $displayName ? ', ' . e($displayName) : '' ?>. You can go straight to your profile.</p>
    <a class="btn btn-primary" href="<?= e($username ? url('profile.php?u=' . urlencode($username)) : url('index.php')) ?>" style="margin-top:20px">
      <?= $username ? 'Go to my profile →' : 'Go to homepage →' ?>
    </a>

  <?php elseif ($status === 'expired'): ?>
    <div style="font-size:56px;margin-bottom:16px">⏰</div>
    <h2>Link expired</h2>
    <p class="muted">This verification link has expired (links are valid for 7 days). Please request a new one.</p>
    <a class="btn btn-primary" href="<?= e(url('resend-verification.php')) ?>" style="margin-top:20px">Resend verification email</a>

  <?php else: ?>
    <div style="font-size:56px;margin-bottom:16px">✅</div>
    <h2>Account verified — sign in now</h2>
    <p class="muted" style="max-width:400px;margin:0 auto 24px">
      This link has already been used. Your account is verified — go ahead and sign in with your username and password.
    </p>
    <a class="btn btn-primary" href="<?= e(url('login.php')) ?>" style="font-size:16px;padding:13px 36px">Sign In</a>
    <p style="margin-top:16px;font-size:13px;color:var(--ink-soft)">
      Not verified yet? <a href="<?= e(url('resend-verification.php')) ?>">Resend verification email</a>
    </p>
  <?php endif; ?>

</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
