<?php
/** Public recipe browser with search + category/cuisine/origin filters. */
require_once __DIR__ . '/includes/bootstrap.php';

$q        = trim($_GET['q'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$cuisine  = (int)($_GET['cuisine'] ?? 0);
$origin   = (int)($_GET['origin'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ["r.status = 'published'"];
$params = [];
if ($q !== '') {
    $where[] = '(r.title LIKE ? OR r.story LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($category) { $where[] = 'r.category_id = ?'; $params[] = $category; }
if ($cuisine)  { $where[] = 'r.cuisine_id = ?';  $params[] = $cuisine; }
if ($origin)   { $where[] = 'r.origin_id = ?';   $params[] = $origin; }
$whereSql = implode(' AND ', $where);

$st = db()->prepare("SELECT COUNT(*) FROM recipes r WHERE $whereSql");
$st->execute($params);
$total = (int)$st->fetchColumn();

$offset = (max(1, min($page, max(1, (int)ceil($total / PER_PAGE)))) - 1) * PER_PAGE;
$st = db()->prepare(
    "SELECT r.*, u.username, u.display_name, u.avatar,
            c.name AS category_name, cu.name AS cuisine_name,
            (SELECT COUNT(*) FROM reactions x WHERE x.target_type='recipe' AND x.target_id = r.id) reaction_count,
            (SELECT COUNT(*) FROM comments x WHERE x.target_type='recipe' AND x.target_id = r.id AND x.status='visible') comment_count
       FROM recipes r
       JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c ON c.id = r.category_id
  LEFT JOIN cuisines cu ON cu.id = r.cuisine_id
      WHERE $whereSql
   ORDER BY r.is_featured DESC, r.created_at DESC
      LIMIT " . PER_PAGE . " OFFSET $offset"
);
$st->execute($params);
$recipes = $st->fetchAll();

$cats = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$cuis = db()->query('SELECT id, name FROM cuisines ORDER BY name')->fetchAll();
$orig = db()->query('SELECT id, name FROM origins ORDER BY name')->fetchAll();

$qs = http_build_query(array_filter([
    'q' => $q, 'category' => $category ?: null, 'cuisine' => $cuisine ?: null, 'origin' => $origin ?: null,
]));

$pageTitle = 'Recipes';
include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <div class="section-head">
    <h2 style="font-size:32px">Fusion veg recipes</h2>
    <?php if (is_logged_in()): ?><a class="btn btn-accent" href="<?= e(url('post-recipe.php')) ?>">+ Post a New Recipe</a><?php endif; ?>
  </div>

  <form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search recipes…">
    <select name="category"><option value="">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="cuisine"><option value="">All cuisines</option>
      <?php foreach ($cuis as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cuisine === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="origin"><option value="">All origins</option>
      <?php foreach ($orig as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $origin === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
  </form>

  <p class="muted small"><?= $total ?> recipe<?= $total === 1 ? '' : 's' ?> found</p>

  <?php if (!$recipes): ?>
    <div class="empty"><span class="big">🍳</span>No recipes match your search — try widening the filters.</div>
  <?php else: ?>
    <div class="grid"><?php foreach ($recipes as $r) echo recipe_card($r); ?></div>
  <?php endif; ?>

  <?= paginate($total, $page, $qs) ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
