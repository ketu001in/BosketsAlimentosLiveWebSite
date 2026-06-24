<?php
/**
 * Admin: Import WordPress recipes from exported ZIP — ONE STEP, no session.
 */
// Must be set before session starts to take effect
@ini_set('memory_limit',       '512M');
@ini_set('max_execution_time', '600');
@set_time_limit(600);

require_once __DIR__ . '/_admin.php';
ensure_recipe_pending_status();

// Temp folder on server for ZIP storage
$tmpDir = dirname(__DIR__) . '/uploads/wp-import-tmp/';
if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);

// Clean up old temp files (older than 2 hours)
foreach (glob($tmpDir . '*.zip') ?: [] as $f) {
    if (filemtime($f) < time() - 7200) @unlink($f);
}

$imported = 0; $skipped = 0; $failed = 0; $done = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ── STEP 1: Upload ZIP → save to server ──────────────────────────────────
    if ($action === 'upload' && !empty($_FILES['zipfile']['name'])) {
        if ($_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Upload failed (error ' . $_FILES['zipfile']['error'] . '). The file may be too large — check that it is under 100 MB.');
            redirect('admin/import-wp.php');
        }
        if (!class_exists('ZipArchive')) {
            flash('error', 'ZipArchive PHP extension is not available on this server. Contact Hostinger support to enable php_zip.');
            redirect('admin/import-wp.php');
        }
        $id      = bin2hex(random_bytes(8));
        $zipPath = $tmpDir . $id . '.zip';
        if (!move_uploaded_file($_FILES['zipfile']['tmp_name'], $zipPath)) {
            flash('error', 'Could not save uploaded file. Check uploads/ folder permissions.');
            redirect('admin/import-wp.php');
        }
        // Validate ZIP contains the JSON
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true || !$zip->getFromName('wp-recipes.json')) {
            $zip->close(); @unlink($zipPath);
            flash('error', 'Invalid export file — wp-recipes.json not found. Please re-export from the WP Exporter.');
            redirect('admin/import-wp.php');
        }
        $total = count(json_decode($zip->getFromName('wp-recipes.json'), true)['recipes'] ?? []);
        $zip->close();
        flash('success', "ZIP uploaded ✅ — found $total recipes. Click Import to start.");
        redirect('admin/import-wp.php?id=' . urlencode($id));
    }

    // ── STEP 2: Run import from saved ZIP ────────────────────────────────────
    if ($action === 'import') {
        $id      = preg_replace('/[^a-f0-9]/', '', $_POST['import_id'] ?? '');
        $zipPath = $tmpDir . $id . '.zip';
        if (!$id || !file_exists($zipPath)) {
            flash('error', 'Import file not found. Please upload the ZIP again.');
            redirect('admin/import-wp.php');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            flash('error', 'Could not open ZIP file.');
            redirect('admin/import-wp.php');
        }

        $data    = json_decode($zip->getFromName('wp-recipes.json'), true);
        $recipes = $data['recipes'] ?? [];
        $adminId = (int)db()->query("SELECT id FROM users WHERE is_admin=1 ORDER BY id LIMIT 1")->fetchColumn();
        $pdo     = db();

        foreach ($recipes as $r) {
            $title = trim($r['title'] ?? '');
            if (!$title) { $skipped++; continue; }

            // Duplicate check
            $dup = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE title=?");
            $dup->execute([$title]);
            if ($dup->fetchColumn() > 0) { $skipped++; continue; }

            // Image
            $imgPath = null;
            if (!empty($r['image'])) {
                $imgData = $zip->getFromName($r['image']);
                if ($imgData) {
                    $ext     = pathinfo($r['image'], PATHINFO_EXTENSION) ?: 'jpg';
                    $imgName = 'wp_' . ($r['wp_id'] ?? uniqid()) . '.' . $ext;
                    $imgDir  = dirname(__DIR__) . '/uploads/recipes/';
                    if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
                    file_put_contents($imgDir . $imgName, $imgData);
                    $imgPath = 'uploads/recipes/' . $imgName;
                }
            }

            // Taxonomy
            $catId = !empty($r['category']) ? find_or_create('categories', $r['category'], $adminId) : null;
            $cuiId = !empty($r['cuisine'])  ? find_or_create('cuisines',   $r['cuisine'],  $adminId) : null;

            // Slug
            $slug = slugify($title);
            $sc = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE slug LIKE ?");
            $sc->execute([$slug . '%']);
            if ($sc->fetchColumn() > 0) $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);

            $prepTime = (int)($r['prep_time'] ?? 0) ?: null;
            $cookTime = (int)($r['cook_time'] ?? 0) ?: null;
            $story    = trim($r['story'] ?? '');

            try {
                $pdo->beginTransaction();

                $pdo->prepare(
                    "INSERT INTO recipes (user_id,title,slug,image,story,prep_time,cook_time,category_id,cuisine_id,status,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,'pending',?)"
                )->execute([$adminId,$title,$slug,$imgPath,$story?:null,$prepTime,$cookTime,$catId,$cuiId,
                    $r['published_at'] ?? date('Y-m-d H:i:s')]);
                $rid = (int)$pdo->lastInsertId();

                // Ingredients
                foreach (($r['ingredients'] ?? []) as $ord => $line) {
                    $line = trim($line); if (!$line) continue;
                    if (preg_match('/^([\d\/\.\s]+(?:g|kg|ml|l|cup|tbsp|tsp|pcs?|piece|pinch|handful|nos?|scoop|medium|large|small)[s]?\.?)\s+(.+)$/i', $line, $m)) {
                        $qty = trim($m[1]); $name = trim($m[2]);
                    } else { $qty = ''; $name = $line; }
                    $ingId = find_or_create('ingredients', $name, $adminId);
                    if ($ingId) $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id,ingredient_id,quantity,sort_order) VALUES (?,?,?,?)")->execute([$rid,$ingId,$qty?:null,$ord]);
                }

                // Steps
                foreach (($r['steps'] ?? []) as $n => $text) {
                    $text = trim($text); if (!$text) continue;
                    $pdo->prepare("INSERT INTO recipe_steps (recipe_id,step_no,instruction) VALUES (?,?,?)")->execute([$rid,$n+1,$text]);
                }

                $pdo->commit();
                $imported++;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $failed++;
                $errors[] = $title . ': ' . $e->getMessage();
            }
        }

        $zip->close();
        @unlink($zipPath); // clean up ZIP

        $done = true;
    }
}

// Current pending ZIP for step 2
$pendingId   = preg_replace('/[^a-f0-9]/', '', $_GET['id'] ?? '');
$pendingZip  = $pendingId ? $tmpDir . $pendingId . '.zip' : '';
$pendingReady = $pendingZip && file_exists($pendingZip);
$pendingTotal = 0;
if ($pendingReady) {
    $z = new ZipArchive();
    if ($z->open($pendingZip) === true) {
        $j = json_decode($z->getFromName('wp-recipes.json'), true);
        $pendingTotal = count($j['recipes'] ?? []);
        $z->close();
    }
}

$pageTitle = 'Admin · Import WordPress';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section">
  <h1>📥 Import WordPress Recipes</h1>
  <?php admin_nav('import-wp'); ?>

  <?php if ($done): ?>
    <!-- Results -->
    <div class="panel" style="text-align:center;padding:40px">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <h2>Import Complete!</h2>
      <div style="display:flex;gap:20px;justify-content:center;margin:20px 0;flex-wrap:wrap">
        <div class="stat-card"><strong style="color:var(--green-700)"><?= $imported ?></strong><span>Imported</span></div>
        <div class="stat-card"><strong style="color:var(--ink-soft)"><?= $skipped ?></strong><span>Skipped (duplicates)</span></div>
        <div class="stat-card"><strong style="color:var(--danger)"><?= $failed ?></strong><span>Failed</span></div>
      </div>
      <?php if ($errors): ?>
        <details style="text-align:left;margin:16px 0"><summary style="cursor:pointer;color:var(--danger)">View <?= count($errors) ?> errors</summary>
          <pre style="font-size:12px;background:var(--green-50);padding:12px;border-radius:8px;overflow-x:auto;margin-top:8px"><?= e(implode("\n", array_slice($errors,0,20))) ?></pre>
        </details>
      <?php endif; ?>
      <p class="muted">All imported recipes are <strong>Pending</strong>. Bulk approve them below.</p>
      <a href="<?= e(url('admin/content.php?tab=pending')) ?>" class="btn btn-primary" style="margin-top:8px">
        Review &amp; Approve Pending Recipes →
      </a>
    </div>

  <?php elseif ($pendingReady): ?>
    <!-- Step 2: confirm import -->
    <div class="panel">
      <div style="background:var(--green-50);border:1.5px solid var(--green-200);border-radius:12px;padding:18px 22px;margin-bottom:20px">
        <p style="margin:0;color:var(--green-900)">✅ ZIP uploaded successfully — <strong><?= $pendingTotal ?> recipes</strong> ready to import.</p>
      </div>
      <div style="padding:14px 18px;background:var(--orange-100);border-radius:10px;margin-bottom:20px">
        <strong>⚠️ This will:</strong>
        <ul style="margin:8px 0 0;padding-left:20px;line-height:2;font-size:14px">
          <li>Import <strong><?= $pendingTotal ?> recipes</strong> as Pending (hidden from public until approved)</li>
          <li>Skip recipes already in the database (title match)</li>
          <li>Set all creators to Admin account</li>
          <li>May take 2–5 minutes — <strong>do not close the page</strong></li>
        </ul>
      </div>
      <form method="post" onsubmit="this.querySelector('button').textContent='Importing… please wait (do not close) ⏳';this.querySelector('button').disabled=true">
        <?= csrf_field() ?>
        <input type="hidden" name="action"    value="import">
        <input type="hidden" name="import_id" value="<?= e($pendingId) ?>">
        <button class="btn btn-primary btn-lg" type="submit">🚀 Start Import (<?= $pendingTotal ?> recipes)</button>
        <a href="admin/import-wp.php" class="btn btn-ghost btn-lg" style="margin-left:10px">Cancel</a>
      </form>
    </div>

  <?php else: ?>
    <!-- Step 1: Upload -->
    <div class="panel" style="max-width:600px">
      <h3>Step 1 — Export from your local WordPress</h3>
      <ol style="line-height:2.2;color:var(--ink-soft)">
        <li>Copy <code>wp-exporter.php</code> to <code>C:\xampp\htdocs\boskets-old\</code></li>
        <li>Visit <a href="http://localhost/boskets-old/wp-exporter.php" target="_blank">http://localhost/boskets-old/wp-exporter.php</a></li>
        <li>Click <strong>Start Export</strong> → download <strong>wp-boskets-export.zip</strong></li>
      </ol>

      <h3 style="margin-top:24px">Step 2 — Upload the ZIP</h3>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <label class="field">Select wp-boskets-export.zip
          <input type="file" name="zipfile" accept=".zip" required>
          <small style="color:var(--ink-soft)">Max size: determined by server settings (usually 64–128 MB)</small>
        </label>
        <button class="btn btn-primary" type="submit" style="margin-top:14px"
                onclick="this.textContent='Uploading… please wait ⏳';this.disabled=true;this.form.submit()">
          Upload ZIP
        </button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
