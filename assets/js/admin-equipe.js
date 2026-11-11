/* ============================================================
   ONGHelp — interações da gestão da equipe
   Modais de ação, atividade, cópia e exibição única de senha.
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
    var bootstrapDisponivel = typeof bootstrap !== 'undefined';

    // Modais abertos automaticamente pelo servidor.
    if (bootstrapDisponivel) {
      document.querySelectorAll('[data-auto-open="true"]').forEach(function (el) {
        bootstrap.Modal.getOrCreateInstance(el).show();
      });
    }

    // Preenche os modais de ação com a conta escolhida na tabela.
    document.querySelectorAll('[data-admin-reset]').forEach(function (botao) {
      botao.addEventListener('click', function () {
        var campoId = document.getElementById('redefinirId');
        var campoNome = document.getElementById('redefinirNome');

        if (campoId) campoId.value = botao.getAttribute('data-id') || '';
        if (campoNome) campoNome.textContent = botao.getAttribute('data-nome') || '';
      });
    });

    document.querySelectorAll('[data-admin-desativar]').forEach(function (botao) {
      botao.addEventListener('click', function () {
        var campoId = document.getElementById('desativarId');
        var campoNome = document.getElementById('desativarNome');

        if (campoId) campoId.value = botao.getAttribute('data-id') || '';
        if (campoNome) campoNome.textContent = botao.getAttribute('data-nome') || '';
      });
    });

    document.querySelectorAll('[data-admin-reativar]').forEach(function (botao) {
      botao.addEventListener('click', function () {
        var campoId = document.getElementById('reativarId');
        var campoNome = document.getElementById('reativarNome');

        if (campoId) campoId.value = botao.getAttribute('data-id') || '';
        if (campoNome) campoNome.textContent = botao.getAttribute('data-nome') || '';
      });
    });

    // Filtro segmentado do drawer de atividade.
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

    function mostrarToast(texto) {
      var toast = document.querySelector('[data-admin-toast]');
      if (!toast) return;

      var rotulo = toast.querySelector('[data-admin-toast-texto]');
      if (rotulo && texto) rotulo.textContent = texto;

      toast.classList.add('is-visible');
      window.clearTimeout(toast._timer);
      toast._timer = window.setTimeout(function () {
        toast.classList.remove('is-visible');
      }, 2600);
    }

    function copiarTexto(texto, mensagem) {
      if (!texto) return;

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(function () {
          mostrarToast(mensagem || 'Mensagem copiada');
        }).catch(function () {
          mostrarToast('Não foi possível copiar');
        });
        return;
      }

      // Alternativa para contextos sem Clipboard API.
      var area = document.createElement('textarea');
      area.value = texto;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();

      try {
        document.execCommand('copy');
        mostrarToast(mensagem || 'Mensagem copiada');
      } catch (erro) {
        mostrarToast('Não foi possível copiar');
      }

      document.body.removeChild(area);
    }

    // Cópia de e-mail/senha temporária e da mensagem completa.
    document.addEventListener('click', function (evento) {
      var botaoMensagem = evento.target.closest('[data-copiar-mensagem]');
      if (botaoMensagem) {
        evento.preventDefault();
        var campo = document.getElementById(botaoMensagem.getAttribute('data-copiar-mensagem'));
        copiarTexto(campo ? campo.value : '', 'Mensagem copiada');
        return;
      }

      var botao = evento.target.closest('[data-copiar]');
      if (!botao) return;

      evento.preventDefault();
      copiarTexto(
        botao.getAttribute('data-copiar') || '',
        botao.getAttribute('data-copiar-toast') || 'Copiado'
      );
    });

    // A senha temporária só pode ser vista uma vez. Ao fechar o
    // modal, o valor é apagado do DOM e dos atributos de cópia.
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
