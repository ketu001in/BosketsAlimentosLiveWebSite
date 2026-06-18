<?php
/** Admin: master lists — ingredients, categories, cuisines, origins (rename / delete / add). */
require_once __DIR__ . '/_admin.php';

$tables = [
    'ingredients' => ['🧺 Ingredients', 'recipe_ingredients', 'ingredient_id'],
    'categories'  => ['🏷️ Categories', 'recipes', 'category_id'],
    'cuisines'    => ['🌍 Cuisines', 'recipes', 'cuisine_id'],
    'origins'     => ['📍 Origins', 'recipes', 'origin_id'],
];

$active = $_GET['list'] ?? 'ingredients';
if (!isset($tables[$active])) {
    $active = 'ingredients';
}
[$label, $refTable, $refCol] = $tables[$active];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $pdo = db();

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            find_or_create($active, $name, (int)$admin['id']);
            flash('success', 'Entry added to ' . $active . '.');
        }
    } elseif ($action === 'rename') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '' && $id) {
            // if the new name already exists, merge references into it
            $st = $pdo->prepare("SELECT id FROM `$active` WHERE name = ? AND id <> ?");
            $st->execute([$name, $id]);
            if ($existing = $st->fetchColumn()) {
                $pdo->prepare("UPDATE `$refTable` SET `$refCol` = ? WHERE `$refCol` = ?")->execute([$existing, $id]);
                $pdo->prepare("DELETE FROM `$active` WHERE id = ?")->execute([$id]);
                flash('success', 'Merged into existing entry "' . $name . '".');
            } else {
                $pdo->prepare("UPDATE `$active` SET name = ? WHERE id = ?")->execute([$name, $id]);
                flash('success', 'Renamed.');
            }
        }
    } elseif ($action === 'delete' && $id) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM `$refTable` WHERE `$refCol` = ?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0 && $active === 'ingredients') {
            flash('error', 'That ingredient is used in recipes — rename/merge it instead.');
        } else {
            // null out optional references on recipes, then remove
            if ($active !== 'ingredients') {
                $pdo->prepare("UPDATE `$refTable` SET `$refCol` = NULL WHERE `$refCol` = ?")->execute([$id]);
            }
            $pdo->prepare("DELETE FROM `$active` WHERE id = ?")->execute([$id]);
            flash('success', 'Entry deleted.');
        }
    }
    redirect('admin/masters.php?list=' . $active);
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '1';
if ($q !== '') {
    $where = 'm.name LIKE ?';
    $params[] = "%$q%";
}
$st = db()->prepare(
    "SELECT m.*, (SELECT COUNT(*) FROM `$refTable` r WHERE r.`$refCol` = m.id) used_count,
            u.username AS creator
       FROM `$active` m LEFT JOIN users u ON u.id = m.created_by
      WHERE $where ORDER BY m.name LIMIT 400"
);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Admin · Master Lists';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>📋 Master Lists</h1>
  <?php admin_nav('masters'); ?>

  <div class="tabs">
    <?php foreach ($tables as $key => [$lbl]): ?>
      <a href="?list=<?= e($key) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>

  <div class="filter-bar">
    <form method="get" style="display:flex;gap:10px;flex:1">
      <input type="hidden" name="list" value="<?= e($active) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search <?= e($active) ?>…" style="margin-top:0">
      <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
    <form method="post" style="display:flex;gap:10px;flex:1">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" required maxlength="100" placeholder="Add new entry…" style="margin-top:0">
      <button class="btn btn-accent btn-sm" type="submit">Add</button>
    </form>
  </div>

  <div class="table-wrap">
  <table class="data">
    <tr><th>Name</th><th>Used in</th><th>Added by</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <form method="post" style="display:flex;gap:8px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="text" name="name" value="<?= e($r['name']) ?>" maxlength="100" style="margin-top:0;max-width:280px">
            <button class="btn btn-sm btn-ghost" name="action" value="rename" title="Rename (merges if the name already exists)">Save</button>
            <button class="btn btn-sm btn-danger" name="action" value="delete"
                    onclick="return confirm('Delete this entry?')">Delete</button>
          </form>
        </td>
        <td><?= (int)$r['used_count'] ?> <?= $active === 'ingredients' ? 'recipe rows' : 'recipes' ?></td>
        <td class="small"><?= $r['creator'] ? '@' . e($r['creator']) : '<span class="muted">seed</span>' ?></td>
        <td></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="form-help">Tip: to merge duplicates (e.g. "Tomato" and "Tomatoes"), rename one to exactly match the other — references move automatically.</p>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
