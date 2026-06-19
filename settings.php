<?php
/** Account management: profile details, avatar, password, account deletion. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';

$me = require_login();
ensure_email_notify_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $section = $_POST['section'] ?? '';

    if ($section === 'profile') {
        $displayName = trim($_POST['display_name'] ?? '');
        $bio         = trim($_POST['bio'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        if ($displayName === '' || mb_strlen($displayName) > 60) {
            flash('error', 'Display name is required (max 60 characters).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
        } else {
            $st = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $st->execute([$email, $me['id']]);
            if ($st->fetch()) {
                flash('error', 'That email is already used by another account.');
            } else {
                db()->prepare('UPDATE users SET display_name = ?, bio = ?, email = ? WHERE id = ?')
                    ->execute([$displayName, mb_substr($bio, 0, 1000), $email, $me['id']]);
                flash('success', 'Profile updated.');
            }
        }
        redirect('settings.php');
    }

    if ($section === 'avatar') {
        try {
            $up = handle_upload('avatar', 'image', 'avatars');
            if ($up) {
                delete_upload($me['avatar']);
                db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$up['file'], $me['id']]);
                flash('success', 'New profile picture saved. Looking good! 😎');
            } elseif (isset($_POST['remove_avatar'])) {
                delete_upload($me['avatar']);
                db()->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$me['id']]);
                flash('success', 'Profile picture removed.');
            } else {
                flash('info', 'Choose an image first.');
            }
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect('settings.php');
    }

    if ($section === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $new2    = $_POST['new_password2'] ?? '';
        if (!password_verify($current, $me['password_hash'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $new2) {
            flash('error', 'The new passwords do not match.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            flash('success', 'Password changed.');
        }
        redirect('settings.php');
    }

    if ($section === 'unlink') {
        ensure_oauth_table();
        $provider = $_POST['provider'] ?? '';
        if (in_array($provider, ['google', 'facebook'], true)) {
            db()->prepare('DELETE FROM oauth_accounts WHERE user_id = ? AND provider = ?')
                ->execute([$me['id'], $provider]);
            flash('success', ucfirst($provider) . ' sign-in disconnected.');
        }
        redirect('settings.php');
    }

    if ($section === 'notifications') {
        $notify = !empty($_POST['email_notify']) ? 1 : 0;
        ensure_email_token((int)$me['id']);
        db()->prepare("UPDATE users SET email_notify = ? WHERE id = ?")->execute([$notify, $me['id']]);
        flash('success', $notify ? 'Email notifications enabled.' : 'Email notifications turned off.');
        redirect('settings.php');
    }

    if ($section === 'delete') {
        if (!password_verify($_POST['confirm_password'] ?? '', $me['password_hash'])) {
            flash('error', 'Password incorrect — account NOT deleted.');
            redirect('settings.php');
        }
        $uid = (int)$me['id'];
        $pdo = db();
        // remove user content + files
        foreach ($pdo->query("SELECT image FROM recipes WHERE user_id = $uid") as $r) delete_upload($r['image']);
        foreach ($pdo->query("SELECT media FROM recipe_steps WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = $uid)") as $r) delete_upload($r['media']);
        foreach ($pdo->query("SELECT image FROM wall_posts WHERE user_id = $uid") as $r) delete_upload($r['image']);
        delete_upload($me['avatar']);
        $pdo->prepare('DELETE FROM recipes WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM wall_posts WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM comments WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM reactions WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM forum_topics WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM buddies WHERE requester_id = ? OR addressee_id = ?')->execute([$uid, $uid]);
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? OR actor_id = ?')->execute([$uid, $uid]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        session_destroy();
        session_start();
        flash('info', 'Your account and content have been deleted. Goodbye, and thank you for cooking with us. 💚');
        redirect('index.php');
    }
}

$pageTitle = 'Account Settings';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:760px">
  <h1>Account Settings</h1>

  <div class="panel">
    <h3>👤 Profile details</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="profile">
      <div class="form-row">
        <label class="field">Display name
          <input type="text" name="display_name" required maxlength="60" value="<?= e($me['display_name']) ?>">
        </label>
        <label class="field">Email
          <input type="email" name="email" required value="<?= e($me['email']) ?>">
        </label>
      </div>
      <label class="field">About you <small>Shown on your profile — your food philosophy, favourite cuisines…</small>
        <textarea name="bio" maxlength="1000"><?= e($me['bio'] ?? '') ?></textarea>
      </label>
      <div><button class="btn btn-primary" type="submit">Save profile</button></div>
    </form>
  </div>

  <div class="panel">
    <h3>🖼️ Profile picture</h3>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="avatar">
      <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <?php if ($me['avatar']): ?>
          <img id="avatar-preview" class="avatar" src="<?= e(url($me['avatar'])) ?>" width="84" height="84" alt="">
        <?php else: ?>
          <?= avatar_html($me, 84) ?>
        <?php endif; ?>
        <div style="flex:1;min-width:220px">
          <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
          <p class="form-help">Avatar or a real photo — JPG, PNG, WEBP or GIF, max 5 MB.</p>
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" type="submit">Upload new picture</button>
        <?php if ($me['avatar']): ?>
          <button class="btn btn-ghost" type="submit" name="remove_avatar" value="1">Remove picture</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="panel">
    <h3>🔒 Change password</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="password">
      <label class="field">Current password
        <input type="password" name="current_password" required>
      </label>
      <div class="form-row">
        <label class="field">New password
          <input type="password" name="new_password" required minlength="8">
        </label>
        <label class="field">Repeat new password
          <input type="password" name="new_password2" required minlength="8">
        </label>
      </div>
      <div><button class="btn btn-primary" type="submit">Change password</button></div>
    </form>
  </div>

  <?php if (oauth_any_enabled()):
    ensure_oauth_table();
    $st = db()->prepare('SELECT provider FROM oauth_accounts WHERE user_id = ?');
    $st->execute([$me['id']]);
    $linked = $st->fetchAll(PDO::FETCH_COLUMN);
  ?>
  <div class="panel">
    <h3>🔗 Connected accounts</h3>
    <p class="muted small">Link Google or Facebook to sign in with one tap. You can still use your password too.</p>
    <?php foreach (['google' => 'Google', 'facebook' => 'Facebook'] as $prov => $label): ?>
      <?php if (!oauth_enabled($prov)) continue; ?>
      <div class="buddy-mini" style="justify-content:space-between">
        <strong style="flex:1"><?= e($label) ?></strong>
        <?php if (in_array($prov, $linked, true)): ?>
          <span class="pill pill-green" style="margin-right:8px">✓ Connected</span>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="unlink">
            <input type="hidden" name="provider" value="<?= e($prov) ?>">
            <button class="btn btn-sm btn-ghost" type="submit">Disconnect</button>
          </form>
        <?php else: ?>
          <a class="btn btn-sm btn-outline" href="<?= e(url('oauth.php?provider=' . $prov)) ?>">Connect</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h3>🔔 Email notifications</h3>
    <p class="muted small">Receive an email when new recipes are posted or your buddies share something.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="notifications">
      <label style="display:flex;align-items:center;gap:12px;cursor:pointer;font-size:15px">
        <input type="checkbox" name="email_notify" value="1" <?= ($me['email_notify'] ?? 0) ? 'checked' : '' ?>>
        <span>Email me about new recipes and buddy activity</span>
      </label>
      <div style="margin-top:14px"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
    <?php if ($me['email_notify'] ?? 0): ?>
      <p class="muted small" style="margin-top:10px">To stop receiving emails, uncheck the box above, or use the unsubscribe link in any notification email.</p>
    <?php endif; ?>
  </div>

  <div class="panel" style="border-color:#f3c1ba">
    <h3 style="color:var(--danger)">⚠️ Delete account</h3>
    <p class="muted small">This permanently removes your account, recipes, wall posts, comments and buddy connections. It cannot be undone.</p>
    <form method="post" class="form-grid" onsubmit="return confirm('Really delete your account and ALL your content? This cannot be undone.');">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="delete">
      <label class="field">Type your password to confirm
        <input type="password" name="confirm_password" required>
      </label>
      <div><button class="btn btn-danger" type="submit">Delete my account forever</button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
