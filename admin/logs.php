<?php
// ============================================================
// admin/logs.php — Histórico de ações da equipe com filtros
// ============================================================

require_once '../includes/admin_auth.php';

$admin = exigirPermissao($pdo, 'logs.ver');

$validarData = static function (string $valor): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)) {
        return '';
    }

    return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $valor : '';
};

$categorias = categoriasLogAdmin();

$filtroPessoa = isset($_GET['pessoa']) && ctype_digit((string) $_GET['pessoa'])
    ? (int) $_GET['pessoa']
    : 0;

$filtroTipo = (string) ($_GET['tipo'] ?? '');
if ($filtroTipo !== '' && !isset($categorias[$filtroTipo])) {
    $filtroTipo = '';
}

$filtroDe  = $validarData((string) ($_GET['de'] ?? ''));
$filtroAte = $validarData((string) ($_GET['ate'] ?? ''));

if ($filtroDe !== '' && $filtroAte !== '' && $filtroDe > $filtroAte) {
    [$filtroDe, $filtroAte] = [$filtroAte, $filtroDe];
}

$filtros = [
    'pessoa' => $filtroPessoa > 0 ? (string) $filtroPessoa : '',
    'tipo'   => $filtroTipo,
    'de'     => $filtroDe,
    'ate'    => $filtroAte,
];
$filtrosAtivos = array_filter($filtros, static fn ($valor) => $valor !== '');

$urlLogs = static function (array $extra = []) use ($filtrosAtivos): string {
    $query = array_filter(
        array_merge($filtrosAtivos, $extra),
        static fn ($valor) => $valor !== '' && $valor !== null
    );

    return 'logs.php' . ($query ? '?' . http_build_query($query) : '');
};

$where  = [];
$params = [];

if ($filtroPessoa > 0) {
    $where[]  = 'l.admin_id = ?';
    $params[] = $filtroPessoa;
}

if ($filtroTipo !== '') {
    $acoesTipo = $categorias[$filtroTipo]['acoes'];

    if ($acoesTipo) {
        $where[] = 'l.acao IN (' . implode(',', array_fill(0, count($acoesTipo), '?')) . ')';
        $params  = array_merge($params, $acoesTipo);
    } else {
        // Categorias sem ações declaradas continuam aceitando tipos novos.
        $where[] = '1 = 0';
    }
}

if ($filtroDe !== '') {
    $where[]  = 'l.criado_em >= ?';
    $params[] = $filtroDe;
}

if ($filtroAte !== '') {
    $where[]  = 'l.criado_em < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $filtroAte;
}

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM   logs_admin l
    LEFT   JOIN admins a ON a.id = l.admin_id
    $sqlWhere
");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$porPagina   = 25;
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina      = isset($_GET['pagina']) && ctype_digit((string) $_GET['pagina'])
    ? max(1, (int) $_GET['pagina'])
    : 1;
$pagina = min($pagina, $totalPaginas);
$limite = $porPagina * $pagina;

$stmt = $pdo->prepare("
    SELECT l.*, a.nome AS admin_nome, a.papel AS admin_papel
    FROM   logs_admin l
    LEFT   JOIN admins a ON a.id = l.admin_id
    $sqlWhere
    ORDER  BY l.criado_em DESC, l.id DESC
    LIMIT  $limite
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$adminsFiltro = $pdo->query(
    "SELECT id, nome, papel
     FROM   admins
     ORDER  BY (papel = 'administrador') DESC, nome ASC"
)->fetchAll();

// Agrupamento visual por dia.
$logsPorDia = [];
foreach ($logs as $log) {
    $logsPorDia[date('Y-m-d', strtotime((string) $log['criado_em']))][] = $log;
}

$hoje  = date('Y-m-d');
$ontem = date('Y-m-d', strtotime('-1 day'));

$tiposFiltro = [];
foreach ($categorias as $chave => $categoria) {
    if (!empty($categoria['acoes'])) {
        $tiposFiltro[$chave] = $categoria['rotulo'];
    }
}

$admin_page_title    = 'Logs';
$admin_page_subtitle = 'Histórico de ações da equipe, com autoria e filtros.';
$admin_active        = 'logs';
include '../includes/admin_header.php';
?>

<div class="admin-log-page">

  <section class="admin-card admin-log-barra" aria-label="Filtros de log">
    <form method="GET" action="logs.php" class="admin-log-filtro-form">
      <div class="admin-log-campo">
        <label class="admin-label" for="filtroPessoa">Pessoa</label>
        <select name="pessoa" id="filtroPessoa" class="form-select" data-auto-submit>
          <option value="">Todas</option>
          <?php foreach ($adminsFiltro as $pessoa): ?>
            <option value="<?= (int) $pessoa['id'] ?>" <?= $filtroPessoa === (int) $pessoa['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($pessoa['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="admin-log-campo">
        <label class="admin-label" for="filtroTipo">Tipo</label>
        <select name="tipo" id="filtroTipo" class="form-select" data-auto-submit>
          <option value="">Todos</option>
          <?php foreach ($tiposFiltro as $chave => $rotulo): ?>
            <option value="<?= htmlspecialchars($chave) ?>" <?= $filtroTipo === $chave ? 'selected' : '' ?>>
              <?= htmlspecialchars($rotulo) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="admin-log-campo admin-log-campo-data">
        <label class="admin-label" for="filtroDe">De</label>
        <input type="date" name="de" id="filtroDe" class="form-control" value="<?= htmlspecialchars($filtroDe) ?>">
      </div>

      <div class="admin-log-campo admin-log-campo-data">
        <label class="admin-label" for="filtroAte">Até</label>
        <input type="date" name="ate" id="filtroAte" class="form-control" value="<?= htmlspecialchars($filtroAte) ?>">
      </div>

      <button type="submit" class="btn btn-primary admin-log-filtrar">
        <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
      </button>

      <div class="admin-log-contador" role="status" aria-live="polite">
        <strong><?= number_format($total, 0, ',', '.') ?></strong>
        <span><?= $total === 1 ? 'registro' : 'registros' ?></span>
      </div>
    </form>

    <?php if ($filtrosAtivos): ?>
      <a href="logs.php" class="admin-link-terciario admin-log-limpar">Limpar filtros</a>
    <?php endif; ?>
  </section>

  <section class="admin-card admin-log-card">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Histórico de ações</h2>
        <p class="admin-card-subtitle">
          Ações anteriores às contas individuais aparecem como Equipe ONGHelp.
        </p>
      </div>
    </div>

    <?php if (empty($logs)): ?>
      <div class="admin-vazio">
        <i class="bi bi-journal" aria-hidden="true"></i>
        <strong>Nenhuma ação encontrada</strong>
        <span>Ajuste os filtros ou aguarde novas ações da equipe.</span>
      </div>
    <?php else: ?>
      <div class="admin-log-dias">
        <?php foreach ($logsPorDia as $dia => $itens): ?>
          <?php
          $rotuloDia = $dia === $hoje
              ? 'Hoje'
              : ($dia === $ontem ? 'Ontem' : date('d/m/Y', strtotime($dia)));
          ?>
          <section class="admin-log-day" aria-label="Registros de <?= htmlspecialchars($rotuloDia) ?>">
            <h3 class="admin-log-day-label">
              <?= htmlspecialchars($rotuloDia) ?>
              <span><?= date('d/m/Y', strtotime($dia)) ?> · <?= count($itens) ?> registro(s)</span>
            </h3>

            <ol class="admin-log-lista">
              <?php foreach ($itens as $log): ?>
                <?php
                $categoria = categoriaLogInfo((string) $log['acao']);
                $infoAcao = rotulosAcaoAdmin()[(string) $log['acao']] ?? null;
                $iconeAcao = is_array($infoAcao) ? $infoAcao[1] : $categoria['icone'];
                $ehVisitante = (string) $log['acao'] === 'denuncia_recebida';
                $ator      = trim((string) ($log['admin_nome'] ?? ''))
                    ?: ($ehVisitante ? 'Visitante' : 'Equipe ONGHelp');
                $papel     = trim((string) ($log['admin_papel'] ?? '')) ?: 'administrador';
                $papelLabel = adminPapelLabel($papel);
                ?>
                <li class="admin-log-evento">
                  <span class="admin-log-icone <?= htmlspecialchars($categoria['classe']) ?>" aria-hidden="true">
                    <i class="bi <?= htmlspecialchars($iconeAcao) ?>"></i>
                  </span>

                  <div class="admin-log-conteudo">
                    <p class="admin-log-frase"><?= htmlspecialchars(resumoLogAdmin($log)) ?></p>
                    <div class="admin-log-secundaria">
                      <?php if (!$ehVisitante): ?>
                        <span class="admin-role-badge is-<?= $papel === 'administrador' ? 'admin' : 'moderador' ?>">
                          <?= htmlspecialchars($papelLabel) ?>
                        </span>
                      <?php endif; ?>
                      <time datetime="<?= htmlspecialchars(date('c', strtotime((string) $log['criado_em'])), ENT_QUOTES, 'UTF-8') ?>">
                        <?= date('H:i', strtotime((string) $log['criado_em'])) ?>
                      </time>
                      <span class="admin-log-ator"><?= htmlspecialchars($ator) ?></span>
                      <?php if (!empty($log['detalhe'])): ?>
                        <span class="admin-log-detalhe"><?= htmlspecialchars($log['detalhe']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <span class="admin-cat-chip <?= htmlspecialchars($categoria['classe']) ?>">
                    <?= htmlspecialchars($categoria['chip']) ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endforeach; ?>
      </div>

      <?php if ($limite < $total): ?>
        <div class="admin-log-mais-wrap">
          <a href="<?= htmlspecialchars($urlLogs(['pagina' => (string) ($pagina + 1)])) ?>"
             class="btn btn-outline-primary admin-log-mais">
            Carregar mais
          </a>
          <span class="admin-log-mostrando">
            Mostrando <?= count($logs) ?> de <?= number_format($total, 0, ',', '.') ?>
          </span>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php include '../includes/admin_footer.php'; ?>
