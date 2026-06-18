<?php
/** CMS: list of pages. */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_superuser();

$pages = db()->query('SELECT * FROM cms_pages ORDER BY updated_at DESC')->fetchAll();

$cmsPageTitle = 'Pages';
$cmsActive = 'pages';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-page-head">
  <div>
    <h1>Pages</h1>
    <p class="cms-sub">Create and manage your own pages. They render on the live site using the same look &amp; feel — your existing pages are never touched.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(cms_url('page-edit.php')) ?>">+ New page</a>
</div>

<?php if (!$pages): ?>
  <div class="panel"><p class="cms-sub" style="margin:0">No pages yet. Click <strong>New page</strong> to create your first one, then link it from a menu.</p></div>
<?php else: ?>
  <table class="cms-table">
    <tr><th>Title</th><th>URL</th><th>Visibility</th><th>Status</th><th>Updated</th><th>Actions</th></tr>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><strong><?= e($p['title']) ?></strong></td>
        <td><code style="font-size:12.5px">/page.php?slug=<?= e($p['slug']) ?></code></td>
        <td><span class="cms-pill <?= $p['visibility'] === 'members' ? 'orange' : 'grey' ?>"><?= $p['visibility'] === 'members' ? 'Members' : 'Public' ?></span></td>
        <td><span class="cms-pill <?= $p['status'] === 'published' ? 'green' : 'grey' ?>"><?= ucfirst($p['status']) ?></span></td>
        <td class="cms-sub"><?= e(time_ago($p['updated_at'])) ?></td>
        <td>
          <div class="cms-actions">
            <a class="btn btn-sm btn-ghost" href="<?= e(cms_url('page-edit.php?id=' . (int)$p['id'])) ?>">Edit</a>
            <?php if ($p['status'] === 'published'): ?>
              <a class="btn btn-sm btn-outline" href="<?= e(cms_site_url('page.php?slug=' . urlencode($p['slug']))) ?>" target="_blank" rel="noopener">View ↗</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
