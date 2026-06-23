<?php
/** Resend email verification link. */
require_once __DIR__ . '/includes/bootstrap.php';
ensure_phone_columns();

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $st = db()->prepare("SELECT id, email, display_name, email_verified_at FROM users WHERE email = ?");
        $st->execute([$email]);
        $user = $st->fetch();
        if ($user) {
            if ($user['email_verified_at']) {
                $errors[] = 'This email is already verified. You can sign in directly.';
            } else {
                $token = generate_verify_token((int)$user['id']);
                send_verification_email($user['email'], $user['display_name'], $token);
                $sent = true;
            }
        } else {
            $sent = true; // don't reveal if email exists
        }
    }
}

$pageTitle = 'Resend Verification';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:480px">
  <div class="panel" style="text-align:center">
    <div style="font-size:40px;margin-bottom:12px">📧</div>
    <h2>Resend verification email</h2>
    <p class="muted small">Enter your email and we'll send you a fresh verification link.</p>

    <?php if ($sent): ?>
      <div class="flash flash-success" style="margin-top:16px">
        If an unverified account exists for that email, a new link has been sent. Check your inbox (and spam folder).
      </div>
      <a href="<?= e(url('login.php')) ?>" class="btn btn-primary" style="margin-top:16px">Back to sign in</a>
    <?php else: ?>
      <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" style="margin-top:18px;text-align:left">
        <?= csrf_field() ?>
        <label class="field">Your email address
          <input type="email" name="email" required placeholder="you@example.com" class="form-input"
                 style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:10px;font-size:15px;box-sizing:border-box">
        </label>
        <button class="btn btn-primary btn-block" type="submit" style="margin-top:14px">Send verification link</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
