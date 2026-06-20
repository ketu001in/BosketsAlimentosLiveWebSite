<?php
/** Admin: manage navigation menus (nav bar + footer). */
require_once __DIR__ . '/_admin.php';
ensure_cms_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $pdo    = db();

    if ($action === 'add') {
        $label    = mb_substr(trim($_POST['label'] ?? ''), 0, 80);
        $location = in_array($_POST['location'] ?? '', ['nav','footer']) ? $_POST['location'] : 'nav';
        $linkType = ($_POST['link_type'] ?? 'url') === 'page' ? 'page' : 'url';
        $pageId   = (int)($_POST['page_id'] ?? 0) ?: null;
        $url      = trim($_POST['url'] ?? '');
        $newTab   = !empty($_POST['new_tab']) ? 1 : 0;
        if ($label === '') { flash('error', 'Label is required.'); redirect('admin/cms-menus.php'); }
        $pdo->prepare(
            "INSERT INTO cms_menu_items (label, location, link_type, page_id, url, new_tab, sort_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_menu_items m2 WHERE m2.location=?), 1, NOW())"
        )->execute([$label, $location, $linkType, $pageId, $url ?: null, $newTab, $location]);
        flash('success', 'Menu item added.');
    }

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE cms_menu_items SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        flash('success', 'Menu item toggled.');
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM cms_menu_items WHERE id=?")->execute([$id]);
        flash('success', 'Menu item deleted.');
    }

    if ($action === 'move') {
        $dir = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
        $st = $pdo->prepare("SELECT sort_order, location FROM cms_menu_items WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            $pdo->prepare("UPDATE cms_menu_items SET sort_order = sort_order + ? WHERE id=?")->execute([$dir, $id]);
        }
        flash('success', 'Order updated.');
    }

    redirect('admin/cms-menus.php');
}

$menuItems = db()->query(
    "SELECT m.*, cp.title AS page_title FROM cms_menu_items m
     LEFT JOIN cms_pages cp ON cp.id = m.page_id
     ORDER BY m.location, m.sort_order, m.id"
)->fetchAll();

$cmsPages = db()->query("SELECT id, title FROM cms_pages WHERE status='published' ORDER BY title")->fetchAll();
$navItems    = array_filter($menuItems, fn($m) => $m['location'] === 'nav');
$footerItems = array_filter($menuItems, fn($m) => $m['location'] === 'footer');

$pageTitle = 'Admin · Menus';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>🗂️ Navigation Menus</h1>
  <?php admin_nav('cms-menus'); ?>

  <!-- Add item form -->
  <div class="panel" style="margin-bottom:28px">
    <h3>Add menu item</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <label class="field">Label <span class="req">*</span>
          <input type="text" name="label" maxlength="80" placeholder="e.g. Blog" required>
        </label>
        <label class="field">Location
          <select name="location">
            <option value="nav">Top navigation</option>
            <option value="footer">Footer</option>
          </select>
        </label>
      </div>
      <div class="form-row">
        <label class="field">Link type
          <select name="link_type" id="link-type-sel" onchange="document.getElementById('url-row').style.display=this.value==='url'?'block':'none';document.getElementById('page-row').style.display=this.value==='page'?'block':'none'">
            <option value="url">External / custom URL</option>
            <option value="page">CMS Page</option>
          </select>
        </label>
        <label class="field" style="align-items:center;padding-top:24px">
          <label style="display:flex;gap:8px;cursor:pointer">
            <input type="checkbox" name="new_tab" value="1"> Open in new tab
          </label>
        </label>
      </div>
      <div id="url-row" class="field">URL
        <input type="text" name="url" placeholder="https://… or /relative/path">
      </div>
      <div id="page-row" class="field" style="display:none">CMS Page
        <select name="page_id">
          <option value="">— select a page —</option>
          <?php foreach ($cmsPages as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><button class="btn btn-primary" type="submit">+ Add item</button></div>
    </form>
  </div>

  <!-- Nav items -->
  <div class="panel" style="margin-bottom:24px">
    <h3>Top navigation</h3>
    <?php if (!$navItems): ?>
      <p class="muted small">No items yet.</p>
    <?php else: ?>
    <table class="data">
      <tr><th>Label</th><th>Links to</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($navItems as $m): ?>
        <tr>
          <td><?= e($m['label']) ?></td>
          <td class="small"><?= $m['link_type'] === 'page' ? e($m['page_title'] ?? '—') : e($m['url'] ?? '—') ?><?= $m['new_tab'] ? ' ↗' : '' ?></td>
          <td><?= $m['is_active'] ? '<span class="pill pill-green">Active</span>' : '<span class="pill pill-red">Hidden</span>' ?></td>
          <td>
            <div style="display:flex;gap:5px">
              <form method="post"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="dir" value="up"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" title="Move up">↑</button></form>
              <form method="post"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="dir" value="down"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" title="Move down">↓</button></form>
              <form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $m['is_active'] ? 'Hide' : 'Show' ?></button></form>
              <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" onclick="return confirm('Delete this menu item?')">Delete</button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <!-- Footer items -->
  <div class="panel">
    <h3>Footer links</h3>
    <?php if (!$footerItems): ?>
      <p class="muted small">No items yet.</p>
    <?php else: ?>
    <table class="data">
      <tr><th>Label</th><th>Links to</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($footerItems as $m): ?>
        <tr>
          <td><?= e($m['label']) ?></td>
          <td class="small"><?= $m['link_type'] === 'page' ? e($m['page_title'] ?? '—') : e($m['url'] ?? '—') ?></td>
          <td><?= $m['is_active'] ? '<span class="pill pill-green">Active</span>' : '<span class="pill pill-red">Hidden</span>' ?></td>
          <td>
            <div style="display:flex;gap:5px">
              <form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost"><?= $m['is_active'] ? 'Hide' : 'Show' ?></button></form>
              <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
