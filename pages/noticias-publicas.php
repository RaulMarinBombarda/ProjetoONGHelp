<?php
// ============================================================
// pages/noticias-publicas.php — Notícias das ONGs
// ============================================================
//
// Destaque (notícia mais recente + manchetes), filtros por texto,
// tipo e causa, e grade de cards com paginação. Toda consulta usa
// a regra central de visibilidade de includes/noticias.php.

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/noticias.php';
require_once '../includes/noticias_ui.php';

$logado = isset($_SESSION['user_id']);

// ── Filtros ─────────────────────────────────────────────────
$filtroBusca = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 120, 'UTF-8');

$tiposNoticia = noticiasTipos();
$filtroTipo = trim((string) ($_GET['tipo'] ?? ''));
$filtroTipo = isset($tiposNoticia[$filtroTipo]) ? $filtroTipo : '';

$coresCausa = coresArea();
$filtroArea = trim((string) ($_GET['area'] ?? ''));
$filtroArea = isset($coresCausa[$filtroArea]) ? $filtroArea : '';

$filtroOng = isset($_GET['ong']) && ctype_digit((string) $_GET['ong']) ? (int) $_GET['ong'] : 0;
$ongFiltroNome = '';

if ($filtroOng > 0) {
    $stmtOngFiltro = $pdo->prepare('SELECT nome FROM usuarios WHERE id = ? LIMIT 1');
    $stmtOngFiltro->execute([$filtroOng]);
    $ongFiltroNome = (string) ($stmtOngFiltro->fetchColumn() ?: '');
    $filtroOng = $ongFiltroNome !== '' ? $filtroOng : 0;
}

$temFiltro = $filtroBusca !== '' || $filtroTipo !== '' || $filtroArea !== '' || $filtroOng > 0;

/** URL da página preservando os filtros atuais. */
function urlNoticiasPublicas(array $override = []): string
{
    $params = array_merge([
        'busca' => $_GET['busca'] ?? '',
        'tipo' => $_GET['tipo'] ?? '',
        'area' => $_GET['area'] ?? '',
        'ong' => $_GET['ong'] ?? '',
        'pagina' => $_GET['pagina'] ?? '',
    ], $override);

    if ((int) $params['pagina'] <= 1) {
        $params['pagina'] = '';
    }

    $params = array_filter(
        $params,
        static fn ($valor): bool => $valor !== '' && $valor !== null && $valor !== '0' && $valor !== 0
    );

    return 'noticias-publicas.php' . ($params !== [] ? '?' . http_build_query($params) : '') . '#lista';
}

// ── Consulta base ───────────────────────────────────────────
$where = noticiasSqlVisivel('n', 'u');
$params = [];

if ($filtroBusca !== '') {
    $where .= ' AND (n.titulo LIKE ? OR n.resumo LIKE ? OR u.nome LIKE ?)';
    array_push($params, "%$filtroBusca%", "%$filtroBusca%", "%$filtroBusca%");
}

if ($filtroTipo !== '') {
    $where .= ' AND n.tipo = ?';
    $params[] = $filtroTipo;
}

if ($filtroArea !== '') {
    $where .= ' AND u.area = ?';
    $params[] = $filtroArea;
}

if ($filtroOng > 0) {
    $where .= ' AND n.usuario_id = ?';
    $params[] = $filtroOng;
}

$stmtTotal = $pdo->prepare(
    "SELECT COUNT(*) FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id WHERE $where"
);
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

// ── Paginação ───────────────────────────────────────────────
// Sem filtros, a primeira página abre com um destaque (1 notícia
// grande + 3 manchetes) e a grade continua a partir da 5ª.
$porPagina = 9;
$noDestaque = $temFiltro ? 0 : min(4, $total);
$totalPaginas = max(1, (int) ceil(max(0, $total - $noDestaque) / $porPagina));
$pagina = isset($_GET['pagina']) && ctype_digit((string) $_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$pagina = min(max(1, $pagina), $totalPaginas);
$mostrarDestaque = $noDestaque > 0 && $pagina === 1;
$offset = $noDestaque + ($pagina - 1) * $porPagina;

$colunas = "n.id, n.titulo, n.resumo, n.tipo, n.slug, n.publicada_em, n.criado_em,
            u.id AS ong_id, u.nome AS ong_nome, u.area AS ong_area, u.cidade AS ong_cidade,
            u.status AS ong_status, u.foto_perfil AS ong_foto_perfil,
            u.verificada_em AS ong_verificada_em";
$ordem = 'ORDER BY COALESCE(n.publicada_em, n.criado_em) DESC, n.id DESC';

$destaques = [];

if ($mostrarDestaque) {
    $stmtDestaque = $pdo->prepare(
        "SELECT $colunas FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE $where $ordem LIMIT $noDestaque"
    );
    $stmtDestaque->execute($params);
    $destaques = $stmtDestaque->fetchAll();
}

$stmtLista = $pdo->prepare(
    "SELECT $colunas FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
     WHERE $where $ordem LIMIT $porPagina OFFSET $offset"
);
$stmtLista->execute($params);
$noticias = $stmtLista->fetchAll();

$fotosPorNoticia = noticiasFotosPorNoticia(
    $pdo,
    array_merge(array_column($destaques, 'id'), array_column($noticias, 'id')),
    '../'
);

// ── Causas com notícias visíveis (chips) ────────────────────
$areasNoticias = $pdo->query(
    "SELECT DISTINCT u.area
     FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
     WHERE " . noticiasSqlVisivel('n', 'u') . " AND u.area IS NOT NULL AND u.area <> ''
     ORDER BY u.area"
)->fetchAll(PDO::FETCH_COLUMN);

if ($filtroArea !== '' && !in_array($filtroArea, $areasNoticias, true)) {
    $areasNoticias[] = $filtroArea;
    sort($areasNoticias);
}

$totalGeral = $temFiltro
    ? (int) $pdo->query(
        "SELECT COUNT(*) FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE " . noticiasSqlVisivel('n', 'u')
    )->fetchColumn()
    : $total;

// ── Números do cabeçalho e contagem por tipo ────────────────
$visivel = noticiasSqlVisivel('n', 'u');
$numeros = $pdo->query(
    "SELECT COUNT(DISTINCT n.usuario_id) AS ongs,
            SUM(COALESCE(n.publicada_em, n.criado_em) >= NOW() - INTERVAL 30 DAY) AS mes
     FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id WHERE $visivel"
)->fetch();
$porTipo = $pdo->query(
    "SELECT n.tipo, COUNT(*) FROM noticias n INNER JOIN usuarios u ON u.id = n.usuario_id
     WHERE $visivel GROUP BY n.tipo"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = 'Notícias';
$page_description = 'Ações, projetos, eventos e prestações de contas publicados pelas ONGs do ONGHelp.';
$active_nav = 'noticias';
include '../includes/header.php';
?>

<!-- ======= CABEÇALHO ======= -->
<?php
// Linha de "edição" do cabeçalho, no estilo de jornal.
$diasSemana = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$mesesAno = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$edicao = ucfirst($diasSemana[(int) date('w')]) . ', ' . date('j') . ' de ' . $mesesAno[(int) date('n')] . ' de ' . date('Y');
$iconesTipo = [
    'acao_realizada' => 'bi-people', 'projeto' => 'bi-diagram-3', 'campanha' => 'bi-megaphone',
    'evento' => 'bi-calendar-event', 'agradecimento' => 'bi-heart', 'prestacao_contas' => 'bi-receipt',
];
?>

<!-- ======= CABEÇALHO EDITORIAL ======= -->
<section class="nt-masthead">
  <div class="container">
    <div class="nt-masthead-topo">
      <span class="nt-edicao"><i class="bi bi-newspaper" aria-hidden="true"></i> <?= noticiaEsc($edicao) ?></span>
      <span class="nt-masthead-numeros">
        <strong><?= $totalGeral ?></strong> notícias ·
        <strong><?= (int) $numeros['ongs'] ?></strong> ONGs ·
        <strong><?= (int) $numeros['mes'] ?></strong> no último mês
      </span>
    </div>

    <div class="nt-masthead-linha">
      <h1 class="nt-masthead-titulo">O que as ONGs <span>estão fazendo</span></h1>
      <div class="nt-masthead-lado">
        <p>Ações, projetos, eventos e prestações de contas contados pelas próprias organizações.</p>
        <?php if ($logado): ?>
          <a class="nt-btn-vidro" href="noticia-editar.php">
            <i class="bi bi-pencil-square" aria-hidden="true"></i> Publicar notícia
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($porTipo !== []): ?>
      <nav class="nt-trilho" aria-label="Filtrar por tipo de notícia">
        <a href="<?= noticiaEsc(urlNoticiasPublicas(['tipo' => '', 'pagina' => ''])) ?>"
           class="<?= $filtroTipo === '' ? 'is-ativo' : '' ?>"<?= $filtroTipo === '' ? ' aria-current="true"' : '' ?>>
          <i class="bi bi-grid" aria-hidden="true"></i> Tudo <span><?= $totalGeral ?></span>
        </a>
        <?php foreach ($tiposNoticia as $tipo => $rotulo):
          if (empty($porTipo[$tipo])) continue;
          $ativo = $filtroTipo === $tipo;
        ?>
          <a href="<?= noticiaEsc(urlNoticiasPublicas(['tipo' => $ativo ? '' : $tipo, 'pagina' => ''])) ?>"
             class="<?= $ativo ? 'is-ativo' : '' ?>"<?= $ativo ? ' aria-current="true"' : '' ?>>
            <i class="bi <?= $iconesTipo[$tipo] ?? 'bi-dot' ?>" aria-hidden="true"></i>
            <?= noticiaEsc($rotulo) ?> <span><?= (int) $porTipo[$tipo] ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>

<section id="lista" class="section page-main ongs-page nt-page">
  <div class="container">

  <?php if ($totalGeral === 0): ?>

    <div class="empty-state nt-vazio" role="status">
      <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-newspaper"></i></span>
      <h2 class="h5">As notícias das ONGs aparecem aqui</h2>
      <p>Quando uma ONG aprovada publicar uma notícia, ela aparece nesta página.</p>
      <a class="btn btn-primary" href="<?= $logado ? 'noticia-editar.php' : 'cadastro.php' ?>">
        <?= $logado ? 'Publicar notícia' : 'Cadastrar minha ONG' ?>
      </a>
    </div>

  <?php else: ?>

    <!-- ── DESTAQUE: foto inteira com legenda de vidro ─────── -->
    <?php if ($mostrarDestaque && $destaques !== []):
      $principal = $destaques[0];
      $fotosPrincipal = $fotosPorNoticia[(int) $principal['id']] ?? [];
      $manchetes = array_slice($destaques, 1);
    ?>
      <div class="nt-destaque<?= $manchetes === [] ? ' nt-destaque--solo' : '' ?>">
        <?= noticiaCard($principal, $fotosPrincipal[0] ?? null, count($fotosPrincipal), 'destaque') ?>

        <?php if ($manchetes !== []): ?>
          <aside class="nt-manchetes" aria-labelledby="ntManchetesTitulo">
            <h2 class="nt-manchetes-titulo" id="ntManchetesTitulo">
              <i class="bi bi-lightning-charge" aria-hidden="true"></i> Também recentes
            </h2>
            <?php foreach ($manchetes as $noticia): ?>
              <?= noticiaCard($noticia, ($fotosPorNoticia[(int) $noticia['id']] ?? [])[0] ?? null, 0, 'linha') ?>
            <?php endforeach; ?>
          </aside>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ── BUSCA + CAUSAS ─────────────────────────────────── -->
    <div class="nt-busca-faixa">
      <form method="get" action="noticias-publicas.php#lista" class="nt-busca" role="search" aria-label="Buscar notícias">
        <input type="hidden" name="area" value="<?= noticiaEsc($filtroArea) ?>">
        <input type="hidden" name="tipo" value="<?= noticiaEsc($filtroTipo) ?>">
        <?php if ($filtroOng > 0): ?>
          <input type="hidden" name="ong" value="<?= $filtroOng ?>">
        <?php endif; ?>
        <label for="fBuscaNoticias" class="visually-hidden">Buscar notícia</label>
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="fBuscaNoticias" name="busca"
               placeholder="Buscar por título ou ONG…"
               value="<?= noticiaEsc($filtroBusca) ?>" data-auto-submit>
      </form>

      <?php if ($areasNoticias !== []): ?>
        <nav class="nt-causas" aria-label="Filtrar por causa">
          <a href="<?= noticiaEsc(urlNoticiasPublicas(['area' => '', 'pagina' => ''])) ?>"
             class="<?= $filtroArea === '' ? 'is-ativo' : '' ?>">Todas as causas</a>
          <?php foreach ($areasNoticias as $area):
            $ativa = $filtroArea === $area;
          ?>
            <a href="<?= noticiaEsc(urlNoticiasPublicas(['area' => $ativa ? '' : $area, 'pagina' => ''])) ?>"
               class="<?= $ativa ? 'is-ativo' : '' ?>"
               style="--causa-cor:<?= noticiaEsc($coresCausa[$area][0] ?? '#5A6A80') ?>;">
              <span class="cause-dot" aria-hidden="true"></span>
              <?= noticiaEsc($area) ?>
            </a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>

    <?php if ($filtroOng > 0): ?>
      <p class="nt-filtro-ong">
        Mostrando notícias de <strong><?= noticiaEsc($ongFiltroNome) ?></strong>
        <a href="<?= noticiaEsc(urlNoticiasPublicas(['ong' => '', 'pagina' => ''])) ?>">
          <i class="bi bi-x-lg" aria-hidden="true"></i> Ver todas
        </a>
      </p>
    <?php endif; ?>

    <!-- ── GRADE ──────────────────────────────────────────── -->
    <?php if ($noticias === [] && !$mostrarDestaque): ?>
      <div class="empty-state nt-vazio" role="status">
        <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
        <h2 class="h5">Nenhuma notícia encontrada</h2>
        <p>Tente outra busca, outro tipo ou remova os filtros.</p>
        <a class="btn btn-outline-secondary" href="noticias-publicas.php#lista">Limpar filtros</a>
      </div>
    <?php elseif ($noticias !== []): ?>
      <div class="nt-secao-head">
        <h2 class="nt-secao-titulo"><?= $mostrarDestaque ? 'Mais notícias' : 'Notícias encontradas' ?></h2>
        <span class="nt-secao-conta"><?= $total ?> <?= $total === 1 ? 'notícia' : 'notícias' ?></span>
      </div>

      <?= noticiasGrade($noticias, $fotosPorNoticia) ?>

      <?php if ($totalPaginas > 1): ?>
        <nav class="ong-paginacao nt-paginacao" aria-label="Paginação">
          <a href="<?= noticiaEsc(urlNoticiasPublicas(['pagina' => $pagina - 1])) ?>"
             class="<?= $pagina === 1 ? 'desabilitado' : '' ?>" aria-label="Página anterior">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
          </a>
          <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
            <a href="<?= noticiaEsc(urlNoticiasPublicas(['pagina' => $p])) ?>"
               class="<?= $p === $pagina ? 'ativo' : '' ?>"
               <?= $p === $pagina ? 'aria-current="page"' : '' ?>><?= $p ?></a>
          <?php endfor; ?>
          <a href="<?= noticiaEsc(urlNoticiasPublicas(['pagina' => $pagina + 1])) ?>"
             class="<?= $pagina === $totalPaginas ? 'desabilitado' : '' ?>" aria-label="Próxima página">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
          </a>
        </nav>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ── CONVITE ────────────────────────────────────────── -->
    <aside class="nt-convite">
      <span class="nt-convite-icone" aria-hidden="true"><i class="bi bi-megaphone"></i></span>
      <div>
        <h2>Sua ONG tem algo para contar?</h2>
        <p>Publique ações, projetos e prestações de contas para quem acompanha a sua causa.</p>
      </div>
      <a href="<?= $logado ? 'noticia-editar.php' : 'cadastro.php' ?>" class="nt-btn-vidro is-escuro">
        <?= $logado ? 'Publicar notícia' : 'Cadastrar minha ONG' ?> <span aria-hidden="true">→</span>
      </a>
    </aside>

  <?php endif; ?>

  </div>
</section>

<?php include '../includes/footer.php'; ?>
