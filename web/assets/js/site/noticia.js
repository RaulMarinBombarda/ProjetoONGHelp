// ============================================================
// assets/js/site/noticia.js — página da notícia
// ============================================================
// visualizador de fotos (<dialog>), copiar link e reabrir o modal
// de denúncia ficam no ui.js (data-copiar e data-auto-open)
(function () {
  'use strict';

  // ── visualizador de fotos ────────────────────────────────
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
    // sem suporte a <dialog>: o botão abre a foto em nova aba
    document.querySelectorAll('[data-nt-foto] img').forEach(function (img) {
      img.parentElement.addEventListener('click', function () {
        window.open(img.src, '_blank', 'noopener');
      });
    });
  }
})();
