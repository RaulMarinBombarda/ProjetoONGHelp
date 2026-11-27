/* ============================================================
   assets/js/admin/verificar.js — checklist da análise de cadastro
   ============================================================
   libera o botão "Aprovar" só com todos os itens bloqueantes
   marcados, o servidor confere o checklist de novo ao aprovar,
   a cópia de CNPJ, e-mail e PIX usa o data-copiar do ui.js
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
  });
})();
