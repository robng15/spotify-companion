<?php
session_start();
require_once __DIR__ . '/spotify.php';

function fail(string $message): never {
    error_log('Spotify OAuth callback error: ' . $message);
    $_SESSION['auth_error'] = $message;
    header('Location: /');
    exit;
}

if (isset($_GET['error'])) {
    fail('Spotify declined authorisation: ' . $_GET['error']
        . (isset($_GET['error_description']) ? ' — ' . $_GET['error_description'] : ''));
}

if (empty($_GET['code']) || empty($_GET['state'])) {
    header('Location: /');
    exit;
}

$expected = $_COOKIE['oauth_state'] ?? null;

if (!$expected || !hash_equals($expected, $_GET['state'])) {
    fail('State mismatch — the login link may have expired. Please try again.');
}

setcookie('oauth_state', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

$data = _token_request([
    'grant_type'   => 'authorization_code',
    'code'         => $_GET['code'],
    'redirect_uri' => SPOTIFY_REDIRECT_URI,
]);

if (empty($data['access_token'])) {
    fail('Token exchange failed: ' . json_encode($data));
}

// Fetch Spotify profile to get a stable user ID and display name
$ch = curl_init('https://api.spotify.com/v1/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $data['access_token']],
]);
$me_raw    = curl_exec($ch);
$me_err    = curl_error($ch);
curl_close($ch);

if ($me_raw === false) {
    fail('Could not fetch Spotify profile: ' . ($me_err ?: 'cURL request failed'));
}

$me = json_decode($me_raw, true) ?? [];

if (!empty($me['error'])) {
    fail('Could not fetch Spotify profile: ' . json_encode($me['error']));
}

// Spotify sometimes returns {"status":403,"message":"..."} with no "error" key
if (!empty($me['message']) && empty($me['id'])) {
    fail('Spotify: ' . $me['message']);
}

if (empty($me['id'])) {
    fail('Could not fetch Spotify profile: no user ID returned (response: ' . substr($me_raw, 0, 200) . ')');
}

$user_id      = $me['id'];
$display_name = $me['display_name'] ?? $user_id;

save_tokens($data['access_token'], $data['refresh_token'], $data['expires_in'], $user_id, $display_name);

header('Location: /');
exit;
