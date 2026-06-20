<?php
/** Admin: manage CMS static pages (About, Contact, etc.) */
require_once __DIR__ . '/_admin.php';
ensure_cms_tables();

// ── Seed existing static pages on first visit so admin can edit them ──────────
$_seed = [
    'about-us' => [
        'title' => "About Us",
        'body'  => "<h2>Our Story</h2>
<p>Welcome to Bosket's Alimentos, where passion meets the plate and tradition finds a new voice.</p>
<p>Our journey began with a simple but profound love for food. Founded by <strong>Boskey (\"Bos\")</strong> and <strong>Ketul (\"Ket\")</strong>, a creative and food-enthusiast couple, our story is one of exploration, heritage, and culinary innovation.</p>
<p>Moving from the vibrant, flavor-rich state of Gujarat to the bustling gastronomic hub of Bangalore, we brought with us a deep appreciation for authentic regional cuisines. Our relentless passion for cooking and experimenting soon caught the attention of Bangalore's culinary community, leading us to win several prestigious culinary titles.</p>

<h2>Our Culinary Canvas</h2>
<p>At the heart of Bosket's Alimentos is our unique USP: <strong>Curated Fusion Vegetarian Cuisine</strong>. We draw deep inspiration from traditional Gujarati recipes and other regional Indian delicacies, carefully deconstructing and reimagining them. By adding meaningful, contemporary twists, we create entirely unique and original fusion dishes.</p>

<h2>Our Vision</h2>
<p>To revolutionize the vegetarian culinary landscape by making innovative fusion food an everyday delight, while establishing India's premier community-driven platform that celebrates and elevates undiscovered home culinary talent.</p>

<h2>Our Mission</h2>
<p>To curate extraordinary, heritage-inspired vegetarian fusion experiences, and to empower passionate home chefs by providing them with the professional tools, platform, and visibility needed to turn their culinary art into a celebrated profession.</p>",
        'meta'  => "The story of Bosket's Alimentos — curated fusion vegetarian cuisine and a professional platform for home chefs to shine.",
    ],
    'contact-intro' => [
        'title' => "Contact Us — Intro",
        'body'  => "<p>A question, an idea, a partnership, or something on the site that doesn't taste right — write to us. We read everything.</p>",
        'meta'  => '',
    ],
];
foreach ($_seed as $_slug => $_data) {
    $exists = db()->prepare("SELECT COUNT(*) FROM cms_pages WHERE slug = ?");
    $exists->execute([$_slug]);
    if (!(int)$exists->fetchColumn()) {
        db()->prepare(
            "INSERT INTO cms_pages (title, slug, body, meta_description, visibility, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'public', 'published', ?, NOW(), NOW())"
        )->execute([$_data['title'], $_slug, $_data['body'], $_data['meta'] ?: null, $admin['id']]);
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pid   = (int)($_POST['page_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body  = $_POST['body'] ?? '';
        $slug  = trim($_POST['slug'] ?? '');
        $meta  = mb_substr(trim($_POST['meta_description'] ?? ''), 0, 200);
        $vis   = in_array($_POST['visibility'] ?? '', ['public','members']) ? $_POST['visibility'] : 'public';
        $stat  = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        if ($title === '') { flash('error', 'Title is required.'); redirect('admin/cms-pages.php'); }

        // Auto-slug
        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
            $slug = trim($slug, '-');
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

        if ($pid) {
            db()->prepare(
                "UPDATE cms_pages SET title=?, slug=?, body=?, meta_description=?, visibility=?, status=?, updated_at=NOW() WHERE id=?"
            )->execute([$title, $slug, $body ?: null, $meta ?: null, $vis, $stat, $pid]);
            flash('success', 'Page updated.');
        } else {
            // Check slug unique
            $exists = db()->prepare("SELECT COUNT(*) FROM cms_pages WHERE slug=?");
            $exists->execute([$slug]);
            if ($exists->fetchColumn()) $slug .= '-' . substr(uniqid(), -4);
            db()->prepare(
                "INSERT INTO cms_pages (title, slug, body, meta_description, visibility, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            )->execute([$title, $slug, $body ?: null, $meta ?: null, $vis, $stat, $admin['id']]);
            flash('success', 'Page created.');
        }
        redirect('admin/cms-pages.php');
    }

    if ($action === 'delete') {
        $pid = (int)($_POST['page_id'] ?? 0);
        db()->prepare("DELETE FROM cms_pages WHERE id=?")->execute([$pid]);
        flash('success', 'Page deleted.');
        redirect('admin/cms-pages.php');
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$editId   = (int)($_GET['edit'] ?? 0);
$editPage = null;
if ($editId) {
    $st = db()->prepare("SELECT * FROM cms_pages WHERE id=?");
    $st->execute([$editId]);
    $editPage = $st->fetch();
}
$pages = db()->query("SELECT * FROM cms_pages ORDER BY updated_at DESC")->fetchAll();

$pageTitle = 'Admin · Pages';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>📄 Static Pages</h1>
  <?php admin_nav('cms-pages'); ?>
  <p class="muted">Create public pages (About Us extras, Contact, Policies etc.) that appear on the live site.</p>

  <!-- Editor -->
  <div class="panel" style="margin-bottom:28px">
    <h3><?= $editPage ? 'Edit: ' . e($editPage['title']) : 'Create new page' ?></h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="page_id" value="<?= $editId ?>">
      <div class="form-row">
        <label class="field">Page title <span class="req">*</span>
          <input type="text" name="title" required maxlength="160" value="<?= e($editPage['title'] ?? '') ?>">
        </label>
        <label class="field">URL slug <small>auto-generated if blank</small>
          <input type="text" name="slug" maxlength="180" placeholder="e.g. privacy-policy" value="<?= e($editPage['slug'] ?? '') ?>">
        </label>
      </div>
      <label class="field">Page content <small>HTML is supported</small>
        <textarea name="body" rows="14" style="font-family:monospace;font-size:13px"><?= e($editPage['body'] ?? '') ?></textarea>
      </label>
      <label class="field">Meta description <small>For SEO — max 200 chars</small>
        <input type="text" name="meta_description" maxlength="200" value="<?= e($editPage['meta_description'] ?? '') ?>">
      </label>
      <div class="form-row">
        <label class="field">Visibility
          <select name="visibility">
            <option value="public"   <?= ($editPage['visibility'] ?? 'public') === 'public'   ? 'selected' : '' ?>>Public</option>
            <option value="members"  <?= ($editPage['visibility'] ?? '') === 'members' ? 'selected' : '' ?>>Members only</option>
          </select>
        </label>
        <label class="field">Status
          <select name="status">
            <option value="draft"     <?= ($editPage['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Draft (hidden)</option>
            <option value="published" <?= ($editPage['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (live)</option>
          </select>
        </label>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" type="submit">💾 Save page</button>
        <?php if ($editPage): ?><a class="btn btn-ghost" href="admin/cms-pages.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Page list -->
  <h3>All pages (<?= count($pages) ?>)</h3>
  <?php if (!$pages): ?>
    <div class="empty"><span class="big">📄</span>No pages yet.</div>
  <?php else: ?>
  <div class="table-wrap">
  <table class="data">
    <tr><th>Title</th><th>URL</th><th>Status</th><th>Updated</th><th>Actions</th></tr>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><?= e($p['title']) ?></td>
        <td><code style="font-size:12px">/page.php?slug=<?= e($p['slug']) ?></code></td>
        <td><?= $p['status'] === 'published' ? '<span class="pill pill-green">Live</span>' : '<span class="pill">Draft</span>' ?></td>
        <td class="small"><?= e(time_ago($p['updated_at'])) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <a class="btn btn-sm btn-ghost" href="admin/cms-pages.php?edit=<?= (int)$p['id'] ?>">Edit</a>
            <?php if ($p['status'] === 'published'): ?>
              <a class="btn btn-sm btn-ghost" href="<?= e(url('page.php?slug=' . urlencode($p['slug']))) ?>" target="_blank">View ↗</a>
            <?php endif; ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this page?')">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
