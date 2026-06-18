<?php
/** CMS dashboard. */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_superuser();

function cms_count(string $sql): int
{
    try {
        return (int)db()->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$stats = [
    'pages_total'   => cms_count('SELECT COUNT(*) FROM cms_pages'),
    'pages_pub'     => cms_count("SELECT COUNT(*) FROM cms_pages WHERE status = 'published'"),
    'menu_items'    => cms_count('SELECT COUNT(*) FROM cms_menu_items'),
    'recipes'       => cms_count("SELECT COUNT(*) FROM recipes WHERE status = 'published'"),
    'comments'      => cms_count("SELECT COUNT(*) FROM comments WHERE status = 'visible'"),
    'wall'          => cms_count("SELECT COUNT(*) FROM wall_posts WHERE status = 'visible'"),
    'topics'        => cms_count("SELECT COUNT(*) FROM forum_topics WHERE status = 'visible'"),
    'new_recipes7'  => cms_count("SELECT COUNT(*) FROM recipes WHERE status='published' AND created_at > NOW() - INTERVAL 7 DAY"),
    'new_comments7' => cms_count("SELECT COUNT(*) FROM comments WHERE status='visible' AND created_at > NOW() - INTERVAL 7 DAY"),
];

// recent moderation actions
$log = [];
try {
    $st = db()->query(
        "SELECT l.*, u.username FROM cms_moderation_log l LEFT JOIN users u ON u.id = l.admin_id
         ORDER BY l.id DESC LIMIT 8"
    );
    $log = $st->fetchAll();
} catch (Throwable $e) {
}

$cmsPageTitle = 'Dashboard';
$cmsActive = 'index';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-page-head">
  <div>
    <h1>Welcome, <?= e($admin['display_name'] ?: $admin['username']) ?></h1>
    <p class="cms-sub">You're signed in as <strong>SuperUser</strong>. Changes here affect the live website immediately.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(cms_url('page-edit.php')) ?>">+ New page</a>
</div>

<div class="cms-stats">
  <div class="cms-tile"><strong><?= $stats['pages_pub'] ?></strong><span>Published pages <?= $stats['pages_total'] > $stats['pages_pub'] ? '(' . ($stats['pages_total'] - $stats['pages_pub']) . ' draft)' : '' ?></span></div>
  <div class="cms-tile"><strong><?= $stats['menu_items'] ?></strong><span>CMS menu items</span></div>
  <div class="cms-tile<?= $stats['new_recipes7'] ? ' alert' : '' ?>"><strong><?= $stats['new_recipes7'] ?></strong><span>New recipes (7 days)</span></div>
  <div class="cms-tile<?= $stats['new_comments7'] ? ' alert' : '' ?>"><strong><?= $stats['new_comments7'] ?></strong><span>New comments (7 days)</span></div>
</div>

<div class="cms-form-2col">
  <div class="panel">
    <h3 style="margin-top:0">Quick actions</h3>
    <div class="cms-inline">
      <a class="btn btn-outline btn-sm" href="<?= e(cms_url('page-edit.php')) ?>">Create a page</a>
      <a class="btn btn-outline btn-sm" href="<?= e(cms_url('menus.php')) ?>">Manage menus</a>
      <a class="btn btn-outline btn-sm" href="<?= e(cms_url('moderation.php')) ?>">Moderate content</a>
      <a class="btn btn-outline btn-sm" href="<?= e(cms_url('appearance.php')) ?>">Change theme &amp; fonts</a>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h3>Live content totals</h3>
    <p class="cms-sub" style="margin:0">
      <strong><?= $stats['recipes'] ?></strong> recipes &nbsp;·&nbsp;
      <strong><?= $stats['comments'] ?></strong> comments &nbsp;·&nbsp;
      <strong><?= $stats['wall'] ?></strong> wall posts &nbsp;·&nbsp;
      <strong><?= $stats['topics'] ?></strong> forum topics
    </p>
    <p class="cms-help">Use <a href="<?= e(cms_url('moderation.php')) ?>">Moderation</a> to hide or remove anything inappropriate. The main-site Admin panel still works the same way too.</p>
  </div>

  <div class="panel">
    <h3 style="margin-top:0">Recent moderation</h3>
    <?php if (!$log): ?>
      <p class="cms-sub" style="margin:0">No moderation actions yet.</p>
    <?php else: ?>
      <ul style="list-style:none;padding:0;margin:0">
        <?php foreach ($log as $l): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line);font-size:13.5px">
            <strong><?= e(ucfirst($l['action'])) ?></strong> <?= e($l['content_type']) ?> #<?= (int)$l['content_id'] ?>
            <span class="cms-sub">· <?= e($l['username'] ? '@' . $l['username'] : 'admin') ?> · <?= e(time_ago($l['created_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
