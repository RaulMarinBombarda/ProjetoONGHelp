/* ============================================================
   ONGHelp — página pública da equipe
   Copia o número de WhatsApp com retorno acessível.
   ============================================================ */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function avisar(texto) {
    var status = document.querySelector('[data-copy-status]');
    if (status) status.textContent = texto;
  }

  function copiar(texto) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(texto);
    }

    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = texto;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();

      try {
        document.execCommand('copy') ? resolve() : reject();
      } catch (erro) {
        reject(erro);
      } finally {
        document.body.removeChild(area);
      }
    });
  }

  ready(function () {
    document.querySelectorAll('[data-copiar-numero]').forEach(function (botao) {
      var rotuloOriginal = botao.innerHTML;

      botao.addEventListener('click', function () {
        var numero = botao.getAttribute('data-copiar-numero') || '';
        if (!numero) return;

        copiar(numero).then(function () {
          botao.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> Número copiado';
          avisar('Número copiado para a área de transferência.');

          window.setTimeout(function () {
            botao.innerHTML = rotuloOriginal;
          }, 2600);
        }).catch(function () {
          avisar('Não foi possível copiar o número. Use o botão do WhatsApp.');
        });
      });
    });
  });
})();
