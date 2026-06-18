<?php
/** Public renderer for CMS-managed pages: /page.php?slug=… */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$page = null;
if ($slug !== '') {
    try {
        $st = db()->prepare("SELECT * FROM cms_pages WHERE slug = ? AND status = 'published'");
        $st->execute([$slug]);
        $page = $st->fetch() ?: null;
    } catch (Throwable $e) {
        $page = null; // CMS not installed yet
    }
}

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Page not found';
    $noIndex = true;
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section section-narrow" style="text-align:center">'
       . '<h1>Page not found</h1><p class="muted">This page may have been moved or unpublished.</p>'
       . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to home</a></p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Members-only pages require a logged-in user.
if ($page['visibility'] === 'members' && !is_logged_in()) {
    $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    flash('info', 'Please sign in to view this page.');
    redirect('login.php');
}

$pageTitle = $page['title'];
$pageDesc  = $page['meta_description'] !== '' ? $page['meta_description']
           : trim(preg_replace('/\s+/', ' ', mb_substr(strip_tags((string)$page['body']), 0, 160)));
include __DIR__ . '/includes/header.php';
?>
<div class="container section section-narrow cms-page">
  <h1><?= e($page['title']) ?></h1>
  <div class="cms-page-body"><?= $page['body'] ?></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
