<?php
// ============================================================
// pages/ongs.php — Listagem pública de ONGs cadastradas
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// PARÂMETROS GET:
//   ?busca=termo       → busca por nome
//   ?cidade=Taquaritinga → filtra pela cidade (dropdown)
//   ?area=Saúde        → filtra pela área de atuação (chips "Explorar por causa")
//   ?uf=SP             → filtra pelo estado
//   ?ordem=nome|recentes|arrecadacao → ordenação da lista
//   ?pagina=N          → paginação (9 ONGs por página)
//
// Todos os filtros são combináveis e preservados entre as
// páginas da paginação.
//
// DIFERENCIAL EM RELAÇÃO AO PORTAL DE TRANSPARÊNCIA:
//   - Foco no perfil institucional (identidade, missão e
//     impacto), não apenas em números financeiros.
//   - Seção "ONG em destaque" com a organização mais
//     arrecadada entre as filtradas.
//   - Botão "Ver perfil" que leva ao perfil público completo
//     da organização (pages/perfilpublico.php).
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';

// ── Sanitização dos filtros ─────────────────────────────────
$filtroBusca   = trim($_GET['busca']   ?? '');
$filtroCidade  = trim($_GET['cidade']  ?? '');
$filtroArea    = trim($_GET['area']    ?? '');
$filtroUf      = trim($_GET['uf']      ?? '');
$ordem         = $_GET['ordem'] ?? 'nome';
if (!in_array($ordem, ['nome', 'recentes', 'arrecadacao'], true)) {
    $ordem = 'nome';
}

// ── Paginação ───────────────────────────────────────────────
$porPagina   = 9;
$pagina      = isset($_GET['pagina']) && ctype_digit($_GET['pagina'])
    ? max(1, (int) $_GET['pagina'])
    : 1;

// ── Monta WHERE dinâmico (reutilizado na contagem e na listagem) ──
$where  = "u.status = 'aprovada'";
$params = [];

if ($filtroBusca !== '') {
    $where   .= " AND u.nome LIKE ?";
    $params[] = "%$filtroBusca%";
}
if ($filtroCidade !== '') {
    $where   .= " AND u.cidade = ?";
    $params[] = $filtroCidade;
}
if ($filtroArea !== '') {
    $where   .= " AND u.area = ?";
    $params[] = $filtroArea;
}
if ($filtroUf !== '') {
    $where   .= " AND u.estado = ?";
    $params[] = $filtroUf;
}

// ── Ordenação ───────────────────────────────────────────────
switch ($ordem) {
    case 'recentes':
        $orderBy = "u.criado_em DESC, u.nome ASC";
        break;
    case 'arrecadacao':
        $orderBy = "(SELECT COALESCE(SUM(m.valor), 0) FROM movimentacoes m
                     WHERE m.usuario_id = u.id AND m.tipo = 'receita') DESC, u.nome ASC";
        break;
    default:
        $orderBy = "u.nome ASC";
}

// ── Contagem total (para a paginação) ────────────────────────
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM usuarios u WHERE $where");
$stmtCount->execute($params);
$totalOngs    = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalOngs / $porPagina));

$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

// ── Listagem com dados agregados (projetos + arrecadação) ────
$sql = "
    SELECT u.id, u.nome, u.area, u.cidade, u.estado, u.descricao, u.pix_chave, u.cor_tema, u.cor_fundo, u.status, u.verificada_em, u.foto_perfil,
           (SELECT COALESCE(SUM(m.valor), 0) FROM movimentacoes m
             WHERE m.usuario_id = u.id AND m.tipo = 'receita') AS arrecadado,
           (SELECT COUNT(*) FROM projetos p WHERE p.usuario_id = u.id) AS projetos
    FROM   usuarios u
    WHERE  $where
    ORDER  BY $orderBy
    LIMIT  $porPagina OFFSET $offset
";
$stmtOngs = $pdo->prepare($sql);
$stmtOngs->execute($params);
$ongs = $stmtOngs->fetchAll();
$idsNaPagina = array_map('intval', array_column($ongs, 'id'));

// ── ONG em destaque ───────────────────────────────────────────
// A organização mais arrecadada entre TODAS as ONGs que passam
// pelos filtros.
//
// Precisa ser uma consulta separada: $ongs já vem truncado pelo
// LIMIT da paginação, e escolher o destaque dentro desse recorte
// fazia a organização destacada trocar conforme o usuário virava
// a página — o oposto do que a seção promete. Reaproveita o mesmo
// $where e os mesmos $params da listagem, então o destaque respeita
// exatamente os mesmos filtros.
$destaqueStmt = $pdo->prepare("
    SELECT u.id, u.nome, u.area, u.cidade, u.estado, u.descricao,
           u.pix_chave, u.cor_tema, u.cor_fundo, u.status, u.verificada_em, u.foto_perfil,
           (SELECT COALESCE(SUM(m.valor), 0) FROM movimentacoes m
             WHERE m.usuario_id = u.id AND m.tipo = 'receita') AS arrecadado,
           (SELECT COUNT(*) FROM projetos p WHERE p.usuario_id = u.id) AS projetos
    FROM   usuarios u
    WHERE  $where
    ORDER  BY arrecadado DESC, u.nome ASC
    LIMIT  1
");
$destaqueStmt->execute($params);
$destaque = $destaqueStmt->fetch() ?: null;

// ── Estatísticas globais (hero) ──────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(DISTINCT u.id)                                             AS total_ongs,
        COALESCE(SUM(CASE WHEN m.tipo = 'receita' THEN m.valor END), 0)  AS total_arrecadado,
        COUNT(DISTINCT u.estado)                                         AS total_estados,
        COUNT(DISTINCT u.area)                                           AS total_areas
    FROM   usuarios u
    LEFT   JOIN movimentacoes m ON m.usuario_id = u.id
    WHERE  u.status = 'aprovada'
")->fetch();

// ── Opções dos filtros (áreas, cidades e estados existentes no banco) ──
$todasAreas   = $pdo->query("SELECT DISTINCT area FROM usuarios WHERE status = 'aprovada' ORDER BY area ASC")->fetchAll();
$todasCidades = $pdo->query("SELECT DISTINCT cidade FROM usuarios WHERE status = 'aprovada' AND cidade IS NOT NULL AND cidade != '' ORDER BY cidade ASC")->fetchAll();
$todasUfs     = $pdo->query("SELECT DISTINCT estado FROM usuarios WHERE status = 'aprovada' AND estado IS NOT NULL AND estado != '' ORDER BY estado ASC")->fetchAll();

// ── Paleta por área (banner, avatar e selo dos cards) ────────
$coresArea = coresArea();

/**
 * Monta a URL da listagem preservando os filtros atuais,
 * substituindo apenas o parâmetro informado.
 */
function urlOngs(array $override): string
{
    $base = [
        'busca'   => $_GET['busca']   ?? '',
        'cidade'  => $_GET['cidade']  ?? '',
        'area'    => $_GET['area']    ?? '',
        'uf'      => $_GET['uf']      ?? '',
        'ordem'   => $_GET['ordem']   ?? 'nome',
        'pagina'  => $_GET['pagina']  ?? '',
    ];
    $params = array_merge($base, $override);
    $params = array_filter($params, fn($v) => $v !== '');
    return '?' . http_build_query($params) . '#lista';
}

$page_title = 'ONGs Cadastradas';
$active_nav = 'ongs';
include '../includes/header.php';
?>

<!-- ======= CABEÇALHO DA PÁGINA ======= -->
<section class="ongs-page-head og-head">
  <div class="container">
    <div class="og-head-grade">
      <div class="og-head-copy">
        <span class="page-eyebrow"><i class="bi bi-buildings" aria-hidden="true"></i> Diretório de ONGs</span>
        <h1 class="ongs-page-title">
          Rede de organizações <span class="og-sem-quebra">no Brasil</span>
        </h1>
        <p class="ongs-page-sub">
          Explore perfis públicos, projetos ativos e a transparência financeira de cada
          organização. Escolha uma causa e descubra quem está transformando comunidades.
        </p>

        <ul class="og-head-numeros" aria-label="Números do diretório">
          <li><strong><?= (int) $stats['total_ongs'] ?></strong> <?= (int) $stats['total_ongs'] === 1 ? 'organização' : 'organizações' ?></li>
          <li><strong><?= (int) $stats['total_areas'] ?></strong> <?= (int) $stats['total_areas'] === 1 ? 'causa' : 'causas' ?></li>
          <li><strong><?= (int) $stats['total_estados'] ?></strong> <?= (int) $stats['total_estados'] === 1 ? 'estado' : 'estados' ?></li>
          <li><strong><?= fmtDinheiro((float) $stats['total_arrecadado'], true) ?></strong> arrecadados</li>
        </ul>
      </div>

      <?php if ($destaque):
        [$corD] = temaOng($destaque, $coresArea);
        $descD = trim($destaque['descricao'] ?? '');
      ?>
        <a class="og-destaque" href="perfilpublico.php?id=<?= (int) $destaque['id'] ?>"
           style="--causa-cor:<?= htmlspecialchars($corD) ?>;">
          <span class="og-destaque-rotulo"><i class="bi bi-stars" aria-hidden="true"></i> Em destaque</span>
          <span class="og-destaque-topo">
            <span class="og-avatar">
              <img src="<?= htmlspecialchars(fotoPerfilUrl($destaque['foto_perfil'] ?? null, '../', $destaque['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true">
            </span>
            <span>
              <span class="og-nome"><?= htmlspecialchars($destaque['nome']) ?> <?= badgeVerificado($destaque, true) ?></span>
              <span class="og-local"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($destaque['cidade'] ?: 'Brasil') ?><?= $destaque['estado'] ? ' · ' . htmlspecialchars($destaque['estado']) : '' ?></span>
            </span>
          </span>
          <?php if ($descD !== ''): ?>
            <span class="og-desc"><?= htmlspecialchars($descD) ?></span>
          <?php endif; ?>
          <span class="og-destaque-base">
            <span><strong><?= fmtDinheiro((float) $destaque['arrecadado'], true) ?></strong> arrecadados</span>
            <span><strong><?= (int) $destaque['projetos'] ?></strong> projetos</span>
            <span class="og-seta" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
          </span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ======= LISTAGEM ======= -->
<section id="lista" class="section page-main ongs-page og-lista">
  <div class="container">

    <!-- ── BARRA DE FILTROS ─────────────────────────────────────
         GET puro, sem JavaScript: cada campo preserva os demais.
         action com #lista: após aplicar, a página rola de volta
         para a listagem em vez de voltar ao topo.
    ────────────────────────────────────────────────────────── -->
    <form method="get" action="ongs.php#lista" class="filtro-bar ongs-filter-bar" aria-label="Filtros de organizações">
      <div class="ongs-filter-fields">
        <div class="ongs-filter-field ongs-filter-search">
          <label for="fBusca" class="visually-hidden">Nome</label>
          <i class="bi bi-search ongs-filter-icon" aria-hidden="true"></i>
          <input type="search" id="fBusca" name="busca" placeholder="Nome da ONG…"
                 value="<?= htmlspecialchars($filtroBusca) ?>"
                 data-auto-submit>
        </div>

        <div class="ongs-filter-field ongs-filter-city">
          <label for="fCidade" class="visually-hidden">Cidade</label>
          <select id="fCidade" name="cidade" data-auto-submit>
            <option value="">Todas</option>
            <?php foreach ($todasCidades as $row): ?>
              <option value="<?= htmlspecialchars($row['cidade']) ?>"
                <?= $filtroCidade === $row['cidade'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['cidade']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (count($todasUfs) > 1): ?>
          <div class="ongs-filter-field ongs-filter-state">
            <label for="fUf" class="visually-hidden">Estado</label>
            <select id="fUf" name="uf" data-auto-submit>
              <option value="">Todos</option>
              <?php foreach ($todasUfs as $row): ?>
                <option value="<?= htmlspecialchars($row['estado']) ?>"
                  <?= $filtroUf === $row['estado'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($row['estado']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="ongs-filter-field ongs-filter-order">
          <label for="fOrdem" class="visually-hidden">Ordenar por</label>
          <select id="fOrdem" name="ordem" data-auto-submit>
            <option value="nome"        <?= $ordem === 'nome'        ? 'selected' : '' ?>>Nome (A–Z)</option>
            <option value="recentes"    <?= $ordem === 'recentes'    ? 'selected' : '' ?>>Cadastro mais recente</option>
            <option value="arrecadacao" <?= $ordem === 'arrecadacao' ? 'selected' : '' ?>>Mais arrecadadas</option>
          </select>
        </div>
      </div>

      <div class="ongs-filter-count" aria-live="polite">
        <strong><?= $totalOngs ?></strong> <?= $totalOngs === 1 ? 'organização' : 'organizações' ?>
      </div>
    </form>

    <!-- ── CHIPS DE CAUSAS ──────────────────────────────────────
         Atalho de filtro por categoria. "Todas" vem por padrão;
         clicar de novo na causa selecionada desmarca e volta
         para "Todas".
    ────────────────────────────────────────────────────────── -->
    <div class="ongs-causas">
      <span class="ongs-causas-titulo"><i class="bi bi-tags me-1"></i>Explorar por causa:</span>
      <a href="<?= htmlspecialchars(urlOngs(['area' => ''])) ?>"
         class="<?= $filtroArea === '' ? 'ativo' : '' ?>">Todas</a>
      <?php foreach ($todasAreas as $row):
        $ativo = ($filtroArea === $row['area']);
        $corCausa = $coresArea[$row['area']][0] ?? '#5A6A80';
        // Clicar na causa já selecionada desmarca (volta para "Todas")
        $href  = $ativo ? urlOngs(['area' => '']) : urlOngs(['area' => $row['area']]);
      ?>
        <a href="<?= htmlspecialchars($href) ?>"
           class="<?= $ativo ? 'ativo' : '' ?>">
          <span class="cause-dot" style="--causa-cor:<?= htmlspecialchars($corCausa) ?>;" aria-hidden="true"></span>
          <?= htmlspecialchars($row['area']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($ongs)): ?>

      <div class="ongs-index ongs-index-vazio">
        <div class="ongs-empty">
          <p class="ongs-empty-text">Nenhuma organização encontrada com esses filtros</p>
          <a href="ongs.php#lista" class="btn btn-outline-secondary rounded-pill px-4">
            Limpar filtros
          </a>
        </div>
      </div>

    <?php else: ?>

      <!-- ── GALERIA DE ORGANIZAÇÕES ─────────────────────────── -->
      <div class="og-grade">
        <?php foreach ($ongs as $ong):
          [$cor1] = temaOng($ong, $coresArea);
          $descricao = trim($ong['descricao'] ?? '');
          if ($descricao === '') {
              $descricao = 'Organização dedicada a ' . mb_strtolower($ong['area']) . '. Conheça os projetos e relatórios desta organização no Portal de Transparência.';
          }
        ?>
        <article class="og-card" style="--causa-cor:<?= htmlspecialchars($cor1) ?>;">
          <span class="og-card-aurora" aria-hidden="true"></span>
          <span class="og-causa"><span class="cause-dot" aria-hidden="true"></span> <?= htmlspecialchars($ong['area']) ?></span>

          <span class="og-avatar">
            <img src="<?= htmlspecialchars(fotoPerfilUrl($ong['foto_perfil'] ?? null, '../', $ong['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                 alt="" aria-hidden="true">
          </span>

          <h2 class="og-nome">
            <a class="og-card-link" href="perfilpublico.php?id=<?= (int) $ong['id'] ?>"><?= htmlspecialchars($ong['nome']) ?></a>
            <?= badgeVerificado($ong, true) ?>
          </h2>
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
            <a class="btn-portal" href="transparencia.php?ong=<?= (int) $ong['id'] ?>">
              <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Ver no portal
            </a>
          </span>
        </article>
        <?php endforeach; ?>
      </div>

      <!-- ── PAGINAÇÃO (preserva os filtros) ── -->
      <?php if ($totalPaginas > 1): ?>
      <nav class="ong-paginacao" aria-label="Paginação">
        <a href="<?= htmlspecialchars(urlOngs(['pagina' => $pagina - 1])) ?>"
           class="<?= $pagina === 1 ? 'desabilitado' : '' ?>" aria-label="Página anterior">
          <i class="bi bi-chevron-left"></i>
        </a>

        <?php
        // Janela de páginas: até 5 botões ao redor da atual
        $inicio = max(1, $pagina - 2);
        $fim    = min($totalPaginas, $pagina + 2);
        if ($inicio > 1) echo '<span class="desabilitado">…</span>';
        for ($p = $inicio; $p <= $fim; $p++):
        ?>
          <a href="<?= htmlspecialchars(urlOngs(['pagina' => $p])) ?>"
             class="<?= $p === $pagina ? 'ativo' : '' ?>"><?= $p ?></a>
        <?php endfor;
        if ($fim < $totalPaginas) echo '<span class="desabilitado">…</span>';
        ?>

        <a href="<?= htmlspecialchars(urlOngs(['pagina' => $pagina + 1])) ?>"
           class="<?= $pagina === $totalPaginas ? 'desabilitado' : '' ?>" aria-label="Próxima página">
          <i class="bi bi-chevron-right"></i>
        </a>
      </nav>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</section>

<?php
// Um modal de doação por ONG da página que tenha chave PIX.
foreach ($ongs as $ongModal):
    if (empty($ongModal['pix_chave'])) continue;
    $modalUid = (int) $ongModal['id'];
    $modalOng = [
        'id'        => (int) $ongModal['id'],
        'nome'      => $ongModal['nome'],
        'pix_chave' => $ongModal['pix_chave'],
        'cidade'    => $ongModal['cidade'] ?? '',
    ];
    include '../includes/modal_doacao.php';
endforeach;
?>

<!-- ======= FECHAMENTO ======= -->
<section class="og-convite-secao">
  <div class="container">
    <div class="og-convite">
      <span class="og-convite-pontos" aria-hidden="true">
        <?php foreach (array_slice($coresArea, 0, 7) as [$corPonto]): ?>
          <i style="--causa-cor:<?= htmlspecialchars($corPonto) ?>;"></i>
        <?php endforeach; ?>
      </span>
      <div>
        <h2>Sua ONG ainda não está aqui?</h2>
        <p>Cadastre-se gratuitamente, publique seus projetos e ganhe a confiança de novos doadores.</p>
      </div>
      <a href="cadastro.php" class="og-convite-btn">
        Cadastrar minha ONG <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </a>
    </div>
  </div>
</section>

<?php include '../includes/footer.php'; ?>