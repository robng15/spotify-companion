<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$data = spotify_api('GET', '/me/player/queue');

if (empty($data['queue'])) {
    echo json_encode(['queue' => []]);
    exit;
}

$queue = array_map(function($item) {
    $artists = implode(', ', array_map(fn($a) => $a['name'], $item['artists'] ?? []));
    $artwork  = $item['album']['images'][2]['url'] // 64px
             ?? $item['album']['images'][0]['url']
             ?? '';
    return [
        'track_id'   => $item['id'],
        'track_name' => $item['name'],
        'artist'     => $artists,
        'artwork_url'=> $artwork,
        'duration_ms'=> $item['duration_ms'] ?? 0,
    ];
}, $data['queue']);

echo json_encode(['queue' => $queue]);
