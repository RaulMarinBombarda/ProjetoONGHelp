/* ============================================================
   ONGHelp — Perfil público
   Abas com hash, navegação por teclado e cópia da chave PIX.
   ============================================================ */
(function () {
  'use strict';

  var tabsBar = document.querySelector('.perfil-abas');
  if (!tabsBar) return;

  var tabs = Array.prototype.slice.call(tabsBar.querySelectorAll('[data-profile-tab]'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('[data-profile-panel]'));
  var toast = document.getElementById('perfilToast');
  var toastTimer = null;

  function tabByName(name) {
    return tabs.filter(function (tab) {
      return tab.getAttribute('data-profile-tab') === name;
    })[0] || null;
  }

  function panelByName(name) {
    return panels.filter(function (panel) {
      return panel.getAttribute('data-profile-panel') === name;
    })[0] || null;
  }

  function atualizarHash(name, moverHistorico) {
    var url = new URL(window.location.href);
    if (name === 'visao-geral') {
      url.hash = '';
    } else {
      url.hash = name;
    }

    var destino = url.pathname + url.search + url.hash;
    if (moverHistorico && window.history.pushState) {
      window.history.pushState(null, '', destino);
    } else if (window.history.replaceState) {
      window.history.replaceState(null, '', destino);
    }
  }

  function ativarAba(name, opcoes) {
    var config = opcoes || {};
    var alvo = tabByName(name);
    if (!alvo) return;

    tabs.forEach(function (tab) {
      var ativa = tab === alvo;
      tab.classList.toggle('is-active', ativa);
      tab.setAttribute('aria-selected', ativa ? 'true' : 'false');
      tab.tabIndex = ativa ? 0 : -1;
    });

    panels.forEach(function (panel) {
      var ativo = panel.getAttribute('data-profile-panel') === name;
      panel.hidden = !ativo;
    });

    if (config.hash !== false) {
      atualizarHash(name, config.historico === true);
    }

    // Mantém a aba ativa visível quando a barra rola no mobile.
    manterAbaVisivel();

    if (config.focus) {
      try {
        alvo.focus({ preventScroll: true });
      } catch (erro) {
        alvo.focus();
      }
    }
  }

  function manterAbaVisivel() {
    if (tabsBar.scrollWidth <= tabsBar.clientWidth) return;

    var ativa = tabsBar.querySelector('.perfil-aba.is-active');
    if (!ativa) return;

    var barraRect = tabsBar.getBoundingClientRect();
    var abaRect = ativa.getBoundingClientRect();

    if (abaRect.left < barraRect.left || abaRect.right > barraRect.right + 1) {
      tabsBar.scrollLeft += abaRect.left - barraRect.left
        - (barraRect.width - abaRect.width) / 2;
    }
  }

  function abaDoHash() {
    var name = window.location.hash.replace(/^#/, '');
    return tabByName(name) ? name : 'visao-geral';
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      ativarAba(tab.getAttribute('data-profile-tab'), { hash: true });
    });

    tab.addEventListener('keydown', function (evento) {
      var indice = tabs.indexOf(tab);
      var destino = null;

      if (evento.key === 'ArrowRight') {
        destino = tabs[(indice + 1) % tabs.length];
      } else if (evento.key === 'ArrowLeft') {
        destino = tabs[(indice - 1 + tabs.length) % tabs.length];
      } else if (evento.key === 'Home') {
        destino = tabs[0];
      } else if (evento.key === 'End') {
        destino = tabs[tabs.length - 1];
      }

      if (!destino) return;
      evento.preventDefault();
      ativarAba(destino.getAttribute('data-profile-tab'), { hash: true, focus: true });
    });
  });

  document.querySelectorAll('[data-profile-jump]').forEach(function (link) {
    link.addEventListener('click', function (evento) {
      evento.preventDefault();
      var name = link.getAttribute('data-profile-jump');
      ativarAba(name, { hash: true, historico: true });

      var nav = document.querySelector('.site-navbar');
      var topo = tabsBar.getBoundingClientRect().top + window.scrollY - ((nav ? nav.offsetHeight : 0) + 12);
      window.scrollTo({ top: topo, behavior: 'smooth' });
    });
  });

  window.addEventListener('hashchange', function () {
    ativarAba(abaDoHash(), { hash: false });
  });

  ativarAba(abaDoHash(), { hash: false });

  function mostrarToast() {
    if (!toast) return;
    toast.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toast.classList.remove('is-visible');
    }, 2600);
  }

  document.addEventListener('click', function (evento) {
    var botao = evento.target.closest('[data-copiar-pix]');
    if (!botao) return;

    var campo = document.getElementById(botao.getAttribute('data-copiar-pix'));
    if (!campo) return;

    function concluir() {
      mostrarToast();
      try {
        botao.focus({ preventScroll: true });
      } catch (erro) {
        botao.focus();
      }
    }

    function copiaAlternativa() {
      campo.select();
      try {
        document.execCommand('copy');
        concluir();
      } catch (erro) {
        campo.setSelectionRange(0, campo.value.length);
      }
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(campo.value).then(concluir).catch(copiaAlternativa);
    } else {
      copiaAlternativa();
    }
  });

  window.addEventListener('load', manterAbaVisivel);
  window.addEventListener('resize', manterAbaVisivel);

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(manterAbaVisivel);
  }
})();
