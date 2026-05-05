<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$db     = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'clear') {
        $db->exec("DELETE FROM history");
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }
    exit;
}

// GET — return history newest-first
$rows = $db->query("
    SELECT track_id, track_name, artist, album, artwork_url, duration_ms, played_at
    FROM history
    ORDER BY id DESC
    LIMIT 200
")->fetchAll();

echo json_encode(['history' => $rows]);
