<?php
/**
 * admin/import-blogspot.php
 * Bulk-import recipes from a public Blogspot JSON feed.
 * Flow: Fetch all posts → Select → Review one at a time → Save as draft.
 */
require_once __DIR__ . '/_admin.php';

// ─── Label → taxonomy suggestion maps ───────────────────────────────────────
const IMP_CAT = [
    'breakfast recipes'=>'Breakfast','breakfast'=>'Breakfast',
    'brunch recipes'=>'Brunch','brunch'=>'Brunch',
    'main course recipes'=>'Main Course','main course'=>'Main Course','dinner'=>'Main Course',
    'side dishes'=>'Side Dish',
    'starters'=>'Starter','appetiser'=>'Starter',
    'sweets'=>'Sweets','peda'=>'Sweets','kulfi'=>'Sweets',
    'baking'=>'Baking','cookies'=>'Baking','cake'=>'Baking','cupcake'=>'Baking',
    'biscuits'=>'Baking','donuts'=>'Baking','sponge cake'=>'Baking',
    'bread recipes'=>'Bread','bun'=>'Bread',
    'pan cakes'=>'Pancakes','pan cake'=>'Pancakes',
    'rice recipes'=>'Rice','biriyani'=>'Rice','khichadi recipes'=>'Rice',
    'soups recipes'=>'Soup',
    'sandwich recipes'=>'Sandwich',
    'pizza recipes'=>'Pizza',
    'salad'=>'Salad','salads'=>'Salad',
    'mocktails'=>'Beverage','juices'=>'Beverage','shakes'=>'Beverage',
    'juice'=>'Beverage','smoothies'=>'Beverage','coolers'=>'Beverage',
    'drink'=>'Beverage','coffee'=>'Beverage','cold coffee'=>'Beverage',
    'shots'=>'Beverage','batido'=>'Beverage','refreshments'=>'Beverage',
    'snacks.'=>'Snack','quick bites'=>'Snack','street food'=>'Snack',
    'indian chaats'=>'Snack','bitings'=>'Snack','quick recipe'=>'Snack',
    'curries'=>'Curry','kadhi'=>'Curry',
    'chutney recipies'=>'Chutney',
    'sauces'=>'Sauce',
    'microwave recipes'=>'Microwave',
    'energy bar'=>'Snack',
    'hi-tea recipes'=>'Hi-Tea','hightea recipies'=>'Hi-Tea','high tea'=>'Hi-Tea',
];

const IMP_CUI = [
    'italian recipes'=>'Italian',
    'chinese recipes'=>'Chinese',
    'mexican recipes'=>'Mexican','maxican recipes'=>'Mexican',
    'mughlai recipes'=>'Mughlai',
    'international recipes'=>'International',
    'fusion dishes'=>'Fusion',
    'south indian recipes'=>'South Indian',
    'gujarati recipes'=>'Gujarati',
    'rajasthani recipes'=>'Rajasthani',
    'punjabi food'=>'Punjabi',
];

const IMP_ORI = [
    'gujarati recipes'=>'Gujarat','gujarati dish'=>'Gujarat',
    'south indian recipes'=>'South India','south indian'=>'South India',
    'punjabi food'=>'Punjab',
    'rajasthani recipes'=>'Rajasthan',
    'italian recipes'=>'Italy',
    'chinese recipes'=>'China',
    'mexican recipes'=>'Mexico','maxican recipes'=>'Mexico',
    'mughlai recipes'=>'Delhi',
];

// ─── Helper: fetch all posts from Blogger JSON feed ──────────────────────────
function imp_fetch_all(string $blogUrl): array|string
{
    $posts      = [];
    $startIndex = 1;
    $batchSize  = 50;
    $base       = rtrim($blogUrl, '/') . '/feeds/posts/default';
    do {
        $url = $base . '?alt=json&max-results=' . $batchSize . '&start-index=' . $startIndex;
        $ctx = stream_context_create(['http' => [
            'timeout' => 25,
            'header'  => "User-Agent: PHP/Boskets-Importer\r\n",
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return 'Could not reach the feed URL. Check the blog address.';
        $data = json_decode($raw, true);
        if (!$data) return 'Feed returned invalid JSON.';

        $entries = $data['feed']['entry'] ?? [];
        foreach ($entries as $e) {
            // Full-size image: prefer the <a href> pointing to blogger CDN inside the content
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

            $postUrl = '';
            foreach ($e['link'] ?? [] as $l) {
                if ($l['rel'] === 'alternate') { $postUrl = $l['href']; break; }
            }

            $posts[] = [
                'id'        => $e['id']['$t'] ?? '',
                'title'     => $e['title']['$t'] ?? '(untitled)',
                'published' => substr($e['published']['$t'] ?? '', 0, 10),
                'labels'    => array_column($e['category'] ?? [], 'term'),
                'img_url'   => $imgUrl,
                'post_url'  => $postUrl,
                'html'      => $html,
            ];
        }

        $total      = (int)($data['feed']['openSearch$totalResults']['$t'] ?? 0);
        $startIndex += $batchSize;
    } while (count($posts) < $total && !empty($entries));

    return $posts;
}

// ─── Helper: parse HTML content into structured fields ───────────────────────
function imp_parse_html(string $html): array
{
    // Flatten block tags → newlines, strip markup
    $flat = preg_replace(['#<br\s*/?\s*>#i', '#</(div|p|li|tr)>#i'], "\n", $html);
    $flat = strip_tags($flat);
    $flat = html_entity_decode($flat, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $flat = preg_replace('/\xc2\xa0/', ' ', $flat);    // non-breaking space
    $flat = preg_replace('/\xef\xbb\xbf/', '', $flat); // BOM
    $flat = preg_replace('/[^\S\n]+/', ' ', $flat);    // collapse inline whitespace

    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $flat)),
        fn($l) => $l !== '' && !preg_match('/^(ENJOY\s*!*|﻿)$/i', $l)
    ));

    $section  = 'pre';
    $story    = '';
    $prepTime = '';
    $ings     = [];
    $steps    = [];

    foreach ($lines as $line) {
        $lower = strtolower($line);

        // Section markers
        if ($lower === 'ingredients' || $lower === 'ingredients:') { $section = 'ingredients'; continue; }
        if ($lower === 'method'      || $lower === 'method:')      { $section = 'method';      continue; }
        // Also handle "Preparations:" as a standalone line
        if (preg_match('/^Preparations?\s*:\s*(.+)/i', $line, $m)) {
            $prepTime = trim($m[1]);
            continue;
        }

        if ($section === 'pre') {
            // Skip the post title (likely repeated as first line), grab next meaningful line as story
            if ($story === '' && mb_strlen($line) > 10 && mb_strlen($line) < 400) {
                $story = $line;
            }
            continue;
        }

        if ($section === 'ingredients') {
            // Split "Name: Qty" — split on LAST colon to handle "Cereals (A, B): 1/4 Cup"
            if (preg_match('/^(.+?)\s*:\s*([^:]+)$/', $line, $m)) {
                $ings[] = ['name' => trim($m[1]), 'qty' => trim($m[2])];
            } elseif (strlen($line) > 1) {
                $ings[] = ['name' => $line, 'qty' => ''];
            }
            continue;
        }

        if ($section === 'method' && mb_strlen($line) > 3) {
            $steps[] = $line;
        }
    }

    return compact('story', 'prepTime', 'ings', 'steps');
}

// ─── Helper: map blog labels to taxonomy suggestions ─────────────────────────
function imp_suggest(array $labels): array
{
    $cat = $cui = $ori = null;
    foreach ($labels as $lbl) {
        $k = strtolower(trim($lbl));
        if (!$cat && isset(IMP_CAT[$k])) $cat = IMP_CAT[$k];
        if (!$cui && isset(IMP_CUI[$k])) $cui = IMP_CUI[$k];
        if (!$ori && isset(IMP_ORI[$k])) $ori = IMP_ORI[$k];
    }
    return ['category' => $cat, 'cuisine' => $cui, 'origin' => $ori];
}

// ─── Helper: download image from Blogger CDN → uploads/recipes/ ──────────────
function imp_download_image(string $url, string $slug): ?string
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
    if (file_exists($dest)) return 'uploads/recipes/' . $file; // already fetched

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 Boskets-Import/1.0',
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

// ─── Session shorthand ────────────────────────────────────────────────────────
function imps(string $k, mixed $default = null): mixed { return $_SESSION['_imp'][$k] ?? $default; }
function impss(string $k, mixed $v): void              { $_SESSION['_imp'][$k] = $v; }

// ─── Routing ──────────────────────────────────────────────────────────────────
$step    = $_GET['step']    ?? 'start';
$action  = $_POST['action'] ?? '';
$err     = '';

// -- FETCH: pull all posts from Blogger feed --
if ($action === 'fetch') {
    $blogUrl = trim($_POST['blog_url'] ?? '');
    if (!$blogUrl) {
        $err = 'Please enter a blog URL.';
    } else {
        $result = imp_fetch_all($blogUrl);
        if (is_string($result)) {
            $err = $result;
        } else {
            impss('posts',   $result);
            impss('blog_url', $blogUrl);
            $step = 'select';
        }
    }
    if ($err) $step = 'start';
}

// -- QUEUE: store selected post indices, start review --
if ($action === 'queue') {
    $sel = array_map('intval', $_POST['sel'] ?? []);
    if (empty($sel)) {
        $err  = 'Select at least one recipe to import.';
        $step = 'select';
    } else {
        impss('queue',   $sel);
        impss('cursor',  0);
        impss('saved',   0);
        impss('skipped', 0);
        impss('last_msg', null);
        header('Location: ?step=review'); exit;
    }
}

// -- SAVE / SKIP: process one reviewed recipe then advance --
if ($action === 'save' || $action === 'skip') {
    $cursor = imps('cursor', 0);
    $queue  = imps('queue',  []);

    if ($action === 'save') {
        $pdo   = db();
        $uid   = (int)$admin['id'];
        $title = trim($_POST['title'] ?? '');

        // Duplicate check by title
        $dup = $pdo->prepare('SELECT id FROM recipes WHERE title = ? LIMIT 1');
        $dup->execute([$title]);
        if ($dup->fetchColumn()) {
            impss('last_msg', "⏭ Skipped (duplicate title): {$title}");
            impss('skipped', imps('skipped', 0) + 1);
        } else {
            // Unique slug
            $slug = slugify($title);
            $st   = $pdo->prepare('SELECT COUNT(*) FROM recipes WHERE slug LIKE ?');
            $st->execute([$slug . '%']);
            if ((int)$st->fetchColumn() > 0) $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 5);

            // Image
            $imgPath = imp_download_image(trim($_POST['img_url'] ?? ''), $slug);

            // Taxonomy — find or create
            $catName = trim($_POST['category_name'] ?? '');
            $cuiName = trim($_POST['cuisine_name']  ?? '');
            $oriName = trim($_POST['origin_name']   ?? '');
            $catId   = $catName ? find_or_create('categories', $catName, $uid) : null;
            $cuiId   = $cuiName ? find_or_create('cuisines',   $cuiName, $uid) : null;
            $oriId   = $oriName ? find_or_create('origins',    $oriName, $uid) : null;

            // Insert recipe
            $pdo->prepare(
                "INSERT INTO recipes (user_id, title, slug, image, story, youtube_url,
                                      category_id, cuisine_id, origin_id, verdict, status, created_at)
                 VALUES (?, ?, ?, ?, ?, '', ?, ?, ?, NULL, 'draft', NOW())"
            )->execute([$uid, $title, $slug, $imgPath,
                        trim($_POST['story'] ?? '') ?: null,
                        $catId, $cuiId, $oriId]);
            $recipeId = (int)$pdo->lastInsertId();

            // Ingredients
            $ingNames = $_POST['ing_name'] ?? [];
            $ingQtys  = $_POST['ing_qty']  ?? [];
            $insIng   = $pdo->prepare(
                'INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, sort_order) VALUES (?, ?, ?, ?)'
            );
            $seen = [];
            foreach ($ingNames as $i => $name) {
                $name = trim($name);
                if (!$name) continue;
                $ingId = find_or_create('ingredients', $name, $uid);
                if ($ingId && !isset($seen[$ingId])) {
                    $seen[$ingId] = true;
                    $insIng->execute([$recipeId, $ingId, mb_substr(trim($ingQtys[$i] ?? ''), 0, 80), $i]);
                }
            }

            // Steps
            $insStep = $pdo->prepare(
                'INSERT INTO recipe_steps (recipe_id, step_no, instruction, media, media_type) VALUES (?, ?, ?, NULL, NULL)'
            );
            $stepNo = 1;
            foreach ($_POST['step_text'] ?? [] as $instruction) {
                $instruction = trim($instruction);
                if ($instruction) { $insStep->execute([$recipeId, $stepNo++, $instruction]); }
            }

            impss('last_msg', "✅ Saved as draft: {$title}");
            impss('saved', imps('saved', 0) + 1);
        }
    } else {
        impss('last_msg', '⏭ Skipped.');
        impss('skipped', imps('skipped', 0) + 1);
    }

    $cursor++;
    impss('cursor', $cursor);
    header('Location: ' . ($cursor >= count($queue) ? '?step=done' : '?step=review'));
    exit;
}

// ─── HTML output ─────────────────────────────────────────────────────────────
$pageTitle = 'Import from Blogspot';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="container section" style="max-width:900px">
  <h1>Import Recipes from Blogspot</h1>
  <p style="color:var(--ink-soft);margin-bottom:28px">
    Fetches your public blog's JSON feed → auto-parses ingredients &amp; steps → you review &amp; correct → saves as <strong>draft</strong>.
  </p>
  <?php admin_nav('import'); ?>

<?php if ($step === 'start'): ?>
<!-- ══ SCREEN 1: blog URL ══════════════════════════════════════════════════ -->
  <form method="post" style="max-width:560px;margin-top:28px">
    <input type="hidden" name="action" value="fetch">
    <label class="field">
      Blogspot blog URL
      <input type="url" name="blog_url" required
             placeholder="https://your-blog.blogspot.com"
             value="<?= e(imps('blog_url', 'https://bosketsalimentos.blogspot.com')) ?>">
    </label>
    <?php if ($err): ?><p class="flash flash-error"><?= e($err) ?></p><?php endif; ?>
    <button type="submit" class="btn btn-primary">Fetch all posts →</button>
  </form>

<?php elseif ($step === 'select'): ?>
<!-- ══ SCREEN 2: post selection ════════════════════════════════════════════ -->
<?php $posts = imps('posts', []); ?>
  <form method="post" style="margin-top:28px">
    <input type="hidden" name="action" value="queue">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
      <strong><?= count($posts) ?> posts found</strong>
      <button type="button" class="btn btn-ghost btn-sm"
              onclick="document.querySelectorAll('.icb').forEach(c=>c.checked=true)">Select all</button>
      <button type="button" class="btn btn-ghost btn-sm"
              onclick="document.querySelectorAll('.icb').forEach(c=>c.checked=false)">Deselect all</button>
    </div>
    <?php if ($err): ?><p class="flash flash-error"><?= e($err) ?></p><?php endif; ?>
    <table class="data" style="width:100%">
      <thead><tr><th></th><th>Title</th><th>Date</th><th>Blog labels</th></tr></thead>
      <tbody>
      <?php foreach ($posts as $i => $p): ?>
        <tr>
          <td><input type="checkbox" class="icb" name="sel[]" value="<?= $i ?>" checked></td>
          <td><?= e($p['title']) ?></td>
          <td style="white-space:nowrap;font-size:13px"><?= e($p['published']) ?></td>
          <td style="font-size:11px;color:var(--ink-soft)"><?= e(implode(', ', array_slice($p['labels'], 0, 5))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-top:20px">
      <button type="submit" class="btn btn-primary">Review &amp; import selected →</button>
      <a href="?step=start" class="btn btn-ghost">← Change URL</a>
    </div>
  </form>

<?php elseif ($step === 'review'): ?>
<!-- ══ SCREEN 3: review one recipe ════════════════════════════════════════ -->
<?php
$queue  = imps('queue',  []);
$cursor = imps('cursor', 0);
$posts  = imps('posts',  []);
$total  = count($queue);
if ($cursor >= $total) { header('Location: ?step=done'); exit; }

$p      = $posts[$queue[$cursor]];
$parsed = imp_parse_html($p['html']);
$tax    = imp_suggest($p['labels']);

$cats = db()->query('SELECT name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$cuis = db()->query('SELECT name FROM cuisines   ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$oris = db()->query('SELECT name FROM origins    ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$lastMsg = imps('last_msg');
impss('last_msg', null);
?>
<?php if ($lastMsg): ?><div class="flash flash-success" style="margin-bottom:16px"><?= e($lastMsg) ?></div><?php endif; ?>

<!-- Progress bar -->
<div style="display:flex;align-items:center;gap:14px;margin:16px 0 24px">
  <span style="font-size:13px;color:var(--ink-soft);white-space:nowrap">
    <?= $cursor + 1 ?> / <?= $total ?> &nbsp;·&nbsp;
    <?= imps('saved',0) ?> saved &nbsp;·&nbsp; <?= imps('skipped',0) ?> skipped
  </span>
  <div style="flex:1;background:var(--line);border-radius:4px;height:7px">
    <div style="background:var(--green-600);height:7px;border-radius:4px;
                width:<?= round($cursor / max($total,1) * 100) ?>%;transition:width .3s"></div>
  </div>
</div>

<form method="post">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="img_url"  value="<?= e($p['img_url']) ?>">

  <div style="display:grid;grid-template-columns:1fr 210px;gap:28px;align-items:start">

    <!-- Left column -->
    <div>
      <label class="field">Title <span class="req">*</span>
        <input type="text" name="title" value="<?= e($p['title']) ?>" required>
      </label>
      <label class="field">Short description / story
        <textarea name="story" rows="2"><?= e($parsed['story']) ?></textarea>
      </label>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:4px">
        <label class="field">Category
          <input type="text" name="category_name" list="imp-cats"
                 value="<?= e($tax['category'] ?? '') ?>" placeholder="(leave blank = unset)">
          <datalist id="imp-cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        </label>
        <label class="field">Cuisine
          <input type="text" name="cuisine_name" list="imp-cuis"
                 value="<?= e($tax['cuisine'] ?? '') ?>" placeholder="(leave blank = unset)">
          <datalist id="imp-cuis"><?php foreach ($cuis as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        </label>
        <label class="field">Origin
          <input type="text" name="origin_name" list="imp-oris"
                 value="<?= e($tax['origin'] ?? '') ?>" placeholder="(leave blank = unset)">
          <datalist id="imp-oris"><?php foreach ($oris as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        </label>
      </div>
      <p style="font-size:11px;color:var(--ink-soft);margin:-2px 0 18px">
        Blog labels: <?= e(implode(', ', $p['labels'])) ?>
      </p>

      <!-- Ingredients -->
      <h3 style="margin-bottom:8px">Ingredients
        <span style="font-size:12px;font-weight:400;color:var(--ink-soft)">(<?= count($parsed['ings']) ?> parsed)</span>
      </h3>
      <div id="ing-list">
        <?php foreach ($parsed['ings'] as $i => $ing): ?>
        <div class="ing-row" style="display:grid;grid-template-columns:1fr 110px 26px;gap:6px;margin-bottom:5px">
          <input type="text" name="ing_name[]" value="<?= e($ing['name']) ?>" placeholder="Ingredient name">
          <input type="text" name="ing_qty[]"  value="<?= e($ing['qty'])  ?>" placeholder="Qty / amount">
          <button type="button" onclick="this.closest('.ing-row').remove()"
                  style="background:none;border:0;cursor:pointer;font-size:16px;color:var(--ink-soft);padding:0">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addIng()" class="btn btn-ghost btn-sm" style="margin-bottom:22px">+ Add ingredient</button>

      <!-- Steps -->
      <h3 style="margin-bottom:8px">Method steps
        <span style="font-size:12px;font-weight:400;color:var(--ink-soft)">(<?= count($parsed['steps']) ?> parsed)</span>
      </h3>
      <div id="step-list">
        <?php foreach ($parsed['steps'] as $s => $step): ?>
        <div class="step-row" style="display:grid;grid-template-columns:22px 1fr 26px;gap:6px;align-items:start;margin-bottom:7px">
          <span class="step-no" style="padding-top:7px;font-weight:700;font-size:13px;color:var(--ink-soft)"><?= $s+1 ?></span>
          <textarea name="step_text[]" rows="2" style="resize:vertical"><?= e($step) ?></textarea>
          <button type="button" onclick="removeStep(this)"
                  style="background:none;border:0;cursor:pointer;font-size:16px;color:var(--ink-soft);padding:0;margin-top:5px">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addStep()" class="btn btn-ghost btn-sm">+ Add step</button>
    </div>

    <!-- Right column -->
    <div>
      <?php if ($p['img_url']): ?>
        <img src="<?= e($p['img_url']) ?>" alt="" loading="lazy"
             style="width:100%;border-radius:10px;object-fit:cover;aspect-ratio:4/3;background:var(--green-100)">
        <p style="font-size:11px;color:var(--ink-soft);margin:4px 0 14px">Image will be downloaded to server on save.</p>
      <?php else: ?>
        <div style="width:100%;aspect-ratio:4/3;background:var(--green-100);border-radius:10px;
                    display:flex;align-items:center;justify-content:center;color:var(--ink-soft);font-size:13px">No image</div>
      <?php endif; ?>
      <?php if ($parsed['prepTime']): ?>
        <p style="font-size:13px;margin-bottom:10px">⏱ Prep: <strong><?= e($parsed['prepTime']) ?></strong></p>
      <?php endif; ?>
      <p style="font-size:11px;color:var(--ink-soft);margin-bottom:6px">Original post:</p>
      <a href="<?= e($p['post_url']) ?>" target="_blank" rel="noopener"
         style="font-size:11px;word-break:break-all;color:var(--green-700)"><?= e($p['post_url']) ?></a>
    </div>
  </div><!-- /grid -->

  <div style="display:flex;gap:12px;margin-top:28px;padding-top:20px;border-top:1px solid var(--line)">
    <button type="submit" class="btn btn-primary">Save as draft &amp; next →</button>
    <button type="submit" name="action" value="skip" class="btn btn-ghost">Skip →</button>
    <a href="?step=select" class="btn btn-ghost" style="margin-left:auto">← Back to list</a>
  </div>
</form>

<script>
function addIng(){
  const r=document.createElement('div');
  r.className='ing-row';
  r.style.cssText='display:grid;grid-template-columns:1fr 110px 26px;gap:6px;margin-bottom:5px';
  r.innerHTML='<input type="text" name="ing_name[]" placeholder="Ingredient name">'
             +'<input type="text" name="ing_qty[]"  placeholder="Qty / amount">'
             +'<button type="button" onclick="this.closest(\'.ing-row\').remove()" '
             +'style="background:none;border:0;cursor:pointer;font-size:16px;color:var(--ink-soft);padding:0">×</button>';
  document.getElementById('ing-list').appendChild(r);
}
function removeStep(btn){
  btn.closest('.step-row').remove();
  document.querySelectorAll('#step-list .step-no').forEach((s,i)=>s.textContent=i+1);
}
function addStep(){
  const n=document.querySelectorAll('#step-list .step-row').length+1;
  const r=document.createElement('div');
  r.className='step-row';
  r.style.cssText='display:grid;grid-template-columns:22px 1fr 26px;gap:6px;align-items:start;margin-bottom:7px';
  r.innerHTML=`<span class="step-no" style="padding-top:7px;font-weight:700;font-size:13px;color:var(--ink-soft)">${n}</span>`
             +`<textarea name="step_text[]" rows="2" style="resize:vertical"></textarea>`
             +`<button type="button" onclick="removeStep(this)" style="background:none;border:0;cursor:pointer;font-size:16px;color:var(--ink-soft);padding:0;margin-top:5px">×</button>`;
  document.getElementById('step-list').appendChild(r);
}
</script>

<?php elseif ($step === 'done'): ?>
<!-- ══ SCREEN 4: done ══════════════════════════════════════════════════════ -->
  <?php $lastMsg = imps('last_msg'); impss('last_msg', null); ?>
  <?php if ($lastMsg): ?><div class="flash flash-success"><?= e($lastMsg) ?></div><?php endif; ?>
  <div style="text-align:center;padding:56px 0">
    <div style="font-size:52px;margin-bottom:12px">🎉</div>
    <h2>Import complete!</h2>
    <p style="color:var(--ink-soft);font-size:17px">
      <strong><?= imps('saved',0) ?></strong> recipes saved as drafts &nbsp;·&nbsp;
      <strong><?= imps('skipped',0) ?></strong> skipped
    </p>
    <p style="margin-top:24px;display:flex;gap:12px;justify-content:center">
      <a href="<?= e(url('admin/content.php')) ?>" class="btn btn-primary">Review drafts in admin →</a>
      <a href="?step=start" class="btn btn-ghost">Import more</a>
    </p>
  </div>
<?php endif; ?>

</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
