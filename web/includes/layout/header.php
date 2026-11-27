<?php
$page_title = $page_title ?? 'ONGHelp';
$active_nav = $active_nav ?? '';
$page_robots = $page_robots ?? '';
$page_description = $page_description ?? '';
$page_canonical = $page_canonical ?? '';
$page_og_title = $page_og_title ?? $page_title;
$page_og_type = $page_og_type ?? 'website';
$page_og_image = $page_og_image ?? '';
$page_og_image_width = (int) ($page_og_image_width ?? 0);
$page_og_image_height = (int) ($page_og_image_height ?? 0);
$page_og_image_alt = $page_og_image_alt ?? '';
$page_published_time = $page_published_time ?? '';
$body_class = $body_class ?? '';

require_once __DIR__ . '/../bootstrap.php';

$base = baseUrl();
$logado = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if ($page_robots !== ''): ?>
    <meta name="robots" content="<?= htmlspecialchars($page_robots) ?>">
  <?php endif; ?>
  <title><?= htmlspecialchars($page_title) ?> | ONGHelp</title>

  <?php if ($page_description !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if ($page_canonical !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>

  <!-- open Graph / Twitter Card -->
  <meta property="og:site_name" content="ONGHelp">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:type" content="<?= htmlspecialchars($page_og_type, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars($page_og_title, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($page_description !== ''): ?>
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if ($page_canonical !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if ($page_og_image !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($page_og_image, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($page_og_image_width > 0 && $page_og_image_height > 0): ?>
      <meta property="og:image:width" content="<?= $page_og_image_width ?>">
      <meta property="og:image:height" content="<?= $page_og_image_height ?>">
    <?php endif; ?>
    <?php if ($page_og_image_alt !== ''): ?>
      <meta property="og:image:alt" content="<?= htmlspecialchars($page_og_image_alt, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($page_published_time !== ''): ?>
    <meta property="article:published_time" content="<?= htmlspecialchars($page_published_time, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="<?= $page_og_image !== '' ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($page_og_title, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($page_description !== ''): ?>
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if ($page_og_image !== ''): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($page_og_image, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>

<?php include __DIR__ . '/head-assets.php'; ?>
  <!-- navbar: pílula deslizante -->
  <script defer src="<?= asset('js/core/navbar.js') ?>"></script>
</head>
<body<?= $body_class !== '' ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<a class="link-pular visually-hidden-focusable" href="#conteudo">Pular para o conteúdo</a>

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<nav class="navbar navbar-expand-lg site-navbar sticky-top<?= $logado ? ' is-logado' : '' ?>" aria-label="Navegação principal">
  <div class="container site-navbar-inner">

    <a class="navbar-brand d-flex align-items-center" href="<?= url('index.php') ?>" aria-label="ONGHelp — página inicial">
      <img src="<?= asset('img/logo-onghelp.png') ?>" alt="ONGHelp" class="navbar-brand-logo" width="127" height="38">
    </a>

    <button class="navbar-toggler site-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse site-navbar-collapse" id="navMenu">
      <ul class="navbar-nav site-nav-pill">
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='home' ? 'is-active' : '' ?>" href="<?= url('index.php') ?>"<?= $active_nav==='home' ? ' aria-current="page"' : '' ?>>Home</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='ongs' ? 'is-active' : '' ?>" href="<?= url('pages/ongs.php') ?>"<?= $active_nav==='ongs' ? ' aria-current="page"' : '' ?>>ONGs</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='transparencia' ? 'is-active' : '' ?>" href="<?= url('pages/transparencia.php') ?>"<?= $active_nav==='transparencia' ? ' aria-current="page"' : '' ?>>Transparência</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='noticias' ? 'is-active' : '' ?>" href="<?= url('pages/noticias.php') ?>"<?= $active_nav==='noticias' ? ' aria-current="page"' : '' ?>>Notícias</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='legislacao' ? 'is-active' : '' ?>" href="<?= url('pages/legislacao.php') ?>"<?= $active_nav==='legislacao' ? ' aria-current="page"' : '' ?>>Legislação</a>
        </li>
        <?php if ($logado): ?>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='gestao' ? 'is-active' : '' ?>" href="<?= url('pages/gestao.php') ?>"<?= $active_nav==='gestao' ? ' aria-current="page"' : '' ?>>Gestão</a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="site-nav-actions">
        <?php if ($logado):
          // sino de avisos: contagem das não lidas e os 5 mais recentes
          require_once __DIR__ . '/../modules/notificacoes.php';
          $naoLidasTopo = notificacoesNaoLidas($pdo, (int) $_SESSION['user_id']);
          $avisosTopo   = notificacoesDaOng($pdo, (int) $_SESSION['user_id'], 5);
        ?>
          <div class="dropdown site-nav-sino">
            <button class="site-nav-sino-btn" type="button" id="menuAvisos"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true"
                    aria-label="Notificações<?= $naoLidasTopo > 0 ? ', ' . $naoLidasTopo . ($naoLidasTopo === 1 ? ' nova' : ' novas') : '' ?>">
              <i class="bi <?= $naoLidasTopo > 0 ? 'bi-bell-fill' : 'bi-bell' ?>" aria-hidden="true"></i>
              <?php if ($naoLidasTopo > 0): ?>
                <span class="site-nav-sino-num" aria-hidden="true"><?= $naoLidasTopo > 9 ? '9+' : $naoLidasTopo ?></span>
              <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end site-nav-dropdown site-nav-sino-menu" aria-labelledby="menuAvisos">
              <div class="site-nav-sino-topo">
                <strong>Notificações</strong>
                <a href="<?= url('pages/notificacoes.php') ?>">Ver todas</a>
              </div>
              <?php if (!$avisosTopo): ?>
                <p class="site-nav-sino-vazio">Nenhuma notificação por enquanto.</p>
              <?php endif; ?>
              <?php foreach ($avisosTopo as $avisoTopo): ?>
                <form method="POST" action="<?= url('pages/notificacoes.php') ?>">
                  <?= csrfField() ?>
                  <input type="hidden" name="acao" value="abrir">
                  <input type="hidden" name="id" value="<?= (int) $avisoTopo['id'] ?>">
                  <button type="submit" class="site-nav-aviso<?= $avisoTopo['lida_em'] === null ? ' is-nova' : '' ?>">
                    <i class="bi <?= notificacaoIcone((string) $avisoTopo['tipo']) ?>" aria-hidden="true"></i>
                    <span>
                      <strong><?= htmlspecialchars($avisoTopo['titulo']) ?></strong>
                      <small><?= htmlspecialchars(notificacaoQuando((string) $avisoTopo['criado_em'])) ?><?= $avisoTopo['lida_em'] === null ? ' · nova' : '' ?></small>
                    </span>
                  </button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="dropdown site-nav-account">
            <button class="site-nav-avatar" type="button" id="menuConta"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true"
                    aria-label="Abrir menu da conta de <?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?>">
              <img class="site-nav-avatar-foto"
                   src="<?= htmlspecialchars(fotoPerfilUrl($_SESSION['user_foto'] ?? null, $base, $_SESSION['user_area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                   alt="" aria-hidden="true">
              <span class="site-nav-avatar-nome" aria-hidden="true"><?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?></span>
              <i class="bi bi-chevron-down site-nav-avatar-caret" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end site-nav-dropdown" aria-labelledby="menuConta">
              <li class="site-nav-conta-topo">
                <img src="<?= htmlspecialchars(fotoPerfilUrl($_SESSION['user_foto'] ?? null, $base, $_SESSION['user_area'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true">
                <span>
                  <strong><?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?></strong>
                  <small><?= htmlspecialchars($_SESSION['user_area'] ?? 'ONG') ?></small>
                </span>
              </li>
              <li><a class="dropdown-item" href="<?= url('pages/perfil-privado.php') ?>"><i class="bi bi-person-circle" aria-hidden="true"></i>Meu perfil</a></li>
              <li><a class="dropdown-item" href="<?= url('pages/perfil-publico.php?id=' . (int) ($_SESSION['user_id'] ?? 0)) ?>"><i class="bi bi-eye" aria-hidden="true"></i>Ver perfil público</a></li>
              <li><a class="dropdown-item" href="<?= url('pages/gestao.php') ?>"><i class="bi bi-wallet2" aria-hidden="true"></i>Gestão</a></li>
              <li><a class="dropdown-item" href="<?= url('pages/minhas-noticias.php') ?>"><i class="bi bi-newspaper" aria-hidden="true"></i>Minhas notícias</a></li>
              <li><a class="dropdown-item" href="<?= url('pages/notificacoes.php') ?>"><i class="bi bi-bell" aria-hidden="true"></i>Notificações</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <button type="button" class="dropdown-item site-nav-sair" data-bs-toggle="modal" data-bs-target="#modalSair" aria-controls="modalSair">
                  <i class="bi bi-box-arrow-right" aria-hidden="true"></i>Sair
                </button>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?= url('pages/login.php') ?>" class="btn-47 btn-47-colors">Entrar</a>
          <a href="<?= url('pages/cadastro.php') ?>" class="btn-47 btn-47-colors">Cadastrar</a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</nav>

<!-- modal confirmar saída -->
<div class="modal fade" id="modalSair" tabindex="-1" aria-labelledby="modalSairTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sair">
    <div class="modal-content">
      <div class="modal-header border-0 justify-content-center">
        <span class="modal-sair-icone" aria-hidden="true">
          <i class="bi bi-box-arrow-right"></i>
        </span>
        <h5 class="modal-title" id="modalSairTitulo">Sair da conta?</h5>
      </div>
      <div class="modal-body text-center">
        Deseja realmente sair da sua conta?
        <span class="modal-sair-sub">Você poderá entrar novamente quando quiser.</span>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="<?= url('pages/actions/logout.php') ?>" class="d-inline">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-primary rounded-pill">
            <i class="bi bi-box-arrow-right me-1"></i>Sair
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php // fechado no footer.php, cada página coloca o conteúdo dentro deste marco ?>
<main id="conteudo" tabindex="-1">
