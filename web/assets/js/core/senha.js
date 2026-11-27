/* ============================================================
   assets/js/core/senha.js — lista de requisitos de senha ao vivo
   ============================================================
   vale para todo form com data-password-form (cadastro, perfil da
   ONG, primeiro acesso e troca de senha da equipe), as regras são
   as mesmas de includes/core/senha.php, que monta os atributos:

     data-min="8"               tamanho mínimo da senha
     data-senhas-comuns="[...]" senhas comuns já normalizadas
     data-email="..."           e-mail da conta (sem ele, usa o campo
                                name="email" do próprio formulário)
     data-temp-salt / data-temp-digest / data-require-temp="1"
                                primeiro acesso: compara com a senha
                                temporária sem mandá-la ao navegador
     data-lock-submit="1"       só libera o botão com tudo marcado

   cada item da lista tem data-check com o nome da regra, a validação
   definitiva é sempre a do servidor
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

  function hex(buffer) {
    return Array.from(new Uint8Array(buffer))
      .map(function (byte) { return byte.toString(16).padStart(2, '0'); })
      .join('');
  }

  // minúsculas e só letras e números, igual a senhaNormalizada() no PHP
  function normalizar(texto) {
    return texto.toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
  }

  function marcar(item, ok) {
    if (!item) return;
    item.classList.toggle('is-ok', !!ok);
    var icone = item.querySelector('i');
    if (icone) icone.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-circle');
  }

  function lerLista(form) {
    try {
      var lista = JSON.parse(form.getAttribute('data-senhas-comuns') || '[]');
      return Array.isArray(lista) ? lista : [];
    } catch (erro) {
      return [];
    }
  }

  ready(function () {
    document.querySelectorAll('[data-password-form]').forEach(function (form) {
      var minimo = parseInt(form.getAttribute('data-min'), 10) || 10;
      var comuns = lerLista(form);
      var emailFixo = (form.getAttribute('data-email') || '').toLowerCase();
      var campoEmail = form.querySelector('input[name="email"]');
      var sal = form.getAttribute('data-temp-salt') || '';
      var digesto = form.getAttribute('data-temp-digest') || '';
      var exigeTemporaria = form.getAttribute('data-require-temp') === '1';
      var travaEnvio = form.getAttribute('data-lock-submit') === '1';
      var botao = form.querySelector('[data-submit-button]');
      var aceite = form.querySelector('input[name="aceite_regras"]');

      var nova = form.querySelector('input[name="senha"], input[name="senha_nova"]');
      var confirmacao = form.querySelector('input[name="confirmacao"], input[name="senha_confirmacao"], input[name="senha_confirm"]');
      var atual = form.querySelector('input[name="senha_atual"]');
      var itens = {};

      form.querySelectorAll('[data-check]').forEach(function (item) {
        itens[item.getAttribute('data-check')] = item;
      });

      if (!nova) return;

      var versao = 0;

      function emailAtual() {
        if (emailFixo) return emailFixo;
        return campoEmail ? campoEmail.value.trim().toLowerCase() : '';
      }

      function atualizarBotao() {
        if (!travaEnvio || !botao) return;
        if (form.getAttribute('data-submitting') === 'true') return;

        var todosOk = Object.keys(itens).every(function (chave) {
          return itens[chave].classList.contains('is-ok');
        });

        if (aceite) todosOk = todosOk && aceite.checked;

        botao.disabled = !todosOk;
      }

      function avaliar() {
        var valor = nova.value || '';
        var confirmado = confirmacao ? (confirmacao.value || '') : '';
        var minusculo = valor.toLowerCase();
        var email = emailAtual();
        var local = email.split('@')[0] || '';

        marcar(itens.minimo, Array.from(valor).length >= minimo);
        marcar(itens.letra_numero, /\p{L}/u.test(valor) && /\d/.test(valor));

        // a parte antes do @ só conta com 3 letras ou mais, como no PHP
        var semEmail = valor !== '' && (email === '' ||
          (minusculo.indexOf(email) === -1 &&
            (local.length < 3 || minusculo.indexOf(local) === -1)));
        marcar(itens.email, semEmail);

        marcar(itens.comum, valor !== '' &&
          comuns.indexOf(normalizar(valor)) === -1 &&
          !/^(.)\1*$/u.test(valor));

        marcar(itens.iguais, valor !== '' && valor === confirmado);

        if (itens.atual) {
          marcar(itens.atual, atual !== null && valor !== '' && valor !== atual.value);
        }

        if (exigeTemporaria && itens.temporaria) {
          var minhaVersao = ++versao;

          if (!sal || !digesto || !window.crypto || !window.crypto.subtle || !window.TextEncoder) {
            // sem Web Crypto o item fica só indicativo, o servidor
            // confere de novo com password_verify()
            marcar(itens.temporaria, valor !== '');
            atualizarBotao();
            return;
          }

          window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(sal + valor))
            .then(function (resultado) {
              if (minhaVersao !== versao) return;
              marcar(itens.temporaria, valor !== '' && hex(resultado) !== digesto);
              atualizarBotao();
            })
            .catch(function () {
              marcar(itens.temporaria, valor !== '');
              atualizarBotao();
            });
        } else if (itens.temporaria) {
          marcar(itens.temporaria, valor !== '');
        }

        atualizarBotao();
      }

      nova.addEventListener('input', avaliar);
      if (confirmacao) confirmacao.addEventListener('input', avaliar);
      if (atual) atual.addEventListener('input', avaliar);
      if (campoEmail) campoEmail.addEventListener('input', avaliar);
      if (aceite) aceite.addEventListener('change', atualizarBotao);

      window.addEventListener('pageshow', avaliar);

      avaliar();
    });
  });
})();
