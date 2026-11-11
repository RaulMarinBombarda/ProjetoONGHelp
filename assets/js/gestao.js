/* ============================================================
   ONGHelp — Gestão: filtro do livro-caixa e confirmações
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // ── Filtro client-side por tipo ─────────────────────────
    var filtros = document.querySelectorAll('[data-gst-filter]');
    var linhas = document.querySelectorAll('.gst-mov-row');
    var contador = document.getElementById('gstLedgerCount');
    var vazio = document.querySelector('.gst-filter-empty');

    function aplicarFiltro(tipo) {
      var visiveis = 0;

      linhas.forEach(function (linha) {
        var mostrar = tipo === 'todas' || linha.getAttribute('data-gst-tipo') === tipo;
        linha.hidden = !mostrar;
        if (mostrar) visiveis += 1;
      });

      if (contador) contador.textContent = '(' + visiveis + ')';
      if (vazio) vazio.hidden = visiveis !== 0;
    }

    filtros.forEach(function (botao) {
      botao.addEventListener('click', function () {
        var tipo = botao.getAttribute('data-gst-filter') || 'todas';

        filtros.forEach(function (outro) {
          var ativo = outro === botao;
          outro.classList.toggle('is-active', ativo);
          outro.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });

        aplicarFiltro(tipo);
      });
    });

    // ── Confirmação de exclusão ─────────────────────────────
    var modalEl = document.getElementById('modalConfirmar');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titulo = document.getElementById('modalConfirmarTituloTexto');
    var texto = document.getElementById('modalConfirmarTexto');
    var formPendente = null;
    var gatilhoPendente = null;

    document.querySelectorAll('[data-confirmar-exclusao]').forEach(function (botao) {
      botao.addEventListener('click', function (evento) {
        evento.preventDefault();
        formPendente = botao.closest('form');
        gatilhoPendente = botao;

        if (titulo) {
          titulo.textContent = botao.getAttribute('data-confirmar-titulo') || 'Confirmar exclusão?';
        }
        if (texto) {
          texto.textContent = botao.getAttribute('data-confirmar-texto') || 'Essa ação não pode ser desfeita.';
        }

        modal.show();
      });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (gatilhoPendente && document.contains(gatilhoPendente)) {
        gatilhoPendente.focus();
      }
    });

    var confirmar = document.getElementById('btnConfirmarExclusao');
    if (confirmar) {
      confirmar.addEventListener('click', function () {
        if (!formPendente) return;

        var form = formPendente;
        formPendente = null;
        modal.hide();
        form.submit();
      });
    }
  });
})();
