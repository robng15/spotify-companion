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

// State stored in a SameSite=Lax cookie (survives cross-origin redirect from Spotify)
$expected = $_COOKIE['oauth_state'] ?? null;

if (!$expected || !hash_equals($expected, $_GET['state'])) {
    http_response_code(400);
    die('Invalid state — please go back and try again.');
}

// Clear the state cookie
setcookie('oauth_state', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

$data = _token_request([
    'grant_type'   => 'authorization_code',
    'code'         => $_GET['code'],
    'redirect_uri' => SPOTIFY_REDIRECT_URI,
]);

if (empty($data['access_token'])) {
    die('Failed to obtain access token from Spotify. Response: ' . htmlspecialchars(json_encode($data)));
}

save_tokens($data['access_token'], $data['refresh_token'], $data['expires_in']);

header('Location: /');
exit;
