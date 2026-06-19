<?php
/** Dynamic XML sitemap: static pages + published recipes + visible forum topics. */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$urls = [
    ['loc' => url('index.php'),    'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => url('recipes.php'),  'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => url('forum.php'),    'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => url('register.php'), 'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => url('about.php'),    'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => url('contact.php'),  'priority' => '0.4', 'changefreq' => 'monthly'],
];

$st = db()->query(
    "SELECT id, created_at FROM recipes WHERE status = 'published' ORDER BY created_at DESC LIMIT 5000"
);
foreach ($st as $r) {
    $urls[] = [
        'loc'        => url('recipe.php?id=' . (int)$r['id']),
        'lastmod'    => date('Y-m-d', strtotime($r['created_at'])),
        'priority'   => '0.8',
        'changefreq' => 'weekly',
    ];
}

$st = db()->query(
    "SELECT id, created_at FROM forum_topics WHERE status = 'visible' ORDER BY created_at DESC LIMIT 5000"
);
foreach ($st as $t) {
    $urls[] = [
        'loc'        => url('forum-topic.php?id=' . (int)$t['id']),
        'lastmod'    => date('Y-m-d', strtotime($t['created_at'])),
        'priority'   => '0.6',
        'changefreq' => 'weekly',
    ];
}

foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($u['lastmod']))    echo "    <lastmod>{$u['lastmod']}</lastmod>\n";
    if (!empty($u['changefreq'])) echo "    <changefreq>{$u['changefreq']}</changefreq>\n";
    echo "    <priority>{$u['priority']}</priority>\n  </url>\n";
}
echo '</urlset>';
