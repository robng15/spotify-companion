# Spotify Companion

A multi-user companion app for Spotify. Controls and enriches the active Spotify session (requires Premium) running alongside the main Spotify client. Supports multiple Spotify accounts with a user switcher in the UI.

## Status

Deployed and working at **https://sc1.red-kite-dev.co.uk**

## Tech Stack

- **Backend**: PHP 8.4 (Authorization Code Flow OAuth, API proxy layer)
- **Frontend**: Bootstrap 5 + Bootstrap Icons + vanilla JS (fetch + setInterval polling)
- **Database**: SQLite — `/var/www/vhosts/sc1.red-kite-dev.co.uk/spotify-companion.db` (outside web root)
- **APIs**: Spotify Web API, MusicBrainz API, Wikipedia REST API

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
  callback.php                      ← OAuth redirect handler; fetches /v1/me after token exchange
  config.php                        ← credentials (git-ignored)
  db.php                            ← PDO singleton, schema init + tokens table migration
  spotify.php                       ← token management + API helper (multi-user aware)
  api/
    now-playing.php                 ← playback state + audio features + history logging
    controls.php                    ← play/pause/skip/seek/volume/repeat/shuffle/queue
    queue.php                       ← upcoming tracks
    history.php                     ← read + clear SQLite history
    search.php                      ← track search
    playlists.php                   ← full playlist library
    musicbrainz.php                 ← members/composers/appears-on/wiki bio+photo (cached)
    users.php                       ← GET: list users + active; POST: switch active user
    clear-mb-cache.php              ← authenticated endpoint to wipe mb_cache table
  assets/
    css/app.css
    js/app.js
```

## Layout

- **Left** (305px): Winchester FM logo / User strip (active user name, switch + add account buttons) / Upcoming tracks (scrollable, circular art)
- **Centre**: Search → queue next / Artist: Song title (artist in red) / Artwork (180×180) + Spotify info box (340px) side-by-side / Mute + full-width volume slider
- **Right** (291px): Playlist active + Change playlist (with search filter) / Progress seek bar / 6 transport controls / MusicBrainz info box (scrollable, includes Wikipedia bio + artist photo)
- **Beat visualiser**: full-width canvas strip between main area and history bar (48 bars, lerp-smoothed, amber→red gradient)
- **Bottom bar** (80px): Recently played — full-width horizontal scroll strip (circular art, 220px each, clear button)
- **Background**: playing artwork at 15% opacity, full-bleed, fading to black via CSS mask-image

## Transport Controls (6)

`|◄` Skip to start · `◄` Previous track · `○` Loop toggle · `▶` Play/Pause · `○` Shuffle toggle · `►` Next track

## Info Box Content Split

**Currently playing (Spotify):** Album · Artist · Released · Duration · BPM · Key · Time signature · Energy · Danceability · Valence · Acousticness · Live performance · Explicit · Popularity

**MusicBrainz (right panel):** Wikipedia artist photo · Wikipedia bio · Band members (with active dates) · Composers / writers · Producers · All releases track appears on

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
  user_id       TEXT PRIMARY KEY,
  display_name  TEXT,
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
- **Multi-user**: tokens table keyed by `user_id` (Spotify user ID). `$_SESSION['active_user_id']` tracks current user; auto-selects first stored user on session restart. `?add_account=1` forces a new OAuth flow regardless of auth state. `switch_user()` clears session token cache.
- **User identity**: `callback.php` fetches `/v1/me` immediately after token exchange to capture `user_id` and `display_name`
- **Audio features**: cached in PHP session per track ID — only fetched once per track, not every poll
- **History deduplication**: checks last DB entry rather than session, so page reloads don't re-log the current track
- **MusicBrainz first load**: 4–5 sequential API calls at 1 req/sec (ISRC → artist → Wikipedia → recording → work) — expect ~5s delay on first play of any track. Instant on repeat plays (SQLite cache). Clear cache at `/api/clear-mb-cache.php`
- **Wikipedia**: MusicBrainz artist `url-rels` checked first for explicit Wikipedia link; falls back to searching by artist name. Disambiguation pages discarded.
- **Beat visualiser**: 48-bar canvas. Primary source: Spotify Audio Analysis beat timestamps. Fallback: BPM from audio features. Last resort: 120 BPM. `vizTarget` array triggered on each beat; `vizBars` lerp toward target (factor 0.18) with exponential decay (factor 0.94) at 60fps.
- **Token refresh**: automatic on any 401 from Spotify, single retry
- **db.php migration**: detects old single-row tokens schema (missing `user_id` column) and drops/recreates table automatically

## Spotify OAuth Scopes

`user-read-playback-state` · `user-modify-playback-state` · `user-read-currently-playing` · `user-read-recently-played` · `playlist-read-private` · `playlist-read-collaborative`
