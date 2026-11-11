/* ============================================================
   ONGHelp — modal de doação via PIX
   ============================================================ */
(function () {
  'use strict';

  function copiarChave(botao) {
    var uid = botao.getAttribute('data-copiar-chave');
    var campo = document.getElementById('chavePix' + uid);
    if (!campo) return;

    var feedback = function () {
      var original = botao.innerHTML;
      botao.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i><span class="visually-hidden">Copiado</span>';
      window.setTimeout(function () { botao.innerHTML = original; }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(campo.value).then(feedback).catch(function () {
        campo.select();
        try { document.execCommand('copy'); } catch (e) { /* sem suporte */ }
        feedback();
      });
    } else {
      campo.select();
      try { document.execCommand('copy'); } catch (e) { /* sem suporte */ }
      feedback();
    }
  }

  function aviso(container, texto) {
    container.innerHTML = '<span class="pix-qr-aviso">' + texto + '</span>';
  }

  function gerarQr(container) {
    if (container.getAttribute('data-pix-qr-pronto') === '1') return;

    var payload = container.getAttribute('data-pix-payload') || '';
    if (!payload) return;

    if (typeof QRCode === 'undefined') {
      aviso(container, 'QR Code indisponível. Copie a chave PIX e faça o pagamento no app do banco.');
      return;
    }

    container.innerHTML = '';
    try {
      new QRCode(container, {
        text: payload,
        width: 160,
        height: 160,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
      container.setAttribute('data-pix-qr-pronto', '1');
    } catch (e) {
      aviso(container, 'Não foi possível desenhar o QR Code. Copie a chave PIX e faça o pagamento no app do banco.');
    }
  }

  document.addEventListener('click', function (evento) {
    var botao = evento.target.closest('[data-copiar-chave]');
    if (botao) copiarChave(botao);
  });

  document.addEventListener('shown.bs.modal', function (evento) {
    var modal = evento.target;
    if (!modal || !modal.id || modal.id.indexOf('modalDoacao') !== 0) return;

    modal.querySelectorAll('[data-pix-payload]').forEach(gerarQr);
  });
})();
