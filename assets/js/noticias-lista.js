// ============================================================
// assets/js/noticias-lista.js — Confirmação de exclusão de notícia
// ============================================================
(function () {
  'use strict';

  var modalEl = document.getElementById('nmgModalExcluir');
  var form = document.getElementById('nmgFormExcluir');
  var idInput = document.getElementById('nmgExcluirId');
  var texto = document.getElementById('nmgExcluirTexto');

  function preparar(botao) {
    var id = botao.getAttribute('data-id') || '';
    var titulo = botao.getAttribute('data-titulo') || 'esta notícia';

    if (idInput) {
      idInput.value = id;
    }

    if (texto) {
      texto.textContent = 'A notícia “' + titulo + '” será excluída permanentemente.';
    }

    return id;
  }

  function fecharMenu(botao) {
    if (!window.bootstrap) {
      return;
    }

    var dropdown = botao.closest('.dropdown');
    var toggle = dropdown ? dropdown.querySelector('[data-bs-toggle="dropdown"]') : null;

    if (toggle) {
      var instancia = window.bootstrap.Dropdown.getInstance(toggle);

      if (instancia) {
        instancia.hide();
      }
    }
  }

  document.addEventListener('click', function (evento) {
    var botao = evento.target.closest('[data-nmg-excluir]');

    if (!botao) {
      return;
    }

    preparar(botao);
    fecharMenu(botao);

    if (modalEl && window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    // Fallback sem Bootstrap: confirmação nativa.
    if (form) {
      var titulo = botao.getAttribute('data-titulo') || 'esta notícia';

      if (window.confirm('Excluir a notícia “' + titulo + '”? Essa ação não pode ser desfeita.')) {
        form.submit();
      }
    }
  });
})();
