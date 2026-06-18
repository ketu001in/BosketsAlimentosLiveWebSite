<?php
/** CMS: image upload endpoint for the page editor (returns JSON). */
require_once __DIR__ . '/includes/bootstrap.php';

$admin = current_superuser();
if (!$admin) {
    json_fail('Not authorized.', 401);
}
csrf_check(); // reads the X-CSRF header

try {
    $up = handle_upload('file', 'image', 'cms');
    if (!$up) {
        json_fail('No image was received.');
    }
    json_out(['ok' => true, 'url' => cms_site_url($up['file'])]);
} catch (RuntimeException $ex) {
    json_fail($ex->getMessage());
}
