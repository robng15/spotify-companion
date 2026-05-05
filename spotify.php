<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Token management ─────────────────────────────────────────────────────────

function get_access_token(): ?string {
    // Session cache still valid
    if (!empty($_SESSION['access_token']) && ($_SESSION['token_expires_at'] ?? 0) > time() + 60) {
        return $_SESSION['access_token'];
    }

    $db  = get_db();
    $row = $db->query("SELECT * FROM tokens WHERE id = 1")->fetch();

    if (!$row) {
        return null;
    }

    if (strtotime($row['expires_at']) > time() + 60) {
        _cache_token_in_session($row['access_token'], strtotime($row['expires_at']));
        return $row['access_token'];
    }

    return _do_refresh($row['refresh_token']);
}

function save_tokens(string $access_token, string $refresh_token, int $expires_in): void {
    $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
    $db = get_db();
    $db->prepare("
        INSERT OR REPLACE INTO tokens (id, access_token, refresh_token, expires_at)
        VALUES (1, ?, ?, ?)
    ")->execute([$access_token, $refresh_token, $expires_at]);

    _cache_token_in_session($access_token, time() + $expires_in);
}

function is_authenticated(): bool {
    return get_access_token() !== null;
}

function _cache_token_in_session(string $token, int $expires_at): void {
    $_SESSION['access_token']      = $token;
    $_SESSION['token_expires_at']  = $expires_at;
}

function _do_refresh(string $refresh_token): ?string {
    $data = _token_request([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
    ]);

    if (empty($data['access_token'])) {
        return null;
    }

    // Spotify may issue a new refresh token on refresh
    $new_refresh = $data['refresh_token'] ?? $refresh_token;
    save_tokens($data['access_token'], $new_refresh, $data['expires_in']);
    return $data['access_token'];
}

function _token_request(array $params): array {
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

// ── API helper ────────────────────────────────────────────────────────────────

/**
 * @param string     $method  GET | POST | PUT | DELETE
 * @param string     $endpoint  e.g. '/me/player'
 * @param array      $query   URL query parameters
 * @param array|null $body    JSON body (for POST/PUT)
 * @return array  Decoded response, or ['error' => '...'] on failure
 */
function spotify_api(string $method, string $endpoint, array $query = [], ?array $body = null): array {
    $token = get_access_token();
    if (!$token) {
        return ['error' => 'not_authenticated'];
    }

    $url = 'https://api.spotify.com/v1' . $endpoint;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response_body = curl_exec($ch);
    $http_code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Token expired mid-session — refresh once and retry
    if ($http_code === 401) {
        $db  = get_db();
        $row = $db->query("SELECT refresh_token FROM tokens WHERE id = 1")->fetch();
        if ($row && _do_refresh($row['refresh_token'])) {
            return spotify_api($method, $endpoint, $query, $body); // single retry
        }
        return ['error' => 'not_authenticated'];
    }

    // 204 No Content is a success with no body (e.g. play/pause)
    if ($http_code === 204) {
        return ['ok' => true];
    }

    return $response_body ? (json_decode($response_body, true) ?? []) : [];
}

// ── Auth URL builder ──────────────────────────────────────────────────────────

function spotify_auth_url(string $state): string {
    return 'https://accounts.spotify.com/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => SPOTIFY_CLIENT_ID,
        'scope'         => SPOTIFY_SCOPES,
        'redirect_uri'  => SPOTIFY_REDIRECT_URI,
        'state'         => $state,
    ]);
}
