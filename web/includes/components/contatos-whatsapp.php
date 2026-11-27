<?php
// ============================================================
// includes/components/contatos-whatsapp.php — opções de WhatsApp
// ============================================================
// lista "Opção 1, 2, 3" com os números de config/app.php, usada em
// pages/equipe.php e pages/contato.php, cada linha abre o WhatsApp
// com a mensagem pronta e o botão ao lado só copia o número
//
// antes de incluir, a página pode definir:
//   $contatosMensagem  texto que já abre escrito na conversa
//                      (padrão: ONGHELP_WHATSAPP_MENSAGEM_CONTATO)

$contatosLista = whatsappsEquipe($contatosMensagem ?? ONGHELP_WHATSAPP_MENSAGEM_CONTATO);
?>
<ol class="equipe-contatos">
  <?php foreach ($contatosLista as $indice => $contato): $opcao = $indice + 1; ?>
    <li class="equipe-contato-opcao">
      <a class="equipe-contato-link"
         href="<?= e($contato['url']) ?>"
         target="_blank" rel="noopener noreferrer"
         aria-label="Opção <?= $opcao ?>: conversar no WhatsApp pelo <?= e($contato['exibicao']) ?>">
        <span class="equipe-contato-wa" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
        <span class="equipe-contato-textos">
          <span class="equipe-contato-rotulo">Opção <?= $opcao ?></span>
          <span class="equipe-contato-numero"><?= e($contato['exibicao']) ?></span>
        </span>
        <i class="bi bi-arrow-up-right equipe-contato-seta" aria-hidden="true"></i>
      </a>
      <button type="button" class="equipe-copiar-btn"
              data-copiar="<?= e($contato['e164']) ?>" data-copiado="Número copiado"
              aria-label="Copiar o número da opção <?= $opcao ?>" title="Copiar número">
        <i class="bi bi-clipboard" aria-hidden="true"></i>
      </button>
    </li>
  <?php endforeach; ?>
</ol>
