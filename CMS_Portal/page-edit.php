<?php
/** CMS: create / edit / delete a page. */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_superuser();

$id = (int)($_GET['id'] ?? 0);
$page = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM cms_pages WHERE id = ?');
    $st->execute([$id]);
    $page = $st->fetch() ?: null;
    if (!$page) {
        flash('error', 'That page was not found.');
        cms_redirect('pages.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // ---- delete
    if (!empty($_POST['delete']) && $page) {
        db()->prepare('UPDATE cms_menu_items SET page_id = NULL, is_active = 0 WHERE page_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM cms_pages WHERE id = ?')->execute([$id]);
        flash('success', 'Page deleted. Any menu links to it were unlinked.');
        cms_redirect('pages.php');
    }

    // ---- save
    $title      = trim($_POST['title'] ?? '');
    $slugInput  = trim($_POST['slug'] ?? '');
    $body       = cms_sanitize_html($_POST['body'] ?? '');
    $meta       = trim($_POST['meta_description'] ?? '');
    $visibility = ($_POST['visibility'] ?? 'public') === 'members' ? 'members' : 'public';
    $status     = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    if ($title === '' || mb_strlen($title) > 160) {
        $errors[] = 'Title is required (max 160 characters).';
    }
    $slugSeed = $slugInput !== '' ? $slugInput : $title;

    if (!$errors) {
        $slug = cms_unique_slug($slugSeed, $id);
        $meta = mb_substr($meta, 0, 200);
        if ($page) {
            db()->prepare(
                'UPDATE cms_pages SET title=?, slug=?, body=?, meta_description=?, visibility=?, status=?, updated_at=NOW() WHERE id=?'
            )->execute([$title, $slug, $body, $meta, $visibility, $status, $id]);
            flash('success', 'Page saved.');
        } else {
            db()->prepare(
                'INSERT INTO cms_pages (title, slug, body, meta_description, visibility, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([$title, $slug, $body, $meta, $visibility, $status, (int)$admin['id']]);
            $id = (int)db()->lastInsertId();
            flash('success', 'Page created.');
        }
        cms_redirect(!empty($_POST['save_continue']) ? 'page-edit.php?id=' . $id : 'pages.php');
    }
}

// values for the form (POST wins, then existing, then blank)
$v = fn(string $k, string $fb = '') => e($_POST[$k] ?? ($page[$k] ?? $fb));
$bodyHtml   = $_POST['body'] ?? ($page['body'] ?? '');
$visVal     = $_POST['visibility'] ?? ($page['visibility'] ?? 'public');
$statusVal  = $_POST['status'] ?? ($page['status'] ?? 'draft');

$cmsPageTitle = $page ? 'Edit page' : 'New page';
$cmsActive = 'pages';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-page-head">
  <div>
    <h1><?= $page ? 'Edit page' : 'New page' ?></h1>
    <?php if ($page): ?>
      <p class="cms-sub">Public URL: <code><?= e(cms_site_url('page.php?slug=' . urlencode($page['slug']))) ?></code></p>
    <?php endif; ?>
  </div>
  <a class="btn btn-ghost btn-sm" href="<?= e(cms_url('pages.php')) ?>">&larr; All pages</a>
</div>

<?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="cms-form-2col">
    <div class="panel">
      <label class="field">Page title
        <input type="text" id="cms-title" name="title" maxlength="160" required value="<?= $v('title') ?>" placeholder="e.g. Our Press Coverage">
      </label>
      <label class="field" style="margin-top:14px">URL slug
        <span class="slug-row">
          <span class="pfx">…/page.php?slug=</span>
          <input type="text" id="cms-slug" name="slug" maxlength="180" value="<?= $v('slug') ?>" placeholder="auto from title" style="margin-top:0">
        </span>
        <span class="cms-help">Leave blank to auto-generate. If it clashes, a number is added.</span>
      </label>

      <label class="field" style="margin-top:16px">Content</label>
      <div class="cms-editor" data-csrf="<?= e(csrf_token()) ?>" data-upload="<?= e(cms_url('page-image.php')) ?>">
        <div class="cms-toolbar">
          <button type="button" data-cmd="bold" title="Bold"><strong>B</strong></button>
          <button type="button" data-cmd="italic" title="Italic"><em>I</em></button>
          <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
          <span class="sep"></span>
          <button type="button" data-cmd="formatBlock" data-val="h2" title="Heading">H2</button>
          <button type="button" data-cmd="formatBlock" data-val="h3" title="Sub-heading">H3</button>
          <button type="button" data-cmd="formatBlock" data-val="p" title="Paragraph">¶</button>
          <span class="sep"></span>
          <button type="button" data-cmd="insertUnorderedList" title="Bulleted list">•</button>
          <button type="button" data-cmd="insertOrderedList" title="Numbered list">1.</button>
          <button type="button" data-cmd="formatBlock" data-val="blockquote" title="Quote">&ldquo;</button>
          <span class="sep"></span>
          <button type="button" data-cmd="createLink" title="Insert link">🔗</button>
          <button type="button" data-img title="Insert image">🖼</button>
          <input type="file" class="cms-img-input" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
          <span class="sep"></span>
          <button type="button" data-cmd="removeFormat" title="Clear formatting">✕</button>
        </div>
        <div class="cms-area" contenteditable="true" spellcheck="true"><?= $bodyHtml ?: '<p></p>' ?></div>
      </div>
      <textarea id="cms-body" name="body" hidden><?= e($bodyHtml) ?></textarea>
    </div>

    <div class="panel">
      <h3 style="margin-top:0">Publish</h3>
      <label class="field">Status
        <select name="status">
          <option value="draft" <?= $statusVal === 'draft' ? 'selected' : '' ?>>Draft (hidden)</option>
          <option value="published" <?= $statusVal === 'published' ? 'selected' : '' ?>>Published (live)</option>
        </select>
      </label>
      <label class="field" style="margin-top:14px">Who can see it
        <select name="visibility">
          <option value="public" <?= $visVal === 'public' ? 'selected' : '' ?>>Public — everyone</option>
          <option value="members" <?= $visVal === 'members' ? 'selected' : '' ?>>Members only — logged-in users</option>
        </select>
      </label>
      <label class="field" style="margin-top:14px">SEO description <small>optional, ~160 chars</small>
        <textarea name="meta_description" maxlength="200" style="min-height:70px"><?= $v('meta_description') ?></textarea>
      </label>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
        <button class="btn btn-primary" type="submit" name="save_continue" value="1">Save</button>
        <button class="btn btn-ghost" type="submit">Save &amp; close</button>
      </div>

      <?php if ($page): ?>
        <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
        <button class="btn btn-danger btn-sm" type="submit" name="delete" value="1"
                data-confirm="Delete this page permanently? Menu links to it will be unlinked.">Delete page</button>
      <?php endif; ?>
    </div>
  </div>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
