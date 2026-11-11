<?php
// ============================================================
// pages/transparencia.php — Portal de Transparência dinâmico
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// FUNCIONAMENTO GERAL:
//   1. Todos os filtros (ano, categoria, busca) chegam via $_GET
//      e são processados aqui em PHP.
//   2. O PHP aplica WHERE/LIKE na query conforme necessário e
//      monta a tabela comparável do portal.
//   3. Cada linha abre um DRAWER (à esquerda) com o relatório
//      completo da ONG. A ordenação das colunas é client-side,
//      feita por assets/js/transparencia.js.
//
// PARÂMETROS GET ESPERADOS:
//   ?ano=2025          → filtra movimentações pelo ano
//   ?categoria=Saúde   → filtra a tabela por área
//   ?busca=esperança   → filtra a tabela por nome (LIKE)
//   ?ong=42            → abre o drawer da ONG 42
//   ?modal=42          → compatibilidade com links antigos
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';

// ─────────────────────────────────────────────────────────────
// 1. SANITIZAÇÃO DOS PARÂMETROS GET
// ─────────────────────────────────────────────────────────────

// Período: a lista de anos vem do próprio banco, e o <select> é
// montado a partir dela. Filtro e formulário derivam da mesma
// fonte, então não podem divergir.
$anosDisponiveis = array_map('intval', array_column(
    $pdo->query("
        SELECT DISTINCT YEAR(data_mov) AS ano
        FROM   movimentacoes
        ORDER  BY ano DESC
    ")->fetchAll(),
    'ano'
));

if (empty($anosDisponiveis)) {
    $anosDisponiveis = [(int) date('Y')];
}

$anoPadrao = $anosDisponiveis[0];

$ano = (isset($_GET['ano']) && ctype_digit($_GET['ano']))
    ? (int) $_GET['ano']
    : $anoPadrao;

if (!in_array($ano, $anosDisponiveis, true)) {
    $ano = $anoPadrao;
}

$filtroCategoria = trim($_GET['categoria'] ?? '');
$filtroBusca     = trim($_GET['busca']     ?? '');
$filtroCidade    = trim($_GET['cidade']    ?? '');
$filtroUf        = trim($_GET['uf']        ?? '');
$ordem           = $_GET['ordem'] ?? 'nome';
if (!in_array($ordem, ['nome', 'arrecadacao', 'despesa', 'saldo', 'projetos'], true)) {
    $ordem = 'nome';
}

// Deep link: ?ong=ID é o nome novo; ?modal=ID continua aceito
// para não quebrar links já publicados em outras páginas.
$ongAberta = 0;
foreach (['ong', 'modal'] as $parametroOng) {
    if (isset($_GET[$parametroOng]) && ctype_digit($_GET[$parametroOng])) {
        $ongAberta = (int) $_GET[$parametroOng];
        break;
    }
}

// ─────────────────────────────────────────────────────────────
// 2. BUSCA DE ONGs (com filtros de categoria e busca aplicados)
// ─────────────────────────────────────────────────────────────

$sql    = "SELECT id, nome, area, cidade, estado, descricao, pix_chave, cor_tema, cor_fundo, status, verificada_em, foto_perfil
           FROM   usuarios
           WHERE  status = 'aprovada'";
$params = [];

if ($filtroCategoria !== '') {
    $sql      .= " AND area = ?";
    $params[]  = $filtroCategoria;
}

if ($filtroBusca !== '') {
    $sql      .= " AND nome LIKE ?";
    $params[]  = '%' . $filtroBusca . '%';
}

if ($filtroCidade !== '') {
    $sql      .= " AND cidade = ?";
    $params[]  = $filtroCidade;
}

if ($filtroUf !== '') {
    $sql      .= " AND estado = ?";
    $params[]  = $filtroUf;
}

$sql .= " ORDER BY nome ASC";

$stmtOngs = $pdo->prepare($sql);
$stmtOngs->execute($params);
$ongs = $stmtOngs->fetchAll();
$ids  = array_map('intval', array_column($ongs, 'id'));

// Se o ID do deep link não estiver entre as ONGs exibidas, ignora.
if (!in_array($ongAberta, $ids, true)) {
    $ongAberta = 0;
}

// Monta a URL do portal mantendo os filtros atuais; $override troca
// ou limpa parâmetros (valor '' remove). A página volta para 1 em
// qualquer troca de filtro.
function urlTransparencia(array $override = []): string
{
    $params = array_merge([
        'ano'       => $_GET['ano']       ?? '',
        'busca'     => $_GET['busca']     ?? '',
        'cidade'    => $_GET['cidade']    ?? '',
        'uf'        => $_GET['uf']        ?? '',
        'categoria' => $_GET['categoria'] ?? '',
        'ordem'     => $_GET['ordem']     ?? '',
        'pagina'    => '',
    ], $override);

    if ((int) $params['pagina'] <= 1) {
        $params['pagina'] = '';
    }
    if ($params['ordem'] === 'nome') {
        $params['ordem'] = '';
    }

    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return 'transparencia.php' . ($params ? '?' . http_build_query($params) : '') . '#lista';
}

// ─────────────────────────────────────────────────────────────
// 3. TOTAIS ANUAIS POR ONG (receita e despesa)
// ─────────────────────────────────────────────────────────────

$totais = [];
if (!empty($ids)) {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtTotais = $pdo->prepare("
        SELECT usuario_id, tipo, COALESCE(SUM(valor), 0) AS total
        FROM   movimentacoes
        WHERE  usuario_id IN ($marcadores)
          AND  YEAR(data_mov) = ?
        GROUP  BY usuario_id, tipo
    ");
    $stmtTotais->execute([...$ids, $ano]);

    foreach ($stmtTotais->fetchAll() as $row) {
        $uid  = $row['usuario_id'];
        $tipo = $row['tipo'];   // 'receita' ou 'despesa'

        if (!isset($totais[$uid])) {
            $totais[$uid] = ['receita' => 0.0, 'despesa' => 0.0];
        }
        $totais[$uid][$tipo] = (float) $row['total'];
    }
}

// ─────────────────────────────────────────────────────────────
// 4. TOTAIS MENSAIS (gráfico e balancete do drawer)
// ─────────────────────────────────────────────────────────────

$mensal = [];
if (!empty($ids)) {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtMensal = $pdo->prepare("
        SELECT usuario_id, tipo, MONTH(data_mov) AS mes, SUM(valor) AS total
        FROM   movimentacoes
        WHERE  usuario_id IN ($marcadores)
          AND  YEAR(data_mov) = ?
        GROUP  BY usuario_id, tipo, mes
        ORDER  BY mes ASC
    ");
    $stmtMensal->execute([...$ids, $ano]);

    foreach ($stmtMensal->fetchAll() as $row) {
        $uid  = $row['usuario_id'];
        $tipo = $row['tipo'];
        $mes  = (int) $row['mes'];
        $mensal[$uid][$tipo][$mes] = (float) $row['total'];
    }
}

// ─────────────────────────────────────────────────────────────
// 5. BATCH QUERIES — projetos e últimas movimentações
// ─────────────────────────────────────────────────────────────

$projPorOng = [];
$movPorOng  = [];
if (!empty($ids)) {
    $projStmt = $pdo->query("
        SELECT usuario_id, nome, indicador, unidade
        FROM   projetos
        WHERE  usuario_id IN (" . implode(',', $ids) . ")
        ORDER  BY usuario_id, nome ASC
    ");
    foreach ($projStmt->fetchAll() as $p) {
        $projPorOng[$p['usuario_id']][] = $p;
    }

    // Últimas 5 movimentações de cada ONG dentro do ano selecionado.
    $movStmt = $pdo->prepare("
        SELECT usuario_id, data_mov, descricao, tipo, valor
        FROM   movimentacoes
        WHERE  usuario_id IN (" . implode(',', $ids) . ")
          AND  YEAR(data_mov) = ?
        ORDER  BY usuario_id, data_mov DESC, id DESC
    ");
    $movStmt->execute([$ano]);
    foreach ($movStmt->fetchAll() as $m) {
        $uid = $m['usuario_id'];
        if (!isset($movPorOng[$uid])) $movPorOng[$uid] = [];
        if (count($movPorOng[$uid]) < 5) {
            $movPorOng[$uid][] = $m;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// 6. DADOS DERIVADOS POR ONG + TOTAIS DA TABELA
// ─────────────────────────────────────────────────────────────

$mesesNome = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
    5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
    9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
];
$mesesNomeCompleto = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

$coresArea = coresArea();
$dadosOng  = [];

$totalListadoReceita = 0.0;
$totalListadoDespesa = 0.0;

foreach ($ongs as $ong) {
    $id      = (int) $ong['id'];
    $receita = (float) ($totais[$id]['receita'] ?? 0.0);
    $despesa = (float) ($totais[$id]['despesa'] ?? 0.0);
    $saldo   = $receita - $despesa;

    [$cor1, $cor2] = temaOng($ong, $coresArea);

    $balancete  = balanceteDoAno($mensal[$id] ?? []);
    $maxMensal  = 0.0;
    foreach ($balancete['linhas'] as $linha) {
        $maxMensal = max($maxMensal, $linha['receita'], $linha['despesa']);
    }

    $projetos = $projPorOng[$id] ?? [];
    $movs     = $movPorOng[$id] ?? [];

    $dadosOng[$id] = [
        'ong'             => $ong,
        'receita'         => $receita,
        'despesa'         => $despesa,
        'saldo'           => $saldo,
        'cor1'            => $cor1,
        'cor2'            => $cor2,
        'balancete'       => $balancete,
        'max_mensal'      => $maxMensal,
        'projetos'        => $projetos,
        'movs'            => $movs,
        'local'           => trim(($ong['cidade'] ?? '') . (($ong['estado'] ?? '') ? ', ' . $ong['estado'] : '')),
    ];

    $totalListadoReceita += $receita;
    $totalListadoDespesa += $despesa;
}

$totalListadoSaldo = $totalListadoReceita - $totalListadoDespesa;

// Ordenação escolhida no filtro (a lista já vem por nome do SQL).
if ($ordem !== 'nome') {
    $valorOrdem = static function (int $id) use ($dadosOng, $ordem): float {
        return match ($ordem) {
            'saldo'    => $dadosOng[$id]['saldo'],
            'despesa'  => $dadosOng[$id]['despesa'],
            'projetos' => (float) count($dadosOng[$id]['projetos']),
            default    => $dadosOng[$id]['receita'],
        };
    };
    usort($ongs, static fn (array $a, array $b): int =>
        $valorOrdem((int) $b['id']) <=> $valorOrdem((int) $a['id'])
        ?: strcmp($a['nome'], $b['nome']));
    $ids = array_map('intval', array_column($ongs, 'id'));
}

// Paginação da tabela (10 por página). Os totais do rodapé continuam
// somando todas as ONGs do filtro. Um link ?ong=ID abre direto na
// página em que a ONG está.
$porPagina = 10;
$totalPaginas = max(1, (int) ceil(count($ongs) / $porPagina));
$pagina = isset($_GET['pagina']) && ctype_digit((string) $_GET['pagina'])
    ? (int) $_GET['pagina']
    : ($ongAberta > 0 ? intdiv((int) array_search($ongAberta, $ids, true), $porPagina) + 1 : 1);
$pagina = min(max(1, $pagina), $totalPaginas);
$ongsPagina = array_slice($ongs, ($pagina - 1) * $porPagina, $porPagina);

// ─────────────────────────────────────────────────────────────
// 7. ESTATÍSTICAS GLOBAIS (faixa de KPIs)
// ─────────────────────────────────────────────────────────────

$statsStmt = $pdo->query("
    SELECT
        COUNT(DISTINCT u.id)                                            AS total_ongs,
        COALESCE(SUM(CASE WHEN m.tipo = 'receita' THEN m.valor END), 0) AS total_receitas
    FROM  usuarios u
    LEFT  JOIN movimentacoes m ON m.usuario_id = u.id
    WHERE u.status = 'aprovada'
");
$stats = $statsStmt->fetch();

$todasCats = $pdo->query("
    SELECT DISTINCT area
    FROM   usuarios
    WHERE  status = 'aprovada'
    ORDER  BY area ASC
")->fetchAll();

// Painel "Arrecadado por causa": receitas do ano somadas por área.
$stmtCausas = $pdo->prepare("
    SELECT u.area, COALESCE(SUM(m.valor), 0) AS total
    FROM   usuarios u
    LEFT   JOIN movimentacoes m
           ON m.usuario_id = u.id AND m.tipo = 'receita' AND YEAR(m.data_mov) = ?
    WHERE  u.status = 'aprovada'
    GROUP  BY u.area
    ORDER  BY total DESC, u.area ASC
");
$stmtCausas->execute([$ano]);
$causasPortal = $stmtCausas->fetchAll();
$maxCausa = max(1.0, ...array_map('floatval', array_column($causasPortal, 'total') ?: [1]));

$todasCidades = $pdo->query("SELECT DISTINCT cidade FROM usuarios WHERE status = 'aprovada' AND cidade IS NOT NULL AND cidade != '' ORDER BY cidade ASC")->fetchAll();
$todasUfs     = $pdo->query("SELECT DISTINCT estado FROM usuarios WHERE status = 'aprovada' AND estado IS NOT NULL AND estado != '' ORDER BY estado ASC")->fetchAll();

// ─────────────────────────────────────────────────────────────
// INÍCIO DO HTML
// ─────────────────────────────────────────────────────────────

$page_title = 'Portal de Transparência';
$active_nav = 'transparencia';
include '../includes/header.php';
?>

<!-- ======= CABEÇALHO ======= -->
<section class="tr-page-head">
  <div class="container">
    <div class="tr-hero">
      <span class="tr-hero-orb is-a" aria-hidden="true"></span>
      <span class="tr-hero-orb is-b" aria-hidden="true"></span>

      <div class="tr-hero-glass">
        <div class="tr-head-copy">
          <span class="page-eyebrow"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> Portal de transparência</span>
          <h1>Contas <span>abertas</span></h1>
          <p>
            Consulte receitas, despesas, projetos e balancetes de todas as organizações
            verificadas na plataforma. Transparência é confiança.
          </p>

          <!-- Números gerais (informativos, sem ação) -->
          <div class="tr-head-kpis" aria-label="Números gerais do portal">
            <div class="tr-kpi">
              <i class="bi bi-patch-check" aria-hidden="true"></i>
              <div class="tr-kpi-value"><?= (int) $stats['total_ongs'] ?></div>
              <div class="tr-kpi-label">ONGs verificadas</div>
            </div>
            <div class="tr-kpi">
              <i class="bi bi-cash-coin" aria-hidden="true"></i>
              <div class="tr-kpi-value"><?= fmtDinheiro((float) $stats['total_receitas'], true) ?></div>
              <div class="tr-kpi-label">Arrecadado no total</div>
            </div>
            <div class="tr-kpi">
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              <div class="tr-kpi-value"><?= $ano ?></div>
              <div class="tr-kpi-label">Ano em exibição</div>
            </div>
          </div>
        </div>

        <!-- Causas no portal: cada linha filtra a tabela -->
        <nav class="tr-causas" aria-labelledby="trCausasTitulo">
          <h2 class="tr-causas-titulo" id="trCausasTitulo">Arrecadado por causa em <?= $ano ?></h2>
          <ul>
            <?php foreach (array_slice($causasPortal, 0, 6) as $causa):
              $corCausa = $coresArea[$causa['area']][0] ?? '#5A6A80';
              $ativa = $filtroCategoria === $causa['area'];
            ?>
            <li>
              <a href="<?= htmlspecialchars(urlTransparencia(['categoria' => $ativa ? '' : $causa['area']])) ?>"
                 class="<?= $ativa ? 'is-ativa' : '' ?>"
                 style="--causa-cor:<?= htmlspecialchars($corCausa) ?>; --causa-pct:<?= round((float) $causa['total'] / $maxCausa * 100) ?>%;"
                 <?= $ativa ? 'aria-current="true"' : '' ?>>
                <span class="tr-causas-nome"><?= htmlspecialchars($causa['area']) ?></span>
                <span class="tr-causas-num"><?= fmtDinheiro((float) $causa['total'], true) ?></span>
                <span class="tr-causas-barra" aria-hidden="true"></span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</section>

<!-- ======= CONTEÚDO PRINCIPAL ======= -->
<section class="tr-page ongs-page" id="lista">
  <div class="container">

    <!-- ── COMO LER ESTES DADOS ───────────────────────────────── -->
    <div class="tr-guide glass-1" id="trGuia">
      <div class="tr-guide-top">
        <span class="tr-guide-eyebrow">Como ler estes dados</span>
        <button type="button" class="tr-link-btn tr-guide-toggle"
                data-tr-guide-toggle aria-expanded="true" aria-controls="trGuiaCorpo">
          Ocultar
        </button>
      </div>

      <div class="tr-guide-body" id="trGuiaCorpo">
        <div class="tr-guide-col">
          <span class="tr-guide-icon" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
          <h2>Como funciona o Portal de Transparência?</h2>
          <p>
            As informações financeiras exibidas neste portal são registradas pelas próprias
            organizações em seus painéis administrativos. O ONGHelp não realiza auditoria
            contábil, mas assegura que toda movimentação registrada seja pública e rastreável.
          </p>
          <p>
            Para cada organização são apresentadas as receitas obtidas e a destinação dos
            recursos, como alimentação, medicamentos, aluguel e materiais utilizados nos
            projetos, além do balancete mensal e dos resultados alcançados.
          </p>
        </div>

        <div class="tr-guide-col">
          <span class="tr-guide-icon" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
          <h2>
            O que significa o selo
            <span class="badge-verificado tr-guide-selo">
              <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
              Verificada em xx/xx/xxxx
            </span>
          </h2>
          <p>
            O selo <strong>“Verificada em xx/xx/xxxx”</strong> indica que o cadastro da
            organização foi conferido manualmente pela equipe ONGHelp na data informada.
            O selo não constitui auditoria financeira: os valores são declarados pela
            própria organização.
          </p>
        </div>
      </div>
    </div>

    <!-- ── BARRA DE FILTROS ───────────────────────────────────── -->
    <!-- Mesmo filtro do diretório de ONGs, com o período (ano) a mais. -->
    <form method="get" action="transparencia.php#lista" class="filtro-bar ongs-filter-bar"
          aria-label="Filtros do Portal de Transparência">
      <?php if ($filtroCategoria !== ''): ?>
        <input type="hidden" name="categoria" value="<?= htmlspecialchars($filtroCategoria) ?>">
      <?php endif; ?>

      <div class="ongs-filter-fields">
        <div class="ongs-filter-field ongs-filter-search">
          <label for="trBusca" class="visually-hidden">Nome</label>
          <i class="bi bi-search ongs-filter-icon" aria-hidden="true"></i>
          <input type="search" id="trBusca" name="busca" placeholder="Nome da ONG…"
                 value="<?= htmlspecialchars($filtroBusca) ?>"
                 autocomplete="off" data-auto-submit>
        </div>

        <div class="ongs-filter-field ongs-filter-city">
          <label for="trCidade" class="visually-hidden">Cidade</label>
          <select id="trCidade" name="cidade" data-auto-submit>
            <option value="">Todas as cidades</option>
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
            <label for="trUf" class="visually-hidden">Estado</label>
            <select id="trUf" name="uf" data-auto-submit>
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

        <div class="ongs-filter-field ongs-filter-year">
          <label for="trPeriodo" class="visually-hidden">Período (ano)</label>
          <select id="trPeriodo" name="ano" data-auto-submit>
            <?php foreach ($anosDisponiveis as $y): ?>
              <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="ongs-filter-field ongs-filter-order">
          <label for="trOrdem" class="visually-hidden">Ordenar por</label>
          <select id="trOrdem" name="ordem" data-auto-submit>
            <option value="nome"        <?= $ordem === 'nome'        ? 'selected' : '' ?>>Nome (A–Z)</option>
            <option value="arrecadacao" <?= $ordem === 'arrecadacao' ? 'selected' : '' ?>>Mais arrecadadas</option>
            <option value="despesa"     <?= $ordem === 'despesa'     ? 'selected' : '' ?>>Maiores despesas</option>
            <option value="saldo"       <?= $ordem === 'saldo'       ? 'selected' : '' ?>>Maior saldo</option>
            <option value="projetos"    <?= $ordem === 'projetos'    ? 'selected' : '' ?>>Mais projetos</option>
          </select>
        </div>
      </div>

      <div class="ongs-filter-count" aria-live="polite">
        <strong><?= count($ongs) ?></strong> <?= count($ongs) === 1 ? 'organização' : 'organizações' ?>
      </div>
    </form>

    <div class="ongs-causas">
      <span class="ongs-causas-titulo"><i class="bi bi-tags me-1"></i>Explorar por causa:</span>
      <a href="<?= htmlspecialchars(urlTransparencia(['categoria' => ''])) ?>"
         class="<?= $filtroCategoria === '' ? 'ativo' : '' ?>">Todas</a>
      <?php foreach ($todasCats as $row):
        $ativo = $filtroCategoria === $row['area'];
        $corCausa = $coresArea[$row['area']][0] ?? '#5A6A80';
      ?>
        <a href="<?= htmlspecialchars(urlTransparencia(['categoria' => $ativo ? '' : $row['area']])) ?>"
           class="<?= $ativo ? 'ativo' : '' ?>"<?= $ativo ? ' aria-current="true"' : '' ?>>
          <span class="cause-dot" style="--causa-cor:<?= htmlspecialchars($corCausa) ?>;" aria-hidden="true"></span>
          <?= htmlspecialchars($row['area']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ── TABELA / PAINEL DE DADOS ───────────────────────────── -->
    <div class="tr-table-panel glass-1">
      <?php if (empty($ongs)): ?>

        <div class="tr-empty">
          <span class="tr-empty-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
          <p>Nenhuma ONG encontrada com esses filtros.</p>
          <a href="transparencia.php" class="btn btn-outline-secondary btn-sm px-4">
            Limpar filtros
          </a>
        </div>

      <?php else: ?>

        <table class="tr-table">
          <caption class="visually-hidden">
            Organizações verificadas, com receitas, despesas, saldo e número de projetos em <?= $ano ?>
          </caption>
          <colgroup>
            <col class="tr-col-org">
            <col class="tr-col-causa">
            <col class="tr-col-receita">
            <col class="tr-col-despesa">
            <col class="tr-col-saldo">
            <col class="tr-col-projetos">
            <col class="tr-col-acao">
          </colgroup>
          <thead>
            <?php
            // Cabeçalhos: chave, rótulo, alinhamento e explicação do "?".
            // A ordenação fica no seletor "Ordenar por" do filtro.
            $colunasTabela = [
                ['nome', 'Organização', false, 'Nome da ONG e data em que o cadastro foi verificado pela equipe ONGHelp.'],
                ['causa', 'Causa', false, 'Área principal de atuação da organização.'],
                ['receita', 'Receitas', true, 'Recursos recebidos no ano por meio de doações, eventos e parcerias.'],
                ['despesa', 'Despesas', true, 'Valores aplicados no ano em alimentação, medicamentos, aluguel, contas e materiais dos projetos.'],
                ['saldo', 'Saldo', true, 'Diferença entre receitas e despesas do ano. A barra indica a parcela das receitas já aplicada.'],
                ['projetos', 'Projetos', false, 'Quantidade de projetos cadastrados, com os resultados alcançados.'],
            ];
            ?>
            <tr>
              <?php foreach ($colunasTabela as [$chave, $rotulo, $numerica, $ajuda]):
                $ordenadaPor = ($ordem === 'nome' && $chave === 'nome')
                    || ($ordem === 'arrecadacao' && $chave === 'receita')
                    || ($ordem === $chave);
              ?>
              <th scope="col"<?= $numerica ? ' class="tr-th-num"' : '' ?><?= $ordenadaPor ? ' aria-sort="' . ($chave === 'nome' ? 'ascending' : 'descending') . '"' : '' ?>>
                <div class="tr-th-inner<?= $numerica ? ' is-right' : '' ?>">
                  <span class="tr-th-rotulo<?= $ordenadaPor ? ' is-ordenada' : '' ?>"><?= $rotulo ?></span>
                  <button type="button" class="tr-th-help"
                          data-bs-toggle="tooltip" data-bs-placement="top"
                          title="<?= htmlspecialchars($ajuda) ?>"
                          aria-label="O que é: <?= $rotulo ?>">?</button>
                </div>
              </th>
              <?php endforeach; ?>

              <th scope="col">
                <div class="tr-th-inner is-center">
                  <span class="tr-th-rotulo">Relatório</span>
                  <button type="button" class="tr-th-help"
                          data-bs-toggle="tooltip" data-bs-placement="top"
                          title="Abre o relatório completo da organização: gráfico mensal, balancete, movimentações, projetos e exportação."
                          aria-label="O que é: Relatório">?</button>
                </div>
              </th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($ongsPagina as $ong):
              $id = (int) $ong['id'];
              $d  = $dadosOng[$id];
              $saldoClasse = abs($d['saldo']) < 0.005
                  ? 'is-zero'
                  : ($d['saldo'] > 0 ? 'is-pos' : 'is-neg');
              $selo = !empty($ong['verificada_em'])
                  ? 'Verificada em ' . fmtData((string) $ong['verificada_em'])
                  : '';
            ?>
            <tr class="tr-row"
                tabindex="0"
                data-tr-open="trDrawer<?= $id ?>"
                aria-haspopup="dialog"
                aria-controls="trDrawer<?= $id ?>"
                aria-label="Abrir relatório de <?= htmlspecialchars($ong['nome']) ?>"
                style="--causa-cor:<?= htmlspecialchars($d['cor1']) ?>;"
                data-sort-nome="<?= htmlspecialchars(mb_strtolower($ong['nome'], 'UTF-8')) ?>"
                data-sort-causa="<?= htmlspecialchars(mb_strtolower($ong['area'], 'UTF-8')) ?>"
                data-sort-receita="<?= number_format($d['receita'], 2, '.', '') ?>"
                data-sort-despesa="<?= number_format($d['despesa'], 2, '.', '') ?>"
                data-sort-saldo="<?= number_format($d['saldo'], 2, '.', '') ?>"
                data-sort-projetos="<?= count($d['projetos']) ?>">

              <td class="tr-cell-org">
                <span class="tr-org">
                  <span class="tr-org-avatar">
                    <img src="<?= htmlspecialchars(fotoPerfilUrl($ong['foto_perfil'] ?? null, '../', $ong['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                         alt="" class="avatar-img" aria-hidden="true">
                  </span>
                  <span class="tr-org-main">
                    <span class="tr-org-name"><?= htmlspecialchars($ong['nome']) ?></span>
                    <?php if ($selo !== ''): ?>
                    <span class="tr-org-seal">
                      <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                      <?= htmlspecialchars($selo) ?>
                    </span>
                    <?php endif; ?>
                  </span>
                </span>
              </td>

              <td class="tr-cell-causa">
                <span class="tr-cause">
                  <span class="cause-dot" style="--causa-cor:<?= htmlspecialchars($d['cor1']) ?>;" aria-hidden="true"></span>
                  <?= htmlspecialchars($ong['area']) ?>
                </span>
              </td>

              <td class="tr-cell-money tr-cell-receita">
                <span class="tr-money-label">Receitas</span>
                <span class="tr-money-value is-receita"><?= fmtDinheiro($d['receita']) ?></span>
              </td>

              <td class="tr-cell-money tr-cell-despesa">
                <span class="tr-money-label">Despesas</span>
                <span class="tr-money-value is-despesa"><?= fmtDinheiro($d['despesa']) ?></span>
              </td>

              <td class="tr-cell-money tr-cell-saldo">
                <span class="tr-money-label">Saldo</span>
                <span class="tr-money-value <?= $saldoClasse ?>"><?= fmtSaldoTabela($d['saldo']) ?></span>
                <?php if ($d['receita'] > 0): ?>
                <span class="tr-uso" style="--uso:<?= (int) round(min(1, $d['despesa'] / $d['receita']) * 100) ?>%;"
                      title="<?= (int) round(min(1, $d['despesa'] / $d['receita']) * 100) ?>% das receitas aplicadas"
                      aria-hidden="true"><span></span></span>
                <?php endif; ?>
              </td>

              <td class="tr-cell-projetos"><?= count($d['projetos']) ?></td>

              <td class="tr-cell-acao">
                <button type="button" class="tr-link-btn tr-report-btn"
                        aria-label="Abrir relatório de <?= htmlspecialchars($ong['nome']) ?>">
                  Relatório <span aria-hidden="true">→</span>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>

          <tfoot>
            <tr>
              <td colspan="2" class="tr-total-label">
                <span class="tr-total-icone" aria-hidden="true"><i class="bi bi-calculator"></i></span>
                Total das organizações listadas
              </td>
              <td class="tr-cell-money tr-cell-receita">
                <span class="tr-money-label">Receitas</span>
                <span class="tr-money-value is-receita"><?= fmtDinheiro($totalListadoReceita) ?></span>
              </td>
              <td class="tr-cell-money tr-cell-despesa">
                <span class="tr-money-label">Despesas</span>
                <span class="tr-money-value is-despesa"><?= fmtDinheiro($totalListadoDespesa) ?></span>
              </td>
              <td class="tr-cell-money tr-cell-saldo">
                <span class="tr-money-label">Saldo</span>
                <span class="tr-money-value <?= abs($totalListadoSaldo) < 0.005 ? 'is-zero' : ($totalListadoSaldo > 0 ? 'is-pos' : 'is-neg') ?>">
                  <?= fmtSaldoTabela($totalListadoSaldo) ?>
                </span>
              </td>
              <td class="tr-cell-projetos" aria-hidden="true"></td>
              <td class="tr-cell-acao" aria-hidden="true"></td>
            </tr>
          </tfoot>
        </table>

        <?php if ($totalPaginas > 1): ?>
          <nav class="ong-paginacao tr-paginacao" aria-label="Paginação das ONGs">
            <a href="<?= htmlspecialchars(urlTransparencia(['pagina' => $pagina - 1])) ?>"
               class="<?= $pagina === 1 ? 'desabilitado' : '' ?>" aria-label="Página anterior">
              <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </a>
            <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
              <a href="<?= htmlspecialchars(urlTransparencia(['pagina' => $p])) ?>" class="<?= $p === $pagina ? 'ativo' : '' ?>"
                 <?= $p === $pagina ? 'aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(urlTransparencia(['pagina' => $pagina + 1])) ?>"
               class="<?= $pagina === $totalPaginas ? 'desabilitado' : '' ?>" aria-label="Próxima página">
              <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
          </nav>
        <?php endif; ?>

        <div class="tr-invite">
          <p>
            <strong>Sua ONG ainda não está aqui?</strong>
            Cadastre-se e publique seus relatórios financeiros com transparência.
          </p>
          <a href="cadastro.php" class="btn btn-outline-secondary btn-sm px-4">
            Cadastrar gratuitamente
          </a>
        </div>

      <?php endif; ?>
    </div>

  </div>
</section>

<!-- ============================================================
     DRAWERS — um relatório completo por ONG
     O drawer usa vidro 2, cabeçalho e rodapé fixos, abas
     segmentadas e foco preso enquanto estiver aberto.
============================================================ -->
<?php foreach ($ongsPagina as $ong):
  $id = (int) $ong['id'];
  $d  = $dadosOng[$id];

  $totalReceitaAno = $d['balancete']['total_receita'];
  $totalDespesaAno = $d['balancete']['total_despesa'];
  $totalSaldoAno   = $d['balancete']['total_saldo'];
  $saldoAcumulado  = $d['balancete']['acumulado_final'];
  $saldoClasse     = abs($d['saldo']) < 0.005
      ? 'is-zero'
      : ($d['saldo'] > 0 ? 'is-pos' : 'is-neg');
  $maxMensal       = max(1.0, (float) $d['max_mensal']);
  $local           = $d['local'] !== '' ? $d['local'] : 'Brasil';
  $temPix          = !empty($ong['pix_chave']);
?>
<div class="tr-drawer-shell" id="trDrawer<?= $id ?>" hidden>
  <div class="tr-drawer-backdrop" data-tr-close aria-hidden="true"></div>

  <aside class="tr-drawer glass-2" role="dialog" aria-modal="true"
         aria-labelledby="trDrawerTitulo<?= $id ?>" tabindex="-1">

    <!-- Cabeçalho fixo: identidade da ONG -->
    <header class="tr-drawer-head" style="--causa-cor:<?= htmlspecialchars($d['cor1']) ?>;">
      <span class="tr-drawer-eyebrow">
        <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> Relatório de transparência · <?= $ano ?>
      </span>

      <button type="button" class="tr-drawer-close" data-tr-close aria-label="Fechar relatório" title="Fechar (Esc)">
        <span class="tr-x" aria-hidden="true"></span>
      </button>

      <div class="tr-drawer-topline">
        <span class="tr-drawer-avatar">
          <img src="<?= htmlspecialchars(fotoPerfilUrl($ong['foto_perfil'] ?? null, '../', $ong['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
               alt="" aria-hidden="true">
        </span>

        <div class="tr-drawer-id">
          <h2 id="trDrawerTitulo<?= $id ?>"><?= htmlspecialchars($ong['nome']) ?></h2>

          <div class="tr-drawer-chips">
            <?= badgeVerificado($ong) ?>
            <span class="tr-drawer-chip">
              <span class="cause-dot" aria-hidden="true"></span>
              <?= htmlspecialchars($ong['area']) ?>
            </span>
            <span class="tr-drawer-chip">
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
              <?= htmlspecialchars($local) ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (!empty($ong['descricao'])): ?>
      <p class="tr-drawer-desc"><?= htmlspecialchars($ong['descricao']) ?></p>
      <?php endif; ?>
    </header>

    <!-- Números do período -->
    <?php $usoOng = $d['receita'] > 0 ? (int) round(min(1, $d['despesa'] / $d['receita']) * 100) : 0; ?>
    <div class="tr-report-kpis" aria-label="Indicadores financeiros de <?= $ano ?>">
      <div class="tr-report-kpi">
        <span class="tr-report-kpi-icon is-receita" aria-hidden="true"><i class="bi bi-arrow-down-left"></i></span>
        <div class="tr-report-kpi-label">Receitas em <?= $ano ?></div>
        <div class="tr-report-kpi-value is-receita"><?= fmtDinheiro($d['receita']) ?></div>
      </div>
      <div class="tr-report-kpi">
        <span class="tr-report-kpi-icon is-despesa" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
        <div class="tr-report-kpi-label">Despesas em <?= $ano ?></div>
        <div class="tr-report-kpi-value is-despesa"><?= fmtDinheiro($d['despesa']) ?></div>
      </div>
      <div class="tr-report-kpi">
        <span class="tr-report-kpi-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
        <div class="tr-report-kpi-label">Saldo do período</div>
        <div class="tr-report-kpi-value <?= $saldoClasse ?>">
          <?php if (abs($d['saldo']) >= 0.005): ?>
            <?= ($d['saldo'] < 0 ? '− ' : '+ ') . fmtDinheiro(abs($d['saldo'])) ?>
          <?php else: ?>
            <?= fmtDinheiro(0.0) ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="tr-report-uso" style="--uso:<?= $usoOng ?>%;">
        <span class="tr-report-uso-texto"><strong><?= $usoOng ?>%</strong> das receitas de <?= $ano ?> já foram aplicadas</span>
        <span class="tr-report-uso-barra" aria-hidden="true"><span></span></span>
      </div>
    </div>

    <!-- Aviso: origem dos dados -->
    <div class="tr-aviso-dados" role="note">
      <span class="tr-aviso-dados-icone" aria-hidden="true"><i class="bi bi-exclamation-lg"></i></span>
      <p>
        <strong>Valores declarados pela própria organização.</strong>
        O ONGHelp confere o cadastro, mas não audita as contas apresentadas neste relatório.
      </p>
    </div>

    <!-- Abas -->
    <div class="tr-tabs">
      <div class="tr-tablist" role="tablist" aria-label="Seções do relatório">
        <button type="button" class="tr-tab" id="trTabResumo<?= $id ?>"
                role="tab" aria-selected="true" aria-controls="trPanelResumo<?= $id ?>" tabindex="0">
          <i class="bi bi-bar-chart" aria-hidden="true"></i> Resumo
        </button>
        <button type="button" class="tr-tab" id="trTabBalancete<?= $id ?>"
                role="tab" aria-selected="false" aria-controls="trPanelBalancete<?= $id ?>" tabindex="-1">
          <i class="bi bi-table" aria-hidden="true"></i> Balancete
        </button>
        <button type="button" class="tr-tab" id="trTabMovs<?= $id ?>"
                role="tab" aria-selected="false" aria-controls="trPanelMovs<?= $id ?>" tabindex="-1">
          <i class="bi bi-arrow-left-right" aria-hidden="true"></i> Movimentações
        </button>
        <button type="button" class="tr-tab" id="trTabProjetos<?= $id ?>"
                role="tab" aria-selected="false" aria-controls="trPanelProjetos<?= $id ?>" tabindex="-1">
          <i class="bi bi-kanban" aria-hidden="true"></i> Projetos
        </button>
      </div>
    </div>

    <!-- Corpo rolável -->
    <div class="tr-drawer-body">

      <!-- Resumo -->
      <section class="tr-tab-panel" id="trPanelResumo<?= $id ?>"
               role="tabpanel" aria-labelledby="trTabResumo<?= $id ?>" tabindex="0">
        <div class="tr-chart-card">
          <h3 class="tr-panel-title">Receitas e despesas por mês — <?= $ano ?></h3>

          <div class="tr-chart" aria-label="Gráfico de receitas e despesas mensais de <?= $ano ?>">
            <?php foreach ($d['balancete']['linhas'] as $mes => $linha):
              $pctReceita = $linha['receita'] > 0 ? round($linha['receita'] / $maxMensal * 100, 2) : 0;
              $pctDespesa = $linha['despesa'] > 0 ? round($linha['despesa'] / $maxMensal * 100, 2) : 0;
              $receitaVazia = $linha['receita'] <= 0;
              $despesaVazia = $linha['despesa'] <= 0;
            ?>
            <div class="tr-chart-month">
              <div class="tr-chart-bars">
                <span class="tr-chart-bar is-receita<?= $receitaVazia ? ' is-empty' : '' ?>"
                      style="--bar-h:<?= $pctReceita ?>%;"
                      tabindex="0" role="img"
                      data-tip="<?= htmlspecialchars($mesesNomeCompleto[$mes]) ?> · Receitas: <?= fmtDinheiro($linha['receita']) ?>"
                      aria-label="<?= htmlspecialchars($mesesNomeCompleto[$mes]) ?>, receitas: <?= fmtDinheiro($linha['receita']) ?>"></span>
                <span class="tr-chart-bar is-despesa<?= $despesaVazia ? ' is-empty' : '' ?>"
                      style="--bar-h:<?= $pctDespesa ?>%;"
                      tabindex="0" role="img"
                      data-tip="<?= htmlspecialchars($mesesNomeCompleto[$mes]) ?> · Despesas: <?= fmtDinheiro($linha['despesa']) ?>"
                      aria-label="<?= htmlspecialchars($mesesNomeCompleto[$mes]) ?>, despesas: <?= fmtDinheiro($linha['despesa']) ?>"></span>
              </div>
              <span class="tr-chart-label"><?= $mesesNome[$mes] ?></span>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="tr-chart-legend">
            <span><i class="tr-chart-dot is-receita" aria-hidden="true"></i> Receitas</span>
            <span><i class="tr-chart-dot is-despesa" aria-hidden="true"></i> Despesas</span>
          </div>
        </div>

        <p class="tr-note">
          Passe o mouse ou use Tab sobre cada coluna para ver o valor do mês.
          Meses sem movimento aparecem como pequenas linhas atenuadas.
        </p>
      </section>

      <!-- Balancete -->
      <section class="tr-tab-panel" id="trPanelBalancete<?= $id ?>"
               role="tabpanel" aria-labelledby="trTabBalancete<?= $id ?>" tabindex="0" hidden>
        <h3 class="tr-panel-title">Balancete mensal — <?= $ano ?></h3>

        <div class="tr-balancete-wrap">
          <table class="tr-balancete">
            <thead>
              <tr>
                <th scope="col">Mês</th>
                <th scope="col">Receitas</th>
                <th scope="col">Despesas</th>
                <th scope="col">Saldo do mês</th>
                <th scope="col">Saldo acumulado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($d['balancete']['linhas'] as $mes => $linha): ?>
              <tr<?= !empty($linha['semMov']) ? ' class="is-empty"' : '' ?>>
                <td><?= $mesesNome[$mes] ?></td>
                <td class="is-receita"><?= fmtDinheiroTabela($linha['receita']) ?></td>
                <td class="is-despesa"><?= fmtDinheiroTabela($linha['despesa']) ?></td>
                <td class="<?= $linha['saldo'] < 0 ? 'is-neg' : 'is-pos' ?>"><?= fmtSaldoTabela($linha['saldo']) ?></td>
                <td class="<?= $linha['acumulado'] < 0 ? 'is-neg' : 'is-pos' ?>"><?= fmtSaldoTabela($linha['acumulado']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td>Total do ano</td>
                <td class="is-receita"><?= fmtDinheiroTabela($totalReceitaAno) ?></td>
                <td class="is-despesa"><?= fmtDinheiroTabela($totalDespesaAno) ?></td>
                <td class="<?= $totalSaldoAno < 0 ? 'is-neg' : 'is-pos' ?>"><?= fmtSaldoTabela($totalSaldoAno) ?></td>
                <td class="<?= $saldoAcumulado < 0 ? 'is-neg' : 'is-pos' ?>"><?= fmtSaldoTabela($saldoAcumulado) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p class="tr-note">
          O saldo acumulado começa em janeiro de <?= $ano ?> e soma mês a mês.
          Meses sem movimentação aparecem como um traço.
        </p>
      </section>

      <!-- Movimentações -->
      <section class="tr-tab-panel" id="trPanelMovs<?= $id ?>"
               role="tabpanel" aria-labelledby="trTabMovs<?= $id ?>" tabindex="0" hidden>
        <h3 class="tr-panel-title">Últimas movimentações de <?= $ano ?></h3>

        <?php if (empty($d['movs'])): ?>
          <div class="tr-inline-empty">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            Nenhuma movimentação registrada em <?= $ano ?>.
          </div>
        <?php else: ?>
          <div class="tr-feed">
            <?php foreach ($d['movs'] as $mov):
              $ehReceita = $mov['tipo'] === 'receita';
            ?>
            <div class="tr-feed-item">
              <span class="tr-feed-icon <?= $ehReceita ? 'is-receita' : 'is-despesa' ?>" aria-hidden="true">
                <i class="bi <?= $ehReceita ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
              </span>
              <div class="tr-feed-main">
                <div class="tr-feed-desc"><?= htmlspecialchars($mov['descricao']) ?></div>
                <div class="tr-feed-meta">
                  <?= fmtData((string) $mov['data_mov']) ?> ·
                  <?= $ehReceita ? 'Receita' : 'Despesa' ?>
                </div>
              </div>
              <span class="tr-feed-value <?= $ehReceita ? 'is-pos' : 'is-neg' ?>">
                <?= ($ehReceita ? '+ ' : '− ') . fmtDinheiro((float) $mov['valor']) ?>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="tr-note">Exibindo as 5 movimentações mais recentes do período.</p>
        <?php endif; ?>
      </section>

      <!-- Projetos -->
      <section class="tr-tab-panel" id="trPanelProjetos<?= $id ?>"
               role="tabpanel" aria-labelledby="trTabProjetos<?= $id ?>" tabindex="0" hidden>
        <h3 class="tr-panel-title">Projetos e resultados</h3>

        <?php if (empty($d['projetos'])): ?>
          <div class="tr-inline-empty">
            <i class="bi bi-kanban" aria-hidden="true"></i>
            Nenhum projeto cadastrado.
          </div>
        <?php else: ?>
          <div class="tr-projects">
            <?php foreach ($d['projetos'] as $proj): ?>
            <div class="tr-project">
              <span class="tr-project-icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
              <div class="tr-project-value"><?= number_format((float) $proj['indicador'], 0, ',', '.') ?></div>
              <div class="tr-project-label"><?= htmlspecialchars($proj['unidade']) ?></div>
              <div class="tr-project-name"><?= htmlspecialchars($proj['nome']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

    </div><!-- /corpo rolável -->

    <!-- Rodapé fixo -->
    <footer class="tr-drawer-foot">
      <div class="tr-drawer-foot-row">
        <div class="tr-drawer-actions">
          <?php if ($temPix): ?>
            <button type="button" class="btn-pix"
                    data-drawer-pix="modalDoacao<?= $id ?>">
              <i class="bi bi-qr-code" aria-hidden="true"></i> Doar via PIX
            </button>
          <?php else: ?>
            <button type="button" class="btn-pix" disabled
                    title="Esta organização ainda não cadastrou uma chave PIX.">
              <i class="bi bi-qr-code" aria-hidden="true"></i> Doar via PIX
            </button>
          <?php endif; ?>

          <a href="perfilpublico.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person-badge" aria-hidden="true"></i> Ver perfil público
          </a>
        </div>

        <div class="tr-export">
          <button type="button" class="btn btn-outline-secondary tr-export-toggle"
                  data-tr-export-toggle aria-expanded="false"
                  aria-controls="trExportMenu<?= $id ?>" aria-haspopup="true">
            <i class="bi bi-download" aria-hidden="true"></i> Exportar <i class="bi bi-chevron-down tr-export-seta" aria-hidden="true"></i>
          </button>

          <div class="tr-export-menu glass-2" id="trExportMenu<?= $id ?>" hidden>
            <a href="exportar_csv.php?ong=<?= $id ?>&amp;ano=<?= $ano ?>" class="tr-export-link">
              <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar CSV
            </a>
            <a href="relatorio_anual.php?ong=<?= $id ?>&amp;ano=<?= $ano ?>" class="tr-export-link">
              <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Relatório anual (PDF)
            </a>

            <div class="tr-export-sep" aria-hidden="true"></div>

            <div class="tr-export-period">
              <h4 class="tr-export-period-title">CSV do período</h4>
              <form method="get" action="exportar_csv.php">
                <input type="hidden" name="ong" value="<?= $id ?>">
                <div class="tr-export-dates">
                  <div>
                    <label for="trCsvIni<?= $id ?>">Data inicial</label>
                    <input type="date" class="form-control" id="trCsvIni<?= $id ?>"
                           name="ini" required value="<?= $ano ?>-01-01">
                  </div>
                  <div>
                    <label for="trCsvFim<?= $id ?>">Data final</label>
                    <input type="date" class="form-control" id="trCsvFim<?= $id ?>"
                           name="fim" required value="<?= $ano ?>-12-31">
                  </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                  <i class="bi bi-download" aria-hidden="true"></i> Baixar CSV
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

    </footer>

  </aside>
</div>

<?php
    // Modal de doação via PIX — só quando a ONG cadastrou a chave.
    if ($temPix):
        $modalUid = $id;
        $modalOng = [
            'id'        => $id,
            'nome'      => $ong['nome'],
            'pix_chave' => $ong['pix_chave'] ?? '',
            'cidade'    => $ong['cidade'] ?? '',
        ];
        include '../includes/modal_doacao.php';
    endif;
endforeach;
?>

<?php
$page_script = 'transparencia.js';
include '../includes/footer.php';
?>
