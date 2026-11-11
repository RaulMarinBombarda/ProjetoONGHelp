<?php
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/noticias.php';
require_once 'includes/noticias_ui.php';

$page_title = 'Home';
$active_nav = 'home';

// ── Stats globais para a seção hero ──────────────────────────
$statsStmt = $pdo->query("
    SELECT
        COUNT(DISTINCT u.id)                                                   AS total_ongs,
        COALESCE(SUM(CASE WHEN m.tipo = 'receita' THEN m.valor END), 0)       AS total_arrecadado,
        COUNT(DISTINCT m.id)                                                   AS total_movimentacoes
    FROM  usuarios u
    LEFT  JOIN movimentacoes m ON m.usuario_id = u.id
    WHERE u.status = 'aprovada'
");
$stats = $statsStmt->fetch();

// Formata arrecadado em formato compacto (ex: R$ 47k)
$arrecadado = (float) $stats['total_arrecadado'];
if ($arrecadado >= 1_000_000) {
    $arrecadadoFmt = 'R$ ' . number_format($arrecadado / 1_000_000, 1, ',', '.') . 'M';
} elseif ($arrecadado >= 1_000) {
    $arrecadadoFmt = 'R$ ' . number_format($arrecadado / 1_000, 0, ',', '.') . 'k';
} else {
    $arrecadadoFmt = 'R$ ' . number_format($arrecadado, 2, ',', '.');
}

// ── ONGs em destaque: até 4 ONGs mais recentes ─────────────
// O id DESEMPATA a ordenação. As ONGs de demonstração são
// cadastradas no mesmo segundo, e sem um critério de desempate
// um LIMIT 4 corta no meio do empate: quais ONGs aparecem passa
// a depender do plano de execução do banco, não do código.
$destaqueStmt = $pdo->query("
    SELECT u.id, u.nome, u.area, u.cidade, u.estado, u.descricao, u.pix_chave,
           u.cor_tema, u.cor_fundo, u.status, u.verificada_em, u.foto_perfil,
           (SELECT COALESCE(SUM(m.valor), 0) FROM movimentacoes m
             WHERE m.usuario_id = u.id AND m.tipo = 'receita') AS arrecadado,
           (SELECT COUNT(*) FROM projetos p WHERE p.usuario_id = u.id) AS projetos
    FROM   usuarios u
    WHERE  u.status = 'aprovada'
    ORDER  BY u.criado_em DESC, u.id DESC
    LIMIT  4
");
$ongsDestaque = $destaqueStmt->fetchAll();

// ── Últimas notícias: 3 mais recentes visíveis ──────────────
$ultimasNoticias = $pdo->query("
    SELECT n.*,
           u.nome AS ong_nome,
           u.status AS ong_status,
           u.area AS ong_area,
           u.cidade AS ong_cidade,
           u.estado AS ong_estado,
           u.descricao AS ong_descricao,
           u.verificada_em AS ong_verificada_em,
           u.pix_chave AS ong_pix_chave,
           u.foto_perfil AS ong_foto_perfil,
           u.cor_tema AS ong_cor_tema,
           u.cor_fundo AS ong_cor_fundo
    FROM   noticias n
    INNER JOIN usuarios u ON u.id = n.usuario_id
    WHERE  " . noticiasSqlVisivel('n', 'u') . "
    ORDER  BY COALESCE(n.publicada_em, n.criado_em) DESC, n.id DESC
    LIMIT  3
")->fetchAll();

$fotosUltimas = noticiasFotosPorNoticia(
    $pdo,
    array_map(static fn (array $noticia): int => (int) $noticia['id'], $ultimasNoticias),
    ''
);

// ── Imagens de capa por área (fallback por categoria) ─────────
$imgPorArea = [
    'Educação'          => 'assets/img/areas/ong-educacao.jpg',
    'Animais'           => 'assets/img/areas/ong-animais.jpg',
    'Meio Ambiente'     => 'assets/img/areas/ong-meioambiente.jpg',
    'Saúde'             => 'assets/img/areas/ong-saude.jpg',
    'Assistência Social'=> 'assets/img/areas/ong-assistenciasocial.jpg',
    'Cultura'           => 'assets/img/areas/ong-cultura.jpg',
    'Esporte'           => 'assets/img/areas/ong-esporte.jpg',
    'Outra'             => 'assets/img/areas/ong-outra.jpg',
];

include 'includes/header.php';
?>

<?php
// ── Toast de conta excluída (vindo de desativar.php) ─────────
if (isset($_GET['conta']) && $_GET['conta'] === 'excluida'): ?>
<div class="app-toast" role="status">
  <div class="alert alert-warning alert-dismissible d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-check-circle-fill text-warning"></i>
    Sua conta foi excluída permanentemente. Lamentamos a sua saída.
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
</div>
<?php endif; ?>

<!-- ======= HERO ======= -->
<section class="hero hm-hero">
  <span class="hm-orbe hm-orbe-a" aria-hidden="true"></span>
  <span class="hm-orbe hm-orbe-b" aria-hidden="true"></span>
  <div class="container">
    <div class="row hero-grid g-4">

      <div class="col-lg-6">
        <span class="hero-kicker hm-kicker"><span class="hm-kicker-ponto" aria-hidden="true"></span> Plataforma gratuita para ONGs brasileiras</span>

        <h1 class="hero-title">
          Transparência e gestão<br>
          para ONGs que<br>
          <span class="hero-title-accent">transformam <span class="hero-mark">o Brasil</span></span>
        </h1>

        <p class="hero-text">
          Gerencie projetos, publique relatórios financeiros com total transparência
          e divulgue o canal de doações da sua ONG via PIX — tudo em uma única plataforma.
        </p>

        <div class="hero-actions">
          <a href="pages/cadastro.php" class="hm-btn-principal">
            Cadastrar gratuitamente <i class="bi bi-arrow-right" aria-hidden="true"></i>
          </a>
          <a href="pages/ongs.php" class="btn-portal hm-btn-secundario">
            <i class="bi bi-people" aria-hidden="true"></i> Explorar ONGs
          </a>
        </div>

        <div class="hero-guarantees">
          <span class="hero-guarantee"><i class="bi bi-check-lg" aria-hidden="true"></i> Sem taxa de adesão</span>
          <span class="hero-guarantee"><i class="bi bi-check-lg" aria-hidden="true"></i> Dados seguros (LGPD)</span>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="hero-visual">
          <span class="hm-palco" aria-hidden="true"></span>
          <div class="hero-img">
            <img src="assets/img/hero/hero.png" alt="Impacto social">
          </div>

          <div class="hero-float hero-float-a glass-1">
            <span class="hero-float-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
            <span class="hero-float-text">
              <strong>Cadastro verificado</strong>
              <span>pela Equipe ONGHelp</span>
            </span>
          </div>

          <div class="hero-float hero-float-b glass-1">
            <span class="hero-float-icon" aria-hidden="true"><i class="bi bi-qr-code"></i></span>
            <span class="hero-float-text">
              <strong>Doação direta via PIX</strong>
              <span>sem intermediação</span>
            </span>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ======= NÚMEROS — dinâmicos do banco ======= -->
<div class="hm-numeros-secao">
  <div class="container">
    <ul class="hm-numeros">
      <li>
        <span class="hm-numero-icone" aria-hidden="true"><i class="bi bi-buildings"></i></span>
        <span><strong><?= (int) $stats['total_ongs'] ?></strong> ONGs cadastradas</span>
      </li>
      <li>
        <span class="hm-numero-icone" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
        <span><strong><?= $arrecadadoFmt ?></strong> arrecadados</span>
      </li>
      <li>
        <span class="hm-numero-icone" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></span>
        <span><strong><?= number_format((int) $stats['total_movimentacoes'], 0, ',', '.') ?></strong> movimentações registradas</span>
      </li>
      <li>
        <span class="hm-numero-icone" aria-hidden="true"><i class="bi bi-eye"></i></span>
        <span><strong>100%</strong> transparência pública</span>
      </li>
    </ul>
  </div>
</div>

<!-- ======= COMO FUNCIONA ======= -->
<section class="section como-funciona">
  <div class="container">
    <div class="home-section-head">
      <span class="eyebrow-label">Como funciona</span>
      <h2>Tudo que sua ONG precisa em um só lugar</h2>
      <p>
        Do cadastro à gestão financeira, o ONGHelp simplifica a operação para
        que você foque no que mais importa: o impacto social.
      </p>
    </div>

    <div class="bento-grid">
      <article class="bento-card bento-card-perfil">
        <span class="bento-num">01</span>
        <h3>Perfil Público</h3>
        <p>
          Crie um perfil completo para sua ONG com projetos, resultados
          e informações de contato acessíveis a qualquer pessoa.
        </p>

        <div class="mini-perfil" aria-hidden="true">
          <div class="mini-perfil-capa"></div>
          <div class="mini-perfil-avatar"></div>
          <div class="mini-perfil-linhas">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      </article>

      <article class="bento-card bento-card-transparencia">
        <span class="bento-num">02</span>
        <h3>Transparência Total</h3>
        <p>
          Publique relatórios financeiros, entradas e saídas com gráficos
          claros para garantir a confiança dos doadores.
        </p>

        <div class="mini-bars" aria-hidden="true">
          <span></span><span></span><span></span><span></span><span></span><span></span>
        </div>
      </article>

      <article class="bento-card bento-card-pix">
        <span class="bento-num">03</span>
        <h3>Divulgação via PIX</h3>
        <p>
          Exiba a chave e o QR Code da sua ONG no perfil público. A doação vai
          diretamente para a organização, sem intermediação da plataforma.
        </p>

        <div class="mini-qr" aria-hidden="true">
          <span></span><span class="is-on"></span><span class="is-on"></span><span class="is-on"></span><span></span>
          <span class="is-on"></span><span></span><span class="is-on"></span><span></span><span class="is-on"></span>
          <span class="is-on"></span><span class="is-on"></span><span></span><span class="is-on"></span><span class="is-on"></span>
          <span></span><span class="is-on"></span><span class="is-on"></span><span></span><span></span>
          <span class="is-on"></span><span></span><span class="is-on"></span><span class="is-on"></span><span class="is-on"></span>
        </div>
      </article>
    </div>
  </div>
</section>

<!-- ======= ONGs EM DESTAQUE — dinâmicas do banco ======= -->
<section class="section ongs-destaque">
  <div class="container">
    <div class="home-section-head-row">
      <div class="home-section-head home-section-head-tight">
        <span class="eyebrow-label">ONGs em Destaque</span>
        <h2>Conheça quem está transformando o Brasil</h2>
        <p>
          Organizações verificadas, transparentes e com impacto real
          nas comunidades brasileiras.
        </p>
      </div>
      <a href="pages/ongs.php" class="link-arrow hm-ver-todas">
        Ver todas as ONGs <span aria-hidden="true">→</span>
      </a>
    </div>

    <?php if (empty($ongsDestaque)): ?>
      <!-- Estado vazio: ainda não há ONGs cadastradas -->
      <p class="text-muted">Nenhuma ONG cadastrada ainda. Seja a primeira!</p>
    <?php else: ?>
    <div class="og-grade hm-og-grade">
      <?php foreach ($ongsDestaque as $ong):
        [$cor1] = temaOng($ong);
        $descricao = trim($ong['descricao'] ?? '') ?: 'Organização dedicada a ' . mb_strtolower($ong['area']) . '.';
      ?>
      <article class="og-card" style="--causa-cor:<?= htmlspecialchars($cor1) ?>;">
        <span class="og-card-aurora" aria-hidden="true"></span>
        <span class="og-causa"><span class="cause-dot" aria-hidden="true"></span> <?= htmlspecialchars($ong['area']) ?></span>

        <span class="og-avatar">
          <img src="<?= htmlspecialchars(fotoPerfilUrl($ong['foto_perfil'] ?? null, '', $ong['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
               alt="" aria-hidden="true">
        </span>

        <h3 class="og-nome">
          <a class="og-card-link" href="pages/perfilpublico.php?id=<?= (int) $ong['id'] ?>"><?= htmlspecialchars($ong['nome']) ?></a>
          <?= badgeVerificado($ong, true) ?>
        </h3>
        <span class="og-local">
          <i class="bi bi-geo-alt" aria-hidden="true"></i>
          <?= htmlspecialchars($ong['cidade'] ?: 'Brasil') ?><?= $ong['estado'] ? ' · ' . htmlspecialchars($ong['estado']) : '' ?>
        </span>
        <span class="og-desc"><?= htmlspecialchars($descricao) ?></span>

        <span class="og-card-base">
          <span class="og-numero"><strong><?= number_format((int) $ong['projetos'], 0, ',', '.') ?></strong> projetos</span>
          <span class="og-numero"><strong><?= fmtDinheiro((float) $ong['arrecadado'], true) ?></strong> arrecadados</span>
          <span class="og-seta" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
        </span>

        <span class="og-card-acoes">
          <?php if (!empty($ong['pix_chave'])): ?>
            <button type="button" class="btn-pix" data-bs-toggle="modal" data-bs-target="#modalDoacao<?= (int) $ong['id'] ?>">
              <i class="bi bi-qr-code" aria-hidden="true"></i> Doar via PIX
            </button>
          <?php endif; ?>
          <a class="btn-portal" href="pages/transparencia.php?ong=<?= (int) $ong['id'] ?>">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Ver no portal
          </a>
        </span>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php if ($ultimasNoticias): ?>
<!-- ======= ÚLTIMAS NOTÍCIAS ======= -->
<section class="section home-news">
  <div class="container">
    <div class="home-section-head-row">
      <div class="home-section-head home-section-head-tight">
        <span class="eyebrow-label">Notícias</span>
        <h2>O que as ONGs estão fazendo</h2>
        <p>Acompanhe ações, projetos e prestações de contas publicados pelas organizações.</p>
      </div>
      <a href="pages/noticias-publicas.php" class="link-arrow hm-ver-todas">
        Ver todas as notícias <span aria-hidden="true">→</span>
      </a>
    </div>

    <?= noticiasGrade($ultimasNoticias, $fotosUltimas, '', 'pages/') ?>
  </div>
</section>
<?php endif; ?>

<!-- ======= BANNER FINAL ======= -->
<section class="hm-final">
  <div class="container">
    <div class="hm-final-painel">
      <span class="hm-orbe hm-orbe-c" aria-hidden="true"></span>
      <span class="hm-orbe hm-orbe-d" aria-hidden="true"></span>
      <span class="hm-kicker"><span class="hm-kicker-ponto" aria-hidden="true"></span> Comece hoje</span>
      <h2>Sua ONG merece o melhor suporte</h2>
      <p>Cadastre-se gratuitamente e comece a gestão transparente da sua organização hoje mesmo.</p>
      <div class="hm-final-acoes">
        <a href="pages/cadastro.php" class="hm-btn-principal">
          Começar agora <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
        <a href="pages/transparencia.php" class="btn-portal hm-btn-secundario">
          <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Ver o portal de transparência
        </a>
      </div>
    </div>
  </div>
</section>

<?php
// Um modal de doação por ONG em destaque que tenha chave PIX.
foreach ($ongsDestaque as $ongModal):
    if (empty($ongModal['pix_chave'])) continue;
    $modalUid = (int) $ongModal['id'];
    $modalOng = [
        'id'        => (int) $ongModal['id'],
        'nome'      => $ongModal['nome'],
        'pix_chave' => $ongModal['pix_chave'],
        'cidade'    => $ongModal['cidade'] ?? '',
    ];
    include 'includes/modal_doacao.php';
endforeach;
?>

<?php include 'includes/footer.php'; ?>

