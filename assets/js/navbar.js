// ============================================================
// assets/js/navbar.js — pílula deslizante da navegação
// ============================================================
// A pílula acompanha o item em foco/hover e desliza suavemente
// até ele. Ao sair da navegação, volta ao item ativo. Em páginas
// sem item ativo, a pílula fica oculta até o primeiro hover.
// ============================================================

(() => {
  const pill = document.querySelector('.site-nav-pill');
  if (!pill) return;

  const active = pill.querySelector('.site-nav-link.is-active');
  const isDesktop = () => window.matchMedia('(min-width: 992px)').matches;
  let current = active;

  const setIndicator = (link, instant = false) => {
    if (!link) return;

    const pillRect = pill.getBoundingClientRect();
    const linkRect = link.getBoundingClientRect();

    if (instant) pill.classList.add('is-instant');

    pill.classList.remove('is-empty');
    pill.style.setProperty('--nav-indicator-x', `${linkRect.left - pillRect.left}px`);
    pill.style.setProperty('--nav-indicator-w', `${linkRect.width}px`);

    if (instant) {
      void pill.offsetWidth;
      pill.classList.remove('is-instant');
    }

    current = link;
  };

  const hideIndicator = () => {
    pill.classList.add('is-empty');
    current = null;
  };

  const reset = () => {
    if (!isDesktop()) return;
    if (active) setIndicator(active);
    else hideIndicator();
  };

  const enableDesktop = () => {
    pill.classList.add('is-magic');
    if (active) setIndicator(active, true);
    else hideIndicator();
  };

  const disableDesktop = () => {
    pill.classList.remove('is-magic');
    pill.classList.remove('is-empty');
  };

  if (isDesktop()) enableDesktop();

  pill.addEventListener('pointerover', (event) => {
    if (!isDesktop()) return;

    const link = event.target.closest('.site-nav-link');
    if (link && pill.contains(link)) setIndicator(link);
  });

  pill.addEventListener('pointerleave', reset);

  pill.addEventListener('focusin', (event) => {
    if (!isDesktop()) return;

    const link = event.target.closest('.site-nav-link');
    if (link && pill.contains(link)) setIndicator(link);
  });

  pill.addEventListener('focusout', (event) => {
    if (!isDesktop()) return;
    if (!pill.contains(event.relatedTarget)) reset();
  });

  window.addEventListener('resize', () => {
    if (!isDesktop()) {
      disableDesktop();
      return;
    }

    pill.classList.add('is-magic');

    if (current && pill.contains(current)) {
      setIndicator(current, true);
    } else if (active) {
      setIndicator(active, true);
    } else {
      hideIndicator();
    }
  }, { passive: true });

  const repaint = () => {
    if (!isDesktop()) return;
    if (current && pill.contains(current)) setIndicator(current, true);
    else if (active) setIndicator(active, true);
  };

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(repaint);
  }

  window.addEventListener('load', repaint, { once: true });
})();
