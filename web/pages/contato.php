<?php
// ============================================================
// pages/contato.php — Fale conosco
// ============================================================
// o link "mailto:" sozinho não faz nada em computadores sem programa
// de e-mail configurado, por isso a página oferece o Gmail no
// navegador, a cópia do endereço, o programa de e-mail e o WhatsApp
// (números em config/app.php), e orienta para onde vai cada assunto

require_once __DIR__ . '/../includes/bootstrap.php';

$email     = ONGHELP_EMAIL_EQUIPE;
$assunto   = 'Contato pelo site ONGHelp';
$gmailUrl  = 'https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode($email) . '&su=' . rawurlencode($assunto);
$mailtoUrl = 'mailto:' . $email . '?subject=' . rawurlencode($assunto);

$assuntos = [
    ['bi-patch-check', 'Cadastro e verificação', 'Dúvidas sobre o cadastro da sua ONG ou sobre a análise da equipe. A situação da análise também aparece em <em>Meu perfil → Verificação e PIX</em>.'],
    ['bi-flag', 'Denunciar uma notícia', 'Use o botão <strong>Denunciar</strong> da própria notícia. A denúncia é anônima e chega direto à moderação.'],
    ['bi-chat-square-text', 'Contestar uma moderação', 'Escreva para o e-mail com o nome da ONG e o título da notícia. Toda decisão tem motivo registrado e pode ser revista.'],
    ['bi-shield-lock', 'Seus dados pessoais (LGPD)', 'Pedidos de acesso, correção ou exclusão de dados: envie do e-mail cadastrado. Respondemos em até 15 dias. Veja seus <a href="privacidade.php#direitos">direitos</a>.'],
    ['bi-qr-code', 'Problema com uma doação', 'O PIX vai direto do seu banco para a ONG, o ONGHelp não recebe valores. Fale com a organização pelo contato do perfil dela.'],
    ['bi-people', 'Fazer parte da equipe', 'Quer ajudar a analisar cadastros e moderar conteúdo? Veja como funciona em <a href="equipe.php">Faça parte da equipe</a>.'],
];

$page_title       = 'Fale conosco';
$page_description = 'Fale com a Equipe ONGHelp por e-mail ou WhatsApp: dúvidas de cadastro, denúncias, moderação e pedidos sobre dados pessoais.';
$active_nav       = '';
include __DIR__ . '/../includes/layout/header.php';
?>

<div class="page-main ct-page">
  <div class="container">

    <!-- ── cabeçalho ── -->
    <header class="ct-hero">
      <span class="badge-secao"><i class="bi bi-chat-heart" aria-hidden="true"></i> Fale conosco</span>
      <h1 class="ct-titulo">Fale com a <span class="ct-destaque">Equipe ONGHelp</span></h1>
      <p class="ct-subtitulo">
        Escolha o canal que for mais prático para você. Pelo WhatsApp a conversa
        costuma ser mais rápida, e pelo e-mail fica tudo registrado.
      </p>
    </header>

    <!-- ── canais ── -->
    <div class="ct-canais">
      <section class="ct-canal glass-1" aria-labelledby="ctEmailTitulo">
        <span class="ct-canal-icone" aria-hidden="true"><i class="bi bi-envelope"></i></span>
        <h2 id="ctEmailTitulo">E-mail</h2>
        <p class="ct-canal-texto">Para assuntos que precisam de registro, como pedidos sobre dados pessoais e contestações.</p>

        <p class="ct-email">
          <span><?= e($email) ?></span>
          <button type="button" class="equipe-copiar-btn" data-copiar="<?= e($email) ?>" data-copiado="E-mail copiado"
                  aria-label="Copiar o e-mail" title="Copiar e-mail">
            <i class="bi bi-clipboard" aria-hidden="true"></i>
          </button>
        </p>

        <div class="ct-acoes">
          <a class="btn btn-primary rounded-pill" href="<?= e($gmailUrl) ?>" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-google" aria-hidden="true"></i> Escrever pelo Gmail
          </a>
          <a class="btn btn-outline-secondary rounded-pill" href="<?= e($mailtoUrl) ?>">
            <i class="bi bi-envelope-open" aria-hidden="true"></i> Abrir no app de e-mail
          </a>
        </div>
        <p class="ct-nota">Se nenhum dos dois abrir, copie o endereço e escreva pelo seu e-mail de costume.</p>
      </section>

      <section class="ct-canal glass-1" aria-labelledby="ctWhatsTitulo">
        <span class="ct-canal-icone" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
        <h2 id="ctWhatsTitulo">WhatsApp</h2>
        <p class="ct-canal-texto">Escolha qualquer um dos números. A conversa já abre com uma mensagem pronta.</p>
        <?php
        $contatosMensagem = ONGHELP_WHATSAPP_MENSAGEM_CONTATO;
        include __DIR__ . '/../includes/components/contatos-whatsapp.php';
        ?>
      </section>
    </div>

    <!-- ── assuntos ── -->
    <section class="ct-assuntos" aria-labelledby="ctAssuntosTitulo">
      <h2 id="ctAssuntosTitulo" class="ct-assuntos-titulo">Qual é o assunto?</h2>
      <p class="ct-assuntos-sub">Alguns pedidos têm um caminho mais rápido.</p>

      <ul class="ct-assuntos-grade">
        <?php foreach ($assuntos as [$icone, $titulo, $texto]): ?>
          <li class="ct-assunto">
            <span class="ct-assunto-icone" aria-hidden="true"><i class="bi <?= $icone ?>"></i></span>
            <div>
              <h3><?= $titulo ?></h3>
              <p><?= $texto ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <p class="ct-local"><i class="bi bi-geo-alt" aria-hidden="true"></i> Equipe ONGHelp · Taquaritinga, SP — Brasil</p>
    </section>

  </div>
</div>

<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
