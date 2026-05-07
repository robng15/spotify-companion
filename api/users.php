<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'users'  => get_all_users(),
        'active' => get_active_user_id(),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = $input['user_id'] ?? '';
    $ok      = $user_id ? switch_user($user_id) : false;
    echo json_encode(['ok' => $ok, 'active' => get_active_user_id()]);
    exit;
}
