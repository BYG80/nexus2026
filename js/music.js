(() => {
  "use strict";

  // Reproductor Web Audio: carga bajo demanda y preferencias persistentes.
  if (window.__NEXUS_SITE_SCRIPT_LOADED) return;
  window.__NEXUS_SITE_SCRIPT_LOADED = true;

  // El almacenamiento puede estar bloqueado en ventanas privadas o por el navegador.
  const storage = {
    getItem(key) { try { return localStorage.getItem(key); } catch (_) { return null; } },
    setItem(key, value) { try { localStorage.setItem(key, value); } catch (_) {} }
  };
  const MUSIC_SRC = new URL("/audio/nexus-ambient.mp3", window.location.origin).href;
  const ENABLED_KEY = "nexusMusicEnabled";
  const MUTED_KEY = "nexusMusicMuted";
  const VOLUME_KEY = "nexusMusicVolume";
  const POSITION_KEY = "nexusMusicTime";

  const engine = window.__NEXUS_MUSIC_ENGINE || (window.__NEXUS_MUSIC_ENGINE = {
    ctx: null,
    buffer: null,
    source: null,
    gain: null,
    startedAt: 0,
    offset: 0,
    loading: null,
    playing: false,
    manuallyPaused: false,
    muted: storage.getItem(MUTED_KEY) === "true",
    enabled: storage.getItem(ENABLED_KEY) !== "false",
    volume: Math.min(1, Math.max(0, (Number.isFinite(Number(storage.getItem(VOLUME_KEY))) && storage.getItem(VOLUME_KEY) !== null ? Number(storage.getItem(VOLUME_KEY)) : 0.18)))
  });

  let player = document.querySelector(".music-player");
  if (!player) {
    player = document.createElement("div");
    player.className = "music-player";
    player.innerHTML = `
      <button class="music-main" type="button" aria-label="Activar música" aria-pressed="false">
        <span class="music-icon">♫</span>
        <span class="music-copy"><strong>NEXUS MUSIC</strong><small>Ambiente de Azeroth</small></span>
        <span class="music-state">OFF</span>
      </button>
      <div class="music-controls">
        <button class="music-mute" type="button" aria-label="Silenciar">🔊</button>
        <input class="music-volume" type="range" min="0" max="100" value="18" aria-label="Volumen">
      </div>`;
    document.body.appendChild(player);
  }

  const main = player.querySelector(".music-main");
  const state = player.querySelector(".music-state");
  const mute = player.querySelector(".music-mute");
  const volume = player.querySelector(".music-volume");
  volume.value = Math.round(engine.volume * 100);

  function getContext() {
    if (!engine.ctx) {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return null;
      engine.ctx = new Ctx({ latencyHint: "playback" });
      engine.gain = engine.ctx.createGain();
      engine.gain.gain.value = engine.muted ? 0 : engine.volume;
      engine.gain.connect(engine.ctx.destination);
    }
    return engine.ctx;
  }

  async function loadBuffer() {
    if (engine.buffer) return engine.buffer;
    if (engine.loading) return engine.loading;
    engine.loading = fetch(MUSIC_SRC, { credentials: "same-origin", cache: "force-cache" })
      .then(r => {
        if (!r.ok) throw new Error("Music HTTP " + r.status);
        return r.arrayBuffer();
      })
      .then(data => {
        const ctx = getContext();
        if (!ctx) throw new Error("Web Audio no disponible");
        return ctx.decodeAudioData(data);
      })
      .then(buffer => {
        engine.buffer = buffer;
        engine.loading = null;
        return buffer;
      })
      .catch(err => {
        engine.loading = null;
        console.warn("NEXUS music load failed:", err);
        throw err;
      });
    return engine.loading;
  }

  function currentOffset() {
    if (!engine.ctx || !engine.playing || !engine.buffer) return engine.offset || 0;
    const elapsed = engine.ctx.currentTime - engine.startedAt;
    const duration = engine.buffer.duration || 0;
    return duration ? ((engine.offset + elapsed) % duration + duration) % duration : 0;
  }

  function savePosition() {
    if (!engine.buffer) return;
    storage.setItem(POSITION_KEY, String(currentOffset()));
  }

  function destroySource(save = true) {
    if (save) savePosition();
    if (engine.source) {
      try { engine.source.onended = null; engine.source.stop(0); } catch (_) {}
      try { engine.source.disconnect(); } catch (_) {}
    }
    engine.source = null;
    engine.playing = false;
  }

  async function startMusic() {
    if (!engine.enabled || engine.muted || engine.manuallyPaused) return;
    const ctx = getContext();
    if (!ctx) return;
    try {
      await ctx.resume();
      const buffer = await loadBuffer();
      if (engine.playing || engine.manuallyPaused || !engine.enabled || engine.muted) return;

      const saved = Number(storage.getItem(POSITION_KEY) || 0);
      if (!engine.source) {
        engine.offset = Number.isFinite(saved) && saved >= 0 && saved < buffer.duration
          ? saved : 0;
        const source = ctx.createBufferSource();
        source.buffer = buffer;
        source.loop = true;
        source.connect(engine.gain);
        engine.source = source;
        engine.startedAt = ctx.currentTime;
        source.start(0, engine.offset);
        engine.playing = true;
      }
      paintMusic();
    } catch (_) {
      paintMusic();
    }
  }

  function pauseMusic() {
    savePosition();
    engine.offset = currentOffset();
    destroySource(false);
    engine.manuallyPaused = true;
    paintMusic();
  }

  function userStart() {
    engine.enabled = true;
    engine.muted = false;
    engine.manuallyPaused = false;
    storage.setItem(ENABLED_KEY, "true");
    storage.setItem(MUTED_KEY, "false");
    if (engine.gain) engine.gain.gain.value = engine.volume;
    startMusic();
  }

  function paintMusic() {
    const playing = !!engine.playing && !engine.muted && !engine.manuallyPaused;
    player.classList.toggle("is-playing", playing);
    state.textContent = playing ? "ON" : "OFF";
    mute.textContent = engine.muted || engine.volume === 0 ? "🔇" : "🔊";
    main.setAttribute("aria-label", playing ? "Pausar música" : "Activar música");
    main.setAttribute("aria-pressed", String(playing));
    mute.setAttribute('aria-label', engine.muted ? 'Activar sonido' : 'Silenciar');
    mute.setAttribute('aria-pressed', String(engine.muted));
  }

  main.addEventListener("click", () => {
    if (engine.playing && !engine.manuallyPaused) {
      pauseMusic();
    } else {
      userStart();
    }
  });

  mute.addEventListener("click", () => {
    engine.muted = !engine.muted;
    storage.setItem(MUTED_KEY, String(engine.muted));
    if (engine.gain) engine.gain.gain.value = engine.muted ? 0 : engine.volume;
    if (!engine.muted) userStart();
    paintMusic();
  });

  volume.addEventListener("input", () => {
    engine.volume = Number(volume.value) / 100;
    storage.setItem(VOLUME_KEY, String(engine.volume));
    if (engine.gain) engine.gain.gain.value = engine.muted ? 0 : engine.volume;
    if (engine.volume > 0 && engine.muted) {
      engine.muted = false;
      userStart();
    }
    paintMusic();
  });

  // El MP3 se descarga solo cuando se solicita reproducirlo.

  // ==========================================================
  // FIRST USER INTERACTION / AUTOPLAY
  // ----------------------------------------------------------
  // Chrome/Edge bloquean el sonido hasta que existe un gesto del
  // usuario. El gesto puede ser CUALQUIER clic, toque o tecla; no
  // hace falta pulsar el botón de música.
  // ==========================================================
  // Reanudar solo una preferencia previa, sin descargar ni decodificar audio al entrar.
  function resumeFromGesture(event) {
    if (event.target.closest?.('.music-player')) return;
    if (engine.enabled && !engine.muted && !engine.manuallyPaused) startMusic();
    window.removeEventListener('pointerdown', resumeFromGesture);
    window.removeEventListener('keydown', resumeFromGesture);
  }
  window.addEventListener('pointerdown', resumeFromGesture, { passive: true });
  window.addEventListener('keydown', resumeFromGesture);

  window.addEventListener("pagehide", savePosition, { passive: true });
  setInterval(() => { if (engine.playing) savePosition(); }, 5000);
  paintMusic();

})();
