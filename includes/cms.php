<?php
/**
 * Shared CMS helpers — used BOTH by the public site (header/footer hooks,
 * page.php) and by the CMS portal. Everything here is defensive: if the CMS
 * tables don't exist yet, the read helpers quietly return empty/defaults so the
 * public website keeps working untouched.
 *
 * This file must NOT depend on the main site's url()/base_url() (those don't
 * exist inside the CMS portal), so it computes site URLs itself.
 */

if (defined('CMS_HELPERS_LOADED')) {
    return;
}
define('CMS_HELPERS_LOADED', 1);

/** Absolute URL to something at the MAIN SITE ROOT, from any context. */
function cms_site_url(string $path = ''): string
{
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir    = rtrim(dirname($script), '/');
    // Strip known sub-locations so we always land on the site root.
    $dir    = preg_replace('~/(admin|api|includes|CMS_Portal(/includes)?)$~', '', $dir);
    return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
}

// ---------------------------------------------------------------- Settings (key/value)

function cms_all_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db()->query('SELECT name, value FROM cms_settings') as $row) {
            $cache[$row['name']] = $row['value'];
        }
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function cms_setting(string $name, ?string $default = null): ?string
{
    $all = cms_all_settings();
    return array_key_exists($name, $all) ? $all[$name] : $default;
}

function cms_set_setting(string $name, string $value): void
{
    db()->prepare(
        'INSERT INTO cms_settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    )->execute([$name, $value]);
}

// ---------------------------------------------------------------- Fonts (curated pairings)

/** key => [label, display-family CSS, body-family CSS, Google Fonts query | ''] */
function cms_font_pairings(): array
{
    return [
        'playfair_inter' => [
            'Playfair Display + Inter (current)',
            "'Playfair Display', Georgia, serif",
            "'Inter', system-ui, -apple-system, sans-serif",
            'Playfair+Display:ital,wght@0,400;0,500;0,600;1,500&family=Inter:wght@400;500;600;700',
        ],
        'marcellus_manrope' => [
            'Marcellus + Manrope',
            "'Marcellus', Georgia, serif",
            "'Manrope', system-ui, sans-serif",
            'Marcellus&family=Manrope:wght@400;500;600;700',
        ],
        'cormorant_jost' => [
            'Cormorant + Jost',
            "'Cormorant Garamond', Georgia, serif",
            "'Jost', system-ui, sans-serif",
            'Cormorant+Garamond:wght@500;600;700&family=Jost:wght@400;500;600',
        ],
        'fraunces_worksans' => [
            'Fraunces + Work Sans',
            "'Fraunces', Georgia, serif",
            "'Work Sans', system-ui, sans-serif",
            'Fraunces:opsz,wght@9..144,400;9..144,600&family=Work+Sans:wght@400;500;600',
        ],
        'system' => [
            'System default (fastest, no web fonts)',
            "Georgia, 'Times New Roman', serif",
            "system-ui, -apple-system, Segoe UI, Roboto, sans-serif",
            '',
        ],
    ];
}

/** Resolved appearance config for the public site. */
function cms_theme_config(): array
{
    $theme = cms_setting('theme_default', 'light');
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
        $theme = 'light';
    }
    $toggle = cms_setting('visitor_toggle', '1') === '1';
    $fontKey = cms_setting('font_pairing', 'playfair_inter');
    $fonts = cms_font_pairings();
    if (!isset($fonts[$fontKey])) {
        $fontKey = 'playfair_inter';
    }
    [$label, $display, $body, $google] = $fonts[$fontKey];
    return [
        'theme'   => $theme,
        'toggle'  => $toggle,
        'font'    => $fontKey,
        'display' => $display,
        'body'    => $body,
        'google'  => $google,
        'is_default_fonts' => ($fontKey === 'playfair_inter'),
    ];
}

// ---------------------------------------------------------------- Menu items

/** Active menu items for a location ('nav'|'footer') as a parent→children tree. */
function cms_menu_tree(string $location): array
{
    try {
        $st = db()->prepare(
            "SELECT mi.*, p.slug AS page_slug, p.status AS page_status, p.visibility AS page_visibility
               FROM cms_menu_items mi
          LEFT JOIN cms_pages p ON p.id = mi.page_id
              WHERE mi.location = ? AND mi.is_active = 1
           ORDER BY (mi.parent_id IS NOT NULL), mi.sort_order, mi.id"
        );
        $st->execute([$location]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $byId = [];
    $tree = [];
    foreach ($rows as $r) {
        $r['children'] = [];
        $byId[$r['id']] = $r;
    }
    foreach ($byId as $id => $r) {
        if ($r['parent_id'] && isset($byId[$r['parent_id']])) {
            $byId[$r['parent_id']]['children'][] = &$byId[$id];
        }
    }
    foreach ($byId as $id => &$r) {
        if (!$r['parent_id']) {
            $tree[] = &$r;
        }
    }
    unset($r);
    return $tree;
}

/** Resolve a menu item to its destination URL, or '' if it points nowhere valid. */
function cms_menu_item_url(array $item): string
{
    if ($item['link_type'] === 'page') {
        if (!empty($item['page_slug']) && ($item['page_status'] ?? '') === 'published') {
            return cms_site_url('page.php?slug=' . urlencode($item['page_slug']));
        }
        return '';
    }
    $url = trim((string)($item['url'] ?? ''));
    return $url;
}

// ---------------------------------------------------------------- Public-site render hooks

/** <head> additions: theme CSS, web font, anti-flash inline script, toggle script. */
function cms_head_html(): string
{
    $cfg = cms_theme_config();
    $css = cms_site_url('assets/css/cms-theme.css');
    $js  = cms_site_url('assets/js/cms-theme.js');
    $h  = '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES) . '">';
    if ($cfg['google'] !== '') {
        $h .= '<link href="https://fonts.googleapis.com/css2?family=' . htmlspecialchars($cfg['google'], ENT_QUOTES) . '&display=swap" rel="stylesheet">';
    }
    // Anti-FOUC: set theme + font on <html> before the body paints.
    $default = $cfg['theme'];
    $toggle  = $cfg['toggle'] ? 'true' : 'false';
    $font    = $cfg['font'];
    $h .= '<script>(function(){try{'
        . 'var d=document.documentElement,def=' . json_encode($default) . ',tg=' . $toggle . ';'
        . 'var s=tg?localStorage.getItem("boskets-theme"):null;var t=s||def;'
        . 'if(t==="system"){t=window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light";}'
        . 'd.setAttribute("data-theme",t);d.setAttribute("data-font",' . json_encode($font) . ');'
        . '}catch(e){}})();</script>';
    $h .= '<script defer src="' . htmlspecialchars($js, ENT_QUOTES) . '"></script>';
    return $h;
}

/** Visitor light/dark toggle button (only when enabled in CMS). */
function cms_theme_toggle_html(): string
{
    $cfg = cms_theme_config();
    if (!$cfg['toggle']) {
        return '';
    }
    return '<button type="button" class="cms-theme-toggle" id="cms-theme-toggle" title="Switch light / dark" aria-label="Switch light or dark theme">'
         . '<span class="cms-toggle-sun" aria-hidden="true">☀️</span><span class="cms-toggle-moon" aria-hidden="true">🌙</span></button>';
}

/** Extra top-nav items added in the CMS (rendered after the built-in nav links). */
function cms_nav_items_html(): string
{
    $tree = cms_menu_tree('nav');
    if (!$tree) {
        return '';
    }
    $tree = array_slice($tree, 0, 5); // at most 5 top-level items in the nav row
    $html = '';
    foreach ($tree as $item) {
        $kids = array_filter($item['children'], fn($c) => cms_menu_item_url($c) !== '' || $c['link_type'] === 'page');
        if ($kids) {
            $html .= '<div class="nav-item cms-nav-item">';
            $html .= '<a href="#" onclick="return false">' . htmlspecialchars($item['label']) . ' <span class="caret">▼</span></a>';
            $html .= '<div class="mega cms-mega"><div>';
            foreach ($item['children'] as $child) {
                $url = cms_menu_item_url($child);
                if ($url === '') continue;
                $tgt = $child['new_tab'] ? ' target="_blank" rel="noopener"' : '';
                $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $tgt . '>' . htmlspecialchars($child['label']) . '</a>';
            }
            $html .= '</div></div></div>';
        } else {
            $url = cms_menu_item_url($item);
            if ($url === '') continue;
            $tgt = $item['new_tab'] ? ' target="_blank" rel="noopener"' : '';
            $html .= '<a class="cms-nav-link" href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $tgt . '>' . htmlspecialchars($item['label']) . '</a>';
        }
    }
    return $html;
}

/** Extra footer column added in the CMS. */
function cms_footer_items_html(): string
{
    $tree = cms_menu_tree('footer');
    if (!$tree) {
        return '';
    }
    $links = '';
    foreach ($tree as $item) {
        $url = cms_menu_item_url($item);
        if ($url === '') continue;
        $tgt = $item['new_tab'] ? ' target="_blank" rel="noopener"' : '';
        $links .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $tgt . '>' . htmlspecialchars($item['label']) . '</a>';
        // one level of children, indented
        foreach ($item['children'] as $child) {
            $curl = cms_menu_item_url($child);
            if ($curl === '') continue;
            $ctgt = $child['new_tab'] ? ' target="_blank" rel="noopener"' : '';
            $links .= '<a href="' . htmlspecialchars($curl, ENT_QUOTES) . '"' . $ctgt . ' style="padding-left:14px;opacity:.85">' . htmlspecialchars($child['label']) . '</a>';
        }
    }
    if ($links === '') {
        return '';
    }
    return '<div class="footer-links cms-footer-links"><h4>More</h4>' . $links . '</div>';
}
