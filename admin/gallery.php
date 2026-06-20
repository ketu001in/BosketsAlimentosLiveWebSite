<?php
/** Admin: manage About Us gallery images. */
require_once __DIR__ . '/_admin.php';
ensure_gallery_table();

const GALLERY_MAX = 200;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $count = (int)db()->query("SELECT COUNT(*) FROM gallery")->fetchColumn();
        if ($count >= GALLERY_MAX) {
            flash('error', 'Gallery is full (max ' . GALLERY_MAX . ' images). Delete some before uploading.');
        } elseif (empty($_FILES['images']['name'][0])) {
            flash('error', 'Select at least one image to upload.');
        } else {
            $uploaded = 0;
            $errors   = [];
            // handle multiple files
            $files = $_FILES['images'];
            $total = count($files['name']);
            for ($i = 0; $i < $total && ($count + $uploaded) < GALLERY_MAX; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                // rebuild single-file array for handle_upload
                $_FILES['__gal_tmp'] = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                try {
                    $up = handle_upload('__gal_tmp', 'image', 'gallery');
                    if ($up) {
                        $caption = trim($_POST['captions'][$i] ?? '');
                        db()->prepare(
                            "INSERT INTO gallery (image, caption, sort_order, created_at, created_by)
                             VALUES (?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM gallery g2), NOW(), ?)"
                        )->execute([$up['file'], $caption ?: null, $admin['id']]);
                        $uploaded++;
                    }
                } catch (RuntimeException $ex) {
                    $errors[] = $files['name'][$i] . ': ' . $ex->getMessage();
                }
            }
            if ($uploaded) flash('success', "$uploaded image(s) uploaded.");
            foreach ($errors as $err) flash('error', $err);
        }
    }

    if ($action === 'update_caption') {
        $id      = (int)($_POST['id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        db()->prepare("UPDATE gallery SET caption = ? WHERE id = ?")->execute([$caption ?: null, $id]);
        flash('success', 'Caption updated.');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare("SELECT image FROM gallery WHERE id = ?");
        $st->execute([$id]);
        if ($img = $st->fetchColumn()) delete_upload($img);
        db()->prepare("DELETE FROM gallery WHERE id = ?")->execute([$id]);
        flash('success', 'Image deleted.');
    }

    redirect('admin/gallery.php');
}

$images = db()->query("SELECT * FROM gallery ORDER BY sort_order, id")->fetchAll();
$count  = count($images);

$pageTitle = 'Admin · Gallery';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>🖼️ Gallery Management</h1>
  <?php admin_nav('gallery'); ?>
  <p class="muted">Images uploaded here appear in the <strong>Gallery</strong> section on the About Us page.
     <strong><?= $count ?> / <?= GALLERY_MAX ?></strong> images used.</p>

  <?php if ($count < GALLERY_MAX): ?>
  <div class="panel" style="margin-bottom:28px">
    <h3>Upload images</h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <label class="field">Select photos <small>JPG, PNG, WEBP — max 5 MB each — you can select multiple</small>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
      </label>
      <div style="margin-top:12px"><button class="btn btn-primary" type="submit">Upload</button></div>
    </form>
  </div>
  <?php else: ?>
  <div class="flash flash-error" style="margin-bottom:20px">Gallery is full (<?= GALLERY_MAX ?> images). Delete some images to upload more.</div>
  <?php endif; ?>

  <?php if (!$images): ?>
    <div class="empty"><span class="big">📷</span>No images uploaded yet.</div>
  <?php else: ?>
  <div class="gallery-masonry" id="gallery-admin-grid">
    <?php foreach ($images as $img): ?>
      <div class="gallery-item" style="position:relative">
        <img src="<?= e(url($img['image'])) ?>" alt="<?= e($img['caption'] ?? '') ?>">
        <div style="padding:8px 4px">
          <form method="post" style="display:flex;gap:6px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_caption">
            <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
            <input type="text" name="caption" value="<?= e($img['caption'] ?? '') ?>" placeholder="Caption (optional)"
                   style="flex:1;font-size:12px;padding:5px 8px;border-radius:5px;border:1px solid var(--line)">
            <button class="btn btn-sm btn-ghost" type="submit" title="Save caption">✓</button>
          </form>
          <form method="post" style="margin-top:5px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit" style="width:100%;font-size:12px"
                    onclick="return confirm('Delete this image?')">🗑 Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
