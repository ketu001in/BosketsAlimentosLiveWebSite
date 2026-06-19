<?php
/**
 * admin/repair-images.php
 * Re-download recipe images from bosketsalimentos.blogspot.com for any
 * recipe whose image file is missing from uploads/recipes/.
 */
require_once __DIR__ . '/_admin.php';
@set_time_limit(300);

define('REPAIR_BLOG_URL', 'https://bosketsalimentos.blogspot.com');

// ── Fetch all posts from Blogspot JSON feed ───────────────────────────────────
function repair_fetch_all()
{
    $posts      = [];
    $startIndex = 1;
    $batchSize  = 50;
    $base       = REPAIR_BLOG_URL . '/feeds/posts/default';
    do {
        $url = $base . '?alt=json&max-results=' . $batchSize . '&start-index=' . $startIndex;
        $ctx = stream_context_create(['http' => [
            'timeout' => 25,
            'header'  => "User-Agent: PHP/Boskets-Repair\r\n",
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return 'Could not reach the Blogspot feed.';
        $data = json_decode($raw, true);
        if (!$data) return 'Feed returned invalid JSON.';

        $entries = $data['feed']['entry'] ?? [];
        foreach ($entries as $e) {
            $html   = $e['content']['$t'] ?? '';
            $imgUrl = '';
            if (preg_match(
                '#href="(https://(?:blogger|[0-9]+)\.googleusercontent\.com/[^"]+)"[^>]*>\s*<img#i',
                $html, $m
            )) {
                $imgUrl = preg_replace('#/s\d+(-[a-z]+)?/#', '/s0/', $m[1]);
            } elseif (!empty($e['media$thumbnail']['url'])) {
                $imgUrl = preg_replace('#/s\d+(-[a-z]+)?/#', '/s0/', $e['media$thumbnail']['url']);
            }
            $posts[] = [
                'title'   => trim($e['title']['$t'] ?? ''),
                'img_url' => $imgUrl,
            ];
        }

        $total      = (int)($data['feed']['openSearch$totalResults']['$t'] ?? 0);
        $startIndex += $batchSize;
    } while (count($posts) < $total && !empty($entries));

    return $posts;
}

// ── Download one image → uploads/recipes/ ────────────────────────────────────
function repair_download($url, $slug)
{
    if (!$url) return null;
    $ext = 'jpg';
    if (preg_match('/\.(png|webp|gif|jpeg|jpg)(?:[?#]|$)/i', $url, $m)) {
        $ext = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    }
    $dir  = dirname(__DIR__) . '/uploads/recipes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $slug . '-' . substr(md5($url), 0, 6) . '.' . $ext;
    $dest = $dir . $file;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 Boskets-Repair/1.0',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $data) {
        file_put_contents($dest, $data);
        return 'uploads/recipes/' . $file;
    }
    return null;
}

// ── Run repair ────────────────────────────────────────────────────────────────
$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'repair') {
    csrf_verify();

    $posts = repair_fetch_all();
    if (is_string($posts)) {
        $results = ['error' => $posts];
    } else {
        // title (lowercase) → img_url
        $blogMap = [];
        foreach ($posts as $p) {
            if ($p['img_url'] && $p['title']) {
                $blogMap[mb_strtolower($p['title'])] = $p['img_url'];
            }
        }

        $recipes = db()->query("SELECT id, title, image FROM recipes ORDER BY id")->fetchAll();

        $fixed = 0; $skipped = 0; $failed = 0; $notFound = 0;
        $log   = [];

        foreach ($recipes as $rec) {
            $key      = mb_strtolower(trim($rec['title']));
            $filePath = $rec['image'] ? dirname(__DIR__) . '/' . $rec['image'] : '';

            if ($filePath && file_exists($filePath)) {
                $skipped++;
                $log[] = ['s' => 'ok', 'title' => $rec['title'], 'msg' => 'Already on disk'];
                continue;
            }

            if (!isset($blogMap[$key])) {
                $notFound++;
                $log[] = ['s' => 'miss', 'title' => $rec['title'], 'msg' => 'Not found in Blogspot feed'];
                continue;
            }

            $slug    = preg_replace('/[^a-z0-9]+/', '-', $key);
            $newPath = repair_download($blogMap[$key], $slug);

            if ($newPath) {
                db()->prepare("UPDATE recipes SET image = ? WHERE id = ?")
                   ->execute([$newPath, $rec['id']]);
                $fixed++;
                $log[] = ['s' => 'fixed', 'title' => $rec['title'], 'msg' => 'Re-downloaded'];
            } else {
                $failed++;
                $log[] = ['s' => 'fail', 'title' => $rec['title'], 'msg' => 'Download failed'];
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
<h2 style="margin-bottom:6px">Repair Recipe Images</h2>
<p style="color:var(--text-muted);margin-bottom:24px">
  Fetches <strong>bosketsalimentos.blogspot.com</strong>, matches posts to recipes by title,
  and re-downloads every image whose file is missing from <code>uploads/recipes/</code>.
</p>

<?php if ($results !== null && isset($results['error'])): ?>
  <div class="flash flash-error"><?= e($results['error']) ?></div>

<?php elseif ($results !== null): ?>
  <div class="flash flash-success">
    Done &mdash;
    <strong><?= (int)$results['fixed'] ?></strong> fixed &nbsp;&middot;&nbsp;
    <strong><?= (int)$results['skipped'] ?></strong> already OK &nbsp;&middot;&nbsp;
    <strong><?= (int)$results['notFound'] ?></strong> not in feed &nbsp;&middot;&nbsp;
    <strong><?= (int)$results['failed'] ?></strong> failed
  </div>
  <table style="width:100%;border-collapse:collapse;margin-top:18px;font-size:14px">
    <thead>
      <tr style="border-bottom:2px solid var(--line);text-align:left">
        <th style="padding:8px 10px"></th>
        <th style="padding:8px 10px">Recipe</th>
        <th style="padding:8px 10px">Note</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($results['log'] as $l):
        $s = $l['s'];
        $icon  = ($s === 'fixed') ? '&#x2705;' : (($s === 'ok') ? '&#x2611;' : (($s === 'miss') ? '&#x2753;' : '&#x274C;'));
        $color = ($s === 'ok') ? 'color:var(--text-muted)' : (($s === 'miss') ? 'color:#b07030' : (($s === 'fail') ? 'color:#c0392b' : ''));
    ?>
      <tr style="border-bottom:1px solid var(--line);<?= $color ?>">
        <td style="padding:7px 10px;text-align:center"><?= $icon ?></td>
        <td style="padding:7px 10px"><?= e($l['title']) ?></td>
        <td style="padding:7px 10px;font-size:12px;opacity:.8"><?= e($l['msg']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

<?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="repair">
    <button class="btn btn-primary" type="submit">&#x1F527; Start Image Repair</button>
    <p style="margin-top:10px;color:var(--text-muted);font-size:13px">
      May take 1&ndash;2 minutes. Do not close the page until results appear.
    </p>
  </form>
<?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
