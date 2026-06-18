<?php
/** One-to-one buddy chat backend.
 *
 *  GET  ?unread=1            -> { ok, unread }                total unread for the nav badge
 *  GET  ?threads=1           -> { ok, threads:[...] }         conversation list + unread per buddy
 *  GET  ?with=ID[&after=MID] -> { ok, messages:[...], me }    messages with one buddy (marks read)
 *  POST { to, body }         -> { ok, message:{...} }         send a message (buddies only)
 *
 *  Buddies-only. Plain polling (no websockets) keeps it shared-hosting friendly.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
ensure_messages_table();

$me  = require_login_json();
$uid = (int)$me['id'];

/* ------------------------------------------------ GET: unread badge count */
if (isset($_GET['unread'])) {
    json_out(['ok' => true, 'unread' => unread_messages_count($uid)]);
}

/* ------------------------------------------------ GET: conversation list */
if (isset($_GET['threads'])) {
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
            $threads[] = [
                'user_id'      => $other,
                'username'     => $u['username'],
                'display_name' => $u['display_name'] ?: $u['username'],
                'avatar'       => $u['avatar'] ? url($u['avatar']) : '',
                'last'         => $last ? mb_strimwidth(preg_replace('/\s+/', ' ', $last['body']), 0, 48, '…') : '',
                'last_time'    => $last ? time_ago($last['created_at']) : '',
                'last_ts'      => $last ? strtotime($last['created_at']) : 0,
                'mine_last'    => $last ? ((int)$last['sender_id'] === $uid) : false,
                'unread'       => (int)$uc->fetchColumn(),
            ];
        }
        usort($threads, fn($a, $b) => $b['last_ts'] <=> $a['last_ts']);
    }
    json_out(['ok' => true, 'threads' => $threads]);
}

/* ------------------------------------------------ POST: send a message */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    $to   = (int)($data['to'] ?? 0);
    $body = trim((string)($data['body'] ?? ''));

    if ($to <= 0 || $to === $uid) {
        json_fail('Invalid recipient.');
    }
    if (!are_buddies($uid, $to)) {
        json_fail('You can only message your buddies.', 403);
    }
    if ($body === '') {
        json_fail('Type a message first.');
    }
    $body = mb_substr($body, 0, 5000);

    db()->prepare(
        'INSERT INTO messages (sender_id, recipient_id, body, created_at) VALUES (?, ?, ?, NOW())'
    )->execute([$uid, $to, $body]);
    $mid = (int)db()->lastInsertId();

    json_out(['ok' => true, 'message' => [
        'id'         => $mid,
        'mine'       => true,
        'body'       => $body,
        'time'       => 'just now',
    ]]);
}

/* ------------------------------------------------ GET: messages with one buddy */
$other = (int)($_GET['with'] ?? 0);
$after = (int)($_GET['after'] ?? 0);
if ($other <= 0) {
    json_fail('Bad request.');
}
if (!are_buddies($uid, $other)) {
    json_fail('You can only message your buddies.', 403);
}

$sql = 'SELECT id, sender_id, body, created_at FROM messages
         WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))';
$params = [$uid, $other, $other, $uid];
if ($after > 0) {
    $sql .= ' AND id > ?';
    $params[] = $after;
}
$sql .= ' ORDER BY id ASC LIMIT 500';
$st = db()->prepare($sql);
$st->execute($params);

$messages = [];
foreach ($st as $m) {
    $messages[] = [
        'id'   => (int)$m['id'],
        'mine' => (int)$m['sender_id'] === $uid,
        'body' => $m['body'],
        'time' => time_ago($m['created_at']),
    ];
}

// mark anything that buddy sent me as read
db()->prepare('UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND sender_id = ? AND is_read = 0')
    ->execute([$uid, $other]);

json_out(['ok' => true, 'me' => $uid, 'messages' => $messages]);
