<?php
/**
 * Bootstrap — every page includes this first.
 * Starts the session, loads config + helpers, exposes db() and current_user().
 */

require_once dirname(__DIR__) . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/functions.php';

/** Shared PDO connection. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

/** Currently logged-in user row, or null. Cached per request. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ? AND is_banned = 0');
            $st->execute([$_SESSION['user_id']]);
            $user = $st->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']); // banned or deleted mid-session
            }
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && (int)$u['is_admin'] === 1;
}

/** Redirect guests to the login page, remembering where they wanted to go. */
function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ((int)$u['is_admin'] !== 1) {
        flash('error', 'That area is for administrators only.');
        redirect('index.php');
    }
    return $u;
}

/** Base URL of the site (no trailing slash). */
function base_url(): string
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Folder the app lives in (handles installs in a subdirectory).
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // Strip known subfolders so links work from /admin and /api too.
    $dir    = preg_replace('~/(admin|api|includes)$~', '', $dir);
    return $scheme . '://' . $host . $dir;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
    exit;
}
