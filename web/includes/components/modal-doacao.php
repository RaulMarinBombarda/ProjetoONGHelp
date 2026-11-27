<?php
// ============================================================
// includes/components/modal-doacao.php — modal de divulgação de doação via PIX
// projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// o QR Code é sempre gerado a partir da chave PIX verificada da ONG,
// não existe mais upload de imagem: a chave é o próprio CNPJ,
// conferido pela Equipe ONGHelp, e o QR é montado no navegador com
// a biblioteca local qrcodejs — sem chamada a serviço externo
//
// como usar:
//   1. antes do include, defina:
//        $modalUid = 7;   // id único do modal na página
//        $modalOng = ['nome' => 'Minha ONG',
//                     'pix_chave' => '12345678000190',
//                     'cidade' => 'Taquaritinga'];
//   2. crie um botão com data-bs-toggle="modal"
//      e data-bs-target="#modalDoacao" . $modalUid
//   3. inclua este arquivo no fim da página
//
// o QR gerado não tem valor embutido: o doador informa quanto quer
// doar no próprio app do banco
// ============================================================

require_once __DIR__ . '/../core/helpers.php';

$uidDoacao = $modalUid ?? 0;
$ongDoacao = $modalOng;

$temChave = !empty($ongDoacao['pix_chave']);

$pixPayload = $temChave
    ? pixPayload($ongDoacao['pix_chave'], $ongDoacao['nome'], $ongDoacao['cidade'] ?? '')
    : '';
?>

<div class="modal fade modal-doacao" id="modalDoacao<?= $uidDoacao ?>" tabindex="-1" aria-labelledby="modalDoacaoTitulo<?= $uidDoacao ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content modal-transp">

      <div class="modal-header">
        <h5 class="modal-title" id="modalDoacaoTitulo<?= $uidDoacao ?>">
          <i class="bi bi-qr-code me-2" aria-hidden="true"></i>
          Doação via PIX — <?= htmlspecialchars($ongDoacao['nome']) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">

        <?php if (!$temChave): ?>
          <div class="alert alert-warning mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span>Esta organização ainda não tem uma chave PIX verificada para receber doações.</span>
          </div>
        <?php else: ?>

        <div class="row g-4 align-items-center">

          <!-- QR Code gerado a partir da chave PIX verificada -->
          <div class="col-md-5 text-center">
            <div class="pix-qr-gerado"
                 data-pix-payload="<?= htmlspecialchars($pixPayload, ENT_QUOTES, 'UTF-8') ?>">
              <span class="pix-qr-aviso">Gerando QR Code…</span>
            </div>
            <small class="text-muted d-block">
              Escaneie no app do seu banco. O valor é informado por você.
            </small>
          </div>

          <!-- chave + ação -->
          <div class="col-md-7">
            <label for="chavePix<?= $uidDoacao ?>" class="form-label">
              Chave PIX (CNPJ)
            </label>
            <div class="chave-demo">
              <input type="text" id="chavePix<?= $uidDoacao ?>" readonly value="<?= htmlspecialchars($ongDoacao['pix_chave']) ?>">
              <button type="button" data-copiar-de="chavePix<?= $uidDoacao ?>" aria-label="Copiar chave PIX">
                <i class="bi bi-clipboard me-1" aria-hidden="true"></i> Copiar
              </button>
            </div>

            <p class="text-muted mt-3 mb-0">
              <i class="bi bi-bank me-1" aria-hidden="true"></i>
              Copie a chave e faça o PIX no app do seu banco. A doação vai diretamente para esta ONG.
            </p>
          </div>

        </div>

        <div class="alert alert-light mt-4 mb-0 d-flex align-items-start gap-2">
          <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
          <span><strong>A doação vai diretamente para a ONG.</strong>
          O ONGHelp não processa, custodia nem concilia pagamentos: o PIX é feito no app do seu banco,
          entre você e a organização.</span>
        </div>

        <?php endif; ?>

      </div>

    </div>
  </div>
</div>

<?php
// a função abaixo é a mesma para qualquer ONG, mas este componente
// entra uma vez por ONG na página, sem esta guarda, o portal de
// transparência emitia várias cópias idênticas dos scripts
if (!isset($GLOBALS['onghelpDoacaoScript'])) {
    $GLOBALS['onghelpDoacaoScript'] = true; ?>
<script src="<?= asset('libs/qrcode/qrcode.min.js') ?>"></script>
<script src="<?= asset('js/site/doacao.js') ?>"></script>
<?php
}
