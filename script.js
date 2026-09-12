document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', e => {
    const id = link.getAttribute('href');
    if (id.length <= 1) return;
    const target = document.querySelector(id);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

const primaryNavigation = document.querySelector('.nav-links');
if (primaryNavigation) {
  const english = document.documentElement.lang.toLowerCase().startsWith('en');
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'nav-toggle';
  primaryNavigation.id = primaryNavigation.id || 'primary-navigation';
  toggle.setAttribute('aria-controls', primaryNavigation.id);
  toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
  const setOpen = open => {
    primaryNavigation.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', english ? (open ? 'Close menu' : 'Open menu') : (open ? 'Fechar menu' : 'Abrir menu'));
  };
  const language = primaryNavigation.parentElement.querySelector('.lang');
  (language || primaryNavigation).after(toggle);
  setOpen(false);
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  primaryNavigation.addEventListener('click', event => {
    if (event.target.closest('a')) setOpen(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
      toggle.focus();
    }
  });
  document.addEventListener('click', event => {
    if (!primaryNavigation.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
  });
  window.matchMedia('(max-width: 980px)').addEventListener('change', () => setOpen(false));
}
