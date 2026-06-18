<?php
/** Start a new forum topic — pick a predefined board or create a new one. */
require_once __DIR__ . '/includes/bootstrap.php';

$me = require_login();
$errors = [];
$cats = db()->query('SELECT * FROM forum_categories ORDER BY name')->fetchAll();
$preselect = (int)($_GET['cat'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title   = trim($_POST['title'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $catId   = (int)($_POST['category_id'] ?? 0);
    $newCat  = trim($_POST['new_category'] ?? '');

    if ($title === '' || mb_strlen($title) > 180) {
        $errors[] = 'Topic title is required (max 180 characters).';
    }
    if ($body === '' || mb_strlen($body) > 8000) {
        $errors[] = 'Write your opening post (max 8000 characters).';
    }
    if ($newCat !== '') {
        $catId = (int)find_or_create('forum_categories', $newCat, (int)$me['id']);
    }
    if ($catId < 1) {
        $errors[] = 'Pick a board or enter a new one.';
    } else {
        $st = db()->prepare('SELECT id FROM forum_categories WHERE id = ?');
        $st->execute([$catId]);
        if (!$st->fetch()) {
            $errors[] = 'That board no longer exists.';
        }
    }

    if (!$errors) {
        $st = db()->prepare(
            "INSERT INTO forum_topics (category_id, user_id, title, body, status, created_at)
             VALUES (?, ?, ?, ?, 'visible', NOW())"
        );
        $st->execute([$catId, $me['id'], $title, $body]);
        $topicId = (int)db()->lastInsertId();
        flash('success', 'Topic posted — let the discussion begin! 🍴');
        redirect('forum-topic.php?id=' . $topicId);
    }
}

$pageTitle = 'Start a New Topic';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:760px">
  <h1>📝 Start a New Topic</h1>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <div class="panel">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <label class="field">Topic title <span class="req">*</span>
        <input type="text" name="title" required maxlength="180" value="<?= e($_POST['title'] ?? '') ?>"
               placeholder="e.g. Best vegan substitute for paneer in tikka?">
      </label>
      <div class="form-row">
        <label class="field">Board <span class="req">*</span>
          <select name="category_id">
            <option value="">— choose a board —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)($_POST['category_id'] ?? $preselect) === (int)$c['id']) ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">…or create a new board <small>It becomes available to everyone</small>
          <span class="ta-wrap"><input type="text" class="typeahead" data-master="forum_categories" name="new_category"
                 maxlength="100" placeholder="e.g. Fermentation Lab" value="<?= e($_POST['new_category'] ?? '') ?>" autocomplete="off"></span>
        </label>
      </div>
      <label class="field">Your opening post <span class="req">*</span>
        <textarea name="body" required maxlength="8000" style="min-height:160px"
                  placeholder="Set the table for the discussion…"><?= e($_POST['body'] ?? '') ?></textarea>
      </label>
      <div><button class="btn btn-accent" type="submit">🚀 Post topic</button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
