/* ============================================================
   ONGHelp — checklist ao vivo de senha da equipe
   A validação definitiva é sempre feita no servidor.
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

  function marcar(item, ok) {
    if (!item) return;
    item.classList.toggle('is-ok', !!ok);
    var icone = item.querySelector('i');
    if (icone) icone.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-circle');
  }

  ready(function () {
    document.querySelectorAll('[data-password-form]').forEach(function (form) {
      var email = (form.getAttribute('data-email') || '').toLowerCase();
      var local = email.split('@')[0] || '';
      var sal = form.getAttribute('data-temp-salt') || '';
      var digesto = form.getAttribute('data-temp-digest') || '';
      var exigeTemporaria = form.getAttribute('data-require-temp') === '1';
      var travaEnvio = form.getAttribute('data-lock-submit') === '1';
      var botao = form.querySelector('[data-submit-button]');
      var aceite = form.querySelector('input[name="aceite_regras"]');

      var nova = form.querySelector('input[name="senha"], input[name="senha_nova"]');
      var confirmacao = form.querySelector('input[name="confirmacao"], input[name="senha_confirmacao"]');
      var atual = form.querySelector('input[name="senha_atual"]');
      var itens = {};

      form.querySelectorAll('[data-check]').forEach(function (item) {
        itens[item.getAttribute('data-check')] = item;
      });

      if (!nova) return;

      var versao = 0;
      var temporariaOk = false;

      function atualizarBotao() {
        if (!travaEnvio || !botao) return;
        if (form.getAttribute('data-submitting') === 'true') return;

        var todosOk = true;
        Object.keys(itens).forEach(function (chave) {
          if (!itens[chave].classList.contains('is-ok')) todosOk = false;
        });

        if (aceite) todosOk = todosOk && aceite.checked;

        botao.disabled = !todosOk;
      }

      function avaliar() {
        var valor = nova.value || '';
        var confirmado = confirmacao ? (confirmacao.value || '') : '';
        var valorLower = valor.toLowerCase();

        marcar(itens.minimo, valor.length >= 10);

        var semEmail = valor !== '' && (email === '' ||
          (valorLower.indexOf(email) === -1 &&
            (local.length < 3 || valorLower.indexOf(local) === -1)));
        marcar(itens.email, semEmail);
        marcar(itens.iguais, valor !== '' && valor === confirmado);

        if (itens.atual) {
          marcar(itens.atual, atual !== null && valor !== '' && valor !== atual.value);
        }

        if (exigeTemporaria && itens.temporaria) {
          var minhaVersao = ++versao;

          if (!sal || !digesto || !window.crypto || !window.crypto.subtle || !window.TextEncoder) {
            // Sem Web Crypto, o item fica apenas indicativo; o servidor
            // revalida com password_verify() e é a fonte de verdade.
            temporariaOk = valor !== '';
            marcar(itens.temporaria, temporariaOk);
            atualizarBotao();
            return;
          }

          window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(sal + valor))
            .then(function (resultado) {
              if (minhaVersao !== versao) return;
              temporariaOk = valor !== '' && hex(resultado) !== digesto;
              marcar(itens.temporaria, temporariaOk);
              atualizarBotao();
            })
            .catch(function () {
              // Sem Web Crypto (contexto não seguro), o servidor
              // continua sendo a única fonte de verdade.
              temporariaOk = valor !== '';
              marcar(itens.temporaria, temporariaOk);
              atualizarBotao();
            });
        } else {
          marcar(itens.temporaria, valor !== '');
          temporariaOk = valor !== '';
        }

        atualizarBotao();
      }

      nova.addEventListener('input', avaliar);
      if (confirmacao) confirmacao.addEventListener('input', avaliar);
      if (atual) atual.addEventListener('input', avaliar);
      if (aceite) aceite.addEventListener('change', atualizarBotao);

      window.addEventListener('pageshow', avaliar);

      avaliar();
    });
  });
})();
