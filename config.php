<?php
/**
 * Bosket's Alimentos — site configuration.
 *
 * EDIT THESE VALUES before uploading to Hostinger.
 * Create the database + user in hPanel -> Databases -> MySQL Databases,
 * then put the credentials below.
 */

// ---------------------------------------------------------------- Database
define('DB_HOST', '127.0.0.1;port=3306'); // LOCAL TEST — set to 'localhost' before uploading to Hostinger
define('DB_NAME', 'boskets');
define('DB_USER', 'boskets');             // LOCAL TEST — replace with Hostinger DB user before upload
define('DB_PASS', 'Boskets@Local1');      // LOCAL TEST — replace with Hostinger DB password before upload

// ---------------------------------------------------------------- Site
define('SITE_NAME', "Bosket's Alimentos");
define('SITE_TAGLINE', 'A world of truly fusion food');
// Base URL with NO trailing slash, e.g. 'https://yourdomain.com'
// Leave '' to auto-detect (works fine in most setups).
define('SITE_URL', '');

// ---------------------------------------------------------------- Uploads
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_IMAGE_BYTES', 5 * 1024 * 1024);   // 5 MB  (avatars, recipe & step photos)
define('MAX_VIDEO_BYTES', 25 * 1024 * 1024);  // 25 MB (recipe step videos)

// ---------------------------------------------------------------- Email
// Address used as the From: header on password-reset emails.
// On Hostinger, use an address on your own domain (create it in
// hPanel -> Emails) so messages don't land in spam.
// Leave '' to auto-build 'no-reply@yourdomain.com' from the request host.
define('MAIL_FROM', '');

// ---------------------------------------------------------------- Social login (optional)
// Leave these blank to hide the "Continue with Google / Facebook" buttons.
// To switch social login ON, create OAuth apps and paste the credentials here.
// Social login needs HTTPS, so it only works on your live domain (not the
// local http test server). Register these exact redirect URIs with each app:
//     https://yourdomain.com/oauth.php?provider=google
//     https://yourdomain.com/oauth.php?provider=facebook
// Google ...... console.cloud.google.com -> APIs & Services -> Credentials ->
//               OAuth client ID (Web application). Enable the "People API".
// Facebook .... developers.facebook.com -> create app -> Facebook Login ->
//               Settings -> Valid OAuth Redirect URIs.
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('FACEBOOK_APP_ID', '');
define('FACEBOOK_APP_SECRET', '');

// ---------------------------------------------------------------- Misc
define('PER_PAGE', 12);          // cards per page on listing pages
define('SESSION_NAME', 'boskets_sid');
date_default_timezone_set('UTC');
