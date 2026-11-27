/* ============================================================
   assets/js/site/gestao.js — formulários da gestão financeira
   ============================================================
   - a lista de categorias mostra só o grupo do tipo marcado
     (receita ou despesa), sem JavaScript os dois grupos aparecem
     e o servidor confere a combinação
   - o botão de comprovante do lançamento rápido mostra o nome do
     arquivo escolhido
   - ao fechar um modal de edição, a URL perde o ?editar_mov ou
     ?editar_proj, assim atualizar a página não reabre o modal
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

  function prepararCategorias(form) {
    var select = form.querySelector('[data-categoria-movimentacao]');
    if (!select) return;

    // guarda os dois grupos e recoloca só o do tipo marcado
    var grupos = {};
    select.querySelectorAll('optgroup[data-grupo]').forEach(function (grupo) {
      grupos[grupo.getAttribute('data-grupo')] = grupo;
    });
    var escolhida = {};

    function tipoMarcado() {
      var marcado = form.querySelector('input[name="tipo"]:checked');
      return marcado ? marcado.value : 'receita';
    }

    function mostrar() {
      var tipo = tipoMarcado();
      var grupo = grupos[tipo];
      if (!grupo) return;

      Object.keys(grupos).forEach(function (chave) {
        if (grupos[chave].parentNode === select && chave !== tipo) {
          escolhida[chave] = select.value;
          select.removeChild(grupos[chave]);
        }
      });

      if (grupo.parentNode !== select) select.appendChild(grupo);

      // volta à escolha anterior do tipo ou cai em "outras"
      var valor = escolhida[tipo] || (grupo.querySelector('option[selected]') || {}).value || 'outra';
      select.value = valor;
      if (select.value !== valor) select.value = 'outra';
    }

    form.querySelectorAll('input[name="tipo"]').forEach(function (radio) {
      radio.addEventListener('change', mostrar);
    });
    mostrar();
  }

  function prepararArquivo(form) {
    var campo = form.querySelector('.gst-quick-file input[type="file"]');
    var nome = form.querySelector('[data-arquivo-nome]');
    if (!campo || !nome) return;

    var textoPadrao = nome.textContent;
    campo.addEventListener('change', function () {
      var arquivo = campo.files && campo.files[0];
      nome.textContent = arquivo ? arquivo.name : textoPadrao;
      campo.closest('.gst-quick-file').classList.toggle('is-escolhido', !!arquivo);
    });

    // depois de enviar ou limpar o formulário, o rótulo volta ao padrão
    form.addEventListener('reset', function () {
      nome.textContent = textoPadrao;
      campo.closest('.gst-quick-file').classList.remove('is-escolhido');
    });
  }

  ready(function () {
    document.querySelectorAll('[data-mov-form]').forEach(function (form) {
      prepararCategorias(form);
      prepararArquivo(form);
    });

    ['modalEditarMov', 'modalEditarProjeto'].forEach(function (idModal) {
      var modal = document.getElementById(idModal);
      if (!modal || !window.history || !window.history.replaceState) return;

      modal.addEventListener('hidden.bs.modal', function () {
        var url = new URL(window.location.href);
        url.searchParams.delete('editar_mov');
        url.searchParams.delete('editar_proj');
        window.history.replaceState(null, '', url.pathname + url.search + url.hash);
      });
    });
  });
})();
