<?php
/** Email verification landing page. */
require_once __DIR__ . '/includes/bootstrap.php';
ensure_phone_columns();

$token = trim($_GET['token'] ?? '');
$status = 'invalid';

if ($token) {
    $st = db()->prepare(
        "SELECT id, display_name, email_verified_at, verify_token_expires
           FROM users WHERE verify_token = ? AND verify_token_expires > NOW()"
    );
    $st->execute([$token]);
    $user = $st->fetch();

    if ($user) {
        if ($user['email_verified_at']) {
            $status = 'already';
        } else {
            db()->prepare(
                "UPDATE users SET email_verified_at = NOW(), verify_token = NULL, verify_token_expires = NULL WHERE id = ?"
            )->execute([$user['id']]);
            // Auto-login the user immediately after verification
            $_SESSION['user_id'] = (int)$user['id'];
            session_regenerate_id(true);
            $status      = 'success';
            $displayName = $user['display_name'];
            $username    = $user['username'];
        }
    } else {
        $status = 'expired';
    }
}

$pageTitle = 'Email Verified';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:560px;text-align:center;padding:60px 20px">
  <?php if ($status === 'success'): ?>
    <div style="font-size:56px;margin-bottom:16px">✅</div>
    <h2>Email verified!</h2>
    <p class="muted">Your account is now active, <?= e($displayName ?? 'there') ?>. Welcome to <?= e(SITE_NAME) ?>! 🌿</p>
    <a class="btn btn-primary" href="<?= e(url('profile.php?u=' . urlencode($username ?? ''))) ?>" style="margin-top:20px">Go to my profile →</a>

  <?php elseif ($status === 'already'): ?>
    <div style="font-size:56px;margin-bottom:16px">☑️</div>
    <h2>Already verified</h2>
    <p class="muted">Your email is already confirmed. You can sign in.</p>
    <a class="btn btn-primary" href="<?= e(url('login.php')) ?>" style="margin-top:20px">Sign in</a>

  <?php elseif ($status === 'expired'): ?>
    <div style="font-size:56px;margin-bottom:16px">⏰</div>
    <h2>Link expired</h2>
    <p class="muted">This verification link has expired (links are valid for 24 hours). Please request a new one.</p>
    <a class="btn btn-primary" href="<?= e(url('resend-verification.php')) ?>" style="margin-top:20px">Resend verification email</a>

  <?php else: ?>
    <div style="font-size:56px;margin-bottom:16px">❌</div>
    <h2>Invalid link</h2>
    <p class="muted">This verification link is not valid.</p>
    <a class="btn btn-outline" href="<?= e(url('index.php')) ?>" style="margin-top:20px">Back to homepage</a>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
