(() => {
  'use strict';
  const slider = document.querySelector('[data-nexus-slider]');
  if (!slider) return;
  const slides = [...slider.querySelectorAll('.nexus-slide')];
  const dots = [...slider.querySelectorAll('.nexus-slider-dots button')];
  const pause = slider.querySelector('.nexus-slider-pause');
  const status = slider.querySelector('[data-slider-status]');
  if (slides.length < 2 || slides.length !== dots.length) return;

  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let timer;
  let paused = reducedMotion.matches;
  let pointerStart = null;

  function show(index, announce = false) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      slide.classList.toggle('is-active', i === current);
      slide.setAttribute('aria-hidden', String(i !== current));
    });
    dots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === current);
      dot.setAttribute('aria-current', String(i === current));
    });
    if (announce && status) status.textContent = `Imagen ${current + 1} de ${slides.length}`;
  }
  function stop() { clearTimeout(timer); }
  function start() {
    stop();
    if (paused || document.hidden || slider.matches(':hover') || slider.contains(document.activeElement)) return;
    timer = setTimeout(() => { show(current + 1); start(); }, 5500);
  }
  function navigate(index) { show(index, true); start(); }
  function paintPause() {
    if (!pause) return;
    pause.textContent = paused ? 'Reanudar carrusel' : 'Pausar carrusel';
    pause.setAttribute('aria-pressed', String(paused));
  }
  slider.querySelector('.nexus-slider-prev')?.addEventListener('click', () => navigate(current - 1));
  slider.querySelector('.nexus-slider-next')?.addEventListener('click', () => navigate(current + 1));
  dots.forEach((dot, i) => dot.addEventListener('click', () => navigate(i)));
  pause?.addEventListener('click', () => { paused = !paused; paintPause(); start(); });
  slider.addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    navigate(event.key === 'Home' ? 0 : event.key === 'End' ? slides.length - 1 : current + (event.key === 'ArrowRight' ? 1 : -1));
  });
  slider.addEventListener('pointerdown', event => {
    if (event.pointerType === 'touch') pointerStart = { x: event.clientX, y: event.clientY };
  }, { passive: true });
  slider.addEventListener('pointerup', event => {
    if (!pointerStart) return;
    const dx = event.clientX - pointerStart.x;
    const dy = event.clientY - pointerStart.y;
    pointerStart = null;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) navigate(current + (dx < 0 ? 1 : -1));
  }, { passive: true });
  slider.addEventListener('pointercancel', () => { pointerStart = null; });
  slider.addEventListener('mouseenter', stop);
  slider.addEventListener('mouseleave', start);
  slider.addEventListener('focusin', stop);
  slider.addEventListener('focusout', event => { if (!slider.contains(event.relatedTarget)) queueMicrotask(start); });
  document.addEventListener('visibilitychange', start);
  reducedMotion.addEventListener('change', () => { paused = reducedMotion.matches; paintPause(); start(); });
  show(0);
  paintPause();
  start();
})();
