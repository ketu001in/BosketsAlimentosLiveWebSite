<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';
ensure_email_notify_columns();

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old = ['username' => '', 'email' => '', 'display_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['username']     = trim($_POST['username'] ?? '');
    $old['email']        = trim($_POST['email'] ?? '');
    $old['display_name'] = trim($_POST['display_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $old['username'])) {
        $errors[] = 'Username must be 3–30 characters: letters, numbers and underscores only.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['display_name'] === '' || mb_strlen($old['display_name']) > 60) {
        $errors[] = 'Please enter your display name (max 60 characters).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $password2) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $st->execute([$old['username'], $old['email']]);
        foreach ($st as $row) {
            if (strcasecmp($row['username'], $old['username']) === 0) {
                $errors[] = 'That username is already taken.';
            }
            if (strcasecmp($row['email'], $old['email']) === 0) {
                $errors[] = 'An account with that email already exists. Try signing in.';
            }
        }
    }

    if (!$errors) {
        $avatar      = null;
        $emailNotify = !empty($_POST['email_notify']) ? 1 : 0;
        $emailToken  = bin2hex(random_bytes(32));
        try {
            $up = handle_upload('avatar', 'image', 'avatars');
            $avatar = $up['file'] ?? null;
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
        if (!$errors) {
            $st = db()->prepare(
                'INSERT INTO users (username, email, password_hash, display_name, avatar, email_notify, email_token, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $st->execute([
                $old['username'], $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $old['display_name'], $avatar,
                $emailNotify, $emailToken,
            ]);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            session_regenerate_id(true);
            flash('success', 'Welcome to ' . SITE_NAME . ', ' . $old['display_name'] . '! 🌿 Your kitchen awaits.');
            redirect('profile.php?u=' . urlencode($old['username']));
        }
    }
}

$pageTitle = 'Join Free';
include __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="panel">
    <h2>Create your account</h2>
    <p class="muted small">Join the <?= e(SITE_NAME) ?> community — post recipes, share food stories and make buddies.</p>
    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:18px">
      <?= csrf_field() ?>
      <label class="field">Username <span class="req">*</span>
        <input type="text" name="username" required maxlength="30" value="<?= e($old['username']) ?>" placeholder="e.g. spice_wizard">
      </label>
      <label class="field">Display name <span class="req">*</span>
        <input type="text" name="display_name" required maxlength="60" value="<?= e($old['display_name']) ?>" placeholder="How others will see you">
      </label>
      <label class="field">Email <span class="req">*</span>
        <input type="email" name="email" required value="<?= e($old['email']) ?>" placeholder="you@example.com">
      </label>
      <div class="form-row">
        <label class="field">Password <span class="req">*</span>
          <input type="password" name="password" required minlength="8" placeholder="Min 8 characters">
        </label>
        <label class="field">Repeat password <span class="req">*</span>
          <input type="password" name="password2" required minlength="8">
        </label>
      </div>
      <label class="field">Profile picture <small>Optional — your avatar or a real photo, max 5 MB (JPG/PNG/WEBP/GIF)</small>
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>
      <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:14px;color:var(--ink-soft)">
        <input type="checkbox" name="email_notify" value="1" style="margin-top:3px;flex-shrink:0" <?= !empty($_POST['email_notify']) ? 'checked' : '' ?>>
        <span>Email me when new recipes are posted or my buddies share something delicious 🍽️ <small>(you can unsubscribe anytime)</small></span>
      </label>
      <button class="btn btn-primary btn-block" type="submit">🌿 Join <?= e(SITE_NAME) ?></button>
    </form>
    <?= oauth_buttons_html() ?>
  </div>
  <p class="auth-alt">Already a member? <a href="<?= e(url('login.php')) ?>"><strong>Sign in</strong></a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
