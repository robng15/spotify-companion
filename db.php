<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        init_schema($db);
    }
    return $db;
}

function init_schema(PDO $db): void {
    // Migrate single-user tokens table to multi-user if needed
    $cols = array_column($db->query("PRAGMA table_info(tokens)")->fetchAll(), 'name');
    if (!empty($cols) && !in_array('user_id', $cols)) {
        $db->exec("DROP TABLE tokens");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS tokens (
            user_id       TEXT PRIMARY KEY,
            display_name  TEXT,
            access_token  TEXT NOT NULL,
            refresh_token TEXT NOT NULL,
            expires_at    TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS history (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            played_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            track_id     TEXT    NOT NULL,
            track_name   TEXT    NOT NULL,
            artist       TEXT    NOT NULL,
            album        TEXT    NOT NULL,
            artwork_url  TEXT,
            duration_ms  INTEGER
        );

        CREATE TABLE IF NOT EXISTS mb_cache (
            isrc       TEXT PRIMARY KEY,
            data       TEXT    NOT NULL,
            fetched_at TEXT    NOT NULL DEFAULT (datetime('now'))
        );
    ");
}
