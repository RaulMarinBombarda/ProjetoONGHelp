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
$base = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'pages') ? '../' : '';

require_once __DIR__ . '/helpers.php';

// Inicia sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

  <!-- Open Graph / Twitter Card -->
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

  <!-- Favicon -->
  <link rel="icon" href="<?= $base ?>assets/img/brand/favicon.ico?v=2" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= $base ?>assets/img/brand/favicon-32x32.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $base ?>assets/img/brand/apple-touch-icon.png?v=2">
  <meta name="theme-color" content="#1d4052">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- CSS próprio (só o que Bootstrap não cobre) -->
  <link rel="stylesheet" href="<?= $base ?>assets/css/global.css?v=72">
  <!-- Navbar: pílula deslizante -->
  <script defer src="<?= $base ?>assets/js/navbar.js?v=3"></script>
</head>
<body<?= $body_class !== '' ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<nav class="navbar navbar-expand-lg site-navbar sticky-top" aria-label="Navegação principal">
  <div class="container site-navbar-inner">

    <a class="navbar-brand d-flex align-items-center" href="<?= $base ?>index.php" aria-label="ONGHelp — página inicial">
      <img src="<?= $base ?>assets/img/brand/logo-onghelp.png" alt="ONGHelp" class="navbar-brand-logo" width="127" height="38">
    </a>

    <button class="navbar-toggler site-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse site-navbar-collapse" id="navMenu">
      <ul class="navbar-nav site-nav-pill">
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='home' ? 'is-active' : '' ?>" href="<?= $base ?>index.php"<?= $active_nav==='home' ? ' aria-current="page"' : '' ?>>Home</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='ongs' ? 'is-active' : '' ?>" href="<?= $base ?>pages/ongs.php"<?= $active_nav==='ongs' ? ' aria-current="page"' : '' ?>>ONGs</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='transparencia' ? 'is-active' : '' ?>" href="<?= $base ?>pages/transparencia.php"<?= $active_nav==='transparencia' ? ' aria-current="page"' : '' ?>>Transparência</a>
        </li>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='noticias' ? 'is-active' : '' ?>" href="<?= $base ?>pages/noticias-publicas.php"<?= $active_nav==='noticias' ? ' aria-current="page"' : '' ?>>Notícias</a>
        </li>
        <?php if ($logado): ?>
        <li class="nav-item">
          <a class="site-nav-link <?= $active_nav==='gestao' ? 'is-active' : '' ?>" href="<?= $base ?>pages/gestao.php"<?= $active_nav==='gestao' ? ' aria-current="page"' : '' ?>>Gestão</a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="site-nav-actions">
        <?php if ($logado): ?>
          <div class="dropdown site-nav-account">
            <button class="site-nav-avatar" type="button" id="menuConta"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true"
                    aria-label="Abrir menu da conta de <?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?>">
              <img class="site-nav-avatar-foto"
                   src="<?= htmlspecialchars(fotoPerfilUrl($_SESSION['user_foto'] ?? null, $base, $_SESSION['user_area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                   alt="" aria-hidden="true">
              <i class="bi bi-chevron-down site-nav-avatar-caret" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end site-nav-dropdown" aria-labelledby="menuConta">
              <li><h6 class="dropdown-header"><?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?></h6></li>
              <li>
                <a class="dropdown-item" href="<?= $base ?>pages/perfilprivado.php">
                  <i class="bi bi-person-circle me-2"></i>Meu Perfil
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="<?= $base ?>pages/noticias.php">
                  <i class="bi bi-newspaper me-2"></i>Minhas notícias
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalSair" aria-controls="modalSair">
                  <i class="bi bi-box-arrow-right me-2"></i>Sair
                </button>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?= $base ?>pages/login.php" class="btn-47 btn-47-colors">Entrar</a>
          <a href="<?= $base ?>pages/cadastro.php" class="btn-47 btn-47-colors">Cadastrar</a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</nav>

<!-- Modal confirmar saída -->
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
        <a href="<?= $base ?>pages/logout.php" class="btn btn-primary rounded-pill">
          <i class="bi bi-box-arrow-right me-1"></i>Sair
        </a>
      </div>
    </div>
  </div>
</div>
