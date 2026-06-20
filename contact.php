<?php
/** Contact Us — stores the message and emails the site admin. */
require_once __DIR__ . '/includes/bootstrap.php';

function ensure_contact_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            name VARCHAR(80) NOT NULL,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$me   = current_user();
$sent = false;
$errors = [];
$old = [
    'name'    => $me ? ($me['display_name'] ?: $me['username']) : '',
    'email'   => $me['email'] ?? '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Honeypot: real visitors never fill this hidden field.
    if (trim($_POST['website'] ?? '') !== '') {
        $sent = true; // pretend success to bots
    } else {
        foreach (['name', 'email', 'subject', 'message'] as $f) {
            $old[$f] = trim($_POST[$f] ?? '');
        }
        if ($old['name'] === '' || mb_strlen($old['name']) > 80) {
            $errors[] = 'Please enter your name (max 80 characters).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($old['subject'] === '' || mb_strlen($old['subject']) > 150) {
            $errors[] = 'Please enter a subject (max 150 characters).';
        }
        if ($old['message'] === '' || mb_strlen($old['message']) > 5000) {
            $errors[] = 'Please write your message (max 5000 characters).';
        }

        // Light rate limit: 3 messages per session per 15 minutes.
        $now = time();
        $_SESSION['contact_times'] = array_filter($_SESSION['contact_times'] ?? [], fn($t) => $t > $now - 900);
        if (count($_SESSION['contact_times']) >= 3) {
            $errors[] = 'You have sent several messages already — please wait a few minutes.';
        }

        if (!$errors) {
            $_SESSION['contact_times'][] = $now;
            ensure_contact_table();
            db()->prepare(
                'INSERT INTO contact_messages (user_id, name, email, subject, message, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([$me['id'] ?? null, $old['name'], $old['email'], $old['subject'], $old['message']]);

            $adminEmail = db()->query('SELECT email FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1')->fetchColumn();
            if ($adminEmail) {
                send_mail(
                    $adminEmail,
                    '[' . SITE_NAME . '] Contact: ' . $old['subject'],
                    "New contact message on " . SITE_NAME . "\n\n"
                    . "From:    {$old['name']} <{$old['email']}>\n"
                    . "Subject: {$old['subject']}\n\n"
                    . $old['message'] . "\n\n—\nAlso saved in Admin Panel → Messages."
                );
            }
            $sent = true;
        }
    }
}

$pageTitle    = 'Contact Us';
$pageDesc     = "Get in touch with the Bosket's Alimentos team — questions, ideas, feedback or partnerships.";
$cmsIntro     = cms_get_page_body('contact-intro');
include __DIR__ . '/includes/header.php';
?>
<div class="container section section-narrow">
  <div style="text-align:center; margin-bottom:30px">
    <h1>Contact us</h1>
    <?php if ($cmsIntro): ?>
      <div style="max-width:520px;margin:8px auto 0;color:var(--ink-soft)"><?= $cmsIntro ?></div>
    <?php else: ?>
      <p class="muted" style="max-width:520px; margin:8px auto 0">A question, an idea, a partnership, or something on the site
      that doesn't taste right — write to us. We read everything.</p>
    <?php endif; ?>
  </div>

  <?php if ($sent): ?>
    <div class="panel" style="text-align:center; padding:44px 30px">
      <h3>Message received — thank you!</h3>
      <p class="muted" style="max-width:440px; margin:10px auto 22px">We'll get back to you at the email address you provided,
      usually within a couple of days.</p>
      <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Back to the homepage</a>
    </div>
  <?php else: ?>
    <div class="panel" style="padding:34px 38px">
      <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form-grid" style="margin-top:<?= $errors ? '16px' : '0' ?>">
        <?= csrf_field() ?>
        <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="form-row">
          <label class="field">Your name
            <input type="text" name="name" maxlength="80" required value="<?= e($old['name']) ?>">
          </label>
          <label class="field">Your email
            <input type="email" name="email" required value="<?= e($old['email']) ?>">
          </label>
        </div>
        <label class="field">Subject
          <input type="text" name="subject" maxlength="150" required value="<?= e($old['subject']) ?>"
                 placeholder="e.g. Suggestion for the recipe page">
        </label>
        <label class="field">Message
          <textarea name="message" maxlength="5000" required rows="7"
                    placeholder="Tell us what's on your mind…"><?= e($old['message']) ?></textarea>
        </label>
        <button class="btn btn-primary" type="submit" style="justify-self:start; padding:12px 34px">Send message</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
