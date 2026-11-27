/* ============================================================
   assets/js/core/ui.js — interações compartilhadas do site e do painel
   ============================================================
   carregado em todas as páginas (includes/layout/scripts.php),
   tudo aqui é ativado por atributos data-* no HTML; nenhuma página
   precisa de script próprio para estes comportamentos:

     data-auto-submit         envia o formulário ao mudar o campo
     data-password-toggle     mostra/oculta a senha do campo indicado
     data-submit-loading      evita duplo envio e mostra o spinner
     data-copiar="texto"      copia o texto para a área de transferência
     data-copiar-de="id"      copia o valor/texto do elemento com esse id
     data-confirmar="texto"   pede confirmação no modal #modalConfirmar
     data-auto-open           abre o modal/offcanvas ao carregar a página

   o JavaScript só melhora a experiência: sem ele, formulários e
   links continuam funcionando e o servidor valida tudo
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

  var temBootstrap = function () {
    return typeof window.bootstrap !== 'undefined';
  };

  // ── avisos para leitores de tela ─────────────────────────
  var regiaoAviso = null;

  function anunciar(texto) {
    if (!regiaoAviso) {
      regiaoAviso = document.createElement('div');
      regiaoAviso.className = 'visually-hidden';
      regiaoAviso.setAttribute('role', 'status');
      regiaoAviso.setAttribute('aria-live', 'polite');
      document.body.appendChild(regiaoAviso);
    }
    regiaoAviso.textContent = '';
    window.setTimeout(function () { regiaoAviso.textContent = texto; }, 50);
  }

  // ── copiar para a área de transferência ──────────────────
  function copiarTexto(texto) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(texto);
    }

    // alternativa para HTTP fora do localhost
    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = texto;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();

      try {
        if (document.execCommand('copy')) resolve(); else reject();
      } catch (erro) {
        reject(erro);
      } finally {
        document.body.removeChild(area);
      }
    });
  }

  function textoParaCopiar(botao) {
    var origem = botao.getAttribute('data-copiar-de');
    if (origem) {
      var el = document.getElementById(origem);
      if (!el) return '';
      return 'value' in el ? el.value : el.textContent.trim();
    }
    return botao.getAttribute('data-copiar') || '';
  }

  function confirmarCopia(botao) {
    var rotulo = botao.getAttribute('data-copiado') || 'Copiado!';

    if (!botao._htmlOriginal) {
      botao._htmlOriginal = botao.innerHTML;
    }

    // botões só com ícone trocam o ícone; botões com texto também
    // trocam o rótulo
    var temTexto = botao.textContent.trim() !== '';
    botao.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i>' + (temTexto ? ' ' + rotulo : '');
    botao.classList.add('is-copiado');
    anunciar(rotulo);

    window.clearTimeout(botao._timerCopia);
    botao._timerCopia = window.setTimeout(function () {
      botao.innerHTML = botao._htmlOriginal;
      botao._htmlOriginal = null;
      botao.classList.remove('is-copiado');
    }, 2000);
  }

  document.addEventListener('click', function (evento) {
    var botao = evento.target.closest('[data-copiar], [data-copiar-de]');
    if (!botao) return;

    evento.preventDefault();
    var texto = textoParaCopiar(botao);
    if (!texto) return;

    copiarTexto(texto).then(function () {
      confirmarCopia(botao);
    }).catch(function () {
      anunciar('Não foi possível copiar. Selecione o texto e copie manualmente.');
    });
  });

  // ── confirmação antes de agir ────────────────────────────
  // o clique é interrompido, o modal pergunta e, ao confirmar, o
  // mesmo botão é clicado de novo: assim o name/value do botão de
  // envio segue junto com o formulário
  document.addEventListener('click', function (evento) {
    var gatilho = evento.target.closest('[data-confirmar]');
    if (!gatilho) return;

    if (gatilho._confirmado) {
      gatilho._confirmado = false;
      return;
    }

    var modalEl = document.getElementById('modalConfirmar');
    var mensagem = gatilho.getAttribute('data-confirmar');

    evento.preventDefault();

    if (!modalEl || !temBootstrap()) {
      if (window.confirm(mensagem)) {
        gatilho._confirmado = true;
        gatilho.click();
      }
      return;
    }

    modalEl.querySelector('[data-confirmar-titulo-texto]').textContent =
      gatilho.getAttribute('data-confirmar-titulo') || 'Confirmar ação?';
    modalEl.querySelector('[data-confirmar-texto]').textContent = mensagem;
    modalEl.querySelector('[data-confirmar-botao-texto]').textContent =
      gatilho.getAttribute('data-confirmar-botao') || 'Confirmar';

    modalEl._gatilho = gatilho;
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });

  ready(function () {
    var modalConfirmar = document.getElementById('modalConfirmar');

    if (modalConfirmar) {
      modalConfirmar.querySelector('[data-confirmar-ok]').addEventListener('click', function () {
        var gatilho = modalConfirmar._gatilho;
        modalConfirmar._gatilho = null;
        window.bootstrap.Modal.getOrCreateInstance(modalConfirmar).hide();

        if (gatilho && document.contains(gatilho)) {
          gatilho._confirmado = true;
          gatilho.click();
        }
      });

      modalConfirmar.addEventListener('hidden.bs.modal', function () {
        var gatilho = modalConfirmar._gatilho;
        modalConfirmar._gatilho = null;
        if (gatilho && document.contains(gatilho)) gatilho.focus();
      });
    }

    // filtros que enviam o formulário ao mudar (GET puro, sem JS obrigatório)
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

    // botões de mostrar/ocultar senha
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

    // formulários com estado de envio: desabilita o botão e mostra o spinner,
    // o ouvinte fica no document, e não no form: o evento chega aqui
    // depois dos ouvintes da página (ex.: validação por etapa do
    // cadastro), então defaultPrevented já reflete a decisão deles,
    // ouvindo no form, este código rodava antes da validação e o botão
    // ficava carregando para sempre quando ela barrava o envio
    document.addEventListener('submit', function (evento) {
      var form = evento.target;
      if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-submit-loading')) return;

      // outro script barrou o envio; o botão não pode ficar carregando
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

    // ao voltar pelo histórico, o botão precisa voltar ao estado normal
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

    // ícones de ajuda dentro de cards clicáveis não devem abrir o modal
    document.querySelectorAll('.ajuda-icone').forEach(function (icone) {
      icone.addEventListener('click', function (evento) {
        evento.stopPropagation();
      });
    });

    if (temBootstrap()) {
      // tooltips do Bootstrap
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        window.bootstrap.Tooltip.getOrCreateInstance(el);
      });

      // modais e painéis que o servidor pede para abrir (ex.: resultado
      // de um envio que precisa ser mostrado de novo ao usuário)
      document.querySelectorAll('.modal[data-auto-open]').forEach(function (el) {
        window.bootstrap.Modal.getOrCreateInstance(el).show();
      });
      document.querySelectorAll('.offcanvas[data-auto-open]').forEach(function (el) {
        window.bootstrap.Offcanvas.getOrCreateInstance(el).show();
      });
    }

    // permite abrir modais com Enter/Espaço em cards com role="button"
    document.querySelectorAll('[role="button"][data-bs-toggle="modal"]').forEach(function (el) {
      el.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Enter' && evento.key !== ' ') return;
        evento.preventDefault();
        el.click();
      });
    });

    // avisos temporários desaparecem sozinhos depois de 4 segundos
    document.querySelectorAll('.app-toast .alert').forEach(function (alerta) {
      window.setTimeout(function () {
        if (temBootstrap() && window.bootstrap.Alert) {
          window.bootstrap.Alert.getOrCreateInstance(alerta).close();
        } else {
          alerta.remove();
        }
      }, 4000);
    });

    // botão "voltar ao topo": criado aqui para valer no site todo,
    // só aparece depois de rolar bastante (1,5 tela ou 900px)
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
