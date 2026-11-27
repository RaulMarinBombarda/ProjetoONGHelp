<?php
// ============================================================
// includes/components/modal-confirmar.php — confirmação genérica
// ============================================================
// incluído uma vez pelos layouts, qualquer botão ou link com
// data-confirmar="Texto da pergunta" passa por este modal antes
// de agir (ver assets/js/core/ui.js), atributos opcionais:
//   data-confirmar-titulo  título do modal  (padrão: "Confirmar ação?")
//   data-confirmar-botao   rótulo do botão  (padrão: "Confirmar")
// sem JavaScript, o botão age direto e o servidor continua
// validando a ação normalmente
?>
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="modalConfirmarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-confirmar">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title" id="modalConfirmarTitulo">
          <i class="bi bi-exclamation-triangle text-danger me-2" aria-hidden="true"></i><span data-confirmar-titulo-texto>Confirmar ação?</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" data-confirmar-texto>Essa ação não pode ser desfeita.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill px-3" data-confirmar-ok>
          <span data-confirmar-botao-texto>Confirmar</span>
        </button>
      </div>
    </div>
  </div>
</div>
