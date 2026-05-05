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

save_tokens($data['access_token'], $data['refresh_token'], $data['expires_in']);

header('Location: /');
exit;
