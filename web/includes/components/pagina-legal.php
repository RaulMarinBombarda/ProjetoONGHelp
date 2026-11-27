<?php
// ============================================================
// includes/components/pagina-legal.php — layout das páginas legais
// ============================================================
// usado por pages/termos.php e pages/privacidade.php, a página
// define $legal antes de incluir este arquivo:
//
//   $legal = [
//       'badge'      => 'Termos de Uso',
//       'titulo'     => 'Termos de Uso',
//       'destaque'   => 'do ONGHelp',          // trecho colorido do título
//       'subtitulo'  => 'Texto de abertura.',
//       'atualizado' => '11 de novembro de 2026',
//       'outro'      => ['url' => 'privacidade.php', 'rotulo' => 'Política de Privacidade'],
//       'resumo'     => [['icone' => 'bi-...', 'titulo' => '...', 'texto' => '...'], ...],
//       'secoes'     => [['id' => 'ancora', 'titulo' => '...', 'html' => '...'], ...],
//       'icone'      => 'bi-bank',             // opcional, ícone do selo do topo
//       'contato'    => 'Texto do quadro final.', // opcional
//   ];
//
// o HTML das seções é escrito pela própria página (conteúdo fixo,
// sem dados do usuário), por isso é impresso sem escape

$legalEsc = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
$totalPalavras = str_word_count(strip_tags(implode(' ', array_column($legal['secoes'], 'html'))));
$minutosLeitura = max(1, (int) round($totalPalavras / 200));
?>

<div class="lg-page">
  <div class="container">

    <!-- ── cabeçalho ── -->
    <header class="lg-hero">
      <span class="badge-secao"><i class="bi <?= $legalEsc($legal['icone'] ?? 'bi-shield-check') ?>" aria-hidden="true"></i> <?= $legalEsc($legal['badge']) ?></span>
      <h1 class="lg-titulo">
        <?= $legalEsc($legal['titulo']) ?>
        <span class="lg-titulo-destaque"><?= $legalEsc($legal['destaque']) ?></span>
      </h1>
      <p class="lg-subtitulo"><?= $legalEsc($legal['subtitulo']) ?></p>
      <ul class="lg-meta">
        <li><i class="bi bi-calendar3" aria-hidden="true"></i> Atualizado em <?= $legalEsc($legal['atualizado']) ?></li>
        <li><i class="bi bi-clock" aria-hidden="true"></i> Cerca de <?= $minutosLeitura ?> min de leitura</li>
        <li><a href="<?= $legalEsc($legal['outro']['url']) ?>"><i class="bi bi-arrow-right-short" aria-hidden="true"></i><?= $legalEsc($legal['outro']['rotulo']) ?></a></li>
      </ul>
    </header>

    <!-- ── resumo em linguagem simples ── -->
    <section class="lg-resumo" aria-labelledby="lgResumoTitulo">
      <h2 class="lg-resumo-titulo" id="lgResumoTitulo">Em resumo</h2>
      <div class="lg-resumo-grade">
        <?php foreach ($legal['resumo'] as $item): ?>
          <article class="lg-resumo-item">
            <span class="lg-resumo-icone" aria-hidden="true"><i class="bi <?= $legalEsc($item['icone']) ?>"></i></span>
            <h3><?= $legalEsc($item['titulo']) ?></h3>
            <p><?= $legalEsc($item['texto']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="lg-resumo-nota">O resumo ajuda na leitura, mas o texto completo abaixo é o que vale.</p>
    </section>

    <!-- ── sumário + texto completo ── -->
    <div class="lg-layout">
      <nav class="lg-sumario" aria-label="Sumário">
        <p class="lg-sumario-titulo">Nesta página</p>
        <ol>
          <?php foreach ($legal['secoes'] as $i => $secao): ?>
            <li><a href="#<?= $legalEsc($secao['id']) ?>"><span><?= $i + 1 ?></span><?= $legalEsc($secao['titulo']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </nav>

      <div class="lg-conteudo">
        <?php foreach ($legal['secoes'] as $i => $secao): ?>
          <section class="lg-secao" id="<?= $legalEsc($secao['id']) ?>" aria-labelledby="<?= $legalEsc($secao['id']) ?>-titulo">
            <h2 id="<?= $legalEsc($secao['id']) ?>-titulo">
              <span class="lg-secao-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <?= $legalEsc($secao['titulo']) ?>
            </h2>
            <div class="lg-texto"><?= $secao['html'] ?></div>
          </section>
        <?php endforeach; ?>

        <!-- contato -->
        <aside class="lg-contato">
          <span class="lg-contato-icone" aria-hidden="true"><i class="bi bi-envelope-paper-heart"></i></span>
          <div>
            <h2>Ficou alguma dúvida?</h2>
            <p><?= $legalEsc($legal['contato'] ?? 'Fale com a Equipe ONGHelp. Respondemos pedidos sobre dados pessoais em até 15 dias.') ?></p>
          </div>
          <a class="btn btn-primary rounded-pill" href="contato.php">
            <i class="bi bi-chat-heart" aria-hidden="true"></i> Fale conosco
          </a>
        </aside>
      </div>
    </div>

  </div>
</div>
