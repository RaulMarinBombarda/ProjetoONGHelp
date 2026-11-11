<?php
// ============================================================
// pages/equipe.php — Página pública para novas pessoas moderadoras
// Página não indexada, com contato único por WhatsApp.
// ============================================================

require_once '../includes/config.php';
require_once '../includes/helpers.php';

$page_title  = 'Faça parte da equipe';
$page_robots = 'noindex, nofollow';
$active_nav  = '';
$page_script = 'equipe-publica.js';

$whatsappUrl    = whatsappEquipeUrl();
$whatsappNumero = whatsappEquipeExibicao();
$whatsappE164   = whatsappEquipeNumeroE164();
$regras         = regrasUsoEquipe();

include '../includes/header.php';
?>

<main class="page-main equipe-publica">

  <section class="equipe-hero" aria-labelledby="equipeTitulo">
    <div class="container">
      <div class="row align-items-center g-4 g-lg-5">
        <div class="col-lg-7">
          <span class="equipe-eyebrow">Equipe</span>

          <h1 id="equipeTitulo" class="equipe-hero-titulo">
            Faça parte da equipe ONGHelp
          </h1>

          <p class="equipe-hero-texto">
            Ajude a verificar organizações e a manter o portal confiável.
          </p>
        </div>

        <div class="col-lg-5">
          <aside class="equipe-contato-card glass-1" aria-labelledby="equipeContatoTitulo">
            <span class="equipe-contato-icone" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
            <h2 id="equipeContatoTitulo">Fale com a equipe</h2>
            <p>Conversamos pelo WhatsApp.</p>

            <a class="btn btn-primary equipe-contato-btn"
               href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener noreferrer">
              <i class="bi bi-whatsapp" aria-hidden="true"></i>
              Falar com a equipe no WhatsApp
            </a>

            <span class="equipe-contato-numero"><?= htmlspecialchars($whatsappNumero) ?></span>

            <button type="button" class="btn btn-link equipe-copiar-btn"
                    data-copiar-numero="<?= htmlspecialchars($whatsappE164, ENT_QUOTES, 'UTF-8') ?>">
              <i class="bi bi-clipboard" aria-hidden="true"></i> Copiar número
            </button>
            <span class="equipe-copy-status visually-hidden" role="status" aria-live="polite" data-copy-status></span>
          </aside>
        </div>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="equipePerfilTitulo">
    <div class="container">
      <div class="equipe-duas-colunas glass-1">
        <div class="equipe-coluna">
          <h2 id="equipePerfilTitulo">O que faz um moderador</h2>
          <ul class="equipe-lista-check">
            <li><i class="bi bi-check2" aria-hidden="true"></i> Analisa cadastros de ONGs</li>
            <li><i class="bi bi-check2" aria-hidden="true"></i> Aprova, recusa ou suspende com critério</li>
            <li><i class="bi bi-check2" aria-hidden="true"></i> Cuida da confiança do portal</li>
            <li><i class="bi bi-check2" aria-hidden="true"></i> No futuro, ajuda a moderar conteúdo publicado</li>
          </ul>
        </div>

        <div class="equipe-coluna">
          <h2>Quem procuramos</h2>
          <ul class="equipe-lista-check">
            <li><i class="bi bi-check2" aria-hidden="true"></i> Pessoas de confiança</li>
            <li><i class="bi bi-check2" aria-hidden="true"></i> Atenção a detalhes</li>
            <li><i class="bi bi-check2" aria-hidden="true"></i> Disponibilidade combinada</li>
            <li><i class="bi bi-check2" aria-hidden="true"></i> Sigilo com dados de terceiros</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-tight" id="como-funciona" aria-labelledby="equipeComoTitulo">
    <div class="container">
      <div class="section-head equipe-section-head">
        <h2 id="equipeComoTitulo">Como funciona</h2>
      </div>

      <ol class="equipe-como">
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">1</span>
          <h3>Você chama a equipe no WhatsApp</h3>
          <p>Conte seu nome e sua cidade para começarmos a conversa.</p>
        </li>
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">2</span>
          <h3>Fazemos uma conversa rápida (entrevista)</h3>
          <p>Alinhamos disponibilidade, expectativas e as regras de sigilo.</p>
        </li>
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">3</span>
          <h3>Você compartilha as informações necessárias</h3>
          <p>Somente os dados pedidos abaixo, sem documentos sensíveis.</p>
        </li>
        <li class="equipe-como-passo">
          <span class="equipe-como-numero" aria-hidden="true">4</span>
          <h3>Criamos sua conta individual</h3>
          <p>Você define sua senha no primeiro acesso e aceita as regras do painel.</p>
        </li>
      </ol>
    </div>
  </section>

  <section class="section" aria-labelledby="equipePedirTitulo">
    <div class="container">
      <div class="equipe-pedir glass-1">
        <div>
          <h2 id="equipePedirTitulo" class="equipe-bloco-titulo">O que vamos pedir</h2>
          <ul class="equipe-lista-pedir">
            <li><i class="bi bi-dot" aria-hidden="true"></i> Nome completo</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> E-mail que será seu login</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Cidade</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Motivação</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Disponibilidade</li>
            <li><i class="bi bi-dot" aria-hidden="true"></i> Se tem vínculo com alguma ONG</li>
          </ul>
        </div>

        <div class="equipe-nota-info">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          <p>Não pedimos CPF nem RG.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="equipeRegrasTitulo">
    <div class="container">
      <div class="equipe-regras glass-1">
        <h2 id="equipeRegrasTitulo" class="equipe-bloco-titulo">Regras e compromissos</h2>
        <ol class="equipe-lista-regras">
          <?php foreach ($regras as $indice => $regra): ?>
            <li>
              <span class="equipe-regra-numero" aria-hidden="true"><?= str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span><?= htmlspecialchars($regra) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <section class="section equipe-fechamento-section">
    <div class="container">
      <div class="equipe-fechamento">
        <div class="equipe-fechamento-texto">
          <h2>Pronto para conversar?</h2>
          <p>Os termos de participação (voluntariado ou remuneração) são combinados na conversa.</p>
        </div>

        <a class="equipe-btn-claro"
           href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
          <i class="bi bi-whatsapp" aria-hidden="true"></i>
          Falar no WhatsApp
        </a>
      </div>
    </div>
  </section>

</main>

<?php include '../includes/footer.php'; ?>
