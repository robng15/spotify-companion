<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {

    case 'play':
        spotify_api('PUT', '/me/player/play');
        break;

    case 'pause':
        spotify_api('PUT', '/me/player/pause');
        break;

    case 'previous':
        spotify_api('POST', '/me/player/previous');
        break;

    case 'next':
        spotify_api('POST', '/me/player/next');
        break;

    case 'seek':
        $ms = (int)($input['position_ms'] ?? 0);
        spotify_api('PUT', '/me/player/seek', ['position_ms' => $ms]);
        break;

    case 'volume':
        $pct = max(0, min(100, (int)($input['percent'] ?? 0)));
        spotify_api('PUT', '/me/player/volume', ['volume_percent' => $pct]);
        break;

    case 'repeat':
        $mode = in_array($input['state'] ?? '', ['off','context','track']) ? $input['state'] : 'off';
        spotify_api('PUT', '/me/player/repeat', ['state' => $mode]);
        break;

    case 'shuffle':
        $on = filter_var($input['state'] ?? false, FILTER_VALIDATE_BOOLEAN);
        spotify_api('PUT', '/me/player/shuffle', ['state' => $on ? 'true' : 'false']);
        break;

    case 'queue':
        $uri = $input['uri'] ?? '';
        if ($uri) {
            spotify_api('POST', '/me/player/queue', ['uri' => $uri]);
        }
        break;

    case 'play_context':
        $uri = $input['uri'] ?? '';
        if ($uri) {
            spotify_api('PUT', '/me/player/play', [], ['context_uri' => $uri]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
        exit;
}

echo json_encode(['ok' => true]);
