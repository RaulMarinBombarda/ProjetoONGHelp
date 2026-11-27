/* ============================================================
   assets/js/admin/avisos.js — formulário de avisos às ONGs
   ============================================================
   mostra a lista de ONGs só quando o destino é "Uma ONG", conta os
   caracteres, atualiza a prévia enquanto a pessoa escreve e pede
   confirmação (modal do ui.js) só no envio para todas as ONGs,
   o servidor valida tudo de novo ao enviar
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-aviso-form]');
    if (!form) return;

    var destinos = form.querySelectorAll('[data-aviso-destino]');
    var campoOng = form.querySelector('[data-aviso-ong-campo]');
    var selectOng = document.getElementById('avisoOng');
    var botao = form.querySelector('[data-aviso-enviar]');
    var titulo = document.getElementById('avisoTitulo');
    var mensagem = document.getElementById('avisoMensagem');
    var link = document.getElementById('avisoLink');

    var previaTitulo = document.querySelector('[data-aviso-previa="titulo"]');
    var previaMensagem = document.querySelector('[data-aviso-previa="mensagem"]');
    var previaBotao = document.querySelector('[data-aviso-previa="botao"]');

    // ── destino: lista de ONGs e confirmação ─────────────────
    function destinoAtual() {
      var escolhido = form.querySelector('[data-aviso-destino]:checked');
      return escolhido ? escolhido.value : 'todas';
    }

    function atualizarDestino() {
      var paraUma = destinoAtual() === 'ong';
      if (campoOng) campoOng.hidden = !paraUma;
      if (selectOng) selectOng.required = paraUma;

      // o envio em massa passa pelo modal de confirmação, o de uma ONG não
      if (botao) {
        if (paraUma) {
          botao.removeAttribute('data-confirmar');
        } else {
          botao.setAttribute('data-confirmar', botao.getAttribute('data-confirmar-todas') || '');
          botao.setAttribute('data-confirmar-titulo', 'Enviar para todas as ONGs?');
          botao.setAttribute('data-confirmar-botao', 'Enviar aviso');
        }
      }
    }

    destinos.forEach(function (radio) { radio.addEventListener('change', atualizarDestino); });

    // ── contadores e prévia ──────────────────────────────────
    function contar(campo) {
      var contador = document.querySelector('[data-aviso-contador-de="' + campo.id + '"]');
      if (contador) contador.textContent = campo.value.length + '/' + campo.getAttribute('maxlength');
    }

    function atualizarPrevia() {
      if (previaTitulo) previaTitulo.textContent = titulo.value.trim() || 'Título do aviso';
      if (previaMensagem) previaMensagem.textContent = mensagem.value.trim() || 'A mensagem aparece aqui.';
      if (previaBotao) previaBotao.hidden = !link.value;
    }

    form.querySelectorAll('[data-aviso-contar]').forEach(function (campo) {
      campo.addEventListener('input', function () {
        contar(campo);
        atualizarPrevia();
      });
    });
    if (link) link.addEventListener('change', atualizarPrevia);

    atualizarDestino();
    atualizarPrevia();
  });
})();
