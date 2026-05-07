<?php
session_start();
require_once __DIR__ . '/spotify.php';

if (!is_authenticated()) {
    $state = bin2hex(random_bytes(16));
    setcookie('oauth_state', $state, [
        'expires'  => time() + 600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: ' . spotify_auth_url($state));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Spotify Companion</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<div class="sc-app">

  <!-- ── MAIN AREA (3 columns) ──────────────────────────── -->
  <div class="sc-main-area">

    <!-- ── LEFT PANEL ───────────────────────────────────── -->
    <aside class="sc-left">

      <div class="sc-name-slot" id="name-slot">
        <img src="/assets/SVG/winchester-fm-logo.svg" alt="Winchester FM" class="sc-logo">
      </div>

      <section class="sc-section">
        <h6 class="sc-section-header">Upcoming tracks...</h6>
        <div class="sc-track-list sc-scrollable" id="upcoming-list">
          <p class="sc-empty">Nothing queued</p>
        </div>
      </section>

    </aside>

    <!-- ── CENTRE PANEL ─────────────────────────────────── -->
    <main class="sc-centre">
      <canvas id="viz-canvas"></canvas>

      <div class="sc-search-row">
        <div class="sc-search-wrap">
          <i class="bi bi-search sc-search-icon"></i>
          <input type="text" class="sc-search-input" id="search-input" placeholder="Search to queue next..." autocomplete="off">
          <button class="sc-btn-arrow" id="search-submit" title="Search"><i class="bi bi-arrow-right-circle-fill"></i></button>
          <div class="sc-search-results" id="search-results"></div>
        </div>
      </div>

      <h2 class="sc-song-title" id="song-title">—</h2>

      <div class="sc-artwork-info-row">
        <div class="sc-artwork-wrap">
          <img id="artwork" class="sc-artwork" src="" alt="">
          <div class="sc-artwork-placeholder" id="artwork-placeholder">
            <i class="bi bi-music-note-beamed"></i>
          </div>
        </div>
        <div class="sc-info-box sc-scrollable" id="track-info">
          <p class="sc-empty">No track playing</p>
        </div>
      </div>

      <div class="sc-volume-row">
        <button class="sc-ctrl sc-mute" id="ctrl-mute" title="Mute / Unmute"><i class="bi bi-volume-up-fill"></i></button>
        <input type="range" class="sc-range sc-volume-bar" id="volume-bar" min="0" max="100" value="80">
      </div>

    </main>

    <!-- ── RIGHT PANEL ──────────────────────────────────── -->
    <aside class="sc-right">

      <div class="sc-playlist-section">
        <div class="sc-playlist-label" id="playlist-name">No playlist active</div>
        <button class="sc-btn-playlist" id="change-playlist-btn">Change playlist</button>
      </div>

      <div class="sc-right-spacer"></div>

      <div class="sc-transport">
        <div class="sc-progress-row">
          <span class="sc-time" id="time-elapsed">0:00</span>
          <input type="range" class="sc-range sc-progress-bar" id="progress-bar" min="0" max="1000" value="0">
          <span class="sc-time" id="time-total">0:00</span>
        </div>

        <div class="sc-controls">
          <button class="sc-ctrl" id="ctrl-start" title="Skip to start"><i class="bi bi-skip-start-fill"></i></button>
          <button class="sc-ctrl" id="ctrl-prev"  title="Previous track"><i class="bi bi-skip-backward-fill"></i></button>
          <button class="sc-ctrl sc-ctrl-toggle" id="ctrl-loop" title="Repeat"><i class="bi bi-arrow-repeat"></i></button>
          <button class="sc-ctrl sc-ctrl-play" id="ctrl-play" title="Play / Pause"><i class="bi bi-play-circle-fill"></i></button>
          <button class="sc-ctrl sc-ctrl-toggle" id="ctrl-shuffle" title="Shuffle"><i class="bi bi-shuffle"></i></button>
          <button class="sc-ctrl" id="ctrl-next"  title="Next track"><i class="bi bi-skip-forward-fill"></i></button>
        </div>
      </div>

      <div class="sc-mb-box sc-scrollable" id="mb-info">
        <p class="sc-empty">No track playing</p>
      </div>

    </aside>

  </div><!-- /.sc-main-area -->

  <!-- ── HISTORY BAR (full width) ─────────────────────── -->
  <div class="sc-history-bar">
    <div class="sc-history-label">
      <span>Recently played</span>
      <button class="sc-btn-clear" id="clear-history">clear</button>
    </div>
    <div class="sc-history-scroll" id="history-list">
      <p class="sc-empty sc-history-empty">No history yet</p>
    </div>
  </div>

</div><!-- /.sc-app -->

<!-- Playlist picker modal -->
<div class="modal fade" id="playlist-modal" tabindex="-1" aria-label="Select Playlist">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content sc-modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Playlist</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="sc-playlist-search-wrap">
        <div class="sc-search-wrap">
          <i class="bi bi-search sc-search-icon"></i>
          <input type="text" class="sc-search-input" id="playlist-search-input" placeholder="Filter playlists..." autocomplete="off">
        </div>
      </div>
      <div class="modal-body" id="playlist-list">
        <p class="sc-empty">Loading...</p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
