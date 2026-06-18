<?php
/** Private one-to-one chat with your buddies (polling-based, no websockets). */
require_once __DIR__ . '/includes/bootstrap.php';

$me  = require_login();
$uid = (int)$me['id'];
ensure_messages_table();

// ---------------------------------------------------------------- which conversation?
$withId = (int)($_GET['with'] ?? 0);
if (!$withId && !empty($_GET['u'])) {
    $st = db()->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute([trim($_GET['u'])]);
    $withId = (int)($st->fetchColumn() ?: 0);
}

$active = null;
if ($withId && are_buddies($uid, $withId)) {
    $st = db()->prepare('SELECT id, username, display_name, avatar FROM users WHERE id = ? AND is_banned = 0');
    $st->execute([$withId]);
    $active = $st->fetch() ?: null;
}

// ---------------------------------------------------------------- thread list (buddies)
$ids = buddy_ids($uid);
$threads = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT id, username, display_name, avatar FROM users WHERE id IN ($in) AND is_banned = 0");
    $st->execute($ids);
    foreach ($st as $u) {
        $other = (int)$u['id'];
        $lm = db()->prepare(
            'SELECT body, created_at, sender_id FROM messages
              WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
              ORDER BY id DESC LIMIT 1'
        );
        $lm->execute([$uid, $other, $other, $uid]);
        $last = $lm->fetch();
        $uc = db()->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND sender_id = ? AND is_read = 0');
        $uc->execute([$uid, $other]);
        $u['last']      = $last ? mb_strimwidth(preg_replace('/\s+/', ' ', $last['body']), 0, 46, '…') : '';
        $u['mine_last'] = $last ? ((int)$last['sender_id'] === $uid) : false;
        $u['last_ts']   = $last ? strtotime($last['created_at']) : 0;
        $u['unread']    = (int)$uc->fetchColumn();
        $threads[] = $u;
    }
    usort($threads, fn($a, $b) => $b['last_ts'] <=> $a['last_ts']);
}

// ---------------------------------------------------------------- initial messages for the open chat
$initial = [];
$lastId  = 0;
if ($active) {
    $st = db()->prepare(
        'SELECT id, sender_id, body, created_at FROM messages
          WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
          ORDER BY id ASC LIMIT 500'
    );
    $st->execute([$uid, (int)$active['id'], (int)$active['id'], $uid]);
    $initial = $st->fetchAll();
    if ($initial) {
        $lastId = (int)$initial[count($initial) - 1]['id'];
    }
    // opening the conversation marks their messages read
    db()->prepare('UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND sender_id = ? AND is_read = 0')
        ->execute([$uid, (int)$active['id']]);
}

$pageTitle = $active ? 'Chat with ' . ($active['display_name'] ?: $active['username']) : 'Messages';
$noIndex = true;
include __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 style="margin-bottom:18px">💬 Messages</h1>
  <div class="chat-grid">

    <aside class="chat-threads panel">
      <?php if (!$threads): ?>
        <p class="muted small" style="padding:6px 4px">You have no buddies yet. Add buddies to start chatting.</p>
        <a class="btn btn-sm btn-outline" href="<?= e(url('buddies.php')) ?>">Find buddies</a>
      <?php else: ?>
        <?php foreach ($threads as $t): $isActive = $active && (int)$t['id'] === (int)$active['id']; ?>
          <a class="chat-thread<?= $isActive ? ' active' : '' ?>" href="<?= e(url('messages.php?with=' . (int)$t['id'])) ?>">
            <?= avatar_html($t, 44) ?>
            <span class="chat-thread-info">
              <span class="chat-thread-top">
                <strong><?= e($t['display_name'] ?: $t['username']) ?></strong>
                <?php if ($t['unread'] > 0): ?><span class="chat-unread"><?= (int)$t['unread'] ?></span><?php endif; ?>
              </span>
              <span class="chat-thread-last muted small">
                <?= $t['last'] !== '' ? ($t['mine_last'] ? 'You: ' : '') . e($t['last']) : 'Say hello 👋' ?>
              </span>
            </span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </aside>

    <section class="chat-main panel">
      <?php if (!$active): ?>
        <div class="empty" style="margin:auto">
          <span class="big">✉️</span>
          <p>Pick a buddy on the left to start chatting.<br>Only your buddies can message you.</p>
        </div>
      <?php else: ?>
        <div class="chat-head">
          <a href="<?= e(url('profile.php?u=' . urlencode($active['username']))) ?>" class="chat-head-user">
            <?= avatar_html($active, 40) ?>
            <strong><?= e($active['display_name'] ?: $active['username']) ?></strong>
          </a>
          <a class="muted small" href="<?= e(url('profile.php?u=' . urlencode($active['username']))) ?>">View profile</a>
        </div>

        <div class="chat-log" id="chat-log" data-with="<?= (int)$active['id'] ?>" data-last="<?= $lastId ?>">
          <?php if (!$initial): ?>
            <div class="chat-empty muted" id="chat-empty">No messages yet — send the first one!</div>
          <?php endif; ?>
          <?php foreach ($initial as $m): $mine = (int)$m['sender_id'] === $uid; ?>
            <div class="chat-msg<?= $mine ? ' mine' : '' ?>">
              <div class="chat-bubble"><?= nl2br(e($m['body'])) ?></div>
              <div class="chat-time muted"><?= e(time_ago($m['created_at'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <form class="chat-composer" id="chat-composer" data-to="<?= (int)$active['id'] ?>">
          <textarea id="chat-input" placeholder="Write a message…" maxlength="5000" rows="1" autocomplete="off"></textarea>
          <button class="btn btn-primary" type="submit">Send</button>
        </form>
      <?php endif; ?>
    </section>

  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
