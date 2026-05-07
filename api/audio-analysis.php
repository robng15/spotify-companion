<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$track_id = trim($_GET['track_id'] ?? '');
if (!$track_id) {
    echo json_encode(['beats' => []]);
    exit;
}

if ($track_id === ($_SESSION['analysis_track'] ?? null)) {
    echo json_encode(['beats' => $_SESSION['analysis_beats']]);
    exit;
}

$data = spotify_api('GET', '/audio-analysis/' . $track_id);

$beats = array_map(fn($b) => [
    'start'      => round($b['start'],      4),
    'confidence' => round($b['confidence'], 3),
], $data['beats'] ?? []);

$_SESSION['analysis_track'] = $track_id;
$_SESSION['analysis_beats'] = $beats;

echo json_encode(['beats' => $beats]);
