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

// CSRF check
if ($_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    http_response_code(400);
    die('Invalid state — possible CSRF. Please try again.');
}
unset($_SESSION['oauth_state']);

$data = _token_request([
    'grant_type'   => 'authorization_code',
    'code'         => $_GET['code'],
    'redirect_uri' => SPOTIFY_REDIRECT_URI,
]);

if (empty($data['access_token'])) {
    die('Failed to obtain access token from Spotify.');
}

save_tokens($data['access_token'], $data['refresh_token'], $data['expires_in']);

header('Location: /');
exit;
