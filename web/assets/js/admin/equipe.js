/* ============================================================
   assets/js/admin/equipe.js — gestão da equipe no painel
   ============================================================
   preenche os modais de ação com a conta escolhida, filtra o
   drawer de atividade e apaga a senha temporária do DOM quando o
   modal de credenciais fecha, copiar e abrir modais automaticamente
   ficam no ui.js (data-copiar e data-auto-open)
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

  ready(function () {
    // botão da tabela → campos do modal de ação correspondente
    var acoes = {
      'data-admin-reset': ['redefinirId', 'redefinirNome'],
      'data-admin-desativar': ['desativarId', 'desativarNome'],
      'data-admin-reativar': ['reativarId', 'reativarNome']
    };

    Object.keys(acoes).forEach(function (atributo) {
      document.querySelectorAll('[' + atributo + ']').forEach(function (botao) {
        botao.addEventListener('click', function () {
          var campoId = document.getElementById(acoes[atributo][0]);
          var campoNome = document.getElementById(acoes[atributo][1]);

          if (campoId) campoId.value = botao.getAttribute('data-id') || '';
          if (campoNome) campoNome.textContent = botao.getAttribute('data-nome') || '';
        });
      });
    });

    // filtro segmentado do drawer de atividade
    document.querySelectorAll('[data-segmentar-atividade]').forEach(function (grupo) {
      var drawer = grupo.closest('.offcanvas');
      var itens = drawer ? drawer.querySelectorAll('[data-segmento-item]') : [];
      var vazio = drawer ? drawer.querySelector('[data-atividade-vazio]') : null;

      grupo.querySelectorAll('[data-segmento]').forEach(function (botao) {
        botao.addEventListener('click', function () {
          var filtro = botao.getAttribute('data-segmento') || 'tudo';
          var visiveis = 0;

          grupo.querySelectorAll('[data-segmento]').forEach(function (outro) {
            var ativo = outro === botao;
            outro.classList.toggle('is-active', ativo);
            outro.setAttribute('aria-pressed', ativo ? 'true' : 'false');
          });

          itens.forEach(function (item) {
            var mostra = filtro === 'tudo' || item.getAttribute('data-segmento-item') === filtro;
            item.classList.toggle('d-none', !mostra);
            if (mostra) visiveis++;
          });

          if (vazio) vazio.classList.toggle('d-none', visiveis > 0);
        });
      });
    });

    // a senha temporária só pode ser vista uma vez, ao fechar o
    // modal, o valor é apagado do DOM e dos atributos de cópia
    var modalCredenciais = document.getElementById('modalCredenciais');
    if (modalCredenciais) {
      modalCredenciais.addEventListener('hidden.bs.modal', function () {
        modalCredenciais.querySelectorAll('[data-copiar]').forEach(function (botao) {
          botao.setAttribute('data-copiar', '');
        });

        var senha = document.getElementById('credencialSenha');
        var email = document.getElementById('credencialEmail');
        var mensagem = modalCredenciais.querySelector('[data-mensagem-whatsapp]');

        if (senha) senha.textContent = '••••••••••';
        if (email) email.textContent = '••••••••••';
        if (mensagem) mensagem.value = '';
      });
    }
  });
})();
