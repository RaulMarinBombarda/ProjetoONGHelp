/* ============================================================
   ONGHelp — interações compartilhadas de interface
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
    // Filtros que enviam o formulário ao mudar (GET puro, sem JS obrigatório).
    document.querySelectorAll('[data-auto-submit]').forEach(function (campo) {
      campo.addEventListener('change', function () {
        if (!campo.form) return;
        if (typeof campo.form.requestSubmit === 'function') {
          campo.form.requestSubmit();
        } else {
          campo.form.submit();
        }
      });
    });

    // Botões de mostrar/ocultar senha.
    document.querySelectorAll('[data-password-toggle]').forEach(function (botao) {
      var input = document.getElementById(botao.getAttribute('data-password-toggle'));
      if (!input) return;
      var icone = botao.querySelector('i');

      botao.addEventListener('click', function () {
        var visivel = input.type === 'text';
        input.type = visivel ? 'password' : 'text';
        if (icone) icone.className = visivel ? 'bi bi-eye' : 'bi bi-eye-slash';
        botao.setAttribute('aria-pressed', visivel ? 'false' : 'true');
        botao.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
      });
    });

    // Formulários com estado de envio: desabilita o botão e mostra o spinner.
    document.querySelectorAll('form[data-submit-loading]').forEach(function (form) {
      form.addEventListener('submit', function (evento) {
        // Outro script pode ter barrado o envio (validação por etapa,
        // por exemplo); nesse caso o botão não pode ficar carregando.
        if (evento.defaultPrevented) return;

        if (form.getAttribute('data-submitting') === 'true') {
          evento.preventDefault();
          return;
        }

        form.setAttribute('data-submitting', 'true');

        var botao = form.querySelector('[data-submit-button]');
        if (!botao) return;

        botao.disabled = true;
        botao.classList.add('is-loading');
        botao.setAttribute('aria-busy', 'true');
      });
    });

    // Ao voltar pelo histórico, o botão precisa voltar ao estado normal.
    window.addEventListener('pageshow', function () {
      document.querySelectorAll('form[data-submit-loading][data-submitting="true"]').forEach(function (form) {
        form.removeAttribute('data-submitting');

        var botao = form.querySelector('[data-submit-button]');
        if (!botao) return;

        botao.disabled = false;
        botao.classList.remove('is-loading');
        botao.removeAttribute('aria-busy');
      });
    });

    // Ícones de ajuda dentro de cards clicáveis não devem abrir o modal.
    document.querySelectorAll('.ajuda-icone').forEach(function (icone) {
      icone.addEventListener('click', function (evento) {
        evento.stopPropagation();
      });
    });

    // Tooltips do Bootstrap.
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el);
      });
    }

    // Permite abrir modais com Enter/Espaço em cards com role="button".
    document.querySelectorAll('[role="button"][data-bs-toggle="modal"]').forEach(function (el) {
      el.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Enter' && evento.key !== ' ') return;
        evento.preventDefault();
        el.click();
      });
    });

    // Avisos temporários desaparecem sozinhos depois de 4 segundos.
    document.querySelectorAll('.app-toast .alert').forEach(function (alerta) {
      window.setTimeout(function () {
        if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
          bootstrap.Alert.getOrCreateInstance(alerta).close();
        } else {
          alerta.remove();
        }
      }, 4000);
    });

    // Botão "voltar ao topo": criado aqui para valer no site todo.
    // Só aparece depois de rolar bastante (1,5 tela ou 900px).
    var topo = document.createElement('button');
    topo.type = 'button';
    topo.className = 'btn-topo';
    topo.setAttribute('aria-label', 'Voltar ao topo da página');
    topo.setAttribute('title', 'Voltar ao topo');
    topo.innerHTML = '<i class="bi bi-arrow-up" aria-hidden="true"></i>';
    topo.tabIndex = -1;
    document.body.appendChild(topo);

    var reduzirMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var agendado = false;

    function atualizarTopo() {
      agendado = false;
      var visivel = window.scrollY > Math.max(900, window.innerHeight * 1.5);
      topo.classList.toggle('is-visivel', visivel);
      topo.tabIndex = visivel ? 0 : -1;
    }

    window.addEventListener('scroll', function () {
      if (!agendado) {
        agendado = true;
        window.requestAnimationFrame(atualizarTopo);
      }
    }, { passive: true });

    topo.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduzirMovimento ? 'auto' : 'smooth' });
    });

    atualizarTopo();
  });
})();
