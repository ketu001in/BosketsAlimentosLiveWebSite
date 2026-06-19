<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

// Demo users identified by their seeded usernames + example.com emails
$DEMO_USERNAMES = ['maya_fusion', 'leo_cocina', 'sakura_spice', 'arjun_tadka'];

// Fetch demo user IDs that actually exist
$in  = implode(',', array_fill(0, count($DEMO_USERNAMES), '?'));
$ids = db()->prepare("SELECT id FROM users WHERE username IN ($in) AND email LIKE '%@example.com' AND is_admin = 0");
$ids->execute($DEMO_USERNAMES);
$demoIds = $ids->fetchAll(PDO::FETCH_COLUMN);

$done    = false;
$preview = [];
$log     = [];

if (!empty($demoIds)) {
    $idList = implode(',', array_map('intval', $demoIds));

    // Count what will be deleted (for preview)
    $preview['users']         = count($demoIds);
    $preview['recipes']       = (int) db()->query("SELECT COUNT(*) FROM recipes WHERE user_id IN ($idList)")->fetchColumn();
    $preview['wall_posts']    = (int) db()->query("SELECT COUNT(*) FROM wall_posts WHERE user_id IN ($idList)")->fetchColumn();
    $preview['forum_topics']  = (int) db()->query("SELECT COUNT(*) FROM forum_topics WHERE user_id IN ($idList)")->fetchColumn();
    $preview['comments_by']   = (int) db()->query("SELECT COUNT(*) FROM comments WHERE user_id IN ($idList)")->fetchColumn();
    $preview['reactions_by']  = (int) db()->query("SELECT COUNT(*) FROM reactions WHERE user_id IN ($idList)")->fetchColumn();
    $preview['buddy_links']   = (int) db()->query("SELECT COUNT(*) FROM buddies WHERE requester_id IN ($idList) OR addressee_id IN ($idList)")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_confirm'] ?? '') === 'yes' && !empty($demoIds)) {
    $idList = implode(',', array_map('intval', $demoIds));

    // Get demo recipe IDs
    $recipeIds = db()->query("SELECT id FROM recipes WHERE user_id IN ($idList)")->fetchAll(PDO::FETCH_COLUMN);
    $ridList   = $recipeIds ? implode(',', array_map('intval', $recipeIds)) : '0';

    // Get demo forum topic IDs
    $topicIds = db()->query("SELECT id FROM forum_topics WHERE user_id IN ($idList)")->fetchAll(PDO::FETCH_COLUMN);
    $tidList  = $topicIds ? implode(',', array_map('intval', $topicIds)) : '0';

    $pdo = db();

    // 1. Ingredients & steps for demo recipes
    $pdo->exec("DELETE FROM recipe_ingredients WHERE recipe_id IN ($ridList)");
    $log[] = 'Deleted recipe ingredients';

    $pdo->exec("DELETE FROM recipe_steps WHERE recipe_id IN ($ridList)");
    $log[] = 'Deleted recipe steps';

    // 2. Reactions ON demo recipes (from any user), and BY demo users
    $pdo->exec("DELETE FROM reactions WHERE (target_type='recipe' AND target_id IN ($ridList)) OR user_id IN ($idList)");
    $log[] = 'Deleted reactions';

    // 3. Comments ON demo recipes and demo topics, and BY demo users
    $pdo->exec("DELETE FROM comments WHERE (target_type='recipe' AND target_id IN ($ridList)) OR (target_type='topic' AND target_id IN ($tidList)) OR user_id IN ($idList)");
    $log[] = 'Deleted comments';

    // 4. Wall posts by demo users, and wall posts sharing demo recipes
    $pdo->exec("DELETE FROM wall_posts WHERE user_id IN ($idList) OR shared_recipe_id IN ($ridList)");
    $log[] = 'Deleted wall posts';

    // 5. Demo forum topics
    $pdo->exec("DELETE FROM forum_topics WHERE user_id IN ($idList)");
    $log[] = 'Deleted forum topics';

    // 6. Demo recipes
    $pdo->exec("DELETE FROM recipes WHERE user_id IN ($idList)");
    $log[] = "Deleted {$preview['recipes']} demo recipes";

    // 7. Buddy links
    $pdo->exec("DELETE FROM buddies WHERE requester_id IN ($idList) OR addressee_id IN ($idList)");
    $log[] = 'Deleted buddy links';

    // 8. Notifications
    $pdo->exec("DELETE FROM notifications WHERE user_id IN ($idList) OR actor_id IN ($idList)");
    $log[] = 'Deleted notifications';

    // 9. Demo users
    $pdo->exec("DELETE FROM users WHERE id IN ($idList)");
    $log[] = "Deleted {$preview['users']} demo users (maya_fusion, leo_cocina, sakura_spice, arjun_tadka)";

    // 10. Delete demo image files
    $deletedFiles = 0;
    $uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    foreach (['recipes', 'avatars'] as $sub) {
        $dir = $uploadsDir . DIRECTORY_SEPARATOR . $sub . DIRECTORY_SEPARATOR;
        if (is_dir($dir)) {
            foreach (glob($dir . 'demo_*') ?: [] as $f) {
                if (is_file($f)) { unlink($f); $deletedFiles++; }
            }
        }
    }
    $log[] = "Deleted $deletedFiles demo image files from uploads/";

    $done = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clean Up Demo Data</title>
<style>
body{font-family:Arial,sans-serif;padding:30px;background:#111;color:#ddd;max-width:700px}
h1{color:#3fa796}
.btn{background:#c0392b;color:#fff;border:0;padding:12px 28px;font-size:15px;border-radius:6px;cursor:pointer}
.btn-cancel{background:#444;color:#fff;border:0;padding:12px 20px;font-size:15px;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-block}
table{width:100%;border-collapse:collapse;margin:16px 0;font-size:14px}
th,td{padding:8px 12px;border-bottom:1px solid #333;text-align:left}
th{background:#1a2e2b;color:#3fa796}
.ok{color:#3fa796}.warn{color:#e67e22}.card{background:#1a2e2b;padding:16px 20px;border-radius:8px;margin:16px 0}
ul{padding-left:20px;line-height:1.9}
a{color:#3fa796}
</style>
</head>
<body>
<h1>&#x1F9F9; Clean Up Demo Data</h1>
<p><a href="<?= e(url('admin/index.php')) ?>">&larr; Back to Admin</a></p>

<?php if ($done): ?>
  <div class="card">
    <p class="ok" style="font-size:16px;font-weight:bold">&#x2705; Demo data removed successfully.</p>
    <ul>
      <?php foreach ($log as $l): ?>
        <li><?= e($l) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <p>Your imported Blogspot recipes and real user data are untouched.</p>

<?php elseif (empty($demoIds)): ?>
  <div class="card">
    <p class="ok">&#x2705; No demo users found in the database. Nothing to clean up.</p>
  </div>

<?php else: ?>
  <div class="card">
    <p class="warn" style="font-size:15px">&#x26A0;&#xFE0F; The following demo data will be <strong>permanently deleted</strong>:</p>
    <table>
      <tr><th>Item</th><th>Count</th></tr>
      <tr><td>Demo users (maya_fusion, leo_cocina, sakura_spice, arjun_tadka)</td><td><?= (int)$preview['users'] ?></td></tr>
      <tr><td>Demo recipes</td><td><?= (int)$preview['recipes'] ?></td></tr>
      <tr><td>Demo wall posts</td><td><?= (int)$preview['wall_posts'] ?></td></tr>
      <tr><td>Demo forum topics</td><td><?= (int)$preview['forum_topics'] ?></td></tr>
      <tr><td>Comments by demo users (or on demo content)</td><td><?= (int)$preview['comments_by'] ?></td></tr>
      <tr><td>Reactions by demo users (or on demo recipes)</td><td><?= (int)$preview['reactions_by'] ?></td></tr>
      <tr><td>Buddy links</td><td><?= (int)$preview['buddy_links'] ?></td></tr>
    </table>
    <p style="color:#aaa;font-size:13px">&#x1F512; Your <strong>admin account</strong>, all <strong>imported Blogspot recipes</strong>, and any <strong>real registered users</strong> will NOT be touched.</p>
  </div>

  <form method="post">
    <input type="hidden" name="_confirm" value="yes">
    <button class="btn" type="submit">&#x1F5D1;&#xFE0F; Delete All Demo Data</button>
    &nbsp;
    <a class="btn-cancel" href="<?= e(url('admin/index.php')) ?>">Cancel</a>
  </form>
<?php endif; ?>

</body>
</html>
