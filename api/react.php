<?php
/** Toggle / switch a reaction on a recipe, wall post, forum topic or comment. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_login_json();
csrf_check();
$in = json_decode(file_get_contents('php://input'), true) ?: [];

$targetType = $in['target_type'] ?? '';
$targetId   = (int)($in['target_id'] ?? 0);
$reaction   = $in['reaction'] ?? '';

if (!in_array($targetType, ['recipe', 'wall', 'topic', 'comment'], true) || $targetId < 1
    || !array_key_exists($reaction, REACTION_EMOJI)) {
    json_fail('Invalid reaction.');
}

// Validate the target exists and find its owner for the notification.
$lookup = [
    'recipe'  => ["SELECT user_id, title FROM recipes WHERE id = ? AND status = 'published'", 'reacted to your recipe "%s"'],
    'wall'    => ["SELECT user_id, NULL AS title FROM wall_posts WHERE id = ? AND status = 'visible'", 'reacted to your wall post'],
    'topic'   => ["SELECT user_id, title FROM forum_topics WHERE id = ? AND status = 'visible'", 'reacted to your topic "%s"'],
    'comment' => ["SELECT user_id, NULL AS title FROM comments WHERE id = ? AND status = 'visible'", 'reacted to your comment'],
];
[$sql, $msgTpl] = $lookup[$targetType];
$st = db()->prepare($sql);
$st->execute([$targetId]);
$target = $st->fetch();
if (!$target) {
    json_fail('That content no longer exists.', 404);
}

$st = db()->prepare('SELECT id, reaction FROM reactions WHERE user_id = ? AND target_type = ? AND target_id = ?');
$st->execute([$me['id'], $targetType, $targetId]);
$existing = $st->fetch();

if ($existing && $existing['reaction'] === $reaction) {
    // same reaction again -> remove (toggle off)
    db()->prepare('DELETE FROM reactions WHERE id = ?')->execute([$existing['id']]);
} elseif ($existing) {
    db()->prepare('UPDATE reactions SET reaction = ? WHERE id = ?')->execute([$reaction, $existing['id']]);
} else {
    db()->prepare(
        'INSERT INTO reactions (user_id, target_type, target_id, reaction, created_at) VALUES (?, ?, ?, ?, NOW())'
    )->execute([$me['id'], $targetType, $targetId, $reaction]);
    $who = $me['display_name'] ?: $me['username'];
    $msg = $target['title'] !== null ? sprintf($msgTpl, mb_strimwidth($target['title'], 0, 60, '…')) : $msgTpl;
    notify((int)$target['user_id'], (int)$me['id'], 'reaction', $targetType, $targetId, $who . ' ' . $msg);
}

$summary = reaction_summary($targetType, $targetId, (int)$me['id']);
json_out(['ok' => true, 'counts' => $summary['counts'], 'total' => $summary['total'], 'mine' => $summary['mine']]);
