(() => {
  'use strict';
  const launcher = document.getElementById('nexus-chat-launcher');
  const panel = document.getElementById('nexus-chat-panel');
  if (!launcher || !panel) return;
  const close = document.getElementById('nexus-chat-close');
  const form = document.getElementById('nexus-chat-form');
  const input = document.getElementById('nexus-chat-input');
  const send = document.getElementById('nexus-chat-send');
  const clear = document.getElementById('nexus-chat-clear');
  const messages = document.getElementById('nexus-chat-messages');
  const status = document.getElementById('nexus-chat-status');
  const suggestions = document.getElementById('nexus-chat-suggestions');
  let csrf = '';
  let ready = false;
  let busy = false;
  let loading = false;
  document.body.classList.add('nexus-chat-mounted');

  function controls() {
    input.disabled = !ready || busy;
    send.disabled = !ready || busy;
    clear.disabled = !csrf || busy;
    suggestions.querySelectorAll('button').forEach(button => { button.disabled = !ready || busy; });
    messages.setAttribute('aria-busy', String(busy));
  }
  async function request(options = {}) {
    const response = await fetch('/api/chat.php', {
      credentials: 'same-origin',
      cache: 'no-store',
      signal: AbortSignal.timeout(38000),
      ...options
    });
    let data;
    try { data = await response.json(); }
    catch (_) { throw new Error('No se ha podido conectar con el asistente. Inténtalo más tarde.'); }
    if (!response.ok) {
      if (response.status === 403) { csrf = ''; ready = false; }
      throw new Error(data.error || 'No se ha podido obtener una respuesta.');
    }
    return data;
  }
  async function initialise() {
    if (loading) return;
    loading = true;
    status.textContent = 'Conectando con el asistente…';
    try {
      const data = await request();
      csrf = data.csrf;
      ready = data.available === true;
      messages.replaceChildren();
      if (Array.isArray(data.history)) data.history.forEach(item => { if (['user','assistant'].includes(item.role) && typeof item.content === 'string') appendMessage(item.role,item.content); });
      suggestions.hidden = !ready;
      status.textContent = ready ? '¿En qué podemos ayudarte con NEXUS?' : 'El asistente IA aún no está activo. Puedes usar los enlaces de la hermandad que aparecen arriba.';
    } catch (error) {
      status.textContent = error.message;
    } finally {
      loading = false;
      controls();
    }
  }
  function setOpen(open) {
    panel.hidden = !open;
    launcher.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('nexus-chat-open', open);
    if (open) {
      close.focus();
      if (!csrf) initialise();
    } else launcher.focus();
  }
  launcher.addEventListener('click', () => setOpen(panel.hidden));
  close.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); setOpen(false); }
  });
  // Crear enlaces de forma segura; nunca insertar HTML devuelto por la IA.
  function appendText(target, text) {
    const pattern = /\[([^\]\n]{1,160})\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<>]+)/g;
    let last = 0;
    for (const match of text.matchAll(pattern)) {
      target.append(document.createTextNode(text.slice(last, match.index)));
      const raw = match[2] || match[3];
      let url;
      try { url = new URL(raw); } catch (_) { url = null; }
      const hosts = [location.hostname, 'nexus-forever.es', 'www.nexus-forever.es', 'discord.gg', 'discord.com'];
      if (url && url.protocol === 'https:' && hosts.includes(url.hostname) && !url.username && !url.password) {
        const link = document.createElement('a');
        link.href = url.href;
        link.textContent = match[1] || raw;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        target.append(link);
      } else target.append(document.createTextNode(match[0]));
      last = match.index + match[0].length;
    }
    target.append(document.createTextNode(text.slice(last)));
  }
  function appendMessage(role, text) {
    const article = document.createElement('div');
    article.className = 'nexus-chat-message';
    article.dataset.role = role;
    const label = document.createElement('strong');
    label.textContent = role === 'user' ? 'Tú' : 'Asistente IA';
    const copy = document.createElement('p');
    appendText(copy, text);
    article.append(label, copy);
    messages.append(article);
    article.scrollIntoView({ block: 'nearest' });
    return article;
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !ready) return;
    const message = input.value.trim();
    if (!message || message.length > 1200) return;
    busy = true;
    controls();
    const sent = appendMessage('user', message);
    input.value = '';
    status.textContent = 'El asistente está preparando la respuesta…';
    try {
      const data = await request({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf, message }) });
      appendMessage('assistant', data.reply);
      status.textContent = 'Confirma los detalles importantes con los oficiales en Discord.';
    } catch (error) {
      sent.remove();
      input.value = message;
      status.textContent = error.name === 'TimeoutError' ? 'La respuesta está tardando demasiado. Inténtalo más tarde.' : error.message;
    } finally {
      busy = false;
      controls();
      if (!panel.hidden && ready) input.focus();
    }
  });
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
  });
  suggestions.addEventListener('click', event => {
    const button = event.target.closest('[data-chat-question]');
    if (!button || busy || !ready) return;
    input.value = button.dataset.chatQuestion;
    form.requestSubmit();
  });
  clear.addEventListener('click', async () => {
    if (busy || !csrf) return;
    busy = true;
    controls();
    try {
      await request({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf, action: 'clear' }) });
      messages.replaceChildren();
      status.textContent = 'Conversación borrada.';
    } catch (error) { status.textContent = error.message; }
    finally { busy = false; controls(); if (!panel.hidden && ready) input.focus(); }
  });
})();
