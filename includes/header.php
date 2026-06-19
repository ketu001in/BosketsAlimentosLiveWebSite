<?php
/** Page shell top. Set $pageTitle (and optionally $pageDesc, $pageImage, $noIndex) before including. */
$me = current_user();

// Recipes mega-menu: master-list values actually used by published recipes.
$navMega = [];
foreach (['category' => ['categories', 'By category'],
          'cuisine'  => ['cuisines',   'By cuisine'],
          'origin'   => ['origins',    'By origin']] as $param => [$table, $label]) {
    $megaRows = db()->query(
        "SELECT m.id, m.name, COUNT(r.id) AS n
           FROM `$table` m JOIN recipes r ON r.{$param}_id = m.id AND r.status = 'published'
          GROUP BY m.id, m.name ORDER BY n DESC, m.name LIMIT 8"
    )->fetchAll();
    if ($megaRows) {
        $navMega[$param] = ['label' => $label, 'rows' => $megaRows];
    }
}
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDesc  = $pageDesc  ?? SITE_NAME . ' — ' . SITE_TAGLINE . '. 100% vegetarian fusion recipes, food stories and community.';
$pageImage = $pageImage ?? '';
$pageUrl   = base_url() . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e(mb_strimwidth($pageDesc, 0, 160, '…')) ?>">
<?php if (!empty($noIndex)): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e(mb_strimwidth($pageDesc, 0, 200, '…')) ?>">
<meta property="og:url" content="<?= e($pageUrl) ?>">
<?php if ($pageImage): ?><meta property="og:image" content="<?= e(url($pageImage)) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $pageImage ? 'summary_large_image' : 'summary' ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=13">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='44' fill='none' stroke='%2323756a' stroke-width='6'/%3E%3Cpath d='M50 26 C39 40 39 58 50 74 C61 58 61 40 50 26 Z' fill='%233fa796'/%3E%3C/svg%3E">
<script>window.BOSKETS = {base: <?= json_encode(base_url()) ?>, csrf: <?= json_encode(is_logged_in() ? csrf_token() : '') ?>, loggedIn: <?= is_logged_in() ? 'true' : 'false' ?>};</script>
<?php require_once __DIR__ . '/cms.php'; echo cms_head_html(); /* CMS: site theme + fonts (no-op until configured) */ ?>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <svg class="brand-seal" viewBox="0 0 100 100" width="46" height="46" role="img" aria-label="Bosket's Alimentos seal">
        <circle cx="50" cy="50" r="46.5" fill="none" stroke="#23756a" stroke-width="2.6"/>
        <circle cx="50" cy="50" r="39" fill="none" stroke="#23756a" stroke-width=".9" stroke-dasharray="2 4.2"/>
        <text x="37.5" y="61.5" font-family="'Playfair Display', Georgia, serif" font-size="34" fill="#23756a" text-anchor="middle">B</text>
        <text x="61.5" y="61.5" font-family="'Playfair Display', Georgia, serif" font-size="34" fill="#1e3833" text-anchor="middle">A</text>
        <path d="M50 67.5 C47 71 47 75 50 78.5 C53 75 53 71 50 67.5 Z" fill="#3fa796"/>
        <path d="M41 29 C45 26 55 26 59 29" fill="none" stroke="#3fa796" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      <span class="brand-text">
        <strong>Bosket's <em>Alimentos</em></strong>
        <small><?= e(SITE_TAGLINE) ?> · <span class="veg-mark">100% VEG</span></small>
      </span>
    </a>
    <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
    <div class="nav-actions">
      <?php if ($me): ?>
        <a class="btn btn-sm btn-primary nav-post" href="<?= e(url('post-recipe.php')) ?>">+ Post a Recipe</a>
        <div class="nav-msg">
          <a href="<?= e(url('messages.php')) ?>" id="msgicon" title="Messages">💬<?php
            ensure_messages_table();
            $mu = unread_messages_count((int)$me['id']);
            if ($mu > 0) echo '<span class="bell-count" id="msg-count">' . $mu . '</span>';
          ?></a>
        </div>
        <div class="nav-bell">
          <a href="<?= e(url('notifications.php')) ?>" id="bell" title="Notifications">🔔<?php
            $n = unread_count((int)$me['id']);
            if ($n > 0) echo '<span class="bell-count" id="bell-count">' . $n . '</span>';
          ?></a>
        </div>
        <div class="nav-user">
          <a class="nav-avatar" href="<?= e(url('profile.php?u=' . urlencode($me['username']))) ?>"><?= avatar_html($me, 34) ?></a>
          <div class="nav-dropdown">
            <a href="<?= e(url('profile.php?u=' . urlencode($me['username']))) ?>">My Profile &amp; Wall</a>
            <a href="<?= e(url('feed.php')) ?>">My Feed</a>
            <a href="<?= e(url('messages.php')) ?>">Messages</a>
            <a href="<?= e(url('buddies.php')) ?>">My Buddies</a>
            <a href="<?= e(url('settings.php')) ?>">Account Settings</a>
            <?php if (is_admin()): ?><a href="<?= e(url('admin/index.php')) ?>">Admin Panel</a><?php endif; ?>
            <a href="<?= e(url('logout.php')) ?>">Sign Out</a>
          </div>
        </div>
      <?php else: ?>
        <a class="nav-signin" href="<?= e(url('login.php')) ?>">Sign In</a>
        <a class="btn btn-sm btn-primary" href="<?= e(url('register.php')) ?>">Join Free</a>
      <?php endif; ?>
      <?= cms_theme_toggle_html() /* CMS: visitor light/dark toggle (only if enabled) */ ?>
    </div>
  </div><!-- /.header-inner -->

  <nav class="header-nav-bar" aria-label="Primary">
    <div class="container">
      <div class="main-nav">
        <?php if ($navMega): ?>
          <div class="nav-item">
            <a href="<?= e(url('recipes.php')) ?>">Recipes <span class="caret">▼</span></a>
            <div class="mega">
              <?php foreach ($navMega as $param => $group): ?>
                <div>
                  <h5><?= e($group['label']) ?></h5>
                  <?php foreach ($group['rows'] as $row): ?>
                    <a href="<?= e(url('recipes.php?' . $param . '=' . (int)$row['id'])) ?>">
                      <?= e($row['name']) ?> <small>(<?= (int)$row['n'] ?>)</small>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
              <a class="mega-all" href="<?= e(url('recipes.php')) ?>">Browse all recipes →</a>
            </div>
          </div>
        <?php else: ?>
          <a href="<?= e(url('recipes.php')) ?>">Recipes</a>
        <?php endif; ?>
        <a href="<?= e(url('forum.php')) ?>">Forum</a>
        <a href="<?= e(url('about.php')) ?>">About Us</a>
        <a href="<?= e(url('contact.php')) ?>">Contact Us</a>
        <?php if ($me): ?>
          <?php
            $wallName = $me['display_name'] ?: $me['username'];
            if (mb_strlen($wallName) > 14) {
                $wallName = preg_split('/\s+/', $wallName)[0];
            }
          ?>
          <a href="<?= e(url('profile.php?u=' . urlencode($me['username']) . '&tab=wall')) ?>"><?= e($wallName) ?>'s Wall</a>
        <?php endif; ?>
        <?= cms_nav_items_html() /* CMS top-level items (max 5) — flow inline with the menu */ ?>
      </div>
    </div>
  </nav>
  <div class="header-search-bar">
    <div class="container">
      <div class="nav-search">
        <span class="nav-search-icon" aria-hidden="true">🔍</span>
        <input type="search" id="recipe-search" placeholder="Search recipes by name…" autocomplete="off"
               aria-label="Search recipes" role="combobox" aria-expanded="false" aria-controls="recipe-search-results">
        <div class="nav-search-results" id="recipe-search-results" role="listbox"></div>
      </div>
    </div>
  </div>
</header>
<main class="site-main">
<?php foreach (take_flashes() as $f): ?>
  <div class="container"><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>
