<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

header('Content-Type: application/json');

$isrc = trim($_GET['isrc'] ?? '');
if (!$isrc) {
    echo json_encode(['error' => 'no_isrc']);
    exit;
}

$db = get_db();

// Return cached result if available (cache for 30 days)
$stmt = $db->prepare("SELECT data FROM mb_cache WHERE isrc = ? AND fetched_at > datetime('now', '-30 days')");
$stmt->execute([$isrc]);
$cached = $stmt->fetchColumn();

if ($cached) {
    echo $cached;
    exit;
}

// ── MusicBrainz fetch ─────────────────────────────────────────────────────────
$ua = 'SpotifyCompanion/1.0 (rob@ng15.co.uk)';

function mb_get(string $url, string $ua): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["User-Agent: $ua", 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    return json_decode($body, true) ?? null;
}

// Step 1: lookup by ISRC
sleep(1);
$isrc_data = mb_get(
    "https://musicbrainz.org/ws/2/isrc/{$isrc}?inc=artists+releases+artist-credits&fmt=json",
    $ua
);

if (empty($isrc_data['recordings'][0])) {
    $json = json_encode(['error' => 'not_found']);
    $db->prepare("INSERT OR REPLACE INTO mb_cache (isrc, data, fetched_at) VALUES (?, ?, datetime('now'))")->execute([$isrc, $json]);
    echo $json;
    exit;
}

$recording  = $isrc_data['recordings'][0];
$rec_mbid   = $recording['id'];

// Releases this track appears on
$appears_on = [];
foreach ($recording['releases'] ?? [] as $rel) {
    $appears_on[] = [
        'title' => $rel['title'],
        'type'  => $rel['release-group']['primary-type'] ?? ($rel['status'] ?? 'Release'),
        'year'  => substr($rel['date'] ?? '', 0, 4) ?: null,
    ];
}
// Deduplicate by title
$seen = [];
$appears_on = array_filter($appears_on, function($r) use (&$seen) {
    if (isset($seen[$r['title']])) return false;
    $seen[$r['title']] = true;
    return true;
});
$appears_on = array_values($appears_on);
usort($appears_on, fn($a, $b) => ($a['year'] ?? '9999') <=> ($b['year'] ?? '9999'));

// Primary artist MBID
$artist_mbid = $recording['artist-credit'][0]['artist']['id'] ?? null;

// Step 2: get artist members
$members = [];
if ($artist_mbid) {
    sleep(1);
    $artist_data = mb_get(
        "https://musicbrainz.org/ws/2/artist/{$artist_mbid}?inc=artist-rels&fmt=json",
        $ua
    );
    if ($artist_data) {
        foreach ($artist_data['relations'] ?? [] as $rel) {
            if (($rel['target-type'] ?? '') !== 'artist') continue;
            $type = strtolower($rel['type'] ?? '');
            if (!in_array($type, ['member of band', 'member'])) continue;
            $members[] = [
                'name'       => $rel['artist']['name'] ?? '',
                'instrument' => $rel['attributes'][0] ?? null,
                'begin'      => $rel['begin'] ?? null,
                'end'        => $rel['end'] ?? null,
            ];
        }
    }
}

// Step 3: get work relations (composers / writers)
$composers = [];
$producers = [];

sleep(1);
$rec_detail = mb_get(
    "https://musicbrainz.org/ws/2/recording/{$rec_mbid}?inc=work-rels&fmt=json",
    $ua
);

$work_mbid = null;
foreach ($rec_detail['relations'] ?? [] as $rel) {
    if ($rel['target-type'] === 'work') {
        $work_mbid = $rel['work']['id'] ?? null;
        break;
    }
}

if ($work_mbid) {
    sleep(1);
    $work_data = mb_get(
        "https://musicbrainz.org/ws/2/work/{$work_mbid}?inc=artist-rels&fmt=json",
        $ua
    );
    foreach ($work_data['relations'] ?? [] as $rel) {
        $type = strtolower($rel['type'] ?? '');
        $name = $rel['artist']['name'] ?? '';
        if (!$name) continue;
        if (in_array($type, ['composer', 'lyricist', 'writer', 'arranger'])) {
            $composers[] = $name;
        } elseif ($type === 'producer') {
            $producers[] = $name;
        }
    }
    $composers = array_unique($composers);
    $producers = array_unique($producers);
}

$result = [
    'members'    => $members,
    'composers'  => array_values($composers),
    'producers'  => array_values($producers),
    'appears_on' => $appears_on,
];

cache_and_respond($db, $isrc, $result);

function cache_and_respond(PDO $db, string $isrc, array $result): never {
    $json = json_encode($result);
    $stmt = $db->prepare("
        INSERT OR REPLACE INTO mb_cache (isrc, data, fetched_at)
        VALUES (?, ?, datetime('now'))
    ");
    $stmt->execute([$isrc, $json]);
    echo $json;
    exit;
}
