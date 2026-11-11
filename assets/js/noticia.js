// ============================================================
// assets/js/noticia.js — Página da notícia
// ============================================================
// Copiar link, visualizador de fotos (<dialog>) e reabertura do
// modal de denúncia depois do envio.
(function () {
  'use strict';

  // ── Copiar link ──────────────────────────────────────────
  document.querySelectorAll('[data-nt-copiar]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var url = botao.getAttribute('data-nt-copiar') || window.location.href;
      var rotulo = botao.querySelector('span');
      var original = rotulo ? rotulo.textContent : '';

      function confirmar() {
        if (rotulo) {
          rotulo.textContent = 'Link copiado!';
          setTimeout(function () { rotulo.textContent = original; }, 2000);
        }
      }

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(confirmar, function () {
          window.prompt('Copie o link:', url);
        });
      } else {
        window.prompt('Copie o link:', url);
      }
    });
  });

  // ── Visualizador de fotos ────────────────────────────────
  var dialogo = document.getElementById('ntVisualizador');
  var dadosEl = document.getElementById('ntFotos');

  if (dialogo && dadosEl && typeof dialogo.showModal === 'function') {
    var fotos = JSON.parse(dadosEl.textContent || '[]');
    var imagem = document.getElementById('ntVisImagem');
    var legenda = document.getElementById('ntVisLegenda');
    var atual = 0;

    function mostrar(indice) {
      atual = (indice + fotos.length) % fotos.length;
      var foto = fotos[atual];
      imagem.src = foto.src;
      imagem.alt = foto.alt;
      var texto = foto.legenda || '';

      if (fotos.length > 1) {
        texto = (atual + 1) + ' de ' + fotos.length + (texto ? ' · ' + texto : '');
      }

      legenda.textContent = texto;
      legenda.hidden = texto === '';
    }

    document.querySelectorAll('[data-nt-foto]').forEach(function (botao) {
      botao.addEventListener('click', function () {
        mostrar(parseInt(botao.getAttribute('data-nt-foto'), 10) || 0);
        dialogo.showModal();
      });
    });

    dialogo.addEventListener('click', function (evento) {
      if (evento.target.closest('[data-nt-fechar]') || evento.target === dialogo) {
        dialogo.close();
      } else if (evento.target.closest('[data-nt-anterior]')) {
        mostrar(atual - 1);
      } else if (evento.target.closest('[data-nt-proxima]')) {
        mostrar(atual + 1);
      }
    });

    dialogo.addEventListener('keydown', function (evento) {
      if (evento.key === 'ArrowLeft') { mostrar(atual - 1); }
      if (evento.key === 'ArrowRight') { mostrar(atual + 1); }
    });
  } else {
    // Sem suporte a <dialog>: o botão abre a foto em nova aba.
    document.querySelectorAll('[data-nt-foto] img').forEach(function (img) {
      img.parentElement.addEventListener('click', function () {
        window.open(img.src, '_blank', 'noopener');
      });
    });
  }

  // ── Denúncia: reabre o modal para mostrar o resultado ────
  var modalDenuncia = document.querySelector('[data-nt-abrir]');

  if (modalDenuncia && window.bootstrap) {
    window.bootstrap.Modal.getOrCreateInstance(modalDenuncia).show();
  }
})();
