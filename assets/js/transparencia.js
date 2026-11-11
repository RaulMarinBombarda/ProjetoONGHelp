/* ============================================================
   ONGHelp — Portal de Transparência
   Drawer acessível, abas, ordenação client-side e filtros
   ============================================================ */
(function () {
  'use strict';

  var drawerAberto = null;
  var ultimoFoco = null;
  var reduzirMovimento = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function elementosFocaveis(container) {
    var seletor = [
      'a[href]',
      'button:not([disabled])',
      'input:not([type="hidden"]):not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    return Array.prototype.filter.call(container.querySelectorAll(seletor), function (el) {
      return el.getClientRects().length > 0
        && window.getComputedStyle(el).visibility !== 'hidden';
    });
  }

  function fecharMenu(menu) {
    if (!menu) return;
    menu.hidden = true;
    var container = menu.closest('.tr-export');
    var botao = container && container.querySelector('[data-tr-export-toggle]');
    if (botao) botao.setAttribute('aria-expanded', 'false');
  }

  function fecharMenusAbertos() {
    document.querySelectorAll('.tr-export-menu:not([hidden])').forEach(fecharMenu);
  }

  function reiniciarDrawer(shell) {
    fecharMenusAbertos();

    var abas = shell.querySelectorAll('[role="tab"]');
    if (abas.length) {
      ativarAba(abas[0]);
    }

    var corpo = shell.querySelector('.tr-drawer-body');
    if (corpo) corpo.scrollTop = 0;
  }

  // Trava a rolagem da página sem "pulo": a largura da barra de
  // rolagem vira padding enquanto o relatório está aberto.
  function travarRolagem(travar) {
    var corpo = document.body;
    if (travar) {
      var barra = window.innerWidth - document.documentElement.clientWidth;
      corpo.style.paddingRight = barra > 0 ? barra + 'px' : '';
      corpo.classList.add('tr-drawer-open');
    } else {
      corpo.classList.remove('tr-drawer-open');
      corpo.style.paddingRight = '';
    }
  }

  function abrirDrawer(shell, origem) {
    if (!shell) return;
    // Já aberto e não está fechando: nada a fazer.
    if (drawerAberto === shell && !shell._trFechando) return;

    if (drawerAberto && drawerAberto !== shell) {
      fecharDrawer(drawerAberto, null, true);
    }

    // Reabrir enquanto ainda fecha: cancela o fechamento.
    if (shell._trFechando) {
      window.clearTimeout(shell._trFechando);
      shell._trFechando = null;
    }

    ultimoFoco = origem || document.activeElement;
    drawerAberto = shell;

    var jaVisivel = !shell.hidden;
    if (!jaVisivel) reiniciarDrawer(shell);
    shell.hidden = false;
    travarRolagem(true);

    // Lê o estilo calculado para o navegador registrar a posição
    // inicial (fora da tela) antes de aplicar a classe que anima.
    var painelDrawer = shell.querySelector('.tr-drawer');
    if (painelDrawer) void window.getComputedStyle(painelDrawer).transform;
    shell.classList.add('is-open');

    var fechar = shell.querySelector('.tr-drawer-close');
    window.setTimeout(function () {
      if (drawerAberto === shell && fechar) {
        try { fechar.focus({ preventScroll: true }); } catch (e) { fechar.focus(); }
      }
    }, reduzirMovimento ? 0 : 120);
  }

  function fecharDrawer(shell, aoFechar, trocando) {
    if (!shell || shell.hidden) return;
    if (shell._trFechando) return;

    var focoAnterior = ultimoFoco;

    shell.classList.remove('is-open');

    // A partir daqui o relatório já conta como fechado: um novo
    // clique em "Relatório" abre de primeira, mesmo durante a saída.
    if (drawerAberto === shell) {
      drawerAberto = null;
      ultimoFoco = null;
    }

    shell._trFechando = window.setTimeout(function () {
      shell.hidden = true;
      shell._trFechando = null;

      if (!drawerAberto && !trocando) {
        travarRolagem(false);

        if (focoAnterior && focoAnterior.isConnected) {
          try { focoAnterior.focus({ preventScroll: true }); } catch (e) { focoAnterior.focus(); }
        }

        limparParametrosDrawer();
      }

      if (typeof aoFechar === 'function') aoFechar();
    }, reduzirMovimento ? 0 : 380);
  }

  function limparParametrosDrawer() {
    var params = new URLSearchParams(window.location.search);
    if (!params.has('modal') && !params.has('ong')) return;

    params.delete('modal');
    params.delete('ong');

    var query = params.toString();
    var url = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
    window.history.replaceState(null, '', url);
  }

  function ativarAba(aba) {
    if (!aba) return;

    var lista = aba.closest('[role="tablist"]');
    var drawer = aba.closest('.tr-drawer');
    if (!lista || !drawer) return;

    lista.querySelectorAll('[role="tab"]').forEach(function (tab) {
      var ativa = tab === aba;
      tab.setAttribute('aria-selected', ativa ? 'true' : 'false');
      tab.tabIndex = ativa ? 0 : -1;
    });

    drawer.querySelectorAll('.tr-tab-panel').forEach(function (painel) {
      painel.hidden = painel.id !== aba.getAttribute('aria-controls');
    });
  }

  // ── Ordenação client-side ───────────────────────────────────
  // Só age se a tabela tiver cabeçalhos com data-sort-key. No portal
  // a ordenação passou para o seletor "Ordenar por" do filtro.
  var ordenacao = { chave: 'nome', direcao: 'asc' };

  function compararLinhas(a, b, chave) {
    if (chave === 'nome' || chave === 'causa') {
      var va = a.getAttribute('data-sort-' + chave) || '';
      var vb = b.getAttribute('data-sort-' + chave) || '';
      return va.localeCompare(vb, 'pt-BR', { sensitivity: 'base' });
    }

    return parseFloat(a.getAttribute('data-sort-' + chave) || '0')
      - parseFloat(b.getAttribute('data-sort-' + chave) || '0');
  }

  function atualizarCabecalhos() {
    document.querySelectorAll('.tr-table thead th[data-sort-key]').forEach(function (th) {
      var chave = th.getAttribute('data-sort-key');
      var ativa = chave === ordenacao.chave;
      var direcao = ativa ? ordenacao.direcao : 'asc';

      th.setAttribute('aria-sort', ativa
        ? (direcao === 'asc' ? 'ascending' : 'descending')
        : 'none');

      var botao = th.querySelector('[data-tr-sort]');
      if (!botao) return;

      botao.classList.toggle('is-active', ativa);
      var icone = botao.querySelector('i');
      if (!icone) return;

      icone.className = 'bi ' + (ativa
        ? (direcao === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down')
        : 'bi-arrow-down-up');
    });
  }

  function ordenarTabela() {
    var tabela = document.querySelector('.tr-table');
    if (!tabela) return;

    var corpo = tabela.tBodies[0];
    if (!corpo) return;

    var linhas = Array.prototype.slice.call(corpo.querySelectorAll('.tr-row'));
    var direcao = ordenacao.direcao === 'asc' ? 1 : -1;

    linhas.sort(function (a, b) {
      var comparacao = compararLinhas(a, b, ordenacao.chave);
      if (comparacao === 0) {
        comparacao = compararLinhas(a, b, 'nome');
      }
      return comparacao * (comparacao === 0 ? 1 : direcao);
    });

    var fragmento = document.createDocumentFragment();
    linhas.forEach(function (linha) { fragmento.appendChild(linha); });
    corpo.appendChild(fragmento);

    atualizarCabecalhos();
  }

  // ── Eventos ─────────────────────────────────────────────────
  document.addEventListener('click', function (evento) {
    // Abrir drawer pela linha inteira ou pelo botão Relatório
    var abridor = evento.target.closest('[data-tr-open]');
    if (abridor) {
      var shell = document.getElementById(abridor.getAttribute('data-tr-open'));
      if (shell) {
        evento.preventDefault();

        // Só ignora o clique se a pessoa acabou de selecionar texto
        // dentro desta própria linha (arrastar para copiar um valor).
        var selecao = window.getSelection ? window.getSelection() : null;
        if (selecao && !selecao.isCollapsed && String(selecao) !== ''
            && selecao.anchorNode && abridor.contains(selecao.anchorNode)
            && !evento.target.closest('button')) {
          return;
        }

        var origem = evento.target.closest('button, a');
        if (!origem) {
          origem = abridor;
          try { abridor.focus({ preventScroll: true }); } catch (e) { abridor.focus(); }
        }

        abrirDrawer(shell, origem);
      }
      return;
    }

    // Fechar drawer (backdrop ou botão ×)
    var fechar = evento.target.closest('[data-tr-close]');
    if (fechar) {
      var drawerFechar = fechar.closest('.tr-drawer-shell');
      if (drawerFechar) {
        evento.preventDefault();
        fecharDrawer(drawerFechar);
      }
      return;
    }

    // Doar via PIX: fecha o drawer e abre o modal já existente
    var pix = evento.target.closest('[data-drawer-pix]');
    if (pix) {
      var drawerPix = pix.closest('.tr-drawer-shell');
      var modal = document.getElementById(pix.getAttribute('data-drawer-pix'));
      if (drawerPix && modal) {
        evento.preventDefault();
        fecharDrawer(drawerPix, function () {
          if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
          }
        });
      }
      return;
    }

    // Mostrar/ocultar o painel "Como ler estes dados"
    var guia = evento.target.closest('[data-tr-guide-toggle]');
    if (guia) {
      var corpoGuia = document.getElementById(guia.getAttribute('aria-controls'));
      var estavaAberto = guia.getAttribute('aria-expanded') === 'true';
      if (corpoGuia) corpoGuia.hidden = estavaAberto;
      var caixaGuia = guia.closest('.tr-guide');
      if (caixaGuia) caixaGuia.classList.toggle('is-recolhido', estavaAberto);
      guia.setAttribute('aria-expanded', estavaAberto ? 'false' : 'true');
      guia.textContent = estavaAberto ? 'Mostrar' : 'Ocultar';
      return;
    }

    // Abas do relatório
    var aba = evento.target.closest('[role="tab"]');
    if (aba) {
      ativarAba(aba);
      return;
    }

    // Exportar ▾
    var exportar = evento.target.closest('[data-tr-export-toggle]');
    if (exportar) {
      var menu = document.getElementById(exportar.getAttribute('aria-controls'));
      if (!menu) return;

      var jaAberto = !menu.hidden;
      fecharMenusAbertos();

      if (!jaAberto) {
        menu.hidden = false;
        exportar.setAttribute('aria-expanded', 'true');
      }
      return;
    }

    // Ordenação das colunas
    var ordenar = evento.target.closest('[data-tr-sort]');
    if (ordenar) {
      var chave = ordenar.getAttribute('data-tr-sort');

      if (ordenacao.chave === chave) {
        ordenacao.direcao = ordenacao.direcao === 'asc' ? 'desc' : 'asc';
      } else {
        ordenacao.chave = chave;
        ordenacao.direcao = 'asc';
      }

      ordenarTabela();
      return;
    }

    // Clique em qualquer área do cabeçalho ordena; o botão interno
    // continua sendo o alvo de teclado e o ? abre o tooltip.
    var thOrdenar = evento.target.closest('.tr-table thead th[data-sort-key]');
    if (thOrdenar && !evento.target.closest('.tr-th-help')) {
      var botaoOrdenar = thOrdenar.querySelector('[data-tr-sort]');
      if (botaoOrdenar) {
        botaoOrdenar.click();
        return;
      }
    }

    // Clique fora de um menu de exportação aberto
    document.querySelectorAll('.tr-export-menu:not([hidden])').forEach(function (menuAberto) {
      var container = menuAberto.closest('.tr-export');
      if (!container || !container.contains(evento.target)) fecharMenu(menuAberto);
    });
  });

  // Teclado: linha, abas, foco preso e Esc
  document.addEventListener('keydown', function (evento) {
    var linha = evento.target.closest('.tr-row[data-tr-open]');
    if (linha && evento.target === linha
        && (evento.key === 'Enter' || evento.key === ' ')) {
      evento.preventDefault();
      linha.click();
      return;
    }

    var aba = evento.target.closest('[role="tab"]');
    if (aba && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(evento.key) !== -1) {
      var abas = Array.prototype.slice.call(
        aba.closest('[role="tablist"]').querySelectorAll('[role="tab"]')
      );
      var indice = abas.indexOf(aba);
      var proximo = indice;

      if (evento.key === 'ArrowLeft') proximo = (indice - 1 + abas.length) % abas.length;
      if (evento.key === 'ArrowRight') proximo = (indice + 1) % abas.length;
      if (evento.key === 'Home') proximo = 0;
      if (evento.key === 'End') proximo = abas.length - 1;

      evento.preventDefault();
      abas[proximo].focus();
      ativarAba(abas[proximo]);
      return;
    }

    if (!drawerAberto) return;

    if (evento.key === 'Escape') {
      var menuAberto = drawerAberto.querySelector('.tr-export-menu:not([hidden])');
      if (menuAberto) {
        evento.preventDefault();
        var botaoMenu = menuAberto.closest('.tr-export').querySelector('[data-tr-export-toggle]');
        fecharMenu(menuAberto);
        if (botaoMenu) botaoMenu.focus();
        return;
      }

      evento.preventDefault();
      fecharDrawer(drawerAberto);
      return;
    }

    if (evento.key !== 'Tab') return;

    var painel = drawerAberto.querySelector('.tr-drawer');
    if (!painel) return;

    var focaveis = elementosFocaveis(painel);
    if (!focaveis.length) {
      evento.preventDefault();
      painel.focus();
      return;
    }

    var primeiro = focaveis[0];
    var ultimo = focaveis[focaveis.length - 1];
    var ativo = document.activeElement;

    if (evento.shiftKey && (ativo === primeiro || !painel.contains(ativo))) {
      evento.preventDefault();
      ultimo.focus();
    } else if (!evento.shiftKey && (ativo === ultimo || !painel.contains(ativo))) {
      evento.preventDefault();
      primeiro.focus();
    }
  });

  // ── Inicialização ───────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    atualizarCabecalhos();

    // Abre ?ong=ID (ou o antigo ?modal=ID) no carregamento.
    var params = new URLSearchParams(window.location.search);
    var alvo = params.get('ong') || params.get('modal');
    if (!alvo || !/^\d+$/.test(alvo)) return;

    var shell = document.getElementById('trDrawer' + alvo);
    if (!shell) return;

    var origem = document.querySelector('[data-tr-open="trDrawer' + alvo + '"]');
    window.setTimeout(function () {
      abrirDrawer(shell, origem);
    }, 60);
  });
})();
