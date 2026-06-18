<?php
/**
 * Social login (Google + Facebook) helpers.
 *
 * The whole feature is dormant until you put OAuth credentials in config.php —
 * oauth_enabled() returns false and the buttons stay hidden. Needs HTTPS, so it
 * only works on your live domain (not the local http test server).
 */

/** Per-provider endpoints + the credentials read from config.php. */
function oauth_config(string $provider): array
{
    return match ($provider) {
        'google' => [
            'id'     => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
            'secret' => defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '',
            'auth'   => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token'  => 'https://oauth2.googleapis.com/token',
            'scope'  => 'openid email profile',
            'label'  => 'Google',
        ],
        'facebook' => [
            'id'     => defined('FACEBOOK_APP_ID') ? FACEBOOK_APP_ID : '',
            'secret' => defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : '',
            'auth'   => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token'  => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'scope'  => 'email public_profile',
            'label'  => 'Facebook',
        ],
        default => [],
    };
}

function oauth_enabled(string $provider): bool
{
    $c = oauth_config($provider);
    return !empty($c['id']) && !empty($c['secret']);
}

function oauth_any_enabled(): bool
{
    return oauth_enabled('google') || oauth_enabled('facebook');
}

/** The redirect URI you must whitelist in the provider console. */
function oauth_redirect_uri(string $provider): string
{
    return url('oauth.php?provider=' . $provider);
}

function oauth_authorize_url(string $provider, string $state): string
{
    $c = oauth_config($provider);
    $params = [
        'client_id'     => $c['id'],
        'redirect_uri'  => oauth_redirect_uri($provider),
        'response_type' => 'code',
        'scope'         => $c['scope'],
        'state'         => $state,
    ];
    if ($provider === 'google') {
        $params['access_type'] = 'online';
        $params['prompt']      = 'select_account';
    }
    return $c['auth'] . '?' . http_build_query($params);
}

/** Exchange the auth code and return ['id','email','name'] or null on failure. */
function oauth_fetch_profile(string $provider, string $code): ?array
{
    $c = oauth_config($provider);
    $tokenRaw = oauth_http_post($c['token'], [
        'code'          => $code,
        'client_id'     => $c['id'],
        'client_secret' => $c['secret'],
        'redirect_uri'  => oauth_redirect_uri($provider),
        'grant_type'    => 'authorization_code',
    ]);
    $token  = json_decode($tokenRaw, true) ?: [];
    $access = $token['access_token'] ?? '';
    if ($access === '') {
        return null;
    }

    if ($provider === 'google') {
        $info = json_decode(oauth_http_get('https://openidconnect.googleapis.com/v1/userinfo', $access), true) ?: [];
        if (empty($info['sub'])) {
            return null;
        }
        return ['id' => (string)$info['sub'], 'email' => trim($info['email'] ?? ''), 'name' => trim($info['name'] ?? '')];
    }

    // Facebook
    $info = json_decode(oauth_http_get('https://graph.facebook.com/v19.0/me?fields=id,name,email', $access), true) ?: [];
    if (empty($info['id'])) {
        return null;
    }
    return ['id' => (string)$info['id'], 'email' => trim($info['email'] ?? ''), 'name' => trim($info['name'] ?? '')];
}

function oauth_http_post(string $url, array $data): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return is_string($out) ? $out : '';
}

function oauth_http_get(string $url, string $bearer): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $bearer, 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    return is_string($out) ? $out : '';
}

/** Attach a provider identity to a local user (no-op if already linked). */
function oauth_link(int $userId, string $provider, array $profile): void
{
    $st = db()->prepare('SELECT id FROM oauth_accounts WHERE provider = ? AND provider_uid = ?');
    $st->execute([$provider, $profile['id']]);
    if (!$st->fetchColumn()) {
        db()->prepare(
            'INSERT INTO oauth_accounts (user_id, provider, provider_uid, email, created_at) VALUES (?, ?, ?, ?, NOW())'
        )->execute([$userId, $provider, $profile['id'], $profile['email'] ?? null]);
    }
}

/** Log a user in and redirect (honours $_SESSION['after_login']). */
function oauth_login_user(int $userId, bool $isNew = false): never
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ? AND is_banned = 0');
    $st->execute([$userId]);
    $u = $st->fetch();
    if (!$u) {
        flash('error', 'This account is not available.');
        redirect('login.php');
    }
    $_SESSION['user_id'] = (int)$u['id'];
    session_regenerate_id(true);
    $dest = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);
    flash('success', ($isNew ? 'Welcome to ' . SITE_NAME . ', ' : 'Welcome back, ') . ($u['display_name'] ?: $u['username']) . '! 👋');
    redirect($dest !== '' && !preg_match('~^https?://~', $dest) ? ltrim($dest, '/') : 'index.php');
}

/** A unique username derived from an email/name seed. */
function oauth_make_username(string $seed): string
{
    $base = strtolower(preg_replace('/[^a-z0-9_]/i', '', explode('@', $seed)[0]));
    $base = substr($base, 0, 24);
    if (strlen($base) < 3) {
        $base = 'chef' . $base;
    }
    $try = $base;
    for ($i = 0; $i <= 9999; $i++) {
        if ($i > 0) {
            $try = substr($base, 0, 22) . $i;
        }
        $st = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $st->execute([$try]);
        if (!$st->fetchColumn()) {
            return $try;
        }
    }
    return $base . bin2hex(random_bytes(3));
}

/** Renders the "Continue with…" buttons for login/register pages. */
function oauth_buttons_html(): string
{
    if (!oauth_any_enabled()) {
        return '';
    }
    $h = '<div class="social-auth"><div class="social-sep"><span>or continue with</span></div>';
    if (oauth_enabled('google')) {
        $h .= '<a class="btn btn-social btn-google" href="' . e(url('oauth.php?provider=google')) . '">'
            . '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">'
            . '<path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.4 29.3 35 24 35c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 3.1 29.6 1 24 1 11.8 1 2 10.8 2 23s9.8 22 22 22c11 0 21-8 21-22 0-1.3-.1-2.3-.4-2.5z"/>'
            . '<path fill="#FF3D00" d="M3.2 13.3l6.6 4.8C11.5 14.1 17.3 11 24 11c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 3.1 29.6 1 24 1 16 1 9 5.5 5.3 12.1z"/>'
            . '<path fill="#4CAF50" d="M24 45c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35.9 26.7 37 24 37c-5.3 0-9.7-3.6-11.3-8.4l-6.5 5C9.6 40.4 16.3 45 24 45z"/>'
            . '<path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4 5.5l6.3 5.3C39.9 36.6 45 31 45 23c0-1.3-.1-2.3-.4-2.5z"/>'
            . '</svg> Continue with Google</a>';
    }
    if (oauth_enabled('facebook')) {
        $h .= '<a class="btn btn-social btn-facebook" href="' . e(url('oauth.php?provider=facebook')) . '">'
            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">'
            . '<path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/>'
            . '</svg> Continue with Facebook</a>';
    }
    return $h . '</div>';
}
