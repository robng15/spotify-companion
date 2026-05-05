# Spotify Companion

A single-user companion app for Spotify. Controls and enriches the active Spotify session (requires Premium) running alongside the main Spotify client.

## Tech Stack

- **Backend**: PHP (Authorization Code Flow OAuth, API proxy layer)
- **Frontend**: Bootstrap + vanilla JS (fetch + setInterval polling)
- **Database**: SQLite (stored outside web root)
- **APIs**: Spotify Web API, MusicBrainz API

## Deployment

- Plesk / Linux server
- SQLite DB at `/home/{user}/spotify-companion.db` (outside web root)
- Web root: `/public_html/spotify-companion/`

## File Structure

```
/home/{user}/
  spotify-companion.db

/public_html/spotify-companion/
  index.php                     ← main UI
  callback.php                  ← OAuth redirect handler
  config.php                    ← Client ID/Secret (git-ignored)
  api/
    now-playing.php
    controls.php
    queue.php
    history.php
    search.php
    playlists.php
    musicbrainz.php
  assets/
    css/app.css
    js/app.js
```

## Layout (3-column)

- **Left**: Upcoming tracks (scrollable, circular art) / Recently played (scrollable, circular art, clear button)
- **Centre**: Search → queue next / Song title (full wrap, no truncation) / Large artwork / Currently playing info box (scrollable — Spotify data)
- **Right**: Playlist active + Change playlist / Progress seek bar / 6 transport controls / Mute + volume / MusicBrainz info box (scrollable)

## Transport Controls (6)

`|◄` Skip to start · `◄` Previous track · `○` Loop toggle · `▶` Play/Pause · `○` Shuffle toggle · `►` Next track

## Info Box Content Split

**Currently playing (Spotify):** Album · Release year · Duration · BPM · Key · Time signature · Energy · Danceability · Valence · Acousticness · Explicit · Popularity

**MusicBrainz:** Band members (with active dates) · Composers / songwriters / producers · All releases this track appears on

## SQLite Schema

```sql
CREATE TABLE history (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  played_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  track_id     TEXT,
  track_name   TEXT,
  artist       TEXT,
  album        TEXT,
  artwork_url  TEXT,
  duration_ms  INTEGER
);

CREATE TABLE mb_cache (
  isrc         TEXT PRIMARY KEY,
  data         TEXT,
  fetched_at   DATETIME
);

CREATE TABLE tokens (
  id            INTEGER PRIMARY KEY CHECK (id = 1),
  access_token  TEXT,
  refresh_token TEXT,
  expires_at    DATETIME
);
```

## Polling Intervals

- Now playing: every 2s
- Queue/upcoming: every 5s
- History: on track change (writes to SQLite)
- MusicBrainz: on track change, cache-first (ISRC key, 1 req/sec rate limit)

## Spotify OAuth Scopes

`user-read-playback-state` · `user-modify-playback-state` · `user-read-currently-playing` · `user-read-recently-played` · `playlist-read-private` · `playlist-read-collaborative`

## Build Order

1. Config, OAuth flow, token storage + refresh
2. Bootstrap layout shell
3. Now playing — artwork, title, progress, info box
4. Transport controls + volume
5. Queue (upcoming tracks)
6. History (SQLite write/read/clear)
7. Search + queue next
8. Playlist selector
9. MusicBrainz integration
10. Polish — empty states, errors, edge cases

## Notes

- "Leave a space for a name" top-left — reserved for later feature
- Single user, no login system needed
- History persists across sessions via SQLite
- MusicBrainz requires descriptive User-Agent header, 1 req/sec limit
- Token auto-refresh: on any 401 response from Spotify API
