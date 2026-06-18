<?php
/** CMS portal page shell (top). Set $cmsPageTitle, $cmsActive, optional $cmsBare. */
$su           = current_superuser();
$cmsActive    = $cmsActive    ?? '';
$cmsPageTitle = $cmsPageTitle ?? 'CMS Portal';
$cmsBare      = $cmsBare      ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($cmsPageTitle) ?> · CMS · <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(cms_site_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(cms_url('assets/cms.css')) ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='44' fill='none' stroke='%2323756a' stroke-width='6'/%3E%3Cpath d='M50 26 C39 40 39 58 50 74 C61 58 61 40 50 26 Z' fill='%233fa796'/%3E%3C/svg%3E">
</head>
<body class="cms-body<?= $cmsBare || !$su ? ' cms-bare' : '' ?>">
<header class="cms-topbar">
  <a class="cms-brand" href="<?= e(cms_url('index.php')) ?>">
    <svg viewBox="0 0 100 100" width="34" height="34" aria-hidden="true">
      <circle cx="50" cy="50" r="46.5" fill="none" stroke="#23756a" stroke-width="2.6"/>
      <text x="37.5" y="62" font-family="'Playfair Display',serif" font-size="33" fill="#23756a" text-anchor="middle">B</text>
      <text x="61.5" y="62" font-family="'Playfair Display',serif" font-size="33" fill="#1e3833" text-anchor="middle">A</text>
      <path d="M50 68 C47 71 47 75 50 78 C53 75 53 71 50 68 Z" fill="#3fa796"/>
    </svg>
    <span class="cms-brand-txt">Bosket's Alimentos <span class="cms-badge">CMS · SuperUser</span></span>
  </a>
  <div class="cms-top-actions">
    <a href="<?= e(cms_site_url('index.php')) ?>" target="_blank" rel="noopener">View site ↗</a>
    <?php if ($su): ?>
      <span class="cms-who"><?= e($su['display_name'] ?: $su['username']) ?></span>
      <a class="btn btn-sm btn-ghost" href="<?= e(cms_url('logout.php')) ?>">Sign out</a>
    <?php endif; ?>
  </div>
</header>
<?php if ($su && !$cmsBare): ?>
<div class="cms-shell">
  <nav class="cms-sidebar">
    <?php
      $nav = [
          'index'      => ['index.php',      'Dashboard',  '▥'],
          'pages'      => ['pages.php',      'Pages',      '▤'],
          'menus'      => ['menus.php',      'Menus',      '☰'],
          'moderation' => ['moderation.php', 'Moderation', '⚑'],
          'appearance' => ['appearance.php', 'Appearance', '🎨'],
      ];
      foreach ($nav as $k => [$href, $label, $icon]) {
          $cls = $k === $cmsActive ? ' class="active"' : '';
          echo '<a' . $cls . ' href="' . e(cms_url($href)) . '"><span class="ci" aria-hidden="true">' . $icon . '</span>' . e($label) . '</a>';
      }
    ?>
  </nav>
  <main class="cms-main">
    <?php foreach (take_flashes() as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
<?php else: ?>
  <main class="cms-auth">
    <?php foreach (take_flashes() as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
<?php endif; ?>
