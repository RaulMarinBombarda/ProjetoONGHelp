<?php
// ============================================================
// admin/index.php — Visão geral e fila de verificação
// ============================================================

require_once '../includes/admin_auth.php';

$admin = exigirPermissao($pdo, 'ong.analisar');

// ── Filtros ─────────────────────────────────────────────────
$statusPermitidos = ['pendente', 'aprovada', 'recusada', 'suspensa'];
$filtroStatus = trim($_GET['status'] ?? '');
if ($filtroStatus !== '' && !in_array($filtroStatus, $statusPermitidos, true)) {
    $filtroStatus = '';
}

$busca = trim($_GET['busca'] ?? '');

// ── Resumo por status ───────────────────────────────────────
$resumo = ['pendente' => 0, 'aprovada' => 0, 'recusada' => 0, 'suspensa' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS total FROM usuarios GROUP BY status")->fetchAll() as $row) {
    if (isset($resumo[$row['status']])) {
        $resumo[$row['status']] = (int) $row['total'];
    }
}

// Denúncias de notícias ainda não tratadas. O aviso acima da fila só
// aparece quando há trabalho de moderação pendente.
$denunciasNoticiasPendentes = (int) $pdo
    ->query("SELECT COUNT(*) FROM noticias_denuncias WHERE estado = 'pendente'")
    ->fetchColumn();

// ── Lista ───────────────────────────────────────────────────
$where  = [];
$params = [];

if ($filtroStatus !== '') {
    $where[]  = "u.status = ?";
    $params[] = $filtroStatus;
}

if ($busca !== '') {
    $termo     = '%' . $busca . '%';
    $cnpjBusca = somenteDigitos($busca);

    if ($cnpjBusca !== '') {
        // Aceita CNPJ com ou sem máscara: o valor gravado é sempre
        // normalizado, mas o admin pode colar o formato da tela.
        $where[]  = "(u.nome LIKE ? OR u.email LIKE ? OR u.cidade LIKE ?
                      OR REPLACE(REPLACE(REPLACE(REPLACE(u.cnpj, '.', ''), '/', ''), '-', ''), ' ', '') LIKE ?)";
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
        $params[] = '%' . $cnpjBusca . '%';
    } else {
        $where[]  = "(u.nome LIKE ? OR u.email LIKE ? OR u.cidade LIKE ?)";
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
    }
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$ordem = ($filtroStatus === 'pendente' || $filtroStatus === '')
    ? "CASE WHEN u.status = 'pendente' THEN 0 ELSE 1 END, u.criado_em ASC"
    : "u.criado_em DESC";

$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.cnpj, u.email, u.area, u.cidade, u.estado,
           u.status, u.criado_em, u.aprovado_em, u.verificada_em,
           u.email_aprovacao_enviado_em, u.verificado_por,
           v.nome AS verificado_por_nome,
           (SELECT COUNT(*) FROM verificacoes vf WHERE vf.usuario_id = u.id) AS total_verificacoes,
           (SELECT MAX(vf.criado_em) FROM verificacoes vf WHERE vf.usuario_id = u.id) AS ultima_decisao_em
    FROM   usuarios u
    LEFT   JOIN admins v ON v.id = u.verificado_por
    $sqlWhere
    ORDER  BY $ordem
    LIMIT  100
");
$stmt->execute($params);
$ongs = $stmt->fetchAll();

// ── Logs recentes ───────────────────────────────────────────
$logsRecentes = $pdo->query("
    SELECT l.*, a.nome AS admin_nome
    FROM   logs_admin l
    LEFT   JOIN admins a ON a.id = l.admin_id
    ORDER  BY l.criado_em DESC, l.id DESC
    LIMIT  8
")->fetchAll();

$rotulosAcao = rotulosAcaoAdmin();

$rotulosStatusPlural = [
    'pendente' => 'Pendentes',
    'aprovada' => 'Aprovadas',
    'recusada' => 'Recusadas',
    'suspensa' => 'Suspensas',
];

$admin_page_title    = $filtroStatus === '' ? 'Visão geral' : $rotulosStatusPlural[$filtroStatus];
$admin_page_subtitle = $filtroStatus === ''
    ? 'Fila de verificação e acompanhamento de cadastros'
    : 'Cadastros ' . [
        'pendente' => 'pendentes',
        'aprovada' => 'aprovados',
        'recusada' => 'recusados',
        'suspensa' => 'suspensos',
    ][$filtroStatus] . ' na plataforma';
$admin_active        = $filtroStatus === '' ? 'dashboard' : [
    'pendente' => 'pendentes',
    'aprovada' => 'aprovadas',
    'recusada' => 'recusadas',
    'suspensa' => 'suspensas',
][$filtroStatus];

$estadosVazios = [
    'pendente' => ['Nenhuma ONG pendente — tudo em dia', 'Novos cadastros aparecem aqui assim que chegarem.'],
    'aprovada' => ['Nenhuma ONG aprovada ainda', 'Os cadastros aprovados pela equipe aparecem nesta lista.'],
    'recusada' => ['Nenhuma ONG recusada', 'Cadastros recusados com motivo registrado aparecem aqui.'],
    'suspensa' => ['Nenhuma ONG suspensa — tudo em dia', 'Perfis suspensos aparecem aqui para acompanhamento.'],
];

if ($busca !== '') {
    $vazioTitulo = 'Nenhum resultado encontrado';
    $vazioTexto  = 'Tente outro nome, CNPJ, e-mail ou cidade.';
    $vazioIcone  = 'bi-search';
} elseif (isset($estadosVazios[$filtroStatus])) {
    [$vazioTitulo, $vazioTexto] = $estadosVazios[$filtroStatus];
    $vazioIcone = [
        'pendente' => 'bi-hourglass-split',
        'aprovada' => 'bi-patch-check',
        'recusada' => 'bi-x-octagon',
        'suspensa' => 'bi-pause-circle',
    ][$filtroStatus];
} else {
    $vazioTitulo = 'Nenhum cadastro encontrado';
    $vazioTexto  = 'Ajuste os filtros ou aguarde novos cadastros.';
    $vazioIcone  = 'bi-inbox';
}

include '../includes/admin_header.php';
?>

<!-- Resumo por status: quatro painéis independentes -->
<div class="admin-kpis">
  <?php
  $cards = [
      'pendente' => ['Pendentes', 'bi-hourglass-split', 'admin-kpi-pendente'],
      'aprovada' => ['Aprovadas', 'bi-patch-check', 'admin-kpi-aprovada'],
      'recusada' => ['Recusadas', 'bi-x-octagon', 'admin-kpi-recusada'],
      'suspensa' => ['Suspensas', 'bi-pause-circle', 'admin-kpi-suspensa'],
  ];
  foreach ($cards as $chave => [$rotulo, $icone, $classe]):
  ?>
    <a href="index.php?status=<?= $chave ?>"
       class="admin-kpi <?= $classe ?> <?= $filtroStatus === $chave ? 'is-active' : '' ?> <?= $chave === 'pendente' && $resumo['pendente'] > 0 ? 'admin-kpi-alerta' : '' ?>">
      <span class="admin-kpi-icon"><i class="bi <?= $icone ?>" aria-hidden="true"></i></span>
      <span class="admin-kpi-texto">
        <span class="admin-kpi-valor"><?= $resumo[$chave] ?></span>
        <span class="admin-kpi-rotulo"><?= $rotulo ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($denunciasNoticiasPendentes > 0): ?>
  <a href="noticias.php" class="admin-aviso-noticias">
    <span class="admin-aviso-noticias-icone" aria-hidden="true"><i class="bi bi-megaphone"></i></span>
    <span class="admin-aviso-noticias-texto">
      <strong><?= $denunciasNoticiasPendentes ?></strong>
      <?= $denunciasNoticiasPendentes === 1
          ? 'denúncia de notícia aguarda análise'
          : 'denúncias de notícias aguardam análise' ?>
    </span>
    <i class="bi bi-arrow-right admin-aviso-noticias-seta" aria-hidden="true"></i>
  </a>
<?php endif; ?>

<div class="row g-4 admin-main-grid">
  <div class="col-xl-8">
    <div class="admin-card admin-fila-card">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">
            <?= $filtroStatus !== '' ? $rotulosStatusPlural[$filtroStatus] : 'Fila de verificação' ?>
          </h2>
          <p class="admin-card-subtitle">
            <?= $filtroStatus === 'pendente'
                ? 'Cadastros aguardando análise manual. Os mais antigos aparecem primeiro.'
                : 'Cadastros da plataforma. Use os filtros para localizar uma ONG.' ?>
          </p>
        </div>
        <span class="admin-count" aria-label="<?= count($ongs) ?> cadastro(s)"><?= count($ongs) ?></span>
      </div>

      <form method="GET" action="index.php" class="admin-filtros" role="search">
        <?php if ($filtroStatus !== ''): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($filtroStatus) ?>">
        <?php endif; ?>
        <div class="admin-busca-linha">
          <div class="input-group admin-busca">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input type="search" name="busca" class="form-control" placeholder="Nome, CNPJ, e-mail ou cidade"
                   value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar ONG">
            <button type="submit" class="btn btn-primary">Buscar</button>
          </div>
          <?php if ($busca !== '' || $filtroStatus !== ''): ?>
            <a href="index.php" class="admin-busca-limpar">Limpar</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if (empty($ongs)): ?>
        <div class="admin-vazio">
          <i class="bi <?= $vazioIcone ?>" aria-hidden="true"></i>
          <strong><?= htmlspecialchars($vazioTitulo) ?></strong>
          <span><?= htmlspecialchars($vazioTexto) ?></span>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table admin-table admin-table-fila align-middle mb-0">
            <thead>
              <tr>
                <th>ONG</th>
                <th>Local</th>
                <th>Status</th>
                <th>Cadastro</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ongs as $ong): ?>
                <?php
                $dias = (int) floor((time() - strtotime($ong['criado_em'])) / 86400);
                $textoDias = $dias === 1 ? '1 dia em análise' : $dias . ' dias em análise';
                $autorDecisao = trim((string) ($ong['verificado_por_nome'] ?? '')) ?: 'Equipe ONGHelp';
                $dataDecisao = (string) ($ong['verificada_em'] ?: ($ong['aprovado_em'] ?: ($ong['ultima_decisao_em'] ?: '')));
                ?>
                <tr class="<?= $ong['status'] === 'pendente' ? 'is-pendente' : '' ?>">
                  <td>
                    <div class="admin-ong-nome"><?= htmlspecialchars($ong['nome']) ?></div>
                    <div class="admin-ong-cnpj"><?= htmlspecialchars(mascaraCnpj($ong['cnpj'])) ?></div>
                  </td>
                  <td class="admin-ong-local">
                    <div>
                      <?= htmlspecialchars($ong['cidade'] ?: '—') ?><?= $ong['estado'] ? ' <span class="admin-local-uf">· ' . htmlspecialchars($ong['estado']) . '</span>' : '' ?>
                    </div>
                    <div class="admin-causa">
                      <span class="admin-causa-dot" aria-hidden="true"></span>
                      <?= htmlspecialchars($ong['area']) ?>
                    </div>
                  </td>
                  <td>
                    <span class="admin-status <?= statusClass($ong['status']) ?>">
                      <?= statusLabel($ong['status']) ?>
                    </span>
                    <?php if ($ong['status'] === 'aprovada' && empty($ong['email_aprovacao_enviado_em'])): ?>
                      <div class="admin-status-nota">
                        <i class="bi bi-envelope-exclamation" aria-hidden="true"></i> e-mail pendente
                      </div>
                    <?php endif; ?>
                    <?php if ($ong['status'] !== 'pendente' && $dataDecisao !== ''): ?>
                      <div class="admin-status-autor">
                        por <?= htmlspecialchars($autorDecisao) ?> · <?= fmtData($dataDecisao) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="admin-ong-data">
                    <div><?= fmtData($ong['criado_em']) ?></div>
                    <?php if ($ong['status'] === 'pendente'): ?>
                      <div class="admin-prazo <?= $dias >= 2 ? 'admin-prazo-alerta' : '' ?>">
                        <?= $textoDias ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a href="verificar.php?id=<?= (int) $ong['id'] ?>"
                       class="btn btn-sm <?= $ong['status'] === 'pendente' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                      Analisar
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="admin-card admin-logs-recentes">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Logs recentes</h2>
          <p class="admin-card-subtitle">Ações registradas pela equipe.</p>
        </div>
        <a href="logs.php" class="admin-link-terciario">Ver todos <span aria-hidden="true">→</span></a>
      </div>

      <?php if (empty($logsRecentes)): ?>
        <div class="admin-vazio admin-vazio-sm">
          <i class="bi bi-journal" aria-hidden="true"></i>
          <strong>Nenhuma ação registrada</strong>
        </div>
      <?php else: ?>
        <ol class="admin-timeline admin-timeline-compact">
          <?php foreach ($logsRecentes as $log): ?>
            <li>
              <div class="admin-log-acao"><?= htmlspecialchars($rotulosAcao[$log['acao']][0] ?? str_replace('_', ' ', $log['acao'])) ?></div>
              <div class="admin-log-alvo"><?= htmlspecialchars($log['alvo_nome'] ?: '—') ?></div>
              <div class="admin-log-meta">
                <?= htmlspecialchars(
                    $log['admin_nome']
                    ?: ((string) $log['acao'] === 'denuncia_recebida' ? 'Visitante' : 'Equipe ONGHelp')
                ) ?> · <?= fmtDataHora($log['criado_em']) ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>

    <div class="admin-note mt-4">
      <h2 class="admin-note-title"><i class="bi bi-info-circle" aria-hidden="true"></i> Lembrete</h2>
      <p>
        A aprovação só é liberada com todos os bloqueantes marcados. Em caso de dúvida,
        recuse com motivo e peça à ONG para corrigir o cadastro.
      </p>
    </div>
  </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
