<?php
/** CMS: manage custom menu items (nav + footer, with one level of submenus). */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_superuser();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $label     = trim($_POST['label'] ?? '');
        $location  = ($_POST['location'] ?? 'nav') === 'footer' ? 'footer' : 'nav';
        $parentId  = (int)($_POST['parent_id'] ?? 0) ?: null;
        $linkType  = ($_POST['link_type'] ?? 'page') === 'url' ? 'url' : 'page';
        $pageId    = (int)($_POST['page_id'] ?? 0) ?: null;
        $url       = trim($_POST['url'] ?? '');
        $newTab    = !empty($_POST['new_tab']) ? 1 : 0;
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;

        $err = null;
        if ($label === '' || mb_strlen($label) > 80) {
            $err = 'Label is required (max 80 characters).';
        }
        // validate parent: must be a top-level item in the same location
        if (!$err && $parentId) {
            $st = $pdo->prepare('SELECT location, parent_id FROM cms_menu_items WHERE id = ?');
            $st->execute([$parentId]);
            $par = $st->fetch();
            if (!$par || $par['parent_id'] !== null || $par['location'] !== $location || $parentId === $id) {
                $parentId = null; // silently drop an invalid parent
            }
        }
        // enforce a maximum of 5 TOP-LEVEL items in the top navigation
        if (!$err && $location === 'nav' && $parentId === null) {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM cms_menu_items WHERE location = "nav" AND parent_id IS NULL AND id <> ?');
            $cnt->execute([$id]);
            if ((int)$cnt->fetchColumn() >= 5) {
                $err = 'You can have at most 5 top-level items in the top navigation. Make this a sub-item, put it in the footer, or remove another top-level item first. (Sub-menu items are unlimited.)';
            }
        }
        if (!$err && $linkType === 'page') {
            if (!$pageId) {
                $err = 'Choose a page to link to (or switch to a custom URL).';
            }
            $url = '';
        } else {
            $pageId = null;
            if ($url === '' || !preg_match('~^(https?://|/)~i', $url)) {
                $err = 'Enter a valid URL starting with https:// or / .';
            }
        }

        if ($err) {
            flash('error', $err);
        } elseif ($id) {
            $pdo->prepare(
                'UPDATE cms_menu_items SET label=?, location=?, parent_id=?, link_type=?, page_id=?, url=?, new_tab=?, is_active=? WHERE id=?'
            )->execute([$label, $location, $parentId, $linkType, $pageId, $url ?: null, $newTab, $isActive, $id]);
            flash('success', 'Menu item updated.');
        } else {
            $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_menu_items WHERE location = ? AND parent_id <=> ?');
            $st->execute([$location, $parentId]);
            $order = (int)$st->fetchColumn();
            $pdo->prepare(
                'INSERT INTO cms_menu_items (label, location, parent_id, link_type, page_id, url, new_tab, sort_order, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([$label, $location, $parentId, $linkType, $pageId, $url ?: null, $newTab, $order, $isActive]);
            flash('success', 'Menu item added.');
        }
        cms_redirect('menus.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM cms_menu_items WHERE id = ? OR parent_id = ?')->execute([$id, $id]);
        flash('success', 'Menu item removed.');
        cms_redirect('menus.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE cms_menu_items SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        cms_redirect('menus.php');
    }

    if ($action === 'move') {
        $id  = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $st = $pdo->prepare('SELECT * FROM cms_menu_items WHERE id = ?');
        $st->execute([$id]);
        if ($it = $st->fetch()) {
            $sib = $pdo->prepare('SELECT id, sort_order FROM cms_menu_items WHERE location = ? AND parent_id <=> ? ORDER BY sort_order, id');
            $sib->execute([$it['location'], $it['parent_id']]);
            $rows = $sib->fetchAll();
            $pos = null;
            foreach ($rows as $i => $r) {
                if ((int)$r['id'] === $id) { $pos = $i; break; }
            }
            $swap = $dir === 'up' ? $pos - 1 : $pos + 1;
            if ($pos !== null && isset($rows[$swap])) {
                $a = $rows[$pos]; $b = $rows[$swap];
                $u = $pdo->prepare('UPDATE cms_menu_items SET sort_order = ? WHERE id = ?');
                $u->execute([(int)$b['sort_order'], (int)$a['id']]);
                $u->execute([(int)$a['sort_order'], (int)$b['id']]);
            }
        }
        cms_redirect('menus.php');
    }
}

// ---- data for rendering
$pages = $pdo->query('SELECT id, title, status FROM cms_pages ORDER BY title')->fetchAll();
$topItems = $pdo->query("SELECT id, label, location FROM cms_menu_items WHERE parent_id IS NULL ORDER BY location, sort_order, id")->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM cms_menu_items WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

/** Fetch a location's items as a parent→children list for display. */
function cms_admin_menu_rows(string $location): array
{
    $st = db()->prepare(
        "SELECT mi.*, p.title AS page_title FROM cms_menu_items mi
       LEFT JOIN cms_pages p ON p.id = mi.page_id
           WHERE mi.location = ? ORDER BY (mi.parent_id IS NOT NULL), mi.sort_order, mi.id"
    );
    $st->execute([$location]);
    $rows = $st->fetchAll();
    $parents = []; $children = [];
    foreach ($rows as $r) {
        if ($r['parent_id']) { $children[$r['parent_id']][] = $r; }
        else { $parents[] = $r; }
    }
    return [$parents, $children];
}

function cms_dest_label(array $it): string
{
    if ($it['link_type'] === 'page') {
        return $it['page_title'] ? 'Page: ' . $it['page_title'] : 'Page (missing)';
    }
    return 'URL: ' . $it['url'];
}

$cmsPageTitle = 'Menus';
$cmsActive = 'menus';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-page-head">
  <div>
    <h1>Menus</h1>
    <p class="cms-sub">Add your own items to the top navigation and footer. The site's built-in menus (Recipes, Forum, About, Contact…) are never changed — your items appear alongside them.</p>
  </div>
</div>

<div class="cms-form-2col">
  <div>
    <?php foreach (['nav' => 'Top navigation', 'footer' => 'Footer'] as $loc => $locLabel):
      [$parents, $children] = cms_admin_menu_rows($loc); ?>
      <div class="cms-menu-col panel" style="margin-bottom:18px">
        <h3><?= e($locLabel) ?> <span class="cms-sub" style="font-weight:400">(<?= $loc === 'nav' ? count($parents) . ' / 5 top-level' : count($parents) . ' top-level' ?>)</span></h3>
        <?php if (!$parents): ?>
          <p class="cms-sub" style="margin:0">No custom items here yet.</p>
        <?php else: ?>
          <ul class="cms-menu-list">
            <?php foreach ($parents as $p): ?>
              <?php $kids = $children[$p['id']] ?? []; ?>
              <li>
                <div class="cms-menu-row">
                  <span class="cms-ord">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="dir" value="up"><button title="Move up">▲</button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="dir" value="down"><button title="Move down">▼</button></form>
                  </span>
                  <span class="ml-label"><?= e($p['label']) ?><br><span class="ml-dest"><?= e(cms_dest_label($p)) ?></span></span>
                  <?php if (!$p['is_active']): ?><span class="cms-pill grey">hidden</span><?php endif; ?>
                  <div class="cms-actions">
                    <a class="btn btn-sm btn-ghost" href="<?= e(cms_url('menus.php?edit=' . (int)$p['id'])) ?>">Edit</a>
                    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button></form>
                    <form method="post" style="display:inline" data-confirm="Delete this item and its sub-items?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                  </div>
                </div>
                <?php foreach ($kids as $c): ?>
                  <div class="cms-menu-row child">
                    <span class="cms-ord">
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="up"><button>▲</button></form>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="down"><button>▼</button></form>
                    </span>
                    <span class="ml-label">↳ <?= e($c['label']) ?> <span class="ml-dest"><?= e(cms_dest_label($c)) ?></span></span>
                    <?php if (!$c['is_active']): ?><span class="cms-pill grey">hidden</span><?php endif; ?>
                    <div class="cms-actions">
                      <a class="btn btn-sm btn-ghost" href="<?= e(cms_url('menus.php?edit=' . (int)$c['id'])) ?>">Edit</a>
                      <form method="post" style="display:inline" data-confirm="Delete this sub-item?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel" id="form">
    <h3 style="margin-top:0"><?= $edit ? 'Edit menu item' : 'Add menu item' ?></h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

      <label class="field">Label
        <input type="text" name="label" maxlength="80" required value="<?= e($edit['label'] ?? '') ?>" placeholder="e.g. Press">
      </label>

      <label class="field">Location
        <select name="location">
          <option value="nav" <?= (($edit['location'] ?? 'nav') === 'nav') ? 'selected' : '' ?>>Top navigation</option>
          <option value="footer" <?= (($edit['location'] ?? '') === 'footer') ? 'selected' : '' ?>>Footer</option>
        </select>
      </label>

      <label class="field">Parent (for a submenu) <small>optional</small>
        <select name="parent_id">
          <option value="0">— None (top level) —</option>
          <?php foreach ($topItems as $t): if ($edit && (int)$t['id'] === (int)$edit['id']) continue; ?>
            <option value="<?= (int)$t['id'] ?>" <?= (($edit['parent_id'] ?? 0) == $t['id']) ? 'selected' : '' ?>>
              [<?= e($t['location']) ?>] <?= e($t['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="cms-help">Pick a top-level item to make this a dropdown sub-item under it.</span>
      </label>

      <label class="field">Links to
        <select name="link_type" id="link-type">
          <option value="page" <?= (($edit['link_type'] ?? 'page') === 'page') ? 'selected' : '' ?>>A CMS page</option>
          <option value="url" <?= (($edit['link_type'] ?? '') === 'url') ? 'selected' : '' ?>>A custom URL</option>
        </select>
      </label>

      <label class="field">CMS page
        <select name="page_id">
          <option value="0">— Select a page —</option>
          <?php foreach ($pages as $pg): ?>
            <option value="<?= (int)$pg['id'] ?>" <?= (($edit['page_id'] ?? 0) == $pg['id']) ? 'selected' : '' ?>>
              <?= e($pg['title']) ?><?= $pg['status'] !== 'published' ? ' (draft)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="cms-help">Only published pages appear on the live site.</span>
      </label>

      <label class="field">Custom URL
        <input type="text" name="url" maxlength="255" value="<?= e($edit['url'] ?? '') ?>" placeholder="https://example.com or /recipes.php">
      </label>

      <label class="cms-switch"><input type="checkbox" name="new_tab" value="1" <?= !empty($edit['new_tab']) ? 'checked' : '' ?>><span class="track"></span> Open in a new tab</label>
      <label class="cms-switch"><input type="checkbox" name="is_active" value="1" <?= (!$edit || $edit['is_active']) ? 'checked' : '' ?>><span class="track"></span> Visible on the site</label>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save item' : 'Add item' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(cms_url('menus.php')) ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
