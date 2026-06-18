<?php
/**
 * Social-login controller — both the start of the flow and the callback.
 *   Start    : /oauth.php?provider=google           (redirects to the provider)
 *   Callback : /oauth.php?provider=google&code=…&state=…   (provider returns here)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';

$provider = $_GET['provider'] ?? '';
if (!in_array($provider, ['google', 'facebook'], true) || !oauth_enabled($provider)) {
    flash('error', 'That sign-in method is not available right now.');
    redirect('login.php');
}
ensure_oauth_table();

// The provider sends an error back (e.g. user clicked "Cancel").
if (!empty($_GET['error'])) {
    flash('info', 'Sign-in was cancelled.');
    redirect('login.php');
}

/* ------------------------------------------------ Callback (code present) */
if (isset($_GET['code'])) {
    if (empty($_GET['state']) || empty($_SESSION['oauth_state'])
        || !hash_equals($_SESSION['oauth_state'], (string)$_GET['state'])) {
        flash('error', 'Your sign-in session expired. Please try again.');
        redirect('login.php');
    }
    unset($_SESSION['oauth_state']);

    $profile = oauth_fetch_profile($provider, (string)$_GET['code']);
    if (!$profile) {
        flash('error', 'Could not complete ' . ucfirst($provider) . ' sign-in. Please try again.');
        redirect('login.php');
    }

    // 1) Identity already linked? Straight in.
    $st = db()->prepare('SELECT user_id FROM oauth_accounts WHERE provider = ? AND provider_uid = ?');
    $st->execute([$provider, $profile['id']]);
    if ($linkedId = (int)($st->fetchColumn() ?: 0)) {
        oauth_login_user($linkedId);
    }

    // 2) Linking from the settings page (already signed in).
    if ($current = current_user()) {
        oauth_link((int)$current['id'], $provider, $profile);
        flash('success', ucfirst($provider) . ' account linked. You can now sign in with it.');
        redirect('settings.php');
    }

    // 3) An account with this email already exists -> ask before linking.
    if ($profile['email'] !== '') {
        $st = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$profile['email']]);
        if ($existingId = (int)($st->fetchColumn() ?: 0)) {
            $_SESSION['oauth_pending'] = [
                'provider' => $provider,
                'id'       => $profile['id'],
                'email'    => $profile['email'],
                'name'     => $profile['name'],
                'user_id'  => $existingId,
            ];
            redirect('link-account.php');
        }
    }

    // 4) Brand-new member.
    $seed     = $profile['email'] !== '' ? $profile['email'] : ($profile['name'] ?: 'chef');
    $username = oauth_make_username($seed);
    $display  = $profile['name'] !== '' ? mb_substr($profile['name'], 0, 60) : $username;
    $email    = $profile['email'] !== '' ? $profile['email'] : ($username . '@' . $provider . '.local');
    $randomPw = password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT);

    db()->prepare(
        'INSERT INTO users (username, email, password_hash, display_name, created_at) VALUES (?, ?, ?, ?, NOW())'
    )->execute([$username, $email, $randomPw, $display]);
    $newId = (int)db()->lastInsertId();
    oauth_link($newId, $provider, $profile);
    oauth_login_user($newId, true);
}

/* ------------------------------------------------ Start (no code yet) */
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
header('Location: ' . oauth_authorize_url($provider, $state));
exit;
