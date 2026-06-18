<?php
/** Live recipe search for the header search box (fires at 3+ typed letters).
 *  Matches recipe title + author (name/username) + cuisine, category & origin.
 *  Only published recipes are returned. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    json_out(['ok' => true, 'items' => []]);
}

$like = '%' . $q . '%';
$st = db()->prepare(
    "SELECT DISTINCT r.id, r.title, r.image, r.views,
            u.display_name, u.username,
            c.name AS category_name, cu.name AS cuisine_name
       FROM recipes r
       JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c  ON c.id  = r.category_id
  LEFT JOIN cuisines  cu ON cu.id = r.cuisine_id
  LEFT JOIN origins   o  ON o.id  = r.origin_id
      WHERE r.status = 'published'
        AND ( r.title LIKE ? OR u.display_name LIKE ? OR u.username LIKE ?
              OR c.name LIKE ? OR cu.name LIKE ? OR o.name LIKE ? )
   ORDER BY (r.title LIKE ?) DESC, r.views DESC, r.created_at DESC
      LIMIT 8"
);
$st->execute([$like, $like, $like, $like, $like, $like, $q . '%']);

$items = [];
foreach ($st as $row) {
    $meta = trim(implode(' · ', array_filter([$row['category_name'], $row['cuisine_name']])));
    $items[] = [
        'title'  => $row['title'],
        'author' => $row['display_name'] ?: $row['username'],
        'meta'   => $meta,
        'image'  => $row['image'] ? url($row['image']) : '',
        'url'    => url('recipe.php?id=' . (int)$row['id']),
    ];
}

json_out(['ok' => true, 'items' => $items]);
