<?php
/** Shared admin bootstrap: auth gate + section nav. Include at the top of each admin page. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();

function admin_nav(string $active): void
{
    $items = [
        'index'    => ['index.php',            'Dashboard'],
        'users'    => ['users.php',            'Users'],
        'content'  => ['content.php',          'Content'],
        'forum'    => ['forum.php',            'Forum'],
        'masters'  => ['masters.php',          'Master Lists'],
        'messages' => ['messages.php',         'Messages'],
        'import'        => ['import-blogspot.php',  'Import'],
        'repair'        => ['repair-images.php',    'Repair Images'],
        'announcements' => ['announcements.php',    'Announcements'],
        'gallery'       => ['gallery.php',          'Gallery'],
        'cms-pages'     => ['cms-pages.php',        'Pages'],
        'cms-appearance'=> ['cms-appearance.php',   'Appearance'],
        'cms-menus'     => ['cms-menus.php',        'Menus'],
    ];
    echo '<div class="admin-nav">';
    foreach ($items as $key => [$href, $label]) {
        $cls = $key === $active ? ' class="active"' : '';
        echo '<a' . $cls . ' href="' . e(url('admin/' . $href)) . '">' . $label . '</a>';
    }
    echo '</div>';
}
