<?php
session_start();
require_once __DIR__ . '/spotify.php';

if (isset($_GET['error'])) {
    die('Spotify authorisation denied: ' . htmlspecialchars($_GET['error']));
}

if (empty($_GET['code']) || empty($_GET['state'])) {
    header('Location: /');
    exit;
}

$expected = $_COOKIE['oauth_state'] ?? null;

if (!$expected || !hash_equals($expected, $_GET['state'])) {
    http_response_code(400);
    die('Invalid state — please <a href="/">try again</a>.');
}

setcookie('oauth_state', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

$data = _token_request([
    'grant_type'   => 'authorization_code',
    'code'         => $_GET['code'],
    'redirect_uri' => SPOTIFY_REDIRECT_URI,
]);

if (empty($data['access_token'])) {
    die('Token exchange failed: <pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>');
}

// Fetch Spotify profile to get a stable user ID and display name
$ch = curl_init('https://api.spotify.com/v1/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $data['access_token']],
]);
$me = json_decode(curl_exec($ch), true) ?? [];
curl_close($ch);

$user_id      = $me['id']           ?? ('user_' . uniqid());
$display_name = $me['display_name'] ?? $user_id;

save_tokens($data['access_token'], $data['refresh_token'], $data['expires_in'], $user_id, $display_name);

header('Location: /');
exit;
