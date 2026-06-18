<?php
/** A forum discussion: opening post, reactions, threaded replies. */
require_once __DIR__ . '/includes/bootstrap.php';

$me = current_user();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare(
    "SELECT t.*, u.username, u.display_name, u.avatar, fc.name AS cat_name, fc.id AS cat_id
       FROM forum_topics t
       JOIN users u ON u.id = t.user_id
       JOIN forum_categories fc ON fc.id = t.category_id
      WHERE t.id = ? AND t.status = 'visible'"
);
$st->execute([$id]);
$t = $st->fetch();
if (!$t) {
    flash('error', 'That topic was not found.');
    redirect('forum.php');
}

db()->prepare('UPDATE forum_topics SET views = views + 1 WHERE id = ?')->execute([$id]);
$replies = comments_for('topic', $id);

$pageTitle = $t['title'];
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:840px">
  <p class="small"><a href="<?= e(url('forum.php')) ?>">💬 Forum</a> › <span class="muted"><?= e($t['cat_name']) ?></span></p>
  <div class="panel">
    <h1 style="margin:0 0 12px;font-size:28px"><?= e($t['title']) ?></h1>
    <div class="post-head">
      <?= avatar_html($t, 44) ?>
      <div class="who">
        <a href="<?= e(url('profile.php?u=' . urlencode($t['username']))) ?>"><?= e($t['display_name'] ?: $t['username']) ?></a>
        <time>started <?= e(time_ago($t['created_at'])) ?> · 👁 <?= (int)$t['views'] ?> views</time>
      </div>
    </div>
    <?= nl2p($t['body']) ?>
    <div class="post-actions"><?= reaction_bar('topic', $id) ?></div>
  </div>

  <div class="panel" style="margin-top:22px">
    <h3>💬 Replies (<?= count($replies) ?>)</h3>
    <div id="topic-replies">
      <?php if (!$replies): ?><div class="empty" style="padding:18px">No replies yet — share your thoughts!</div><?php endif; ?>
      <?php foreach ($replies as $c): ?>
        <div class="comment">
          <?= avatar_html($c, 38) ?>
          <div class="comment-body">
            <div class="comment-head">
              <a href="<?= e(url('profile.php?u=' . urlencode($c['username']))) ?>"><?= e($c['display_name'] ?: $c['username']) ?></a>
              <time><?= e(time_ago($c['created_at'])) ?></time>
            </div>
            <div><?= nl2br(e($c['body'])) ?></div>
            <?= reaction_bar('comment', (int)$c['id']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($me): ?>
      <form class="comment-form" data-type="topic" data-id="<?= $id ?>" data-list="#topic-replies">
        <textarea placeholder="Join the discussion…" maxlength="3000"></textarea>
        <button class="btn btn-primary" type="submit">Reply</button>
      </form>
    <?php else: ?>
      <p class="muted" style="margin-top:14px"><a href="<?= e(url('login.php')) ?>">Sign in</a> to reply and react.</p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
