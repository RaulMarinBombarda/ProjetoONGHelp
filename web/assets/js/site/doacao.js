/* ============================================================
   assets/js/site/doacao.js — QR Code do modal de doação via PIX
   ============================================================
   o payload PIX (BR Code) é montado no servidor; aqui só se
   desenha o QR Code quando o modal abre, a cópia da chave usa o
   data-copiar-de do ui.js
   ============================================================ */
(function () {
  'use strict';

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

  document.addEventListener('shown.bs.modal', function (evento) {
    var modal = evento.target;
    if (!modal || !modal.id || modal.id.indexOf('modalDoacao') !== 0) return;

    modal.querySelectorAll('[data-pix-payload]').forEach(gerarQr);
  });
})();
