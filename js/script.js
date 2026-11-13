(() => {
  'use strict';

  // La navegación nativa ejecuta correctamente PHP, formularios y scripts de cada página.
  const pagePath = pathname => pathname.replace(/\/index\.php$/, '/').replace(/\.php$/, '').replace(/\/+$/, '') || '/';
  const currentPath = pagePath(location.pathname);
  document.querySelectorAll('.nav-links a').forEach(link => {
    const active = pagePath(new URL(link.href).pathname) === currentPath;
    link.classList.toggle('active', active);
    if (active) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });
  const toggle = document.querySelector('.menu-toggle');
  const links = document.querySelector('.nav-links');
  function closeMenu() {
    links?.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Abrir menú');
  }
  toggle?.addEventListener('click', () => {
    const open = links?.classList.toggle('open') || false;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  links?.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
  const top = document.querySelector('.back-top');
  if (top) {
    const update = () => top.classList.toggle('show', window.scrollY > 450);
    window.addEventListener('scroll', update, { passive: true });
    update();
    top.addEventListener('click', () => window.scrollTo({
      top: 0,
      behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'
    }));
  }
})();
