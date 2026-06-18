<?php
/** Typeahead suggestions for master lists (fires after 3 typed letters). */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$map = [
    'ingredients'      => 'ingredients',
    'categories'       => 'categories',
    'cuisines'         => 'cuisines',
    'origins'          => 'origins',
    'forum_categories' => 'forum_categories',
];

$type = $_GET['type'] ?? '';
$q    = trim($_GET['q'] ?? '');

if (!isset($map[$type])) {
    json_fail('Unknown list.');
}
if (mb_strlen($q) < 3) {
    json_out(['ok' => true, 'items' => []]);
}

$st = db()->prepare("SELECT name FROM `{$map[$type]}` WHERE name LIKE ? ORDER BY name LIMIT 10");
$st->execute([$q . '%']);
$items = $st->fetchAll(PDO::FETCH_COLUMN);

// fall back to substring match when prefix yields nothing
if (!$items) {
    $st = db()->prepare("SELECT name FROM `{$map[$type]}` WHERE name LIKE ? ORDER BY name LIMIT 10");
    $st->execute(['%' . $q . '%']);
    $items = $st->fetchAll(PDO::FETCH_COLUMN);
}

json_out(['ok' => true, 'items' => $items]);
