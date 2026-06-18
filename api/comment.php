<?php
/** Post a comment on a recipe, wall post or forum topic. Returns rendered HTML. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_login_json();
csrf_check();
$in = json_decode(file_get_contents('php://input'), true) ?: [];

$targetType = $in['target_type'] ?? '';
$targetId   = (int)($in['target_id'] ?? 0);
$body       = trim($in['body'] ?? '');

if (!in_array($targetType, ['recipe', 'wall', 'topic'], true) || $targetId < 1) {
    json_fail('Invalid target.');
}
if ($body === '' || mb_strlen($body) > 3000) {
    json_fail('Comments must be between 1 and 3000 characters.');
}

$lookup = [
    'recipe' => ["SELECT user_id, title FROM recipes WHERE id = ? AND status = 'published'", 'commented on your recipe "%s"'],
    'wall'   => ["SELECT user_id, NULL AS title FROM wall_posts WHERE id = ? AND status = 'visible'", 'commented on your wall post'],
    'topic'  => ["SELECT user_id, title FROM forum_topics WHERE id = ? AND status = 'visible'", 'replied to your topic "%s"'],
];
[$sql, $msgTpl] = $lookup[$targetType];
$st = db()->prepare($sql);
$st->execute([$targetId]);
$target = $st->fetch();
if (!$target) {
    json_fail('That content no longer exists.', 404);
}

// Wall posts are buddies-only, so commenting is too.
if ($targetType === 'wall' && !are_buddies((int)$me['id'], (int)$target['user_id'])) {
    json_fail('Only buddies can comment on wall posts.', 403);
}

$st = db()->prepare(
    "INSERT INTO comments (user_id, target_type, target_id, body, status, created_at)
     VALUES (?, ?, ?, ?, 'visible', NOW())"
);
$st->execute([$me['id'], $targetType, $targetId, $body]);
$commentId = (int)db()->lastInsertId();

$who = $me['display_name'] ?: $me['username'];
$msg = $target['title'] !== null ? sprintf($msgTpl, mb_strimwidth($target['title'], 0, 60, '…')) : $msgTpl;
notify((int)$target['user_id'], (int)$me['id'], 'comment', $targetType, $targetId, $who . ' ' . $msg);

$html = '<div class="comment">'
      . avatar_html($me, 36)
      . '<div class="comment-body"><div class="comment-head">'
      . '<a href="' . e(url('profile.php?u=' . urlencode($me['username']))) . '">' . e($who) . '</a>'
      . '<time>just now</time></div>'
      . '<div>' . nl2br(e($body)) . '</div>'
      . reaction_bar('comment', $commentId)
      . '</div></div>';

json_out(['ok' => true, 'id' => $commentId, 'html' => $html]);
