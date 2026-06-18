<?php
/** Wall actions: share a recipe to your wall, or delete your own wall post. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_login_json();
csrf_check();
$in = json_decode(file_get_contents('php://input'), true) ?: [];

$action = $in['action'] ?? '';

if ($action === 'share') {
    $recipeId = (int)($in['recipe_id'] ?? 0);
    $body     = trim($in['body'] ?? '');
    if (mb_strlen($body) > 2000) {
        json_fail('Your note is too long (2000 characters max).');
    }
    $st = db()->prepare("SELECT id, title, user_id FROM recipes WHERE id = ? AND status = 'published'");
    $st->execute([$recipeId]);
    $recipe = $st->fetch();
    if (!$recipe) {
        json_fail('Recipe not found.', 404);
    }
    db()->prepare(
        "INSERT INTO wall_posts (user_id, body, shared_recipe_id, status, created_at)
         VALUES (?, ?, ?, 'visible', NOW())"
    )->execute([$me['id'], $body, $recipeId]);

    $meName = $me['display_name'] ?: $me['username'];
    notify((int)$recipe['user_id'], (int)$me['id'], 'share', 'recipe', $recipeId,
           $meName . ' shared your recipe "' . mb_strimwidth($recipe['title'], 0, 60, '…') . '" on their wall');
    json_out(['ok' => true]);
}

if ($action === 'delete') {
    $postId = (int)($in['post_id'] ?? 0);
    $st = db()->prepare('SELECT id, user_id, image FROM wall_posts WHERE id = ?');
    $st->execute([$postId]);
    $post = $st->fetch();
    if (!$post || ((int)$post['user_id'] !== (int)$me['id'] && !is_admin())) {
        json_fail('You can only delete your own posts.', 403);
    }
    delete_upload($post['image']);
    db()->prepare('DELETE FROM wall_posts WHERE id = ?')->execute([$postId]);
    db()->prepare("DELETE FROM comments WHERE target_type = 'wall' AND target_id = ?")->execute([$postId]);
    db()->prepare("DELETE FROM reactions WHERE target_type = 'wall' AND target_id = ?")->execute([$postId]);
    json_out(['ok' => true, 'message' => 'Post deleted.']);
}

json_fail('Unknown action.');
