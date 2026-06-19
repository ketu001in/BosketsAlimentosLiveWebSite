<?php
/** Homepage: hero, featured + latest recipes, live forum activity. */
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();

$featured = $pdo->query(
    "SELECT r.*, u.username, u.display_name, u.avatar,
            c.name AS category_name, cu.name AS cuisine_name,
            (SELECT COUNT(*) FROM reactions x WHERE x.target_type='recipe' AND x.target_id = r.id) reaction_count,
            (SELECT COUNT(*) FROM comments x WHERE x.target_type='recipe' AND x.target_id = r.id AND x.status='visible') comment_count
       FROM recipes r JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c ON c.id = r.category_id
  LEFT JOIN cuisines cu ON cu.id = r.cuisine_id
      WHERE r.status = 'published' AND r.is_featured = 1
   ORDER BY r.created_at DESC LIMIT 4"
)->fetchAll();

$latest = $pdo->query(
    "SELECT r.*, u.username, u.display_name, u.avatar,
            c.name AS category_name, cu.name AS cuisine_name,
            (SELECT COUNT(*) FROM reactions x WHERE x.target_type='recipe' AND x.target_id = r.id) reaction_count,
            (SELECT COUNT(*) FROM comments x WHERE x.target_type='recipe' AND x.target_id = r.id AND x.status='visible') comment_count
       FROM recipes r JOIN users u ON u.id = r.user_id
  LEFT JOIN categories c ON c.id = r.category_id
  LEFT JOIN cuisines cu ON cu.id = r.cuisine_id
      WHERE r.status = 'published'
   ORDER BY r.created_at DESC LIMIT 8"
)->fetchAll();

$hotTopics = $pdo->query(
    "SELECT t.id, t.title, t.created_at, u.username, u.display_name, u.avatar,
            (SELECT COUNT(*) FROM comments c WHERE c.target_type='topic' AND c.target_id = t.id AND c.status='visible') replies
       FROM forum_topics t JOIN users u ON u.id = t.user_id
      WHERE t.status = 'visible'
   ORDER BY t.created_at DESC LIMIT 5"
)->fetchAll();

$counts = [
    'recipes' => $pdo->query("SELECT COUNT(*) FROM recipes WHERE status='published'")->fetchColumn(),
    'members' => $pdo->query('SELECT COUNT(*) FROM users WHERE is_banned = 0')->fetchColumn(),
    'topics'  => $pdo->query("SELECT COUNT(*) FROM forum_topics WHERE status='visible'")->fetchColumn(),
];

$starRecipe = get_star_recipe();

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>
<section class="hero<?= $starRecipe ? ' hero-compact' : '' ?>">
  <div class="container">
    <h1>A world of <em>truly fusion</em> food</h1>
    <p>Welcome to <?= e(SITE_NAME) ?> — a 100% vegetarian community where cuisines collide deliciously. Post recipes, swap food stories, debate in the forum and cook alongside your buddies.</p>
    <div class="hero-actions">
      <?php if (is_logged_in()): ?>
        <a class="btn btn-accent" href="<?= e(url('post-recipe.php')) ?>">Post a New Recipe</a>
        <a class="btn btn-outline" style="border-color:#fff;color:#fff" href="<?= e(url('feed.php')) ?>">My Feed</a>
      <?php else: ?>
        <a class="btn btn-accent" href="<?= e(url('register.php')) ?>">Join free — start cooking</a>
        <a class="btn btn-outline" style="border-color:#fff;color:#fff" href="<?= e(url('recipes.php')) ?>">Browse recipes</a>
      <?php endif; ?>
    </div>
    <div class="hero-badges">
      <div><strong><?= (int)$counts['recipes'] ?></strong> fusion recipes</div>
      <div><strong><?= (int)$counts['members'] ?></strong> hungry members</div>
      <div><strong><?= (int)$counts['topics'] ?></strong> forum discussions</div>
      <div><strong>100%</strong> vegetarian</div>
    </div>
  </div>
</section>

<?php if ($starRecipe): ?>
<section class="star-recipe-section">
  <div class="container">
    <div class="star-recipe-inner">
      <a class="star-recipe-img" href="<?= e(url('recipe.php?id=' . (int)$starRecipe['id'])) ?>">
        <?php if ($starRecipe['image']): ?>
          <img src="<?= e(url($starRecipe['image'])) ?>" alt="<?= e($starRecipe['title']) ?>">
          <div class="star-recipe-bg-blur"><img src="<?= e(url($starRecipe['image'])) ?>" aria-hidden="true"></div>
        <?php else: ?>
          <div class="star-recipe-img-empty">🍳</div>
        <?php endif; ?>
      </a>
      <div class="star-recipe-body">
        <span class="star-badge">&#11088; <?= e($starRecipe['star_label']) ?></span>
        <h2 class="star-recipe-title">
          <a href="<?= e(url('recipe.php?id=' . (int)$starRecipe['id'])) ?>"><?= e($starRecipe['title']) ?></a>
        </h2>
        <?php
          $starDesc = trim($starRecipe['story'] ?? '');
          if ($starDesc && strtolower($starDesc) !== strtolower($starRecipe['title'])) {
              echo '<p class="star-recipe-desc">' . e(mb_strimwidth(preg_replace('/\s+/', ' ', $starDesc), 0, 200, '…')) . '</p>';
          }
        ?>
        <div class="star-recipe-meta">
          <?php if ($starRecipe['category_name']): ?><span class="star-tag"><?= e($starRecipe['category_name']) ?></span><?php endif; ?>
          <?php if ($starRecipe['cuisine_name']): ?><span class="star-tag"><?= e($starRecipe['cuisine_name']) ?></span><?php endif; ?>
          <span class="star-tag">100% Veg</span>
        </div>
        <a class="btn btn-accent star-recipe-btn" href="<?= e(url('recipe.php?id=' . (int)$starRecipe['id'])) ?>">View Recipe →</a>
        <p class="star-recipe-author">by <?= e($starRecipe['display_name'] ?: $starRecipe['username']) ?></p>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Featured fusions</h2>
      <a class="more" href="<?= e(url('recipes.php')) ?>">All recipes →</a>
    </div>
    <div class="grid"><?php foreach ($featured as $r) echo recipe_card($r); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="background:var(--green-50)">
  <div class="container">
    <div class="section-head">
      <h2>Fresh from the kitchen</h2>
      <a class="more" href="<?= e(url('recipes.php')) ?>">All recipes →</a>
    </div>
    <?php if (!$latest): ?>
      <div class="empty"><span class="big">🍳</span>No recipes yet — <a href="<?= e(url('post-recipe.php')) ?>">be the first to post one!</a></div>
    <?php else: ?>
      <div class="grid"><?php foreach ($latest as $r) echo recipe_card($r); ?></div>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>From the forum</h2>
      <a class="more" href="<?= e(url('forum.php')) ?>">Visit the forum →</a>
    </div>
    <?php if (!$hotTopics): ?>
      <div class="empty"><span class="big">💭</span>No discussions yet — <a href="<?= e(url('new-topic.php')) ?>">start the first topic!</a></div>
    <?php endif; ?>
    <?php foreach ($hotTopics as $t): ?>
      <div class="topic-row">
        <?= avatar_html($t, 40) ?>
        <div class="topic-main">
          <h3><a href="<?= e(url('forum-topic.php?id=' . (int)$t['id'])) ?>"><?= e($t['title']) ?></a></h3>
          <span class="muted small">by <?= e($t['display_name'] ?: $t['username']) ?> · <?= e(time_ago($t['created_at'])) ?></span>
        </div>
        <div class="topic-stats"><span><strong><?= (int)$t['replies'] ?></strong>replies</span></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!is_logged_in()): ?>
<section class="section" style="background:linear-gradient(120deg,var(--orange-100),var(--green-100))">
  <div class="container" style="text-align:center;padding:30px 20px">
    <h2>Bring your taste buds. We'll bring the community.</h2>
    <p class="muted" style="max-width:560px;margin:10px auto 22px">Create a free account to post recipes, build your wall of food stories, make buddies and join the tastiest forum on the internet.</p>
    <a class="btn btn-primary" href="<?= e(url('register.php')) ?>" style="font-size:16px;padding:13px 36px">Join <?= e(SITE_NAME) ?> free</a>
  </div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
