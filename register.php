<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';
ensure_email_notify_columns();
ensure_phone_columns();

if (is_logged_in()) {
    redirect('index.php');
}

$errors       = [];
$dupAccounts  = []; // holds duplicate account info for the "show details" reveal
$old = ['username' => '', 'email' => '', 'display_name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['username']     = trim($_POST['username']     ?? '');
    $old['email']        = trim($_POST['email']        ?? '');
    $old['display_name'] = trim($_POST['display_name'] ?? '');
    $old['phone']        = trim($_POST['phone_full']   ?? ''); // intl-tel-input full number
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // ── Validation ────────────────────────────────────────────────────────
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $old['username'])) {
        $errors[] = 'Username must be 3–30 characters: letters, numbers and underscores only.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['display_name'] === '' || mb_strlen($old['display_name']) > 60) {
        $errors[] = 'Please enter your display name (max 60 characters).';
    }
    // Phone: optional — only validate if provided
    if ($old['phone']) {
        $phoneDigits = preg_replace('/\D/', '', $old['phone']);
        if (strlen($phoneDigits) < 7) {
            $errors[] = 'The mobile number you entered doesn\'t look valid. Please check it or leave it blank.';
        }
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $password2) {
        $errors[] = 'The two passwords do not match.';
    }

    // ── Duplicate detection (email, username, phone) ───────────────────
    if (!$errors) {
        // Username / email duplicates
        $st = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $st->execute([$old['username'], $old['email']]);
        foreach ($st as $row) {
            if (strcasecmp($row['username'], $old['username']) === 0) {
                $errors[] = 'That username is already taken.';
            }
            if (strcasecmp($row['email'], $old['email']) === 0) {
                $dupAccounts['email'] = $row['username'];
            }
        }
        // Phone duplicate
        if ($old['phone']) {
            $stP = db()->prepare("SELECT username FROM users WHERE phone = ?");
            $stP->execute([$old['phone']]);
            if ($dupUser = $stP->fetchColumn()) {
                $dupAccounts['phone'] = $dupUser;
            }
        }
        if ($dupAccounts) {
            $errors[] = '__dup__'; // sentinel — handled separately in HTML
        }
    }

    // ── Create account ────────────────────────────────────────────────────
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
                'INSERT INTO users (username, email, password_hash, display_name, avatar, phone,
                                    email_notify, email_token, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $st->execute([
                $old['username'], $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $old['display_name'], $avatar,
                $old['phone'] ?: null,
                $emailNotify, $emailToken,
            ]);
            $newId = (int)db()->lastInsertId();
            // Send verification email — do NOT log in yet
            $verifyToken = generate_verify_token($newId);
            send_verification_email($old['email'], $old['display_name'], $verifyToken);
            flash('info', '🌿 Account created! Verification email sent to ' . $old['email'] . '. Check your inbox and spam folder, then click the link to activate your account.');
            redirect('login.php');
        }
    }
}

// Remove sentinel from errors array for display
$displayErrors = array_filter($errors, fn($e) => $e !== '__dup__');

$pageTitle = 'Join Free';
include __DIR__ . '/includes/header.php';
?>

<!-- intl-tel-input styles -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23/build/css/intlTelInput.css">
<style>
.iti { width: 100%; }
.iti input { width: 100%; }

/* Duplicate account alert */
.dup-alert {
  background: #fff8e1;
  border: 1.5px solid #f0c040;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 14px;
}
.dup-alert strong { color: #7a5c00; }
.dup-alert .dup-detail {
  margin-top: 10px;
  padding: 10px 14px;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #f0c040;
  font-size: 13px;
  display: none;
}
.dup-alert .dup-detail.show { display: block; }
.dup-toggle {
  background: none;
  border: none;
  color: #b07a00;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  margin-top: 6px;
}
</style>

<div class="container auth-wrap">
  <div class="panel">
    <h2>Create your account</h2>
    <p class="muted small">Join the <?= e(SITE_NAME) ?> community — post recipes, share food stories and make buddies.</p>

    <?php foreach ($displayErrors as $err): ?>
      <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($dupAccounts): ?>
      <div class="dup-alert">
        <strong>⚠️ An account already exists</strong>
        <?php if (isset($dupAccounts['email']) && isset($dupAccounts['phone'])): ?>
          <p style="margin:4px 0 0;color:#7a5c00;font-size:13px">Both the email address and mobile number you entered are already registered.</p>
        <?php elseif (isset($dupAccounts['email'])): ?>
          <p style="margin:4px 0 0;color:#7a5c00;font-size:13px">The email address you entered is already registered.</p>
        <?php else: ?>
          <p style="margin:4px 0 0;color:#7a5c00;font-size:13px">The mobile number you entered is already registered.</p>
        <?php endif; ?>

        <button type="button" class="dup-toggle" onclick="document.getElementById('dup-detail').classList.toggle('show');this.textContent=this.textContent==='Show account details ▼'?'Hide details ▲':'Show account details ▼'">
          Show account details ▼
        </button>

        <div class="dup-detail" id="dup-detail">
          <?php if (isset($dupAccounts['email'])): ?>
            <p><strong>Email account:</strong> @<?= e($dupAccounts['email']) ?></p>
          <?php endif; ?>
          <?php if (isset($dupAccounts['phone'])): ?>
            <p><strong>Mobile account:</strong> @<?= e($dupAccounts['phone']) ?></p>
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            <a href="<?= e(url('login.php')) ?>" class="btn btn-sm btn-primary">Sign in</a>
            <a href="<?= e(url('forgot-password.php')) ?>" class="btn btn-sm btn-ghost">Reset password</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="phone_full" id="phone_full">

      <label class="field">Username <span class="req">*</span>
        <input type="text" name="username" required maxlength="30"
               value="<?= e($old['username']) ?>" placeholder="e.g. spice_wizard">
      </label>

      <label class="field">Display name <span class="req">*</span>
        <input type="text" name="display_name" required maxlength="60"
               value="<?= e($old['display_name']) ?>" placeholder="How others will see you">
      </label>

      <label class="field">Email <span class="req">*</span>
        <input type="email" name="email" required
               value="<?= e($old['email']) ?>" placeholder="you@example.com">
      </label>

      <label class="field">Mobile number <small style="color:var(--ink-soft)">Optional</small>
        <small style="color:var(--ink-soft);font-size:12px">Select your country code then enter number</small>
        <input type="tel" id="phone_input" placeholder="9876543210"
               value="<?= e(preg_replace('/^\+\d+\s*/', '', $old['phone'])) ?>">
        <?php if ($old['phone']): ?><input type="hidden" name="phone_full" value="<?= e($old['phone']) ?>"><?php endif; ?>
      </label>

      <div class="form-row">
        <label class="field">Password <span class="req">*</span>
          <input type="password" name="password" required minlength="8" placeholder="Min 8 characters">
        </label>
        <label class="field">Repeat password <span class="req">*</span>
          <input type="password" name="password2" required minlength="8">
        </label>
      </div>

      <label class="field">Profile picture <small>Optional — max 5 MB (JPG/PNG/WEBP/GIF)</small>
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>

      <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:14px;color:var(--ink-soft)">
        <input type="checkbox" name="email_notify" value="1" style="margin-top:3px;flex-shrink:0"
               <?= !empty($_POST['email_notify']) ? 'checked' : '' ?>>
        <span>Email me when new recipes are posted or my buddies share something delicious 🍽️ <small>(you can unsubscribe anytime)</small></span>
      </label>

      <button class="btn btn-primary btn-block" type="submit" id="register-btn">🌿 Join <?= e(SITE_NAME) ?></button>
    </form>
    <?= oauth_buttons_html() ?>
  </div>
  <p class="auth-alt">Already a member? <a href="<?= e(url('login.php')) ?>"><strong>Sign in</strong></a></p>
</div>

<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23/build/js/intlTelInput.min.js"></script>
<script>
var phoneInput = document.getElementById('phone_input');
var iti = window.intlTelInput(phoneInput, {
  initialCountry: 'in',          // default to India
  separateDialCode: true,
  utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23/build/js/utils.js',
  preferredCountries: ['in', 'us', 'gb', 'au', 'ca', 'sg', 'ae'],
});

// Before form submit, store full international number if user entered one
document.querySelector('form').addEventListener('submit', function(e) {
  var rawNum = phoneInput.value.trim();
  if (rawNum) {
    // Phone entered — validate format
    if (!iti.isValidNumber()) {
      e.preventDefault();
      phoneInput.style.borderColor = '#e74c3c';
      phoneInput.focus();
      alert('The mobile number you entered doesn\'t look valid. Please check and try again, or leave it blank.');
      return false;
    }
    document.getElementById('phone_full').value = iti.getNumber();
    phoneInput.style.borderColor = '';
  } else {
    // Phone left blank — that's fine, clear the hidden field
    document.getElementById('phone_full').value = '';
  }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
