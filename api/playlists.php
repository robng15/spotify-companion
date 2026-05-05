<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$all    = [];
$offset = 0;
$limit  = 50;

// Page through all playlists (Spotify max 50 per request)
do {
    $data = spotify_api('GET', '/me/playlists', ['limit' => $limit, 'offset' => $offset]);
    if (empty($data['items'])) break;

    foreach ($data['items'] as $p) {
        $all[] = [
            'uri'         => $p['uri'],
            'id'          => $p['id'],
            'name'        => $p['name'],
            'track_count' => $p['tracks']['total'] ?? 0,
            'artwork'     => $p['images'][0]['url'] ?? '',
        ];
    }

    $offset += $limit;
} while (!empty($data['next']));

echo json_encode(['playlists' => $all]);
