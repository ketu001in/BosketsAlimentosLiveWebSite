<?php
require_once __DIR__ . '/_admin.php';
@set_time_limit(300);

$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'repair') {
    csrf_verify();

    // --- fetch all posts from Blogspot ---
    $posts      = [];
    $startIndex = 1;
    $base       = 'https://bosketsalimentos.blogspot.com/feeds/posts/default';
    $fetchError = '';
    do {
        $url = $base . '?alt=json&max-results=50&start-index=' . $startIndex;
        $ctx = stream_context_create(['http' => ['timeout' => 25, 'header' => "User-Agent: PHP/Boskets\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) { $fetchError = 'Cannot reach Blogspot feed.'; break; }
        $data = json_decode($raw, true);
        if (!$data)          { $fetchError = 'Blogspot feed returned invalid JSON.'; break; }
        $entries = $data['feed']['entry'] ?? [];
        foreach ($entries as $e) {
            $html = $e['content']['$t'] ?? '';
            $img  = '';
            if (preg_match('#href="(https://(?:blogger|[0-9]+)\.googleusercontent\.com/[^"]+)"[^>]*>\s*<img#i', $html, $m)) {
                $img = preg_replace('#/s\d+(-[a-z]+)?/#', '/s0/', $m[1]);
            } elseif (!empty($e['media$thumbnail']['url'])) {
                $img = preg_replace('#/s\d+(-[a-z]+)?/#', '/s0/', $e['media$thumbnail']['url']);
            }
            $posts[] = ['title' => trim($e['title']['$t'] ?? ''), 'img' => $img];
        }
        $total      = (int)($data['feed']['openSearch$totalResults']['$t'] ?? 0);
        $startIndex += 50;
    } while (count($posts) < $total && !empty($entries));

    if ($fetchError) {
        $results = ['error' => $fetchError];
    } else {
        // title → img map
        $map = [];
        foreach ($posts as $p) {
            if ($p['img'] && $p['title']) $map[mb_strtolower($p['title'])] = $p['img'];
        }

        $recipes = db()->query("SELECT id, title, image FROM recipes ORDER BY id")->fetchAll();
        $fixed = 0; $skipped = 0; $failed = 0; $notFound = 0; $log = [];

        foreach ($recipes as $rec) {
            $key  = mb_strtolower(trim($rec['title']));
            $disk = $rec['image'] ? dirname(__DIR__) . '/' . $rec['image'] : '';

            if ($disk && file_exists($disk)) {
                $skipped++;
                $log[] = ['ok', $rec['title'], 'Already on disk'];
                continue;
            }
            if (!isset($map[$key])) {
                $notFound++;
                $log[] = ['miss', $rec['title'], 'Not found in Blogspot feed'];
                continue;
            }

            // download image
            $imgUrl = $map[$key];
            $ext    = 'jpg';
            if (preg_match('/\.(png|webp|gif|jpeg|jpg)(?:[?#]|$)/i', $imgUrl, $mx)) {
                $ext = strtolower($mx[1] === 'jpeg' ? 'jpg' : $mx[1]);
            }
            $dir  = dirname(__DIR__) . '/uploads/recipes/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $slug = preg_replace('/[^a-z0-9]+/', '-', $key);
            $file = $slug . '-' . substr(md5($imgUrl), 0, 6) . '.' . $ext;
            $dest = $dir . $file;

            $ch = curl_init($imgUrl);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 30,
                                    CURLOPT_USERAGENT => 'Mozilla/5.0 Boskets/1.0']);
            $bytes = curl_exec($ch);
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && $bytes) {
                file_put_contents($dest, $bytes);
                $newPath = 'uploads/recipes/' . $file;
                db()->prepare("UPDATE recipes SET image = ? WHERE id = ?")->execute([$newPath, $rec['id']]);
                $fixed++;
                $log[] = ['fixed', $rec['title'], 'Re-downloaded'];
            } else {
                $failed++;
                $log[] = ['fail', $rec['title'], 'Download failed (HTTP ' . $code . ')'];
            }
        }
        $results = compact('fixed', 'skipped', 'failed', 'notFound', 'log');
    }
}

$pageTitle = 'Repair Images';
include __DIR__ . '/../includes/header.php';
?>
<div class="container section">
<?php admin_nav('repair'); ?>
<h2>Repair Recipe Images</h2>
<p style="color:var(--text-muted);margin-bottom:20px">
  Re-downloads missing recipe images from bosketsalimentos.blogspot.com by matching post titles to DB recipes.
</p>

<?php if ($results !== null && isset($results['error'])): ?>
  <div class="flash flash-error"><?= e($results['error']) ?></div>

<?php elseif ($results !== null): ?>
  <div class="flash flash-success">
    Done: <strong><?= (int)$results['fixed'] ?></strong> fixed,
    <strong><?= (int)$results['skipped'] ?></strong> OK,
    <strong><?= (int)$results['notFound'] ?></strong> not in feed,
    <strong><?= (int)$results['failed'] ?></strong> failed.
  </div>
  <table style="width:100%;border-collapse:collapse;margin-top:16px;font-size:14px">
    <thead><tr style="border-bottom:2px solid var(--line);text-align:left">
      <th style="padding:7px 10px"></th><th style="padding:7px 10px">Recipe</th><th style="padding:7px 10px">Note</th>
    </tr></thead>
    <tbody>
    <?php foreach ($results['log'] as $row):
      $icon  = $row[0]==='fixed' ? '&#x2705;' : ($row[0]==='ok' ? '&#x2611;' : ($row[0]==='miss' ? '&#x2753;' : '&#x274C;'));
      $style = $row[0]==='ok' ? 'opacity:.5' : ($row[0]==='miss' ? 'color:#b07030' : ($row[0]==='fail' ? 'color:#c0392b' : ''));
    ?>
      <tr style="border-bottom:1px solid var(--line);<?= $style ?>">
        <td style="padding:6px 10px;text-align:center"><?= $icon ?></td>
        <td style="padding:6px 10px"><?= e($row[1]) ?></td>
        <td style="padding:6px 10px;font-size:12px"><?= e($row[2]) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

<?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="repair">
    <button class="btn btn-primary" type="submit">&#x1F527; Start Image Repair</button>
    <p style="margin-top:8px;color:var(--text-muted);font-size:13px">Takes 1-2 minutes. Do not close the page.</p>
  </form>
<?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
