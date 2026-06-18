<?php
/** Admin: member management — create, ban/unban, promote/demote, set password, delete. */
require_once __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ---- create a member manually
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $display  = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $isAdmin  = !empty($_POST['is_admin']) ? 1 : 0;

        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3–30 characters (letters, numbers, underscores).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($display === '' || mb_strlen($display) > 60) {
            $errors[] = 'Display name is required (max 60 characters).';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!$errors) {
            $st = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
            $st->execute([$username, $email]);
            foreach ($st as $row) {
                if (strcasecmp($row['username'], $username) === 0) $errors[] = 'That username is already taken.';
                if (strcasecmp($row['email'], $email) === 0)       $errors[] = 'That email is already registered.';
            }
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            db()->prepare(
                'INSERT INTO users (username, email, password_hash, display_name, is_admin, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $display, $isAdmin]);
            flash('success', 'Member @' . $username . ' created' . ($isAdmin ? ' as an administrator' : '') . '. Share the password with them securely.');
        }
        redirect('admin/users.php');
    }

    $uid = (int)($_POST['user_id'] ?? 0);
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$uid]);
    $target = $st->fetch();

    if (!$target) {
        flash('error', 'User not found.');
    } elseif ($uid === (int)$admin['id']) {
        flash('error', 'You cannot moderate your own account.');
    } else {
        switch ($action) {
            case 'ban':
                db()->prepare('UPDATE users SET is_banned = 1 WHERE id = ?')->execute([$uid]);
                flash('success', '@' . $target['username'] . ' has been suspended.');
                break;
            case 'unban':
                db()->prepare('UPDATE users SET is_banned = 0 WHERE id = ?')->execute([$uid]);
                flash('success', '@' . $target['username'] . ' has been re-activated.');
                break;
            case 'promote':
                db()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$uid]);
                flash('success', '@' . $target['username'] . ' is now an administrator.');
                break;
            case 'demote':
                db()->prepare('UPDATE users SET is_admin = 0 WHERE id = ?')->execute([$uid]);
                flash('success', '@' . $target['username'] . ' is no longer an administrator.');
                break;
            case 'setpass':
                $new = $_POST['new_pass'] ?? '';
                if (strlen($new) < 8) {
                    flash('error', 'The new password must be at least 8 characters.');
                } else {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
                    ensure_password_resets_table();
                    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$uid]);
                    flash('success', 'Password for @' . $target['username'] . ' has been reset. Share it with them securely.');
                }
                break;
            case 'delete':
                $pdo = db();
                foreach ($pdo->query("SELECT image FROM recipes WHERE user_id = $uid") as $r) delete_upload($r['image']);
                foreach ($pdo->query("SELECT media FROM recipe_steps WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = $uid)") as $r) delete_upload($r['media']);
                foreach ($pdo->query("SELECT image FROM wall_posts WHERE user_id = $uid") as $r) delete_upload($r['image']);
                delete_upload($target['avatar']);
                $pdo->prepare('DELETE FROM recipes WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM wall_posts WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM comments WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM reactions WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM forum_topics WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('DELETE FROM buddies WHERE requester_id = ? OR addressee_id = ?')->execute([$uid, $uid]);
                $pdo->prepare('DELETE FROM notifications WHERE user_id = ? OR actor_id = ?')->execute([$uid, $uid]);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                flash('success', '@' . $target['username'] . ' and all their content has been deleted.');
                break;
        }
    }
    redirect('admin/users.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '1';
if ($q !== '') {
    $where = '(username LIKE ? OR display_name LIKE ? OR email LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$st = db()->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM recipes r WHERE r.user_id = u.id AND r.status='published') recipe_count
       FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT 200"
);
$st->execute($params);
$users = $st->fetchAll();

$pageTitle = 'Admin · Users';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>Users</h1>
  <?php admin_nav('users'); ?>

  <div class="panel" style="margin-bottom:24px">
    <h3 style="margin-bottom:6px">Add a member manually</h3>
    <p class="muted small" style="margin-top:0">Creates an account instantly — no email verification. Hand the password to the member securely.</p>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <label class="field">Username <small>3–30 letters, numbers, underscores</small>
          <input type="text" name="username" pattern="[A-Za-z0-9_]{3,30}" required>
        </label>
        <label class="field">Display name
          <input type="text" name="display_name" maxlength="60" required>
        </label>
      </div>
      <div class="form-row">
        <label class="field">Email
          <input type="email" name="email" required>
        </label>
        <label class="field">Password <small>at least 8 characters</small>
          <input type="text" name="password" minlength="8" required autocomplete="off">
        </label>
      </div>
      <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600">
          <input type="checkbox" name="is_admin" value="1" style="width:auto;margin:0"> Grant administrator role
        </label>
        <button class="btn btn-primary" type="submit">Create member</button>
      </div>
    </form>
  </div>

  <form method="get" class="filter-bar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search username, name or email…">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <div class="table-wrap">
  <table class="data">
    <tr><th>Member</th><th>Email</th><th>Recipes</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($users as $u): $isSelf = (int)$u['id'] === (int)$admin['id']; ?>
      <tr>
        <td><?= avatar_html($u, 30) ?> <a href="<?= e(url('profile.php?u=' . urlencode($u['username']))) ?>"><strong><?= e($u['display_name'] ?: $u['username']) ?></strong></a>
            <span class="muted small">@<?= e($u['username']) ?></span>
            <?php if ($u['is_admin']): ?><span class="pill pill-orange">admin</span><?php endif; ?></td>
        <td class="small"><?= e($u['email']) ?></td>
        <td><?= (int)$u['recipe_count'] ?></td>
        <td class="small"><?= date('j M Y', strtotime($u['created_at'])) ?></td>
        <td><?= $u['is_banned'] ? '<span class="pill pill-red">suspended</span>' : '<span class="pill pill-green">active</span>' ?></td>
        <td>
          <?php if (!$isSelf): ?>
          <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <?php if ($u['is_banned']): ?>
              <button class="btn btn-sm btn-ghost" name="action" value="unban">Unban</button>
            <?php else: ?>
              <button class="btn btn-sm btn-ghost" name="action" value="ban">Ban</button>
            <?php endif; ?>
            <?php if ($u['is_admin']): ?>
              <button class="btn btn-sm btn-ghost" name="action" value="demote">Remove admin</button>
            <?php else: ?>
              <button class="btn btn-sm btn-ghost" name="action" value="promote">Make admin</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-danger" name="action" value="delete"
                    onclick="return confirm('Delete @<?= e($u['username']) ?> and ALL their content? This cannot be undone.')">Delete</button>
          </form>
          <form method="post" style="display:flex;gap:6px;margin-top:6px">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="text" name="new_pass" minlength="8" required autocomplete="off"
                   placeholder="New password" style="width:150px;margin-top:0;padding:6px 10px;font-size:13px">
            <button class="btn btn-sm btn-ghost" name="action" value="setpass"
                    onclick="return confirm('Reset the password for @<?= e($u['username']) ?>?')">Reset password</button>
          </form>
          <?php else: ?><span class="muted small">that's you</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
