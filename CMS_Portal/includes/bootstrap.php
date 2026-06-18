<?php
/**
 * CMS portal bootstrap.
 *
 * The CMS is a SEPARATE portal that shares the main website's database and
 * helper functions, but runs on its own session — so being logged into the
 * public site does NOT log you into the CMS, and vice-versa. The SuperUser is
 * the main site's Admin account (users.is_admin = 1).
 */

// Main site config (DB credentials, SITE_NAME, etc.).
require_once dirname(__DIR__, 2) . '/config.php';

// Separate session from the main website.
session_name('boskets_cms_sid');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

/** Shared PDO connection (same database as the main site). */
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

// Reuse the main site's helpers (e(), csrf_*, flash, send_mail, time_ago,
// avatar_html, slugify…). functions.php only DEFINES functions and calls db()
// lazily, so it is safe to load here with our own db() above.
require_once dirname(__DIR__, 2) . '/includes/functions.php';

require_once __DIR__ . '/cms_functions.php';

// Make sure the CMS tables exist (idempotent).
cms_migrate();
