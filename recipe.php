<?php
/** Single recipe page: hero, ingredients, steps, verdict, reactions, comments, share. */
require_once __DIR__ . '/includes/bootstrap.php';

$me = current_user();
$id = (int)($_GET['id'] ?? 0);
ensure_recipe_youtube_column();

$st = db()->prepare(
    "SELECT r.*, u.username, u.display_name, u.avatar, u.bio,
            c.name AS category_name, cu.name AS cuisine_name, o.name AS origin_name
       FROM recipes r
       JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c ON c.id = r.category_id
  LEFT JOIN cuisines cu ON cu.id = r.cuisine_id
  LEFT JOIN origins o ON o.id = r.origin_id
      WHERE r.id = ? AND r.status = 'published'"
);
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    flash('error', 'That recipe was not found (it may have been removed).');
    redirect('recipes.php');
}

db()->prepare('UPDATE recipes SET views = views + 1 WHERE id = ?')->execute([$id]);

$st = db()->prepare(
    'SELECT ri.quantity, i.name FROM recipe_ingredients ri
       JOIN ingredients i ON i.id = ri.ingredient_id
      WHERE ri.recipe_id = ? ORDER BY ri.sort_order, ri.id'
);
$st->execute([$id]);
$ingredients = $st->fetchAll();

$st = db()->prepare('SELECT * FROM recipe_steps WHERE recipe_id = ? ORDER BY step_no');
$st->execute([$id]);
$steps = $st->fetchAll();

$comments = comments_for('recipe', $id);
$isOwner  = $me && (int)$me['id'] === (int)$r['user_id'];
$shareUrl = url('recipe.php?id=' . $id);
$embed    = youtube_embed_html($r['youtube_url'] ?? null, $r['title']);

$pageTitle = $r['title'];
$pageDesc  = trim((string)$r['story']) !== ''
    ? preg_replace('/\s+/', ' ', $r['story'])
    : '100% veg fusion recipe on ' . SITE_NAME . ' by ' . ($r['display_name'] ?: $r['username']) . '.';
$pageImage = $r['image'] ?: '';
include __DIR__ . '/includes/header.php';
?>
<div class="container section">

  <div class="recipe-hero">
    <div class="recipe-hero-img">
      <?php if ($r['image']): ?>
        <img src="<?= e(url($r['image'])) ?>" class="recipe-hero-bg" aria-hidden="true">
        <img src="<?= e(url($r['image'])) ?>" alt="<?= e($r['title']) ?>" class="recipe-cover-img" title="Click to view full image">
      <?php endif; ?>
    </div>
    <div>
      <h1 class="recipe-title"><?= e($r['title']) ?></h1>
      <div class="byline">
        <?= avatar_html($r, 42) ?>
        <div>
          by <a href="<?= e(url('profile.php?u=' . urlencode($r['username']))) ?>"><strong><?= e($r['display_name'] ?: $r['username']) ?></strong></a>
          <div class="muted small"><?= e(time_ago($r['created_at'])) ?> · 👁 <?= (int)$r['views'] ?> views</div>
        </div>
      </div>
      <div class="recipe-tags">
        <span class="tag">🌱 100% Veg</span>
        <?php if ($r['category_name']): ?><span class="tag"><?= e($r['category_name']) ?></span><?php endif; ?>
        <?php if ($r['cuisine_name']): ?><span class="tag tag-orange"><?= e($r['cuisine_name']) ?> cuisine</span><?php endif; ?>
        <?php if ($r['origin_name']): ?><span class="tag">📍 <?= e($r['origin_name']) ?></span><?php endif; ?>
      </div>
      <?php if ($r['story']): ?><?= nl2p($r['story']) ?><?php endif; ?>
      <?= reaction_bar('recipe', $id) ?>
      <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
        <button class="btn btn-outline" data-share-recipe="<?= $id ?>"
                data-share-url="<?= e($shareUrl) ?>" data-share-title="<?= e($r['title']) ?>">📤 Share</button>
        <?php if ($isOwner): ?>
          <a class="btn btn-ghost" href="<?= e(url('post-recipe.php?id=' . $id)) ?>">✏️ Edit</a>
          <form method="post" action="<?= e(url('post-recipe.php')) ?>" onsubmit="return confirm('Delete this recipe permanently?');">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_id" value="<?= $id ?>">
            <button class="btn btn-danger" type="submit">🗑 Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($embed): ?>
    <div class="panel recipe-video" style="margin-top:28px">
      <h3>▶ Watch how it's made</h3>
      <?= $embed ?>
    </div>
  <?php endif; ?>

  <div class="profile-cols" style="margin-top:36px">
    <div class="panel">
      <h3>🧺 Ingredients</h3>
      <?php if (!$ingredients): ?><p class="muted">No ingredients listed.</p><?php endif; ?>
      <ul class="ingredients-list">
        <?php foreach ($ingredients as $ing): ?>
          <li><span><?= e($ing['name']) ?></span><span class="qty"><?= e($ing['quantity']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div>
      <div class="panel">
        <details class="steps-collapse" open>
          <summary>
            <h3>👨‍🍳 Method — step by step</h3>
            <span class="collapse-hint" aria-hidden="true"></span>
          </summary>
          <div class="steps-body">
            <?php foreach ($steps as $s): ?>
              <div class="step">
                <div class="step-no"><?= (int)$s['step_no'] ?></div>
                <div style="flex:1">
                  <?= nl2p($s['instruction']) ?>
                  <?php if ($s['media']): ?>
                    <div class="step-media">
                      <?php if ($s['media_type'] === 'video'): ?>
                        <video controls preload="metadata" src="<?= e(url($s['media'])) ?>"></video>
                      <?php else: ?>
                        <img src="<?= e(url($s['media'])) ?>" alt="Step <?= (int)$s['step_no'] ?>" loading="lazy">
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
      </div>

      <?php if ($r['verdict']): ?>
        <div class="verdict-box" style="margin-top:22px">
          <strong>💡 Chef's verdict &amp; trivia</strong>
          <?= nl2p($r['verdict']) ?>
        </div>
      <?php endif; ?>

      <div class="panel" style="margin-top:22px" id="comments">
        <h3>💬 Comments (<?= count($comments) ?>)</h3>
        <div id="recipe-comments">
          <?php if (!$comments): ?><div class="empty" style="padding:18px">Be the first to comment! 🥄</div><?php endif; ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment">
              <?= avatar_html($c, 36) ?>
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
          <form class="comment-form" data-type="recipe" data-id="<?= $id ?>" data-list="#recipe-comments">
            <textarea placeholder="Tried it? Loved it? Tell <?= e($r['display_name'] ?: $r['username']) ?>…" maxlength="3000"></textarea>
            <button class="btn btn-primary" type="submit">Comment</button>
          </form>
        <?php else: ?>
          <p class="muted" style="margin-top:14px"><a href="<?= e(url('login.php')) ?>">Sign in</a> to react and comment.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Share modal -->
<div class="modal-overlay" id="share-modal">
  <div class="modal">
    <h3>📤 Share this recipe</h3>
    <?php if ($me): ?>
      <label class="field">Share to your wall with a note <small>Your buddies will see it in their feed</small>
        <textarea id="share-note" maxlength="2000" placeholder="Why do you love this recipe? (optional)"></textarea>
      </label>
      <button class="btn btn-primary" id="share-repost" style="margin-top:10px">📌 Share to my wall</button>
      <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <?php endif; ?>
    <label class="field">Recipe link
      <input type="text" id="share-url" readonly value="<?= e($shareUrl) ?>" onclick="this.select()">
    </label>
    <div class="share-ext">
      <button class="btn btn-sm btn-ghost" id="share-copy">🔗 Copy link</button>
      <a class="btn btn-sm btn-ghost" id="share-wa" target="_blank" rel="noopener">WhatsApp</a>
      <a class="btn btn-sm btn-ghost" id="share-fb" target="_blank" rel="noopener">Facebook</a>
      <a class="btn btn-sm btn-ghost" id="share-x" target="_blank" rel="noopener">X / Twitter</a>
    </div>
    <button class="btn btn-sm btn-outline" data-close-modal style="margin-top:18px">Close</button>
  </div>
</div>
<!-- Lightbox for recipe cover image -->
<div class="recipe-img-lb" id="recipe-img-lb" role="dialog" aria-modal="true" aria-label="Full size image">
  <button class="recipe-img-lb-close" id="recipe-img-lb-close" aria-label="Close">&times;</button>
  <img src="" alt="">
</div>
<script>
(function () {
  var cover = document.querySelector('.recipe-cover-img');
  if (!cover) return;
  var lb   = document.getElementById('recipe-img-lb');
  var lbImg = lb.querySelector('img');

  function openLb() {
    lbImg.src = cover.src;
    lbImg.alt = cover.alt;
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLb() {
    lb.classList.remove('open');
    document.body.style.overflow = '';
  }

  cover.addEventListener('click', openLb);
  document.getElementById('recipe-img-lb-close').addEventListener('click', closeLb);
  lb.addEventListener('click', function (e) { if (e.target !== lbImg) closeLb(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLb(); });
}());
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
