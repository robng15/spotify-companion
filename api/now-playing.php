<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$playback = spotify_api('GET', '/me/player', ['additional_types' => 'track']);

if (empty($playback) || empty($playback['item'])) {
    echo json_encode(['is_playing' => false]);
    exit;
}

$item    = $playback['item'];
$album   = $item['album'] ?? [];
$artists = array_map(fn($a) => $a['name'], $item['artists'] ?? []);
$artwork = $album['images'][0]['url'] ?? '';
$isrc    = $item['external_ids']['isrc'] ?? null;
$track_id = $item['id'];

// Audio features don't change per track — cache in session to avoid
// an extra Spotify API call on every 2-second poll
if ($track_id !== ($_SESSION['features_track'] ?? null)) {
    $features = spotify_api('GET', '/audio-features/' . $track_id);
    $_SESSION['features_track'] = $track_id;
    $_SESSION['features']       = $features;
} else {
    $features = $_SESSION['features'] ?? [];
}

// Log to history when track changes — check DB directly so page reloads
// don't re-insert the currently playing track
if ($playback['is_playing'] ?? false) {
    try {
        $db   = get_db();
        $last = $db->query("SELECT track_id FROM history ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($last !== $track_id) {
            $db->prepare("
                INSERT INTO history (track_id, track_name, artist, album, artwork_url, duration_ms)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$track_id, $item['name'], implode(', ', $artists), $album['name'] ?? '', $artwork, $item['duration_ms'] ?? 0]);
        }
    } catch (Exception $e) {}
}

echo json_encode([
    'is_playing'       => $playback['is_playing'] ?? false,
    'shuffle_state'    => $playback['shuffle_state'] ?? false,
    'repeat_state'     => $playback['repeat_state'] ?? 'off',
    'device_volume'    => $playback['device']['volume_percent'] ?? null,
    'progress_ms'      => $playback['progress_ms'] ?? 0,
    'track_id'         => $track_id,
    'track_name'       => $item['name'],
    'artists'          => implode(', ', $artists),
    'album'            => $album['name'] ?? '',
    'release_year'     => substr($album['release_date'] ?? '', 0, 4) ?: null,
    'duration_ms'      => $item['duration_ms'] ?? 0,
    'artwork_url'      => $artwork,
    'isrc'             => $isrc,
    'explicit'         => $item['explicit'] ?? false,
    'popularity'       => $item['popularity'] ?? null,
    'tempo'            => $features['tempo'] ?? null,
    'key'              => $features['key'] ?? null,
    'mode'             => $features['mode'] ?? null,
    'time_signature'   => $features['time_signature'] ?? null,
    'energy'           => $features['energy'] ?? null,
    'danceability'     => $features['danceability'] ?? null,
    'valence'          => $features['valence'] ?? null,
    'acousticness'     => $features['acousticness'] ?? null,
    'instrumentalness' => $features['instrumentalness'] ?? null,
    'liveness'         => $features['liveness'] ?? null,
    'speechiness'      => $features['speechiness'] ?? null,
    'loudness'         => $features['loudness'] ?? null,
    'context_uri'      => $playback['context']['uri'] ?? null,
]);
