/* ============================================================
   ONGHelp — checklist e cópia na análise de cadastro
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var checks = document.querySelectorAll('.check-bloqueante');
    var btn = document.getElementById('btnAprovar');
    var dica = document.getElementById('dicaAprovar');
    var cont = document.getElementById('contadorBloqueantes');
    var barra = document.getElementById('barraBloqueantes');
    var progresso = document.getElementById('progressoBloqueantes');
    var total = checks.length;

    function atualizar() {
      var marcados = 0;
      checks.forEach(function (check) { if (check.checked) marcados++; });
      if (cont) cont.textContent = marcados + ' de ' + total + ' verificações';
      if (barra) barra.style.width = (total ? (marcados / total) * 100 : 0) + '%';
      if (progresso) progresso.setAttribute('aria-valuenow', String(marcados));
      if (btn) btn.disabled = marcados !== total;
      if (dica) {
        dica.textContent = marcados === total
          ? 'Todos os bloqueantes conferidos. A aprovação será registrada com a data de hoje.'
          : 'A aprovação só é liberada com todos os bloqueantes marcados. Em caso de dúvida, recuse com motivo.';
      }
    }

    checks.forEach(function (check) { check.addEventListener('change', atualizar); });
    atualizar();

    document.querySelectorAll('[data-copiar]').forEach(function (botao) {
      botao.addEventListener('click', function () {
        var valor = botao.getAttribute('data-copiar') || '';
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(valor).then(function () {
          var antigo = botao.innerHTML;
          botao.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i>';
          window.setTimeout(function () { botao.innerHTML = antigo; }, 1500);
        });
      });
    });
  });
})();
