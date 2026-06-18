<?php
/** Shared helper functions. */

// ---------------------------------------------------------------- Output

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Escaped multi-line text -> paragraphs with <br>. */
function nl2p(?string $s): string
{
    $blocks = preg_split('/\R{2,}/', trim((string)$s)) ?: [];
    $html = '';
    foreach ($blocks as $b) {
        if ($b !== '') {
            $html .= '<p>' . nl2br(e($b)) . '</p>';
        }
    }
    return $html;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('j M Y', strtotime($datetime));
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
    return $slug !== '' ? $slug : 'recipe';
}

// ---------------------------------------------------------------- Flash messages

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Dies on a bad token. Accepts the token from POST or the X-CSRF header. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$sent)) {
        http_response_code(419);
        exit('Session expired — please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------- JSON API helpers

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function json_fail(string $msg, int $code = 400): never
{
    json_out(['ok' => false, 'error' => $msg], $code);
}

function require_login_json(): array
{
    $u = current_user();
    if ($u === null) {
        json_fail('Please sign in first.', 401);
    }
    return $u;
}

// ---------------------------------------------------------------- Uploads

/**
 * Validate and store an uploaded file.
 *
 * @param string $field   name in $_FILES
 * @param string $kind    'image' or 'media' (media also allows short videos)
 * @param string $subdir  folder under /uploads, e.g. 'avatars'
 * @return array|null     ['file' => relative path, 'type' => 'image'|'video'],
 *                        null when no file was submitted
 * @throws RuntimeException with a user-friendly message on invalid input
 */
function handle_upload(string $field, string $kind, string $subdir): ?array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed — the file may be larger than the server allows.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']) ?: '';

    $images = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $videos = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    if (isset($images[$mime])) {
        if ($f['size'] > MAX_IMAGE_BYTES) {
            throw new RuntimeException('Images must be 5 MB or smaller.');
        }
        $ext = $images[$mime];
        $type = 'image';
    } elseif ($kind === 'media' && isset($videos[$mime])) {
        if ($f['size'] > MAX_VIDEO_BYTES) {
            throw new RuntimeException('Videos must be 25 MB or smaller.');
        }
        $ext = $videos[$mime];
        $type = 'video';
    } else {
        throw new RuntimeException($kind === 'media'
            ? 'Only JPG, PNG, WEBP, GIF images or MP4/WEBM/MOV videos are allowed.'
            : 'Only JPG, PNG, WEBP or GIF images are allowed.');
    }

    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return ['file' => 'uploads/' . $subdir . '/' . $name, 'type' => $type];
}

function delete_upload(?string $relPath): void
{
    if ($relPath && str_starts_with($relPath, 'uploads/')) {
        $abs = dirname(__DIR__) . '/' . $relPath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

// ---------------------------------------------------------------- Email

/** From: address for outgoing mail. */
function mail_from(): string
{
    if (defined('MAIL_FROM') && MAIL_FROM !== '') {
        return MAIL_FROM;
    }
    $host = preg_replace('/^www\.|:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return 'no-reply@' . $host;
}

/** Plain-text email via PHP mail(). Returns false when sending fails. */
function send_mail(string $to, string $subject, string $body): bool
{
    $from = mail_from();
    $headers = implode("\r\n", [
        'From: ' . SITE_NAME . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $subject, $body, $headers);
}

// ---------------------------------------------------------------- Password reset

/** Make sure the password_resets table exists (covers pre-existing installs). */
function ensure_password_resets_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY idx_user (user_id),
            KEY idx_token (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ---------------------------------------------------------------- Schema upgrades (added features)
// These let an already-installed site (local or live) pick up the columns and
// tables that newer features need, without re-running install.php. Each is
// guarded so the work happens at most once per request.

/** recipes.youtube_url — optional video link shown on the recipe page. */
function ensure_recipe_youtube_column(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $exists = db()->query("SHOW COLUMNS FROM recipes LIKE 'youtube_url'")->fetch();
    if (!$exists) {
        db()->exec("ALTER TABLE recipes ADD COLUMN youtube_url VARCHAR(255) NULL AFTER story");
    }
}

/** One-to-one private messages between buddies. */
function ensure_messages_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            recipient_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_pair (sender_id, recipient_id, id),
            KEY idx_inbox (recipient_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** Links a Google/Facebook identity to a local user account. */
function ensure_oauth_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS oauth_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            provider VARCHAR(20) NOT NULL,
            provider_uid VARCHAR(190) NOT NULL,
            email VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_provider (provider, provider_uid),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ---------------------------------------------------------------- YouTube

/** Extract the 11-char video id from any common YouTube URL (or a raw id). */
function youtube_id(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
        return $url;
    }
    return '';
}

/** Responsive privacy-friendly embed for a stored YouTube link; '' when invalid. */
function youtube_embed_html(?string $url, string $title = 'Recipe video'): string
{
    $id = youtube_id($url);
    if ($id === '') {
        return '';
    }
    $src = 'https://www.youtube-nocookie.com/embed/' . $id;
    return '<div class="video-embed"><iframe src="' . e($src) . '" title="' . e($title)
         . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
         . ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
}

// ---------------------------------------------------------------- Messages (buddy chat)

/** Total unread private messages for a user (0 if the table is missing). */
function unread_messages_count(int $userId): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0');
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// ---------------------------------------------------------------- Master lists (find-or-create)

/**
 * Look up a name in a master table (ingredients, categories, cuisines, origins,
 * forum_categories); create it if new so it becomes available to everyone.
 */
function find_or_create(string $table, string $name, ?int $userId): ?int
{
    $allowed = ['ingredients', 'categories', 'cuisines', 'origins', 'forum_categories'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Bad master table.');
    }
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '' || mb_strlen($name) > 100) {
        return null;
    }
    $st = db()->prepare("SELECT id FROM `$table` WHERE name = ?");
    $st->execute([$name]);
    if ($id = $st->fetchColumn()) {
        return (int)$id;
    }
    $st = db()->prepare("INSERT INTO `$table` (name, created_by) VALUES (?, ?)");
    $st->execute([$name, $userId]);
    return (int)db()->lastInsertId();
}

// ---------------------------------------------------------------- Buddies

/** 'self' | 'buddies' | 'pending_out' | 'pending_in' | 'none' */
function buddy_status(int $me, int $other): string
{
    if ($me === $other) {
        return 'self';
    }
    $st = db()->prepare(
        'SELECT requester_id, status FROM buddies
          WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)
          ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$me, $other, $other, $me]);
    $row = $st->fetch();
    if (!$row || $row['status'] === 'rejected') {
        return 'none';
    }
    if ($row['status'] === 'accepted') {
        return 'buddies';
    }
    return ((int)$row['requester_id'] === $me) ? 'pending_out' : 'pending_in';
}

function are_buddies(int $a, int $b): bool
{
    return $a === $b || buddy_status($a, $b) === 'buddies';
}

/** Ids of accepted buddies of a user. */
function buddy_ids(int $userId): array
{
    $st = db()->prepare(
        "SELECT IF(requester_id = ?, addressee_id, requester_id) AS bid
           FROM buddies
          WHERE status = 'accepted' AND (requester_id = ? OR addressee_id = ?)"
    );
    $st->execute([$userId, $userId, $userId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// ---------------------------------------------------------------- Notifications

function notify(int $userId, ?int $actorId, string $type, ?string $targetType, ?int $targetId, string $message): void
{
    if ($actorId !== null && $actorId === $userId) {
        return; // don't notify people about their own actions
    }
    $st = db()->prepare(
        'INSERT INTO notifications (user_id, actor_id, type, target_type, target_id, message, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $st->execute([$userId, $actorId, $type, $targetType, $targetId, $message]);
}

function unread_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

/** Link target for a notification row. */
function notification_url(array $n): string
{
    return match ($n['target_type']) {
        'recipe' => 'recipe.php?id=' . (int)$n['target_id'],
        'topic'  => 'forum-topic.php?id=' . (int)$n['target_id'],
        'wall'   => 'wall-post.php?id=' . (int)$n['target_id'],
        'user'   => 'buddies.php',
        default  => 'notifications.php',
    };
}

// ---------------------------------------------------------------- Reactions & comments

const REACTION_EMOJI = ['like' => '👍', 'love' => '❤️', 'yum' => '😋', 'wow' => '🤩'];

/** ['counts' => [reaction => n], 'total' => n, 'mine' => reaction|null] */
function reaction_summary(string $targetType, int $targetId, ?int $userId = null): array
{
    $st = db()->prepare(
        'SELECT reaction, COUNT(*) c FROM reactions WHERE target_type = ? AND target_id = ? GROUP BY reaction'
    );
    $st->execute([$targetType, $targetId]);
    $counts = [];
    $total = 0;
    foreach ($st as $row) {
        $counts[$row['reaction']] = (int)$row['c'];
        $total += (int)$row['c'];
    }
    $mine = null;
    if ($userId) {
        $st = db()->prepare(
            'SELECT reaction FROM reactions WHERE target_type = ? AND target_id = ? AND user_id = ?'
        );
        $st->execute([$targetType, $targetId, $userId]);
        $mine = $st->fetchColumn() ?: null;
    }
    return ['counts' => $counts, 'total' => $total, 'mine' => $mine];
}

function comment_count(string $targetType, int $targetId): int
{
    $st = db()->prepare(
        "SELECT COUNT(*) FROM comments WHERE target_type = ? AND target_id = ? AND status = 'visible'"
    );
    $st->execute([$targetType, $targetId]);
    return (int)$st->fetchColumn();
}

function comments_for(string $targetType, int $targetId): array
{
    $st = db()->prepare(
        "SELECT c.*, u.username, u.display_name, u.avatar
           FROM comments c JOIN users u ON u.id = c.user_id
          WHERE c.target_type = ? AND c.target_id = ? AND c.status = 'visible'
          ORDER BY c.created_at ASC"
    );
    $st->execute([$targetType, $targetId]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------- Rendering partials

/** Avatar image or an initials circle. $user needs avatar + display_name. */
function avatar_html(array $user, int $size = 40): string
{
    $name = $user['display_name'] ?: ($user['username'] ?? '?');
    if (!empty($user['avatar'])) {
        return '<img class="avatar" src="' . e(url($user['avatar'])) . '" alt="' . e($name) . '" width="' . $size . '" height="' . $size . '">';
    }
    $initials = mb_strtoupper(mb_substr($name, 0, 1));
    $hue = crc32($user['username'] ?? $name) % 360;
    return '<span class="avatar avatar-initials" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . (int)($size * .45) . 'px;background:hsl(' . $hue . ',45%,38%)">' . e($initials) . '</span>';
}

/** Recipe card used on listings, profiles and the feed. */
function recipe_card(array $r): string
{
    $link = url('recipe.php?id=' . (int)$r['id']);
    $img  = !empty($r['image']) ? url($r['image']) : '';
    $meta = array_filter([$r['category_name'] ?? null, $r['cuisine_name'] ?? null]);
    $html  = '<article class="card recipe-card">';
    $html .= '<a class="card-img" href="' . e($link) . '">';
    $html .= $img ? '<img src="' . e($img) . '" alt="' . e($r['title']) . '" loading="lazy">' : '<span class="card-img-empty">🥗</span>';
    if (!empty($r['is_featured'])) {
        $html .= '<span class="badge badge-featured">★ Featured</span>';
    }
    $html .= '</a><div class="card-body">';
    $html .= '<h3 class="card-title"><a href="' . e($link) . '">' . e($r['title']) . '</a></h3>';
    if ($meta) {
        $html .= '<p class="card-meta">' . e(implode(' · ', $meta)) . '</p>';
    }
    $html .= '<div class="card-foot">';
    $html .= '<a class="card-author" href="' . e(url('profile.php?u=' . urlencode($r['username']))) . '">'
           . avatar_html($r, 26) . '<span>' . e($r['display_name'] ?: $r['username']) . '</span></a>';
    $html .= '<span class="card-stats">👍 ' . (int)($r['reaction_count'] ?? 0) . ' &nbsp; 💬 ' . (int)($r['comment_count'] ?? 0) . '</span>';
    $html .= '</div></div></article>';
    return $html;
}

/** Reaction bar (works for recipes, wall posts, topics, comments). */
function reaction_bar(string $targetType, int $targetId, ?array $summary = null): string
{
    $me = current_user();
    $summary ??= reaction_summary($targetType, $targetId, $me['id'] ?? null);
    $html = '<div class="react-bar" data-type="' . e($targetType) . '" data-id="' . $targetId . '">';
    foreach (REACTION_EMOJI as $key => $emoji) {
        $count  = $summary['counts'][$key] ?? 0;
        $active = ($summary['mine'] === $key) ? ' active' : '';
        $html .= '<button type="button" class="react-btn' . $active . '" data-reaction="' . $key . '" title="' . ucfirst($key) . '">'
               . $emoji . ' <span class="react-count">' . $count . '</span></button>';
    }
    $html .= '</div>';
    return $html;
}

// ---------------------------------------------------------------- Pagination

function paginate(int $total, int $page, string $baseQuery): string
{
    $pages = max(1, (int)ceil($total / PER_PAGE));
    if ($pages <= 1) {
        return '';
    }
    $page = max(1, min($page, $pages));
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $page) {
            $html .= '<span class="page current">' . $i . '</span>';
        } elseif ($i <= 2 || $i > $pages - 2 || abs($i - $page) <= 2) {
            $html .= '<a class="page" href="?' . e($baseQuery . ($baseQuery ? '&' : '') . 'page=' . $i) . '">' . $i . '</a>';
        } elseif (abs($i - $page) === 3) {
            $html .= '<span class="page gap">…</span>';
        }
    }
    return $html . '</nav>';
}
