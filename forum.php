<?php
/** Forum board: categories with their latest topics. */
require_once __DIR__ . '/includes/bootstrap.php';

$cats = db()->query('SELECT * FROM forum_categories ORDER BY name')->fetchAll();

$topicsByCat = [];
$st = db()->query(
    "SELECT t.*, u.username, u.display_name, u.avatar,
            (SELECT COUNT(*) FROM comments c WHERE c.target_type='topic' AND c.target_id = t.id AND c.status='visible') reply_count,
            (SELECT COUNT(*) FROM reactions x WHERE x.target_type='topic' AND x.target_id = t.id) reaction_count,
            (SELECT MAX(c.created_at) FROM comments c WHERE c.target_type='topic' AND c.target_id = t.id AND c.status='visible') last_reply
       FROM forum_topics t JOIN users u ON u.id = t.user_id
      WHERE t.status = 'visible'
   ORDER BY COALESCE((SELECT MAX(c2.created_at) FROM comments c2 WHERE c2.target_type='topic' AND c2.target_id = t.id), t.created_at) DESC"
);
foreach ($st as $t) {
    $topicsByCat[(int)$t['category_id']][] = $t;
}

$pageTitle = 'Forum';
include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <div class="section-head">
    <h2 style="font-size:32px">💬 Food Forum</h2>
    <a class="btn btn-accent" href="<?= e(url('new-topic.php')) ?>">+ Start a New Topic</a>
  </div>
  <p class="muted" style="margin-top:-12px">Talk food with the community — questions, debates, tips and tales. Pick a board or <a href="<?= e(url('new-topic.php')) ?>">create a new one</a>.</p>

  <?php foreach ($cats as $cat): $topics = $topicsByCat[(int)$cat['id']] ?? []; ?>
    <div class="forum-cat">
      <div class="forum-cat-head">
        <h2><?= e($cat['name']) ?></h2>
        <?php if ($cat['description']): ?><span class="muted small"><?= e($cat['description']) ?></span><?php endif; ?>
        <a class="more small" style="margin-left:auto" href="<?= e(url('new-topic.php?cat=' . (int)$cat['id'])) ?>">+ new topic here</a>
      </div>
      <?php if (!$topics): ?>
        <p class="muted small" style="padding-left:4px">No discussions yet — be the first to start one!</p>
      <?php endif; ?>
      <?php foreach (array_slice($topics, 0, 6) as $t): ?>
        <div class="topic-row">
          <?= avatar_html($t, 42) ?>
          <div class="topic-main">
            <h3><a href="<?= e(url('forum-topic.php?id=' . (int)$t['id'])) ?>"><?= e($t['title']) ?></a></h3>
            <span class="muted small">
              by <a href="<?= e(url('profile.php?u=' . urlencode($t['username']))) ?>"><?= e($t['display_name'] ?: $t['username']) ?></a>
              · <?= e(time_ago($t['created_at'])) ?>
              <?php if ($t['last_reply']): ?> · last reply <?= e(time_ago($t['last_reply'])) ?><?php endif; ?>
            </span>
          </div>
          <div class="topic-stats">
            <span><strong><?= (int)$t['reply_count'] ?></strong>replies</span>
            <span><strong><?= (int)$t['reaction_count'] ?></strong>reactions</span>
            <span><strong><?= (int)$t['views'] ?></strong>views</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
