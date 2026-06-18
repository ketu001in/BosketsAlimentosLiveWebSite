<?php
/** Sign out of the CMS portal (does not affect the main-site session). */
require_once __DIR__ . '/includes/bootstrap.php';
unset($_SESSION['cms_admin_id']);
session_regenerate_id(true);
flash('info', 'You have signed out of the CMS.');
cms_redirect('login.php');
