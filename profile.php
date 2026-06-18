<?php
/** Member profile: wall (buddies-only), recipes, buddy list. */
require_once __DIR__ . '/includes/bootstrap.php';

$me = current_user();
$username = trim($_GET['u'] ?? '');

$st = db()->prepare('SELECT * FROM users WHERE username = ? AND is_banned = 0');
$st->execute([$username]);
$user = $st->fetch();
if (!$user) {
    flash('error', 'That member was not found.');
    redirect('index.php');
}
$uid     = (int)$user['id'];
$isOwn   = $me && (int)$me['id'] === $uid;
$status  = $me ? buddy_status((int)$me['id'], $uid) : 'none';
$canWall = $isOwn || $status === 'buddies' || ($me && is_admin());

// ---- own-wall composer (text + optional photo)
if ($isOwn && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'wall') {
    csrf_check();
    $body = trim($_POST['body'] ?? '');
    try {
        $up = handle_upload('image', 'image', 'wall');
        if ($body === '' && !$up) {
            flash('error', 'Write something or add a photo first.');
        } else {
            db()->prepare(
                "INSERT INTO wall_posts (user_id, body, image, status, created_at) VALUES (?, ?, ?, 'visible', NOW())"
            )->execute([$uid, mb_substr($body, 0, 4000), $up['file'] ?? null]);
            flash('success', 'Posted to your wall! Your buddies will see it in their feed.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('profile.php?u=' . urlencode($username));
}

$tab = $_GET['tab'] ?? 'wall';
if (!in_array($tab, ['wall', 'recipes', 'buddies'], true)) {
    $tab = 'wall';
}

// ---- data for tabs
$recipes = db()->prepare(
    "SELECT r.*, u.username, u.display_name, u.avatar,
            c.name AS category_name, cu.name AS cuisine_name,
            (SELECT COUNT(*) FROM reactions x WHERE x.target_type='recipe' AND x.target_id = r.id) reaction_count,
            (SELECT COUNT(*) FROM comments x WHERE x.target_type='recipe' AND x.target_id = r.id AND x.status='visible') comment_count
       FROM recipes r
       JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c ON c.id = r.category_id
  LEFT JOIN cuisines cu ON cu.id = r.cuisine_id
      WHERE r.user_id = ? AND r.status = 'published'
   ORDER BY r.created_at DESC"
);
$recipes->execute([$uid]);
$recipes = $recipes->fetchAll();

$buddyIds = buddy_ids($uid);
$buddies = [];
if ($buddyIds) {
    $in = implode(',', array_fill(0, count($buddyIds), '?'));
    $st = db()->prepare("SELECT id, username, display_name, avatar, bio FROM users WHERE id IN ($in) AND is_banned = 0 ORDER BY display_name");
    $st->execute($buddyIds);
    $buddies = $st->fetchAll();
}

$wallPosts = [];
if ($canWall) {
    $st = db()->prepare(
        "SELECT w.*, u.username, u.display_name, u.avatar,
                r.title AS r_title, r.image AS r_image, r.id AS r_id
           FROM wall_posts w
           JOIN users u ON u.id = w.user_id
      LEFT JOIN recipes r ON r.id = w.shared_recipe_id AND r.status = 'published'
          WHERE w.user_id = ? AND w.status = 'visible'
       ORDER BY w.created_at DESC LIMIT 50"
    );
    $st->execute([$uid]);
    $wallPosts = $st->fetchAll();
}

$displayName = $user['display_name'] ?: $user['username'];
$pageTitle = $displayName;
include __DIR__ . '/includes/header.php';
?>
<div class="container section">

  <div class="profile-head">
    <?= avatar_html($user, 110) ?>
    <div>
      <h1><?= e($displayName) ?></h1>
      <div class="muted">@<?= e($user['username']) ?> · member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
      <div class="muted small" style="margin-top:6px">🍲 <?= count($recipes) ?> recipes · 🤝 <?= count($buddies) ?> buddies</div>
    </div>
    <div class="profile-actions">
      <?php if ($isOwn): ?>
        <a class="btn btn-ghost" href="<?= e(url('settings.php')) ?>" style="background:rgba(255,255,255,.16);color:#fff">⚙️ Edit Profile</a>
        <a class="btn btn-accent" href="<?= e(url('post-recipe.php')) ?>">+ Post a Recipe</a>
      <?php elseif ($me): ?>
        <?php if ($status === 'buddies'): ?>
          <a class="btn btn-ghost" href="<?= e(url('messages.php?with=' . $uid)) ?>" style="background:rgba(255,255,255,.16);color:#fff">💬 Message</a>
          <span class="btn" style="background:rgba(255,255,255,.16);color:#fff">✓ Buddies</span>
          <button class="btn btn-danger" data-buddy-action="remove" data-user-id="<?= $uid ?>">Remove buddy</button>
        <?php elseif ($status === 'pending_out'): ?>
          <span class="btn" style="background:rgba(255,255,255,.16);color:#fff">⏳ Request sent</span>
        <?php elseif ($status === 'pending_in'): ?>
          <button class="btn btn-accent" data-buddy-action="accept" data-user-id="<?= $uid ?>">✓ Accept buddy request</button>
          <button class="btn btn-danger" data-buddy-action="reject" data-user-id="<?= $uid ?>">✗ Decline</button>
        <?php else: ?>
          <button class="btn btn-accent" data-buddy-action="send" data-user-id="<?= $uid ?>">🤝 Send Buddy Request</button>
        <?php endif; ?>
      <?php else: ?>
        <a class="btn btn-accent" href="<?= e(url('login.php')) ?>">🤝 Sign in to send a Buddy Request</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($user['bio'])): ?>
    <div class="panel" style="margin-top:20px"><strong>About</strong><?= nl2p($user['bio']) ?></div>
  <?php endif; ?>

  <div class="tabs" style="margin-top:26px">
    <a href="?u=<?= e(urlencode($username)) ?>&amp;tab=wall" class="<?= $tab === 'wall' ? 'active' : '' ?>">📌 Wall</a>
    <a href="?u=<?= e(urlencode($username)) ?>&amp;tab=recipes" class="<?= $tab === 'recipes' ? 'active' : '' ?>">🍲 Recipes (<?= count($recipes) ?>)</a>
    <a href="?u=<?= e(urlencode($username)) ?>&amp;tab=buddies" class="<?= $tab === 'buddies' ? 'active' : '' ?>">🤝 Buddies (<?= count($buddies) ?>)</a>
  </div>

  <?php if ($tab === 'wall'): ?>
    <?php if (!$canWall): ?>
      <div class="panel locked-wall">
        <span class="lock">🔒</span>
        <h3>This wall is for buddies only</h3>
        <p class="muted">Food stories and wall posts are shared between buddies — like close friends around a dinner table.<br>
        Send <?= e($displayName) ?> a buddy request to see and share posts with each other.</p>
      </div>
    <?php else: ?>
      <?php if ($isOwn): ?>
        <div class="panel" style="margin-bottom:20px">
          <h3>Share a food story 📖</h3>
          <form method="post" enctype="multipart/form-data" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="wall">
            <textarea name="body" maxlength="4000" placeholder="What did you cook today? A kitchen win, a fusion experiment, a food memory…"></textarea>
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" style="flex:1;min-width:200px">
              <button class="btn btn-primary" type="submit">Post to wall</button>
            </div>
            <p class="form-help">Visible to you and your buddies. Photo optional, max 5 MB.</p>
          </form>
        </div>
      <?php endif; ?>

      <?php if (!$wallPosts): ?>
        <div class="empty"><span class="big">🍃</span>No wall posts yet<?= $isOwn ? ' — share your first food story above!' : '.' ?></div>
      <?php endif; ?>

      <?php foreach ($wallPosts as $p): ?>
        <article class="post">
          <div class="post-head">
            <?= avatar_html($p, 44) ?>
            <div class="who">
              <a href="<?= e(url('profile.php?u=' . urlencode($p['username']))) ?>"><?= e($p['display_name'] ?: $p['username']) ?></a>
              <time><?= e(time_ago($p['created_at'])) ?></time>
            </div>
            <?php if ($isOwn || is_admin()): ?>
              <button class="btn btn-sm btn-danger" style="margin-left:auto"
                      onclick="if(confirm('Delete this post?'))fetch(BOSKETS.base+'/api/wall.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':BOSKETS.csrf},body:JSON.stringify({action:'delete',post_id:<?= (int)$p['id'] ?>})}).then(r=>r.json()).then(()=>location.reload())">🗑</button>
            <?php endif; ?>
          </div>
          <?php if ($p['body'] !== ''): ?><?= nl2p($p['body']) ?><?php endif; ?>
          <?php if ($p['image']): ?><img class="post-img" src="<?= e(url($p['image'])) ?>" alt=""><?php endif; ?>
          <?php if ($p['r_id']): ?>
            <a class="shared-recipe" href="<?= e(url('recipe.php?id=' . (int)$p['r_id'])) ?>">
              <?php if ($p['r_image']): ?><img src="<?= e(url($p['r_image'])) ?>" alt=""><?php endif; ?>
              <div><span class="muted small">Shared recipe</span><br><strong><?= e($p['r_title']) ?></strong></div>
            </a>
          <?php endif; ?>
          <div class="post-actions"><?= reaction_bar('wall', (int)$p['id']) ?></div>
          <?php $cs = comments_for('wall', (int)$p['id']); ?>
          <div id="wall-comments-<?= (int)$p['id'] ?>">
            <?php foreach ($cs as $c): ?>
              <div class="comment">
                <?= avatar_html($c, 36) ?>
                <div class="comment-body">
                  <div class="comment-head">
                    <a href="<?= e(url('profile.php?u=' . urlencode($c['username']))) ?>"><?= e($c['display_name'] ?: $c['username']) ?></a>
                    <time><?= e(time_ago($c['created_at'])) ?></time>
                  </div>
                  <div><?= nl2br(e($c['body'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($me): ?>
            <form class="comment-form" data-type="wall" data-id="<?= (int)$p['id'] ?>" data-list="#wall-comments-<?= (int)$p['id'] ?>">
              <textarea placeholder="Write a comment…" maxlength="3000"></textarea>
              <button class="btn btn-sm btn-primary" type="submit">Comment</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php elseif ($tab === 'recipes'): ?>
    <?php if (!$recipes): ?>
      <div class="empty"><span class="big">🥘</span>No recipes posted yet<?= $isOwn ? ' — <a href="' . e(url('post-recipe.php')) . '">post your first one!</a>' : '.' ?></div>
    <?php else: ?>
      <div class="grid"><?php foreach ($recipes as $r) echo recipe_card($r); ?></div>
    <?php endif; ?>

  <?php else: ?>
    <?php if (!$buddies): ?>
      <div class="empty"><span class="big">🤝</span>No buddies yet<?= $isOwn ? ' — <a href="' . e(url('buddies.php')) . '">find some food friends!</a>' : '.' ?></div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($buddies as $b): ?>
          <div class="card" style="padding:18px;display:flex;gap:14px;align-items:center">
            <?= avatar_html($b, 56) ?>
            <div style="min-width:0">
              <strong><a href="<?= e(url('profile.php?u=' . urlencode($b['username']))) ?>"><?= e($b['display_name'] ?: $b['username']) ?></a></strong>
              <div class="muted small">@<?= e($b['username']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
