<?php
// ============================================================
// includes/admin_header.php — Layout do painel da Equipe ONGHelp
// ============================================================

$admin_page_title    = $admin_page_title ?? 'Painel';
$admin_page_subtitle = $admin_page_subtitle ?? 'Painel restrito da Equipe ONGHelp';
$admin_active        = $admin_active ?? '';
$admin_page_actions  = $admin_page_actions ?? '';

if (!isset($admin) || !is_array($admin) || empty($admin['id'])) {
    $admin = [
        'id'    => (int) ($_SESSION['admin_id'] ?? 0),
        'nome'  => $_SESSION['admin_nome'] ?? 'Equipe ONGHelp',
        'email' => '',
        'papel' => $_SESSION['admin_papel'] ?? 'moderador',
        'estado' => 'ativo',
    ];
}

$admin_nome      = (string) ($admin['nome'] ?? 'Equipe ONGHelp');
$admin_papel     = (string) ($admin['papel'] ?? 'moderador');
$admin_papelLabel = adminPapelLabel($admin_papel);
$admin_iniciais  = adminIniciais($admin_nome);
$admin_podeEquipe = adminPode($admin, 'equipe.gerenciar');

// Selo de denúncias pendentes no menu. Falhas de consulta não devem
// impedir o restante do painel de abrir.
$admin_denunciasPendentes = 0;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $admin_denunciasPendentes = (int) $pdo
            ->query("SELECT COUNT(*) FROM noticias_denuncias WHERE estado = 'pendente'")
            ->fetchColumn();
    } catch (Throwable $erro) {
        error_log('[ONGHelp] Contagem de denúncias pendentes: ' . $erro->getMessage());
    }
}

$admin_nav = [
    ['id' => 'dashboard', 'rotulo' => 'Visão geral',   'icone' => 'bi-grid-1x2',        'url' => 'index.php',                    'grupo' => 'verificacao'],
    ['id' => 'pendentes', 'rotulo' => 'Pendentes',     'icone' => 'bi-hourglass-split', 'url' => 'index.php?status=pendente',    'grupo' => 'verificacao'],
    ['id' => 'aprovadas', 'rotulo' => 'Aprovadas',     'icone' => 'bi-patch-check',     'url' => 'index.php?status=aprovada',    'grupo' => 'verificacao'],
    ['id' => 'recusadas', 'rotulo' => 'Recusadas',     'icone' => 'bi-x-octagon',       'url' => 'index.php?status=recusada',    'grupo' => 'verificacao'],
    ['id' => 'suspensas', 'rotulo' => 'Suspensas',     'icone' => 'bi-pause-circle',    'url' => 'index.php?status=suspensa',    'grupo' => 'verificacao'],
    ['id' => 'noticias',  'rotulo' => 'Notícias',      'icone' => 'bi-newspaper',       'url' => 'noticias.php',                 'grupo' => 'verificacao', 'badge' => $admin_denunciasPendentes],
    ['id' => 'logs',      'rotulo' => 'Logs',          'icone' => 'bi-journal-text',    'url' => 'logs.php',                     'grupo' => 'administracao'],
];

// A gestão da equipe é a única área exclusiva do administrador e fica
// depois de Logs, separada por um filete fino.
if ($admin_podeEquipe) {
    $admin_nav[] = [
        'id' => 'equipe',
        'rotulo' => 'Equipe',
        'icone' => 'bi-people',
        'url' => 'equipe.php',
        'grupo' => 'administracao',
        'separador' => true,
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($admin_page_title) ?> | Equipe ONGHelp</title>
  <meta name="robots" content="noindex, nofollow">

  <!-- Favicon -->
  <link rel="icon" href="../assets/img/brand/favicon.ico?v=2" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/brand/favicon-32x32.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/brand/apple-touch-icon.png?v=2">
  <meta name="theme-color" content="#1d4052">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css?v=72">
</head>
<body class="admin-body">

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<div class="admin-shell">

  <!-- Sidebar desktop -->
  <aside class="admin-sidebar glass-dark d-none d-lg-flex">
    <a class="admin-brand" href="index.php">
      <img src="../assets/img/brand/icone-onghelp.png" alt="" class="brand-icon brand-icon-lg" width="40" height="40">
      <span>
        <strong>ONGHelp</strong>
        <small>Equipe de verificação</small>
      </span>
    </a>

    <nav class="admin-nav" aria-label="Navegação do painel">
      <span class="admin-nav-label">Verificações</span>
      <?php foreach ($admin_nav as $item): ?>
        <?php if ($item['grupo'] !== 'verificacao') continue; ?>
        <a href="<?= $item['url'] ?>" class="admin-nav-link <?= $admin_active === $item['id'] ? 'active' : '' ?>"<?= $admin_active === $item['id'] ? ' aria-current="page"' : '' ?>>
          <i class="bi <?= $item['icone'] ?>" aria-hidden="true"></i>
          <?= $item['rotulo'] ?>
          <?php if (($item['badge'] ?? 0) > 0): ?>
            <span class="admin-nav-badge" aria-label="<?= (int) $item['badge'] ?> denúncia(s) pendente(s)"><?= (int) $item['badge'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <span class="admin-nav-label">Administração</span>
      <?php foreach ($admin_nav as $item): ?>
        <?php if ($item['grupo'] !== 'administracao') continue; ?>
        <?php if (!empty($item['separador'])): ?><span class="admin-nav-separador" aria-hidden="true"></span><?php endif; ?>
        <a href="<?= $item['url'] ?>" class="admin-nav-link <?= $admin_active === $item['id'] ? 'active' : '' ?>"<?= $admin_active === $item['id'] ? ' aria-current="page"' : '' ?>>
          <i class="bi <?= $item['icone'] ?>" aria-hidden="true"></i>
          <?= $item['rotulo'] ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar-footer">
      <a href="../index.php" class="admin-nav-link">
        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver o site
      </a>
      <button type="submit" form="formSairAdmin" class="admin-nav-link admin-nav-button">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair
      </button>
    </div>
  </aside>

  <!-- Offcanvas mobile -->
  <div class="offcanvas offcanvas-end admin-offcanvas glass-dark" tabindex="-1" id="adminMenu" aria-labelledby="adminMenuLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title admin-brand mb-0" id="adminMenuLabel">
        <img src="../assets/img/brand/icone-onghelp.png" alt="" width="32" height="32">
        <span>
          <strong>ONGHelp</strong>
          <small>Equipe de verificação</small>
        </span>
      </h5>
      <button type="button" class="btn-close btn-close-frost" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
      <nav class="admin-nav" aria-label="Navegação do painel">
        <span class="admin-nav-label">Verificações</span>
        <?php foreach ($admin_nav as $item): ?>
          <?php if ($item['grupo'] !== 'verificacao') continue; ?>
          <a href="<?= $item['url'] ?>" class="admin-nav-link <?= $admin_active === $item['id'] ? 'active' : '' ?>"<?= $admin_active === $item['id'] ? ' aria-current="page"' : '' ?>>
            <i class="bi <?= $item['icone'] ?>" aria-hidden="true"></i>
            <?= $item['rotulo'] ?>
            <?php if (($item['badge'] ?? 0) > 0): ?>
              <span class="admin-nav-badge" aria-label="<?= (int) $item['badge'] ?> denúncia(s) pendente(s)"><?= (int) $item['badge'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>

        <span class="admin-nav-label">Administração</span>
        <?php foreach ($admin_nav as $item): ?>
          <?php if ($item['grupo'] !== 'administracao') continue; ?>
          <?php if (!empty($item['separador'])): ?><span class="admin-nav-separador" aria-hidden="true"></span><?php endif; ?>
          <a href="<?= $item['url'] ?>" class="admin-nav-link <?= $admin_active === $item['id'] ? 'active' : '' ?>"<?= $admin_active === $item['id'] ? ' aria-current="page"' : '' ?>>
            <i class="bi <?= $item['icone'] ?>" aria-hidden="true"></i>
            <?= $item['rotulo'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-sidebar-footer">
        <a href="../index.php" class="admin-nav-link">
          <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver o site
        </a>
        <button type="submit" form="formSairAdmin" class="admin-nav-link admin-nav-button">
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair
        </button>
      </div>
    </div>
  </div>

  <!-- Conteúdo -->
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="btn-icon admin-menu-toggle d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="Abrir menu do painel">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div>
          <h1 class="admin-page-title"><?= htmlspecialchars($admin_page_title) ?></h1>
          <p class="admin-page-subtitle mb-0"><?= htmlspecialchars($admin_page_subtitle) ?></p>
        </div>
      </div>

      <div class="admin-topbar-right">
        <?php if ($admin_page_actions !== ''): ?>
          <div class="admin-topbar-actions"><?= $admin_page_actions ?></div>
        <?php endif; ?>

        <div class="dropdown admin-user-menu">
          <button class="admin-user-pill" type="button" id="menuAdmin" data-bs-toggle="dropdown"
                  aria-expanded="false" aria-haspopup="true" aria-label="Abrir menu da conta de <?= htmlspecialchars($admin_nome) ?>">
            <span class="admin-avatar" aria-hidden="true"><?= htmlspecialchars($admin_iniciais) ?></span>
            <span class="admin-user-pill-text">
              <strong><?= htmlspecialchars($admin_nome) ?></strong>
              <small><?= htmlspecialchars($admin_papelLabel) ?></small>
            </span>
            <i class="bi bi-chevron-down admin-user-caret" aria-hidden="true"></i>
          </button>

          <ul class="dropdown-menu dropdown-menu-end admin-user-dropdown" aria-labelledby="menuAdmin">
            <li class="admin-user-dropdown-head">
              <strong><?= htmlspecialchars($admin_nome) ?></strong>
              <?php if (!empty($admin['email'])): ?>
                <span><?= htmlspecialchars($admin['email']) ?></span>
              <?php endif; ?>
              <em><?= htmlspecialchars($admin_papelLabel) ?></em>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="minha_conta.php">
                <i class="bi bi-person-gear" aria-hidden="true"></i> Minha conta
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <button type="submit" form="formSairAdmin" class="dropdown-item">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair
              </button>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="admin-content">
      <?php if ($msg = flashGet('admin_sucesso')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle" aria-hidden="true"></i> <?= htmlspecialchars($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
      <?php endif; ?>

      <?php if ($msg = flashGet('admin_erro')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
      <?php endif; ?>

      <?php if ($msg = flashGet('admin_aviso')): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <i class="bi bi-info-circle" aria-hidden="true"></i> <?= htmlspecialchars($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
      <?php endif; ?>
