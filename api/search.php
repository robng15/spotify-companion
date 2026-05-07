<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['tracks' => []]);
    exit;
}

$data = spotify_api('GET', '/search', [
    'q'     => $q,
    'type'  => 'track',
    'limit' => 12,
]);

if (empty($data['tracks']['items'])) {
    echo json_encode(['tracks' => []]);
    exit;
}

$tracks = array_map(function($item) {
    $artists = implode(', ', array_map(fn($a) => $a['name'], $item['artists'] ?? []));
    $artwork  = $item['album']['images'][2]['url'] // 64px thumbnail
             ?? $item['album']['images'][0]['url']
             ?? '';
    return [
        'uri'        => $item['uri'],
        'track_id'   => $item['id'],
        'name'       => $item['name'],
        'artists'    => $artists,
        'album'      => $item['album']['name'] ?? '',
        'artwork'    => $artwork,
        'duration_ms'=> $item['duration_ms'] ?? 0,
        'explicit'   => $item['explicit'] ?? false,
    ];
}, $data['tracks']['items']);

echo json_encode(['tracks' => $tracks]);
