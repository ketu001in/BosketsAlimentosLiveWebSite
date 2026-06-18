<?php
/** Notification helpers: unread count (GET ?count=1) and mark-all-read (POST). */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_login_json();

if (isset($_GET['count'])) {
    json_out(['ok' => true, 'unread' => unread_count((int)$me['id'])]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$me['id']]);
    json_out(['ok' => true]);
}

json_fail('Bad request.');
