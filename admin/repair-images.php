<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
@set_time_limit(300);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Repair Images</title>
<style>
body{font-family:Arial,sans-serif;padding:30px;background:#111;color:#ddd}
h1{color:#3fa796}
.btn{background:#3fa796;color:#fff;border:0;padding:12px 28px;font-size:15px;border-radius:6px;cursor:pointer}
table{width:100%;border-collapse:collapse;margin-top:20px;font-size:14px}
th,td{padding:8px 12px;border-bottom:1px solid #333;text-align:left}
th{background:#1a2e2b}
.ok{color:#888}.fixed{color:#3fa796}.miss{color:#c8a020}.fail{color:#c0392b}
.summary{background:#1a2e2b;padding:16px 20px;border-radius:8px;margin:16px 0;font-size:15px}
a{color:#3fa796}
</style>
</head>
<body>
<h1>Repair Recipe Images</h1>
<p>Re-downloads missing images from <strong>bosketsalimentos.blogspot.com</strong> by matching post titles to your recipes.</p>
<p><a href="<?= e(url('admin/index.php')) ?>">&larr; Back to Admin</a></p>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_repair'] ?? '') === '1'):

    // Fetch Blogspot feed
    $posts = [];
    $si    = 1;
    $base  = 'https://bosketsalimentos.blogspot.com/feeds/posts/default';
    $err   = '';

    do {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: PHP\r\n"]]);
        $raw = @file_get_contents($base . '?alt=json&max-results=50&start-index=' . $si, false, $ctx);
        if (!$raw) { $err = 'Cannot reach Blogspot.'; break; }
        $d = json_decode($raw, true);
        if (!$d)   { $err = 'Invalid JSON from feed.'; break; }
        foreach ($d['feed']['entry'] ?? [] as $e) {
            $html = $e['content']['$t'] ?? '';
            $img  = '';
            if (preg_match('#href="(https://(?:blogger|[0-9]+)\.googleusercontent\.com/[^"]+)"[^>]*>\s*<img#i', $html, $m)) {
                $img = preg_replace('#/s\d+(-[a-z]+)?/#', '/s0/', $m[1]);
            } elseif (!empty($e['media$thumbnail']['url'])) {
                $img = preg_replace('#/s\d+(-[a-z]+)?/#', '/s0/', $e['media$thumbnail']['url']);
            }
            if ($img && isset($e['title']['$t'])) {
                $posts[] = ['title' => trim($e['title']['$t']), 'img' => $img];
            }
        }
        $total = (int)($d['feed']['openSearch$totalResults']['$t'] ?? 0);
        $si   += 50;
    } while (!$err && count($posts) < $total && !empty($d['feed']['entry'] ?? []));

    if ($err) {
        echo '<p class="fail"><strong>Error:</strong> ' . e($err) . '</p>';
    } else {
        // Build lookup map
        $map = [];
        foreach ($posts as $p) {
            $map[mb_strtolower($p['title'])] = $p['img'];
        }

        $rows    = db()->query("SELECT id, title, image FROM recipes ORDER BY id")->fetchAll();
        $fixed   = 0;
        $skipped = 0;
        $failed  = 0;
        $miss    = 0;
        $log     = [];

        foreach ($rows as $r) {
            $key  = mb_strtolower(trim($r['title']));
            $file = $r['image'] ? dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $r['image']) : '';

            if ($file && file_exists($file)) {
                $skipped++;
                $log[] = ['ok', $r['title'], 'Already on disk'];
                continue;
            }

            if (!isset($map[$key])) {
                $miss++;
                $log[] = ['miss', $r['title'], 'Not in Blogspot feed'];
                continue;
            }

            // Download
            $imgUrl = $map[$key];
            $ext = 'jpg';
            if (preg_match('/\.(png|webp|gif|jpeg|jpg)(?:[?#]|$)/i', $imgUrl, $mx)) {
                $ext = strtolower($mx[1] === 'jpeg' ? 'jpg' : $mx[1]);
            }
            $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $slug = preg_replace('/[^a-z0-9]+/', '-', $key);
            $fname = $slug . '-' . substr(md5($imgUrl), 0, 6) . '.' . $ext;

            $ch = curl_init($imgUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_USERAGENT      => 'Mozilla/5.0',
            ]);
            $bytes = curl_exec($ch);
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && $bytes) {
                file_put_contents($dir . $fname, $bytes);
                $newPath = 'uploads/recipes/' . $fname;
                db()->prepare("UPDATE recipes SET image=? WHERE id=?")->execute([$newPath, $r['id']]);
                $fixed++;
                $log[] = ['fixed', $r['title'], 'Downloaded OK'];
            } else {
                $failed++;
                $log[] = ['fail', $r['title'], 'HTTP ' . $code];
            }
        }

        echo '<div class="summary">Done &mdash; <strong>' . $fixed . '</strong> fixed &nbsp;|&nbsp; <strong>' . $skipped . '</strong> already OK &nbsp;|&nbsp; <strong>' . $miss . '</strong> not in feed &nbsp;|&nbsp; <strong>' . $failed . '</strong> failed</div>';
        echo '<table><thead><tr><th></th><th>Recipe</th><th>Note</th></tr></thead><tbody>';
        foreach ($log as $row) {
            $icon = $row[0] === 'fixed' ? '&#x2705;' : ($row[0] === 'ok' ? '&#x2611;' : ($row[0] === 'miss' ? '&#x2753;' : '&#x274C;'));
            echo '<tr class="' . e($row[0]) . '"><td>' . $icon . '</td><td>' . e($row[1]) . '</td><td>' . e($row[2]) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

else:
?>
<form method="post" style="margin-top:24px">
  <input type="hidden" name="_repair" value="1">
  <button class="btn" type="submit">&#x1F527; Start Image Repair</button>
  <p style="margin-top:10px;color:#888;font-size:13px">Takes 1&ndash;2 minutes. Do not close the page.</p>
</form>
<?php endif; ?>
</body>
</html>
