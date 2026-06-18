<?php
/** CMS portal: migrations, auth, portal URLs, slug + HTML sanitiser. */

require_once dirname(__DIR__, 2) . '/includes/cms.php'; // shared settings/menu/font helpers

// ---------------------------------------------------------------- Schema

function cms_migrate(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cms_pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            body MEDIUMTEXT NULL,
            meta_description VARCHAR(200) NULL,
            visibility ENUM('public','members') NOT NULL DEFAULT 'public',
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cms_menu_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(80) NOT NULL,
            location ENUM('nav','footer') NOT NULL DEFAULT 'nav',
            parent_id INT UNSIGNED NULL,
            link_type ENUM('page','url') NOT NULL DEFAULT 'page',
            page_id INT UNSIGNED NULL,
            url VARCHAR(255) NULL,
            new_tab TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            KEY idx_loc (location, is_active, sort_order),
            KEY idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cms_settings (
            name VARCHAR(50) NOT NULL PRIMARY KEY,
            value TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cms_moderation_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NULL,
            content_type VARCHAR(20) NOT NULL,
            content_id INT UNSIGNED NOT NULL,
            action VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_content (content_type, content_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // seed default appearance settings only if missing
    $defaults = ['theme_default' => 'light', 'visitor_toggle' => '1', 'font_pairing' => 'playfair_inter'];
    $ins = $pdo->prepare('INSERT IGNORE INTO cms_settings (name, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
}

// ---------------------------------------------------------------- Portal URLs

function cms_base_url(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir    = rtrim(dirname($script), '/');
    $dir    = preg_replace('~/includes$~', '', $dir); // when called from a portal include
    return $scheme . '://' . $host . $dir;
}

function cms_url(string $path = ''): string
{
    return cms_base_url() . '/' . ltrim($path, '/');
}

function cms_redirect(string $path): never
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : cms_url($path)));
    exit;
}

// ---------------------------------------------------------------- Auth (SuperUser = site admin)

function current_superuser(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['cms_admin_id'])) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ? AND is_admin = 1 AND is_banned = 0');
            $st->execute([$_SESSION['cms_admin_id']]);
            $u = $st->fetch() ?: null;
            if (!$u) {
                unset($_SESSION['cms_admin_id']);
            }
        }
    }
    return $u;
}

function is_cms_logged_in(): bool
{
    return current_superuser() !== null;
}

function require_superuser(): array
{
    $u = current_superuser();
    if (!$u) {
        $_SESSION['cms_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        cms_redirect('login.php');
    }
    return $u;
}

// ---------------------------------------------------------------- Slugs

function cms_unique_slug(string $title, int $ignoreId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 1;
    while (true) {
        $st = db()->prepare('SELECT id FROM cms_pages WHERE slug = ? AND id <> ?');
        $st->execute([$slug, $ignoreId]);
        if (!$st->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . (++$i);
    }
}

// ---------------------------------------------------------------- Moderation log

function cms_log_action(int $adminId, string $type, int $id, string $action): void
{
    db()->prepare(
        'INSERT INTO cms_moderation_log (admin_id, content_type, content_id, action, created_at) VALUES (?, ?, ?, ?, NOW())'
    )->execute([$adminId, $type, $id, $action]);
}

// ---------------------------------------------------------------- HTML sanitiser (WYSIWYG body)

/**
 * Allow-list sanitiser for page HTML. The author is the trusted SuperUser, but
 * pages are public, so we still strip scripts, event handlers and unsafe URLs.
 */
function cms_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowed = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
        'span' => [], 'div' => [], 'figure' => [], 'figcaption' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'code' => [], 'pre' => [],
    ];
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea',
             'select', 'button', 'link', 'meta', 'base', 'title', 'head', 'noscript'];

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="cms-sanitize-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $root = $dom->getElementById('cms-sanitize-root');
    if (!$root) {
        return '';
    }
    cms_clean_node($root, $allowed, $drop);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

function cms_clean_node(DOMNode $node, array $allowed, array $drop): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            cms_clean_node($child, $allowed, $drop); // clean descendants first
            $tag = strtolower($child->nodeName);
            if (in_array($tag, $drop, true)) {
                $node->removeChild($child);
            } elseif (!isset($allowed[$tag])) {
                // unwrap unknown-but-harmless tags: keep their (already-clean) content
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            } else {
                cms_clean_attrs($child, $allowed[$tag]);
            }
        } elseif ($child instanceof DOMComment) {
            $node->removeChild($child);
        }
    }
}

function cms_clean_attrs(DOMElement $el, array $allowedAttrs): void
{
    foreach (iterator_to_array($el->attributes) as $attr) {
        $name = strtolower($attr->name);
        $val  = trim($attr->value);
        $ok = in_array($name, $allowedAttrs, true);
        if ($ok && ($name === 'href' || $name === 'src')) {
            // only safe schemes (allow relative, http(s), mailto, tel; block javascript:/data:)
            if (preg_match('~^\s*(javascript|data|vbscript)\s*:~i', $val)) {
                $ok = false;
            }
        }
        if (!$ok) {
            $el->removeAttribute($attr->name);
        }
    }
    if (strtolower($el->nodeName) === 'a' && $el->getAttribute('target') === '_blank') {
        $el->setAttribute('rel', 'noopener noreferrer');
    }
}
