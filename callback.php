<?php
// Debug log — remove once auth is working
$log = fn(string $msg) => file_put_contents(dirname(__DIR__) . '/callback.log', date('[H:i:s] ') . $msg . "\n", FILE_APPEND);

$log('--- hit ---');
$log('GET: ' . json_encode($_GET));
$log('Cookies present: ' . json_encode(array_keys($_COOKIE)));
$log('oauth_state cookie: ' . ($_COOKIE['oauth_state'] ?? 'NOT SET'));

session_start();
require_once __DIR__ . '/spotify.php';

if (isset($_GET['error'])) {
    $log('Spotify error: ' . $_GET['error']);
    die('Spotify authorisation denied: ' . htmlspecialchars($_GET['error']));
}

if (empty($_GET['code']) || empty($_GET['state'])) {
    $log('Missing code or state — redirecting home');
    header('Location: /');
    exit;
}

$expected = $_COOKIE['oauth_state'] ?? null;
$received = $_GET['state'];
$log('Expected state: ' . ($expected ?? 'NULL'));
$log('Received state: ' . $received);

if (!$expected || !hash_equals($expected, $received)) {
    $log('STATE MISMATCH — auth failed here');
    http_response_code(400);
    die('State mismatch — please <a href="/">try again</a>.');
}

// Clear state cookie
setcookie('oauth_state', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);

$log('State OK — requesting token');

$data = _token_request([
    'grant_type'   => 'authorization_code',
    'code'         => $_GET['code'],
    'redirect_uri' => SPOTIFY_REDIRECT_URI,
]);

$log('Token response keys: ' . json_encode(array_keys($data)));

if (empty($data['access_token'])) {
    $log('FAILED — no access_token in response: ' . json_encode($data));
    die('Token exchange failed: <pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>');
}

save_tokens($data['access_token'], $data['refresh_token'], $data['expires_in']);
$log('Tokens saved — redirecting home');

header('Location: /');
exit;
