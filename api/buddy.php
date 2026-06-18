<?php
/** Buddy requests: send / accept / reject / remove. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_login_json();
csrf_check();
$in = json_decode(file_get_contents('php://input'), true) ?: [];

$action = $in['action'] ?? '';
$otherId = (int)($in['user_id'] ?? 0);

if ($otherId < 1 || $otherId === (int)$me['id']) {
    json_fail('Invalid user.');
}
$st = db()->prepare('SELECT id, username, display_name FROM users WHERE id = ? AND is_banned = 0');
$st->execute([$otherId]);
$other = $st->fetch();
if (!$other) {
    json_fail('User not found.', 404);
}

$meName = $me['display_name'] ?: $me['username'];
$status = buddy_status((int)$me['id'], $otherId);

switch ($action) {
    case 'send':
        if ($status === 'buddies') {
            json_fail('You are already buddies.');
        }
        if ($status === 'pending_out') {
            json_fail('Request already sent — waiting for them to respond.');
        }
        if ($status === 'pending_in') {
            json_fail('They already sent you a request — check your buddy requests!');
        }
        // clear any old rejected rows between the pair, then create a fresh request
        db()->prepare(
            "DELETE FROM buddies WHERE status = 'rejected'
              AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))"
        )->execute([$me['id'], $otherId, $otherId, $me['id']]);
        db()->prepare(
            "INSERT INTO buddies (requester_id, addressee_id, status, created_at) VALUES (?, ?, 'pending', NOW())"
        )->execute([$me['id'], $otherId]);
        notify($otherId, (int)$me['id'], 'buddy_request', 'user', (int)$me['id'],
               $meName . ' sent you a BUDDY REQUEST 🤝');
        json_out(['ok' => true, 'message' => 'Buddy request sent!']);

    case 'accept':
    case 'reject':
        $st = db()->prepare(
            "SELECT id FROM buddies WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'"
        );
        $st->execute([$otherId, $me['id']]);
        $reqId = $st->fetchColumn();
        if (!$reqId) {
            json_fail('No pending request from that user.');
        }
        $newStatus = $action === 'accept' ? 'accepted' : 'rejected';
        db()->prepare('UPDATE buddies SET status = ?, responded_at = NOW() WHERE id = ?')
            ->execute([$newStatus, $reqId]);
        if ($action === 'accept') {
            notify($otherId, (int)$me['id'], 'buddy_accept', 'user', (int)$me['id'],
                   $meName . ' accepted your buddy request 🎉 You are now buddies!');
            json_out(['ok' => true, 'message' => 'You are now buddies with ' . ($other['display_name'] ?: $other['username']) . '!']);
        }
        json_out(['ok' => true, 'message' => 'Request declined.']);

    case 'remove':
        db()->prepare(
            "DELETE FROM buddies WHERE status = 'accepted'
              AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))"
        )->execute([$me['id'], $otherId, $otherId, $me['id']]);
        json_out(['ok' => true, 'message' => 'Removed from your buddy list.']);

    default:
        json_fail('Unknown action.');
}
