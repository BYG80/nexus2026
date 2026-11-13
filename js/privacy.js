(() => {
  'use strict';
  const KEY = 'nexus_privacy_choice_v1';
  const banner = document.getElementById('nexus-cookie-banner');
  if (!banner) return;
  const settings = document.getElementById('nexus-cookie-settings');
  const external = document.getElementById('nexus-cookie-external');
  const frames = [...document.querySelectorAll('iframe[data-consent-src], iframe[src*="youtube.com/embed/"], iframe[src*="youtube-nocookie.com/embed/"]')];
  function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); }
    catch (_) { return null; }
  }
  function openSettings() {
    external.checked = read()?.external === true;
    settings.hidden = false;
    banner.hidden = false;
    document.getElementById('nexus-cookie-external').focus();
  }
  function updateEmbeds(allowed) {
    frames.forEach(frame => {
      // Mantener la URL permite retirar el consentimiento después de aceptarlo.
      if (!frame.dataset.consentSrc) frame.dataset.consentSrc = frame.getAttribute('src') || '';
      const placeholder = frame.previousElementSibling;
      if (allowed) {
        frame.src = frame.dataset.consentSrc;
        frame.hidden = false;
        if (placeholder?.classList.contains('nexus-youtube-consent')) placeholder.remove();
      } else {
        frame.removeAttribute('src');
        frame.hidden = true;
        if (placeholder?.classList.contains('nexus-youtube-consent')) return;
        const box = document.createElement('div');
        box.className = 'nexus-youtube-consent';
        box.innerHTML = '<div><strong>Vídeo externo de YouTube</strong><p>Permite el contenido externo para cargar este vídeo.</p><button type="button">Configurar YouTube</button></div>';
        box.querySelector('button').addEventListener('click', openSettings);
        frame.before(box);
      }
    });
  }
  function apply(allowed) {
    try { localStorage.setItem(KEY, JSON.stringify({ external: allowed, updated: new Date().toISOString() })); }
    catch (_) { /* La selección sigue siendo válida para esta página. */ }
    banner.hidden = true;
    settings.hidden = true;
    updateEmbeds(allowed);
  }
  const saved = read();
  banner.hidden = typeof saved?.external === 'boolean';
  external.checked = saved?.external === true;
  updateEmbeds(external.checked);
  document.getElementById('nexus-cookie-accept').addEventListener('click', () => apply(true));
  document.getElementById('nexus-cookie-reject').addEventListener('click', () => apply(false));
  document.getElementById('nexus-cookie-configure').addEventListener('click', () => { settings.hidden = !settings.hidden; });
  document.getElementById('nexus-cookie-save').addEventListener('click', () => apply(external.checked));
  const manage = document.createElement('button');
  manage.type = 'button';
  manage.className = 'nexus-privacy-manage';
  manage.textContent = 'Privacidad y cookies';
  manage.setAttribute('aria-label', 'Cambiar preferencias de cookies');
  manage.addEventListener('click', openSettings);
  document.body.appendChild(manage);
})();
