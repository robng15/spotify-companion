# Spotify Companion

A single-user companion app for Spotify. Controls and enriches the active Spotify session (requires Premium) running alongside the main Spotify client.

## Status

Deployed and working at **https://sc1.red-kite-dev.co.uk**

## Tech Stack

- **Backend**: PHP 8.4 (Authorization Code Flow OAuth, API proxy layer)
- **Frontend**: Bootstrap 5 + Bootstrap Icons + vanilla JS (fetch + setInterval polling)
- **Database**: SQLite — `/var/www/vhosts/sc1.red-kite-dev.co.uk/spotify-companion.db` (outside web root)
- **APIs**: Spotify Web API, MusicBrainz API

## Deployment

- Plesk / Linux server
- Web root: `/var/www/vhosts/sc1.red-kite-dev.co.uk/httpdocs/`
- SQLite DB one level above web root (confirmed writable)
- `config.php` is git-ignored — must be created manually on the server after each fresh deploy

## config.php (server only — not in git)

```php
<?php
define('SPOTIFY_CLIENT_ID',     '...');
define('SPOTIFY_CLIENT_SECRET', '...');
define('SPOTIFY_REDIRECT_URI',  'https://sc1.red-kite-dev.co.uk/callback.php');
define('SPOTIFY_SCOPES', implode(' ', [
    'user-read-playback-state',
    'user-modify-playback-state',
    'user-read-currently-playing',
    'user-read-recently-played',
    'playlist-read-private',
    'playlist-read-collaborative',
]));
define('DB_PATH', '/var/www/vhosts/sc1.red-kite-dev.co.uk/spotify-companion.db');
```

## File Structure

```
/var/www/vhosts/sc1.red-kite-dev.co.uk/
  spotify-companion.db              ← SQLite (outside web root)

httpdocs/
  index.php                         ← auth gate + full UI
  callback.php                      ← OAuth redirect handler
  config.php                        ← credentials (git-ignored)
  db.php                            ← PDO singleton, schema init
  spotify.php                       ← token management + API helper
  api/
    now-playing.php                 ← playback state + audio features + history logging
    controls.php                    ← play/pause/skip/seek/volume/repeat/shuffle/queue
    queue.php                       ← upcoming tracks
    history.php                     ← read + clear SQLite history
    search.php                      ← track search
    playlists.php                   ← full playlist library
    musicbrainz.php                 ← members/composers/appears-on (cached)
  assets/
    css/app.css
    js/app.js
```

## Layout (3-column)

- **Left**: Upcoming tracks (scrollable, circular art) / Recently played (scrollable, circular art, clear button) — 50/50 vertical split
- **Centre**: Search → queue next / Song title (full wrap) / Large artwork / Currently playing info box (scrollable — Spotify data)
- **Right**: Playlist active + Change playlist / Progress seek bar / 6 transport controls / Mute + volume / MusicBrainz info box (scrollable)

## Transport Controls (6)

`|◄` Skip to start · `◄` Previous track · `○` Loop toggle · `▶` Play/Pause · `○` Shuffle toggle · `►` Next track

## Info Box Content Split

**Currently playing (Spotify):** Album · Artist · Released · Duration · BPM · Key · Time signature · Energy · Danceability · Valence · Acousticness · Live performance · Explicit · Popularity

**MusicBrainz:** Band members (with active dates) · Composers / writers · Producers · All releases track appears on

## SQLite Schema

```sql
CREATE TABLE history (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  played_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  track_id     TEXT    NOT NULL,
  track_name   TEXT    NOT NULL,
  artist       TEXT    NOT NULL,
  album        TEXT    NOT NULL,
  artwork_url  TEXT,
  duration_ms  INTEGER
);

CREATE TABLE mb_cache (
  isrc       TEXT PRIMARY KEY,
  data       TEXT    NOT NULL,
  fetched_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE tokens (
  id            INTEGER PRIMARY KEY CHECK (id = 1),
  access_token  TEXT    NOT NULL,
  refresh_token TEXT    NOT NULL,
  expires_at    TEXT    NOT NULL
);
```

## Polling Intervals

- Now playing: every 2s (single Spotify API call — audio features cached in session per track)
- Queue/upcoming: every 5s
- History: every 10s
- MusicBrainz: on track change, cache-first (30-day SQLite cache, 1 req/sec rate limit)

## Key Implementation Notes

- **OAuth state**: stored in a `SameSite=Lax` cookie (not PHP session) so it survives the cross-origin redirect from Spotify back to `callback.php`
- **Audio features**: cached in PHP session per track ID — only fetched once per track, not every poll
- **History deduplication**: checks last DB entry rather than session, so page reloads don't re-log the current track
- **MusicBrainz first load**: 3–4 sequential API calls at 1 req/sec — expect ~4s delay on first play of any track. Instant on repeat plays (SQLite cache)
- **Token refresh**: automatic on any 401 from Spotify, single retry

## Spotify OAuth Scopes

`user-read-playback-state` · `user-modify-playback-state` · `user-read-currently-playing` · `user-read-recently-played` · `playlist-read-private` · `playlist-read-collaborative`
