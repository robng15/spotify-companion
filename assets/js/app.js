'use strict';

// ── DOM refs ──────────────────────────────────────────────────────────────────
const el = {
  songTitle:       document.getElementById('song-title'),
  artwork:         document.getElementById('artwork'),
  artworkPH:       document.getElementById('artwork-placeholder'),
  trackInfo:       document.getElementById('track-info'),
  mbInfo:          document.getElementById('mb-info'),
  upcomingList:    document.getElementById('upcoming-list'),
  historyList:     document.getElementById('history-list'),
  clearHistory:    document.getElementById('clear-history'),
  searchInput:     document.getElementById('search-input'),
  searchSubmit:    document.getElementById('search-submit'),
  searchResults:   document.getElementById('search-results'),
  playlistName:    document.getElementById('playlist-name'),
  changePlaylist:  document.getElementById('change-playlist-btn'),
  playlistList:    document.getElementById('playlist-list'),
  playlistSearch:  document.getElementById('playlist-search-input'),
  timeElapsed:     document.getElementById('time-elapsed'),
  timeTotal:       document.getElementById('time-total'),
  progressBar:     document.getElementById('progress-bar'),
  volumeBar:       document.getElementById('volume-bar'),
  ctrlPlay:        document.getElementById('ctrl-play'),
  ctrlPrev:        document.getElementById('ctrl-prev'),
  ctrlNext:        document.getElementById('ctrl-next'),
  ctrlStart:       document.getElementById('ctrl-start'),
  ctrlLoop:        document.getElementById('ctrl-loop'),
  ctrlShuffle:     document.getElementById('ctrl-shuffle'),
  ctrlMute:        document.getElementById('ctrl-mute'),
};

// ── State ─────────────────────────────────────────────────────────────────────
const state = {
  trackId:     null,
  isrc:        null,
  isPlaying:   false,
  durationMs:  0,
  progressMs:  0,
  volume:      80,
  isMuted:     false,
  shuffle:     false,
  repeat:      'off',        // off | context | track
  seekDragging: false,
  contextUri:  null,         // spotify:playlist:... or null
  tempo:       null,
};

// ── Helpers ───────────────────────────────────────────────────────────────────
function msToTime(ms) {
  const s = Math.floor((ms || 0) / 1000);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function esc(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
  return String(str ?? '').replace(/"/g, '&quot;');
}

async function apiGet(path) {
  try {
    const r = await fetch('/api/' + path);
    return r.ok ? r.json() : null;
  } catch { return null; }
}

async function apiPost(path, body) {
  try {
    await fetch('/api/' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
  } catch {}
}

// ── Now playing ───────────────────────────────────────────────────────────────
async function pollNowPlaying() {
  const data = await apiGet('now-playing.php');
  if (!data || data.error) return;
  applyNowPlaying(data);
}

function applyNowPlaying(d) {
  // Title
  el.songTitle.textContent = d.track_name ?? '—';

  // Artwork
  if (d.artwork_url) {
    el.artwork.src = d.artwork_url;
    el.artwork.classList.add('loaded');
    el.artworkPH.style.display = 'none';
  } else {
    el.artwork.classList.remove('loaded');
    el.artworkPH.style.display = 'flex';
  }

  // Progress
  state.durationMs = d.duration_ms ?? 0;
  if (!state.seekDragging) {
    state.progressMs = d.progress_ms ?? 0;
    el.progressBar.value = state.durationMs ? Math.round((state.progressMs / state.durationMs) * 1000) : 0;
    el.timeElapsed.textContent = msToTime(state.progressMs);
  }
  el.timeTotal.textContent = msToTime(state.durationMs);

  // Play/pause icon
  state.isPlaying = !!d.is_playing;
  el.ctrlPlay.querySelector('i').className = state.isPlaying
    ? 'bi bi-pause-circle-fill' : 'bi bi-play-circle-fill';

  // Toggle states
  state.shuffle = !!d.shuffle_state;
  el.ctrlShuffle.classList.toggle('active', state.shuffle);

  state.repeat = d.repeat_state ?? 'off';
  el.ctrlLoop.classList.toggle('active', state.repeat !== 'off');

  // Volume (initialise once)
  if (d.device_volume != null && !state.volumeInit) {
    state.volume = d.device_volume;
    el.volumeBar.value = state.volume;
    state.volumeInit = true;
  }

  // Track context and tempo
  state.contextUri = d.context_uri ?? null;
  state.tempo      = d.tempo ?? null;

  // Track info
  renderTrackInfo(d);

  // New track — update beat tempo + MusicBrainz
  if (d.track_id && d.track_id !== state.trackId) {
    state.trackId = d.track_id;
    state.isrc    = d.isrc ?? null;
    vizSetTempo(state.tempo);
    fetchMusicBrainz();
  }
}

function renderTrackInfo(d) {
  const KEYS = ['C','C♯','D','D♯','E','F','F♯','G','G♯','A','A♯','B'];
  const keyStr = (d.key != null && d.mode != null)
    ? KEYS[d.key] + (d.mode === 1 ? ' major' : ' minor')
    : null;

  const rows = [
    ['Album',        d.album],
    ['Artist',       d.artists],
    ['Released',     d.release_year],
    ['Duration',     msToTime(d.duration_ms)],
    ['BPM',          d.tempo != null ? Math.round(d.tempo) : null],
    ['Key',          keyStr],
    ['Time sig',     d.time_signature != null ? `${d.time_signature}/4` : null],
    ['Energy',       d.energy       != null ? pct(d.energy)       : null],
    ['Danceability', d.danceability != null ? pct(d.danceability) : null],
    ['Valence',      d.valence      != null ? pct(d.valence)      : null],
    ['Acousticness', d.acousticness != null ? pct(d.acousticness) : null],
    ['Live perf.',   d.liveness     != null ? pct(d.liveness)     : null],
  ].filter(([, v]) => v != null);

  if (!rows.length) {
    el.trackInfo.innerHTML = '<p class="sc-empty">No information available</p>';
    return;
  }

  el.trackInfo.innerHTML = rows.map(([label, value]) =>
    `<span class="sc-info-label">${esc(label)}: </span><span class="sc-info-value">${esc(String(value))}</span><br>`
  ).join('');
}

function pct(v) { return `${Math.round(v * 100)}%`; }

// ── Progress ticker (client-side interpolation between polls) ─────────────────
function tickProgress() {
  if (!state.isPlaying || !state.durationMs || state.seekDragging) return;
  state.progressMs = Math.min(state.progressMs + 1000, state.durationMs);
  el.timeElapsed.textContent = msToTime(state.progressMs);
  el.progressBar.value = Math.round((state.progressMs / state.durationMs) * 1000);
}

// Progress drag
el.progressBar.addEventListener('mousedown', () => { state.seekDragging = true; });
el.progressBar.addEventListener('touchstart', () => { state.seekDragging = true; }, { passive: true });
el.progressBar.addEventListener('change', () => {
  const ms = Math.round((el.progressBar.value / 1000) * state.durationMs);
  state.progressMs    = ms;
  state.seekDragging  = false;
  el.timeElapsed.textContent = msToTime(ms);
  apiPost('controls.php', { action: 'seek', position_ms: ms });
});

// ── Queue ─────────────────────────────────────────────────────────────────────
async function pollQueue() {
  const data = await apiGet('queue.php');
  if (!data?.queue) return;
  renderQueueList(el.upcomingList, data.queue, 'Nothing queued');
}

function renderQueueList(container, tracks, emptyMsg) {
  if (!tracks.length) {
    container.innerHTML = `<p class="sc-empty">${esc(emptyMsg)}</p>`;
    return;
  }
  container.innerHTML = tracks.map(t => `
    <div class="sc-track-item">
      ${t.artwork_url
        ? `<img class="sc-track-thumb" src="${esc(t.artwork_url)}" alt="" loading="lazy">`
        : `<div class="sc-track-thumb-empty"></div>`}
      <div class="sc-track-name-wrap">
        <div class="sc-track-name">${esc(t.track_name)}</div>
        <div class="sc-track-artist">${esc(t.artist)}</div>
      </div>
      ${t.uri && state.contextUri
        ? `<button class="sc-track-play" data-uri="${escAttr(t.uri)}" title="Jump to this track"><i class="bi bi-music-note"></i></button>`
        : ''}
    </div>`
  ).join('');

  container.querySelectorAll('.sc-track-play').forEach(btn => {
    btn.addEventListener('click', async () => {
      const uri = btn.dataset.uri;
      if (!uri || !state.contextUri) return;
      await apiPost('controls.php', {
        action: 'skip_to_track',
        context_uri: state.contextUri,
        track_uri: uri,
      });
      setTimeout(pollNowPlaying, 600);
      setTimeout(pollQueue, 1000);
    });
  });
}

// ── History ───────────────────────────────────────────────────────────────────
async function pollHistory() {
  const data = await apiGet('history.php');
  if (!data?.history) return;
  renderHistoryList(data.history);
}

function renderHistoryList(tracks) {
  if (!tracks.length) {
    el.historyList.innerHTML = '<p class="sc-empty sc-history-empty">No history yet</p>';
    return;
  }
  el.historyList.innerHTML = tracks.map(t => {
    const uri = t.track_id ? `spotify:track:${t.track_id}` : null;
    return `
    <div class="sc-track-item">
      ${t.artwork_url
        ? `<img class="sc-track-thumb" src="${esc(t.artwork_url)}" alt="" loading="lazy">`
        : `<div class="sc-track-thumb-empty"></div>`}
      <div class="sc-track-name-wrap">
        <div class="sc-track-name">${esc(t.track_name)}</div>
        <div class="sc-track-artist">${esc(t.artist)}</div>
      </div>
      ${uri && state.contextUri
        ? `<button class="sc-track-play" data-uri="${escAttr(uri)}" title="Jump to this track"><i class="bi bi-music-note"></i></button>`
        : ''}
    </div>`;
  }).join('');

  el.historyList.querySelectorAll('.sc-track-play').forEach(btn => {
    btn.addEventListener('click', async () => {
      const uri = btn.dataset.uri;
      if (!uri || !state.contextUri) return;
      await apiPost('controls.php', {
        action: 'skip_to_track',
        context_uri: state.contextUri,
        track_uri: uri,
      });
      setTimeout(pollNowPlaying, 600);
      setTimeout(pollQueue, 1000);
    });
  });
}

el.clearHistory.addEventListener('click', async () => {
  await apiPost('history.php', { action: 'clear' });
  el.historyList.innerHTML = '<p class="sc-empty sc-history-empty">No history yet</p>';
});

// ── MusicBrainz ───────────────────────────────────────────────────────────────
async function fetchMusicBrainz() {
  el.mbInfo.innerHTML = '<p class="sc-empty">Loading…</p>';
  if (!state.isrc) {
    el.mbInfo.innerHTML = '<p class="sc-empty">No ISRC available</p>';
    return;
  }
  const data = await apiGet(`musicbrainz.php?isrc=${encodeURIComponent(state.isrc)}`);
  if (!data || data.error) {
    el.mbInfo.innerHTML = '<p class="sc-empty">No data found</p>';
    return;
  }
  renderMusicBrainz(data);
}

function renderMusicBrainz(d) {
  let html = '';

  if (d.members?.length) {
    html += `<strong>Members</strong>`;
    html += d.members.map(m => {
      let line = esc(m.name);
      if (m.instrument) line += ` <span style="color:var(--muted)">(${esc(m.instrument)})</span>`;
      if (m.begin || m.end) line += ` <span style="color:var(--muted);font-size:.7rem">${esc(m.begin ?? '?')}–${esc(m.end ?? '')}</span>`;
      return line;
    }).join('<br>');
    html += '<br>';
  }

  if (d.composers?.length) {
    html += `<strong>Composers / Writers</strong>${d.composers.map(c => esc(c)).join(', ')}<br>`;
  }

  if (d.producers?.length) {
    html += `<strong>Producers</strong>${d.producers.map(p => esc(p)).join(', ')}<br>`;
  }

  if (d.appears_on?.length) {
    html += `<strong>Appears on</strong>`;
    html += d.appears_on.map(r =>
      `${esc(r.title)} <span style="color:var(--muted)">(${esc(r.type)}, ${esc(r.year ?? '?')})</span>`
    ).join('<br>');
  }

  el.mbInfo.innerHTML = html || '<p class="sc-empty">No data found</p>';
}

// ── Transport controls ────────────────────────────────────────────────────────
el.ctrlPlay.addEventListener('click', () => {
  const action = state.isPlaying ? 'pause' : 'play';
  state.isPlaying = !state.isPlaying;
  el.ctrlPlay.querySelector('i').className = state.isPlaying
    ? 'bi bi-pause-circle-fill' : 'bi bi-play-circle-fill';
  apiPost('controls.php', { action });
});

el.ctrlPrev.addEventListener('click',  () => apiPost('controls.php', { action: 'previous' }));
el.ctrlNext.addEventListener('click',  () => apiPost('controls.php', { action: 'next' }));
el.ctrlStart.addEventListener('click', () => {
  state.progressMs = 0;
  el.progressBar.value = 0;
  el.timeElapsed.textContent = '0:00';
  apiPost('controls.php', { action: 'seek', position_ms: 0 });
});

el.ctrlLoop.addEventListener('click', () => {
  const next = { off: 'context', context: 'track', track: 'off' }[state.repeat] ?? 'off';
  state.repeat = next;
  el.ctrlLoop.classList.toggle('active', next !== 'off');
  apiPost('controls.php', { action: 'repeat', state: next });
});

el.ctrlShuffle.addEventListener('click', () => {
  state.shuffle = !state.shuffle;
  el.ctrlShuffle.classList.toggle('active', state.shuffle);
  apiPost('controls.php', { action: 'shuffle', state: state.shuffle });
});

// Volume
let volumeTimer;
el.volumeBar.addEventListener('input', () => {
  state.volume = Number(el.volumeBar.value);
  if (state.isMuted && state.volume > 0) {
    state.isMuted = false;
    el.ctrlMute.querySelector('i').className = 'bi bi-volume-up-fill';
  }
  clearTimeout(volumeTimer);
  volumeTimer = setTimeout(() => {
    apiPost('controls.php', { action: 'volume', percent: state.volume });
  }, 200);
});

el.ctrlMute.addEventListener('click', () => {
  state.isMuted = !state.isMuted;
  el.ctrlMute.querySelector('i').className = state.isMuted
    ? 'bi bi-volume-mute-fill' : 'bi bi-volume-up-fill';
  apiPost('controls.php', { action: 'volume', percent: state.isMuted ? 0 : state.volume });
});

// ── Search ────────────────────────────────────────────────────────────────────
let searchTimer;
el.searchInput.addEventListener('input', () => {
  clearTimeout(searchTimer);
  const q = el.searchInput.value.trim();
  if (!q) { el.searchResults.style.display = 'none'; return; }
  searchTimer = setTimeout(runSearch, 380);
});

el.searchInput.addEventListener('keydown', e => {
  if (e.key === 'Enter')  { e.preventDefault(); runSearch(); }
  if (e.key === 'Escape') el.searchResults.style.display = 'none';
});

el.searchSubmit.addEventListener('click', runSearch);

async function runSearch() {
  const q = el.searchInput.value.trim();
  if (!q) return;
  el.searchResults.innerHTML = '<p class="sc-empty" style="padding:12px 14px">Searching…</p>';
  el.searchResults.style.display = 'block';

  const data = await apiGet(`search.php?q=${encodeURIComponent(q)}`);
  if (!data?.tracks?.length) {
    el.searchResults.innerHTML = '<p class="sc-empty" style="padding:12px 14px">No results found</p>';
    return;
  }

  el.searchResults.innerHTML = data.tracks.map((t, i) => `
    <div class="sc-search-result-item" data-i="${i}">
      ${t.artwork ? `<img src="${esc(t.artwork)}" alt="" loading="lazy">` : ''}
      <div>
        <span class="sc-search-result-track">${esc(t.name)}</span>
        <span class="sc-search-result-artist">${esc(t.artists)}</span>
      </div>
    </div>`
  ).join('');

  const items = el.searchResults.querySelectorAll('.sc-search-result-item');
  items.forEach((item, i) => {
    item.addEventListener('click', async () => {
      const t = data.tracks[i];
      el.searchResults.style.display = 'none';
      el.searchInput.value = '';
      await apiPost('controls.php', { action: 'queue', uri: t.uri });
      setTimeout(pollQueue, 800);
    });
  });
}

// Close search results when clicking outside — check contains() to handle icon child clicks
document.addEventListener('click', e => {
  if (!el.searchResults.contains(e.target) && e.target !== el.searchInput && !el.searchSubmit.contains(e.target)) {
    el.searchResults.style.display = 'none';
  }
});

// ── Playlist picker ───────────────────────────────────────────────────────────
const playlistModal = new bootstrap.Modal(document.getElementById('playlist-modal'));

el.changePlaylist.addEventListener('click', async () => {
  el.playlistSearch.value = '';
  el.playlistList.innerHTML = '<p class="sc-empty">Loading…</p>';
  playlistModal.show();

  const data = await apiGet('playlists.php');
  if (!data?.playlists?.length) {
    el.playlistList.innerHTML = '<p class="sc-empty">No playlists found</p>';
    return;
  }

  el.playlistList.innerHTML = data.playlists.map((p, i) => `
    <div class="sc-playlist-item" data-i="${i}">
      ${p.artwork
        ? `<img src="${esc(p.artwork)}" alt="">`
        : `<div class="sc-playlist-item-empty"></div>`}
      <div>
        <div class="sc-playlist-item-name">${esc(p.name)}</div>
        <div class="sc-playlist-item-count">${p.track_count} tracks</div>
      </div>
    </div>`
  ).join('');

  el.playlistList.querySelectorAll('.sc-playlist-item').forEach((item, i) => {
    item.addEventListener('click', async () => {
      const p = data.playlists[i];
      await apiPost('controls.php', { action: 'play_context', uri: p.uri });
      el.playlistName.textContent = p.name;
      el.playlistName.classList.add('active');
      playlistModal.hide();
    });
  });
});

// Playlist filter
el.playlistSearch.addEventListener('input', () => {
  const q = el.playlistSearch.value.toLowerCase().trim();
  el.playlistList.querySelectorAll('.sc-playlist-item').forEach(item => {
    const name = item.querySelector('.sc-playlist-item-name')?.textContent.toLowerCase() ?? '';
    item.style.display = !q || name.includes(q) ? '' : 'none';
  });
});

// ── Beat visualiser ───────────────────────────────────────────────────────────
const vizCanvas = document.getElementById('viz-canvas');
const vizCtx    = vizCanvas.getContext('2d');
const VIZ_BARS  = 36;
const vizBars   = new Float32Array(VIZ_BARS);

let vizBeatInterval = 0.5;   // seconds between beats (120 BPM default)
let vizLastBeatWall = 0;     // performance.now()/1000 at last beat trigger

function vizSetTempo(tempo) {
  vizBeatInterval = 60 / (tempo || 120);
  vizLastBeatWall = 0;  // reset phase on track change
}

function vizTriggerBeat() {
  const intensity = 0.5 + Math.random() * 0.5;
  const raw = Array.from({length: VIZ_BARS}, () => Math.random());
  for (let i = 0; i < VIZ_BARS; i++) {
    const l = raw[Math.max(0, i - 1)];
    const r = raw[Math.min(VIZ_BARS - 1, i + 1)];
    vizBars[i] = (l * 0.2 + raw[i] * 0.6 + r * 0.2) * intensity;
  }
}

function vizLoop() {
  // Size canvas to its CSS dimensions
  const W = vizCanvas.offsetWidth;
  const H = vizCanvas.offsetHeight;
  if (vizCanvas.width !== W || vizCanvas.height !== H) {
    vizCanvas.width  = W;
    vizCanvas.height = H;
  }

  vizCtx.clearRect(0, 0, W, H);

  // Fire a beat whenever the wall-clock interval elapses
  if (state.isPlaying) {
    const nowSec = performance.now() / 1000;
    if (vizLastBeatWall === 0 || nowSec - vizLastBeatWall > vizBeatInterval * 3) {
      vizLastBeatWall = nowSec;  // initialise or re-sync after long pause
    }
    while (nowSec - vizLastBeatWall >= vizBeatInterval) {
      vizTriggerBeat();
      vizLastBeatWall += vizBeatInterval;
    }
  }

  // Draw bars (one shared gradient per frame)
  if (W && H) {
    const barW = W / VIZ_BARS;
    const gap  = Math.max(1, barW * 0.12);
    const grad = vizCtx.createLinearGradient(0, 0, 0, H);
    grad.addColorStop(0,   'rgba(245,162,0,0.0)');
    grad.addColorStop(0.4, 'rgba(245,162,0,0.5)');
    grad.addColorStop(1,   'rgba(245,162,0,0.9)');
    vizCtx.fillStyle = grad;

    for (let i = 0; i < VIZ_BARS; i++) {
      const h = vizBars[i] * H * 0.88;
      if (h >= 1) vizCtx.fillRect(i * barW + gap / 2, H - h, barW - gap, h);
      vizBars[i] = Math.max(0.015, vizBars[i] - 0.036);
    }
  }

  requestAnimationFrame(vizLoop);
}

vizLoop();

// ── Boot ──────────────────────────────────────────────────────────────────────
pollNowPlaying();
pollQueue();
pollHistory();

setInterval(pollNowPlaying, 2000);
setInterval(pollQueue,      5000);
setInterval(pollHistory,   10000);
setInterval(tickProgress,   1000);
