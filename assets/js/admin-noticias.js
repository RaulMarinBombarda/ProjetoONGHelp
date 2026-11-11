/* ============================================================
   ONGHelp — moderação de notícias no painel
   Abre o drawer de análise, controla a galeria compacta e valida
   visualmente a mensagem mínima de ocultação.
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var drawer = document.getElementById('anmDrawer');

    if (drawer && window.bootstrap) {
      var auto = drawer.getAttribute('data-anm-auto-modal') || '';
      var modais = {
        ocultar: 'anmModalOcultar',
        bloquear: 'anmModalBloquear',
        restaurar: 'anmModalRestaurar'
      };

      if (auto && modais[auto]) {
        drawer.addEventListener('shown.bs.offcanvas', function () {
          var modal = document.getElementById(modais[auto]);

          if (modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
          }
        }, { once: true });
      }

      window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
    }

    var galeria = document.querySelector('[data-anm-gallery]');

    if (galeria) {
      var slides = Array.prototype.slice.call(galeria.querySelectorAll('[data-anm-slide]'));
      var thumbs = Array.prototype.slice.call(galeria.querySelectorAll('[data-anm-thumb]'));
      var contador = galeria.querySelector('[data-anm-count]');
      var atual = 0;

      var mostrar = function (indice) {
        if (!slides.length) {
          return;
        }

        atual = (indice + slides.length) % slides.length;

        slides.forEach(function (slide, posicao) {
          slide.classList.toggle('is-active', posicao === atual);
        });

        thumbs.forEach(function (thumb, posicao) {
          var ativo = posicao === atual;
          thumb.classList.toggle('is-active', ativo);
          thumb.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });

        if (contador) {
          contador.textContent = (atual + 1) + ' / ' + slides.length;
        }
      };

      var anterior = galeria.querySelector('[data-anm-prev]');
      var proxima = galeria.querySelector('[data-anm-next]');

      if (anterior) {
        anterior.addEventListener('click', function () {
          mostrar(atual - 1);
        });
      }

      if (proxima) {
        proxima.addEventListener('click', function () {
          mostrar(atual + 1);
        });
      }

      thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
          mostrar(Number(thumb.getAttribute('data-anm-thumb')) || 0);
        });
      });
    }

    var mensagem = document.querySelector('[data-anm-mensagem]');
    var contadorMensagem = document.querySelector('[data-anm-contador]');
    var botaoOcultar = document.querySelector('[data-anm-submit-ocultar]');

    if (mensagem) {
      var atualizarContador = function () {
        var tamanho = mensagem.value.trim().length;

        if (contadorMensagem) {
          contadorMensagem.textContent = tamanho + '/20';
          contadorMensagem.classList.toggle('is-ok', tamanho >= 20);
          contadorMensagem.classList.toggle('is-erro', tamanho > 0 && tamanho < 20);
        }

        if (botaoOcultar) {
          botaoOcultar.disabled = tamanho < 20;
        }
      };

      mensagem.addEventListener('input', atualizarContador);
      atualizarContador();
    }
  });
})();
