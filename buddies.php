<?php
/** Buddy hub: pending requests, your buddy list, and member search. */
require_once __DIR__ . '/includes/bootstrap.php';

$me  = require_login();
$uid = (int)$me['id'];

// pending requests TO me
$st = db()->prepare(
    "SELECT b.id req_id, b.created_at, u.id, u.username, u.display_name, u.avatar
       FROM buddies b JOIN users u ON u.id = b.requester_id
      WHERE b.addressee_id = ? AND b.status = 'pending' AND u.is_banned = 0
   ORDER BY b.created_at DESC"
);
$st->execute([$uid]);
$incoming = $st->fetchAll();

// requests I sent that are pending
$st = db()->prepare(
    "SELECT u.id, u.username, u.display_name, u.avatar, b.created_at
       FROM buddies b JOIN users u ON u.id = b.addressee_id
      WHERE b.requester_id = ? AND b.status = 'pending' AND u.is_banned = 0
   ORDER BY b.created_at DESC"
);
$st->execute([$uid]);
$outgoing = $st->fetchAll();

// accepted buddies
$ids = buddy_ids($uid);
$buddies = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT id, username, display_name, avatar FROM users WHERE id IN ($in) AND is_banned = 0 ORDER BY display_name");
    $st->execute($ids);
    $buddies = $st->fetchAll();
}

// member search
$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $st = db()->prepare(
        'SELECT id, username, display_name, avatar FROM users
          WHERE is_banned = 0 AND id <> ? AND (username LIKE ? OR display_name LIKE ?)
       ORDER BY display_name LIMIT 30'
    );
    $st->execute([$uid, "%$q%", "%$q%"]);
    $results = $st->fetchAll();
}

$pageTitle = 'My Buddies';
include __DIR__ . '/includes/header.php';
?>
<div class="container section" style="max-width:860px">
  <h1>🤝 My Buddies</h1>
  <p class="muted">Buddies see each other's wall posts and food stories — like Facebook friends, but hungrier.</p>

  <?php if ($incoming): ?>
    <div class="panel">
      <h3>📬 Buddy requests waiting for you (<?= count($incoming) ?>)</h3>
      <?php foreach ($incoming as $r): ?>
        <div class="notif">
          <?= avatar_html($r, 44) ?>
          <div>
            <a href="<?= e(url('profile.php?u=' . urlencode($r['username']))) ?>"><strong><?= e($r['display_name'] ?: $r['username']) ?></strong></a>
            wants to be your buddy
            <div class="muted small"><?= e(time_ago($r['created_at'])) ?></div>
          </div>
          <div class="notif-actions" style="margin-left:auto">
            <button class="btn btn-sm btn-primary" data-buddy-action="accept" data-user-id="<?= (int)$r['id'] ?>">✓ Accept</button>
            <button class="btn btn-sm btn-danger" data-buddy-action="reject" data-user-id="<?= (int)$r['id'] ?>">✗ Reject</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <h3>🔎 Find members</h3>
    <form method="get" style="display:flex;gap:10px">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or username…" style="margin-top:0">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <?php if ($q !== ''): ?>
      <div style="margin-top:16px">
        <?php if (!$results): ?>
          <p class="muted">No members match "<?= e($q) ?>".</p>
        <?php endif; ?>
        <?php foreach ($results as $r): $bs = buddy_status($uid, (int)$r['id']); ?>
          <div class="buddy-mini">
            <?= avatar_html($r, 40) ?>
            <a href="<?= e(url('profile.php?u=' . urlencode($r['username']))) ?>" style="flex:1">
              <strong><?= e($r['display_name'] ?: $r['username']) ?></strong> <span class="muted small">@<?= e($r['username']) ?></span>
            </a>
            <?php if ($bs === 'buddies'): ?>
              <a class="btn btn-sm btn-outline" href="<?= e(url('messages.php?with=' . (int)$r['id'])) ?>">💬 Message</a>
              <span class="pill pill-green">✓ Buddies</span>
            <?php elseif ($bs === 'pending_out'): ?><span class="pill pill-orange">⏳ Requested</span>
            <?php elseif ($bs === 'pending_in'): ?>
              <button class="btn btn-sm btn-primary" data-buddy-action="accept" data-user-id="<?= (int)$r['id'] ?>">✓ Accept</button>
            <?php else: ?>
              <button class="btn btn-sm btn-outline" data-buddy-action="send" data-user-id="<?= (int)$r['id'] ?>">🤝 Add buddy</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>💚 Your buddy list (<?= count($buddies) ?>)</h3>
    <?php if (!$buddies): ?>
      <p class="muted">No buddies yet. Search above, or visit a member's profile and hit <strong>Send Buddy Request</strong>.</p>
    <?php endif; ?>
    <?php foreach ($buddies as $b): ?>
      <div class="buddy-mini">
        <?= avatar_html($b, 40) ?>
        <a href="<?= e(url('profile.php?u=' . urlencode($b['username']))) ?>" style="flex:1">
          <strong><?= e($b['display_name'] ?: $b['username']) ?></strong> <span class="muted small">@<?= e($b['username']) ?></span>
        </a>
        <a class="btn btn-sm btn-outline" href="<?= e(url('messages.php?with=' . (int)$b['id'])) ?>">💬 Message</a>
        <button class="btn btn-sm btn-danger" data-buddy-action="remove" data-user-id="<?= (int)$b['id'] ?>">Remove</button>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($outgoing): ?>
    <div class="panel">
      <h3>⏳ Requests you sent</h3>
      <?php foreach ($outgoing as $r): ?>
        <div class="buddy-mini">
          <?= avatar_html($r, 40) ?>
          <a href="<?= e(url('profile.php?u=' . urlencode($r['username']))) ?>" style="flex:1">
            <strong><?= e($r['display_name'] ?: $r['username']) ?></strong>
          </a>
          <span class="muted small">sent <?= e(time_ago($r['created_at'])) ?> — awaiting reply</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
