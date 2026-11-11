<?php
// ============================================================
// admin/equipe.php — Gestão da equipe e das contas de moderação
// ============================================================

require_once '../includes/admin_auth.php';

$admin = exigirPermissao($pdo, 'equipe.gerenciar');
csrfExigir();

$erro  = '';
$aviso = '';

// ── Ações de gestão (sempre POST + CSRF) ─────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    $buscarModerador = static function (PDO $pdo, int $id): ?array {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $alvo = $stmt->fetch();

        return $alvo ?: null;
    };

    $redirecionar = static function (string $destino = 'equipe.php'): void {
        header('Location: ' . $destino);
        exit;
    };

    switch ($acao) {
        case 'criar_moderador':
            $nome  = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nome'] ?? '')) ?? '');
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');

            if (mb_strlen($nome, 'UTF-8') < 3) {
                flashSet('admin_erro', 'Informe o nome completo da pessoa moderadora.');
                $redirecionar();
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 160) {
                flashSet('admin_erro', 'Informe um e-mail válido para a conta de moderação.');
                $redirecionar();
            }

            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                flashSet('admin_erro', 'Já existe uma conta da equipe com este e-mail.');
                $redirecionar();
            }

            $senhaTemporaria = gerarSenhaTemporaria();
            $expiraEm        = date('Y-m-d H:i:s', time() + 48 * 3600);
            $token           = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare(
                "INSERT INTO admins
                    (nome, email, senha, papel, estado, senha_temporaria_expira_em,
                     sessao_token, criado_por, conta_teste)
                 VALUES (?, ?, ?, 'moderador', 'aguardando', ?, ?, ?, 0)"
            );
            $stmt->execute([
                $nome,
                $email,
                password_hash($senhaTemporaria, PASSWORD_DEFAULT),
                $expiraEm,
                $token,
                (int) $admin['id'],
            ]);

            $novoId = (int) $pdo->lastInsertId();

            registrarLogAdmin(
                $pdo,
                'conta_criada',
                'admin',
                $novoId,
                $nome,
                'E-mail de acesso: ' . $email . '. Senha temporária válida por 48 horas.'
            );

            flashSet('admin_credenciais', json_encode([
                'tipo'      => 'criada',
                'nome'      => $nome,
                'email'     => $email,
                'senha'     => $senhaTemporaria,
                'expira_em' => $expiraEm,
            ], JSON_UNESCAPED_UNICODE));

            flashSet('admin_sucesso', 'Conta de moderação criada. Entregue os dados de acesso à pessoa responsável.');
            $redirecionar();
            break;

        case 'redefinir_senha':
            $id   = isset($_POST['id']) && ctype_digit((string) $_POST['id']) ? (int) $_POST['id'] : 0;
            $alvo = $id > 0 ? $buscarModerador($pdo, $id) : null;

            if (!$alvo || $alvo['papel'] !== 'moderador') {
                flashSet('admin_erro', 'Conta de moderação não encontrada.');
                $redirecionar();
            }

            if ($alvo['estado'] === 'desativado') {
                flashSet('admin_erro', 'Reative a conta antes de gerar uma nova senha.');
                $redirecionar();
            }

            $senhaTemporaria = gerarSenhaTemporaria();
            $expiraEm        = date('Y-m-d H:i:s', time() + 48 * 3600);

            $pdo->prepare(
                "UPDATE admins
                 SET senha = ?,
                     estado = 'aguardando',
                     senha_temporaria_expira_em = ?,
                     sessao_token = ?
                 WHERE id = ? AND papel = 'moderador'"
            )->execute([
                password_hash($senhaTemporaria, PASSWORD_DEFAULT),
                $expiraEm,
                bin2hex(random_bytes(32)),
                (int) $alvo['id'],
            ]);

            registrarLogAdmin(
                $pdo,
                'senha_redefinida',
                'admin',
                (int) $alvo['id'],
                (string) $alvo['nome'],
                'Nova senha temporária válida por 48 horas; sessões anteriores invalidadas.'
            );

            flashSet('admin_credenciais', json_encode([
                'tipo'      => 'redefinida',
                'nome'      => $alvo['nome'],
                'email'     => $alvo['email'],
                'senha'     => $senhaTemporaria,
                'expira_em' => $expiraEm,
            ], JSON_UNESCAPED_UNICODE));

            flashSet('admin_sucesso', 'Senha temporária gerada para ' . $alvo['nome'] . '. Ela será exibida uma única vez.');
            $redirecionar();
            break;

        case 'desativar':
            $id   = isset($_POST['id']) && ctype_digit((string) $_POST['id']) ? (int) $_POST['id'] : 0;
            $alvo = $id > 0 ? $buscarModerador($pdo, $id) : null;

            if (!$alvo || $alvo['papel'] !== 'moderador') {
                flashSet('admin_erro', 'Somente contas de moderação podem ser desativadas.');
                $redirecionar();
            }

            if ((int) $alvo['id'] === (int) $admin['id']) {
                flashSet('admin_erro', 'Você não pode desativar a própria conta.');
                $redirecionar();
            }

            if ($alvo['estado'] === 'desativado') {
                flashSet('admin_aviso', 'Esta conta já está desativada.');
                $redirecionar();
            }

            $pdo->prepare(
                "UPDATE admins
                 SET estado = 'desativado',
                     senha_temporaria_expira_em = NULL,
                     sessao_token = ?
                 WHERE id = ? AND papel = 'moderador'"
            )->execute([bin2hex(random_bytes(32)), (int) $alvo['id']]);

            registrarLogAdmin(
                $pdo,
                'conta_desativada',
                'admin',
                (int) $alvo['id'],
                (string) $alvo['nome'],
                'Acesso revogado; sessões abertas encerradas imediatamente.'
            );

            flashSet('admin_sucesso', 'Conta de ' . $alvo['nome'] . ' desativada. O acesso foi revogado imediatamente.');
            $redirecionar();
            break;

        case 'reativar':
            $id   = isset($_POST['id']) && ctype_digit((string) $_POST['id']) ? (int) $_POST['id'] : 0;
            $alvo = $id > 0 ? $buscarModerador($pdo, $id) : null;

            if (!$alvo || $alvo['papel'] !== 'moderador') {
                flashSet('admin_erro', 'Somente contas de moderação podem ser reativadas.');
                $redirecionar();
            }

            if ($alvo['estado'] !== 'desativado') {
                flashSet('admin_aviso', 'Esta conta não está desativada.');
                $redirecionar();
            }

            $senhaTemporaria = gerarSenhaTemporaria();
            $expiraEm        = date('Y-m-d H:i:s', time() + 48 * 3600);

            $pdo->prepare(
                "UPDATE admins
                 SET senha = ?,
                     estado = 'aguardando',
                     senha_temporaria_expira_em = ?,
                     sessao_token = ?
                 WHERE id = ? AND papel = 'moderador'"
            )->execute([
                password_hash($senhaTemporaria, PASSWORD_DEFAULT),
                $expiraEm,
                bin2hex(random_bytes(32)),
                (int) $alvo['id'],
            ]);

            registrarLogAdmin(
                $pdo,
                'conta_reativada',
                'admin',
                (int) $alvo['id'],
                (string) $alvo['nome'],
                'Conta reativada com nova senha temporária válida por 48 horas.'
            );

            flashSet('admin_credenciais', json_encode([
                'tipo'      => 'reativada',
                'nome'      => $alvo['nome'],
                'email'     => $alvo['email'],
                'senha'     => $senhaTemporaria,
                'expira_em' => $expiraEm,
            ], JSON_UNESCAPED_UNICODE));

            flashSet('admin_sucesso', 'Conta de ' . $alvo['nome'] . ' reativada. Entregue a nova senha temporária.');
            $redirecionar();
            break;

        case 'alterar_senha_admin':
            $atual       = (string) ($_POST['senha_atual'] ?? '');
            $nova        = (string) ($_POST['senha_nova'] ?? '');
            $confirmacao = (string) ($_POST['senha_confirmacao'] ?? '');

            if (!password_verify($atual, (string) $admin['senha'])) {
                flashSet('admin_erro', 'A senha atual do administrador não confere.');
                $redirecionar('equipe.php?abrir=senha');
            }

            $requisitos = requisitosSenhaEquipe(
                $nova,
                $confirmacao,
                (string) $admin['email'],
                null,
                (string) $admin['senha']
            );

            if (!senhaEquipeValida($requisitos)) {
                flashSet('admin_erro', 'A nova senha não atende aos requisitos mínimos.');
                $redirecionar('equipe.php?abrir=senha');
            }

            $novoToken = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE admins SET senha = ?, sessao_token = ? WHERE id = ?")
                ->execute([password_hash($nova, PASSWORD_DEFAULT), $novoToken, (int) $admin['id']]);

            $_SESSION['admin_sessao_token'] = $novoToken;

            registrarLogAdmin(
                $pdo,
                'senha_alterada',
                'admin',
                (int) $admin['id'],
                (string) $admin['nome'],
                'Senha do administrador alterada; demais sessões invalidadas.'
            );

            flashSet('admin_sucesso', 'Senha do administrador alterada. As demais sessões foram encerradas.');
            $redirecionar();
            break;

        default:
            flashSet('admin_erro', 'Ação de equipe desconhecida.');
            $redirecionar();
    }
}

// ── Credenciais exibidas uma única vez ──────────────────────
$credenciais = null;
$credenciaisJson = flashGet('admin_credenciais');
if ($credenciaisJson !== '') {
    $decodificado = json_decode($credenciaisJson, true);
    if (is_array($decodificado) && !empty($decodificado['senha'])) {
        $credenciais = $decodificado;
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

// ── Dados da equipe ─────────────────────────────────────────
$todos = $pdo->query(
    "SELECT a.*, c.nome AS criado_por_nome
     FROM   admins a
     LEFT   JOIN admins c ON c.id = a.criado_por
     ORDER  BY (a.papel = 'administrador') DESC, a.nome ASC"
)->fetchAll();

$adminPrincipal = null;
$moderadores    = [];

foreach ($todos as $membro) {
    if ($membro['papel'] === 'administrador' && $adminPrincipal === null) {
        $adminPrincipal = $membro;
    } elseif ($membro['papel'] === 'moderador') {
        $moderadores[] = $membro;
    }
}

$resumoEquipe = ['ativo' => 0, 'aguardando' => 0, 'desativado' => 0];
foreach ($moderadores as $membro) {
    if (isset($resumoEquipe[$membro['estado']])) {
        $resumoEquipe[$membro['estado']]++;
    }
}

// ── Atividade recente por moderador ─────────────────────────
$atividadePorAdmin = [];
$idsModeradores = array_map(static fn ($m) => (int) $m['id'], $moderadores);

if ($idsModeradores) {
    $marcadores = implode(',', array_fill(0, count($idsModeradores), '?'));
    $stmt = $pdo->prepare("
        SELECT l.*, a.nome AS admin_nome, a.papel AS admin_papel
        FROM   logs_admin l
        LEFT   JOIN admins a ON a.id = l.admin_id
        WHERE  l.admin_id IN ($marcadores)
           OR  (l.alvo_tipo = 'admin' AND l.alvo_id IN ($marcadores))
        ORDER  BY l.criado_em DESC, l.id DESC
        LIMIT  600
    ");
    $stmt->execute(array_merge($idsModeradores, $idsModeradores));
    $logsEquipe = $stmt->fetchAll();

    foreach ($logsEquipe as $log) {
        $ator = (int) ($log['admin_id'] ?? 0);
        $alvo = $log['alvo_tipo'] === 'admin' ? (int) ($log['alvo_id'] ?? 0) : 0;

        if (in_array($ator, $idsModeradores, true)) {
            $atividadePorAdmin[$ator][] = $log;
        }

        if ($alvo > 0 && $alvo !== $ator && in_array($alvo, $idsModeradores, true)) {
            $atividadePorAdmin[$alvo][] = $log;
        }
    }

    foreach ($atividadePorAdmin as $id => $itens) {
        $atividadePorAdmin[$id] = array_slice($itens, 0, 80);
    }
}

$abrirSenha = isset($_GET['abrir']) && $_GET['abrir'] === 'senha';

$admin_page_title    = 'Equipe';
$admin_page_subtitle = 'Gerencie quem pode moderar o ONGHelp.';
$admin_active        = 'equipe';
$admin_page_actions  = '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriarModerador">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar moderador</button>';
include '../includes/admin_header.php';
?>

<div class="admin-kpis admin-equipe-kpis">
  <div class="admin-kpi admin-kpi-aprovada">
    <span class="admin-kpi-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
    <span class="admin-kpi-texto">
      <span class="admin-kpi-valor"><?= $resumoEquipe['ativo'] ?></span>
      <span class="admin-kpi-rotulo">Moderadores ativos</span>
    </span>
  </div>
  <div class="admin-kpi admin-kpi-pendente">
    <span class="admin-kpi-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
    <span class="admin-kpi-texto">
      <span class="admin-kpi-valor"><?= $resumoEquipe['aguardando'] ?></span>
      <span class="admin-kpi-rotulo">Aguardando primeiro acesso</span>
    </span>
  </div>
  <div class="admin-kpi admin-kpi-suspensa">
    <span class="admin-kpi-icon"><i class="bi bi-person-slash" aria-hidden="true"></i></span>
    <span class="admin-kpi-texto">
      <span class="admin-kpi-valor"><?= $resumoEquipe['desativado'] ?></span>
      <span class="admin-kpi-rotulo">Desativados</span>
    </span>
  </div>
</div>

<?php if ($adminPrincipal): ?>
  <section class="admin-card admin-equipe-admin-panel" aria-labelledby="adminPrincipalTitulo">
    <span class="admin-equipe-admin-icone" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>

    <div class="admin-equipe-admin-info">
      <div class="admin-equipe-admin-nome">
        <h2 id="adminPrincipalTitulo"><?= htmlspecialchars($adminPrincipal['nome']) ?></h2>
        <span class="admin-selo-contorno">Administrador</span>
      </div>
      <p class="admin-equipe-admin-texto">
        Conta compartilhada por até 3 pessoas de confiança. As ações dela não identificam quem as fez.
      </p>
      <p class="admin-equipe-admin-dica">Troque a senha sempre que alguém deixar a equipe.</p>
    </div>

    <button type="button" class="btn btn-outline-primary admin-equipe-admin-acao"
            data-bs-toggle="modal" data-bs-target="#modalSenhaAdmin">
      Alterar senha
    </button>
  </section>
<?php endif; ?>

<section class="admin-card admin-equipe-membros">
  <div class="admin-card-header">
    <div class="admin-equipe-painel-titulo">
      <h2 class="admin-equipe-titulo">Moderadores</h2>
      <span class="admin-count" aria-label="<?= count($moderadores) ?> moderador(es)"><?= count($moderadores) ?></span>
    </div>
  </div>

  <?php if (empty($moderadores)): ?>
    <div class="admin-vazio admin-equipe-vazio">
      <i class="bi bi-people" aria-hidden="true"></i>
      <strong>Nenhum moderador ainda</strong>
      <span>Adicione pessoas de confiança para ajudar a analisar os cadastros.</span>
      <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalCriarModerador">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar moderador
      </button>
      <a href="../pages/equipe.php" class="admin-link-terciario mt-2">Ver a página de recrutamento <span aria-hidden="true">→</span></a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table admin-table admin-table-equipe align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Pessoa</th>
            <th scope="col">Status</th>
            <th scope="col">Último acesso</th>
            <th scope="col">Criado por</th>
            <th scope="col"><span class="visually-hidden">Ações</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($moderadores as $membro): ?>
            <?php
            $estado   = (string) $membro['estado'];
            $expiraEm = $membro['senha_temporaria_expira_em'] ? strtotime((string) $membro['senha_temporaria_expira_em']) : false;
            $expirada = $estado === 'aguardando' && (!$expiraEm || $expiraEm <= time());
            $criador  = trim((string) ($membro['criado_por_nome'] ?? '')) ?: 'Equipe ONGHelp';
            $idDrawer = 'drawerAtividade' . (int) $membro['id'];
            ?>
            <tr>
              <td>
                <div class="admin-membro">
                  <span class="admin-avatar" aria-hidden="true"><?= htmlspecialchars(adminIniciais((string) $membro['nome'])) ?></span>
                  <div class="admin-membro-info">
                    <strong>
                      <?= htmlspecialchars($membro['nome']) ?>
                      <?php if (!empty($membro['conta_teste'])): ?>
                        <span class="admin-teste-badge">Teste</span>
                      <?php endif; ?>
                    </strong>
                    <span class="admin-membro-email"><?= htmlspecialchars($membro['email']) ?></span>
                  </div>
                </div>
              </td>
              <td>
                <span class="admin-estado is-<?= htmlspecialchars($estado) ?>">
                  <?= htmlspecialchars(adminEstadoLabel($estado)) ?>
                </span>
                <?php if ($expirada): ?>
                  <small class="admin-estado-nota is-erro">senha temporária expirada</small>
                <?php endif; ?>
              </td>
              <td class="admin-membro-data">
                <?= !empty($membro['ultimo_acesso_em']) ? fmtDataHora($membro['ultimo_acesso_em']) : 'Nunca acessou' ?>
              </td>
              <td class="admin-membro-criado">
                <span><?= fmtData($membro['criado_em']) ?></span>
                <small>por <?= htmlspecialchars($criador) ?></small>
              </td>
              <td class="text-end">
                <div class="dropdown admin-row-actions">
                  <button class="admin-actions-btn" type="button" data-bs-toggle="dropdown"
                          data-bs-strategy="fixed" aria-expanded="false"
                          aria-label="Ações de <?= htmlspecialchars($membro['nome']) ?>">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end admin-row-menu">
                    <li>
                      <button type="button" class="dropdown-item" data-bs-toggle="offcanvas"
                              data-bs-target="#<?= $idDrawer ?>" aria-controls="<?= $idDrawer ?>">
                        <i class="bi bi-activity" aria-hidden="true"></i> Ver atividade
                      </button>
                    </li>
                    <?php if ($estado !== 'desativado'): ?>
                      <li>
                        <button type="button" class="dropdown-item"
                                data-admin-reset
                                data-id="<?= (int) $membro['id'] ?>"
                                data-nome="<?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                data-email="<?= htmlspecialchars($membro['email'], ENT_QUOTES, 'UTF-8') ?>"
                                data-bs-toggle="modal" data-bs-target="#modalRedefinir">
                          <i class="bi bi-key" aria-hidden="true"></i> Redefinir senha
                        </button>
                      </li>
                      <li>
                        <button type="button" class="dropdown-item is-danger"
                                data-admin-desativar
                                data-id="<?= (int) $membro['id'] ?>"
                                data-nome="<?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                data-bs-toggle="modal" data-bs-target="#modalDesativar">
                          <i class="bi bi-person-slash" aria-hidden="true"></i> Desativar
                        </button>
                      </li>
                    <?php else: ?>
                      <li>
                        <button type="button" class="dropdown-item is-success"
                                data-admin-reativar
                                data-id="<?= (int) $membro['id'] ?>"
                                data-nome="<?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                data-bs-toggle="modal" data-bs-target="#modalReativar">
                          <i class="bi bi-person-check" aria-hidden="true"></i> Reativar
                        </button>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ── Drawer de atividade por moderador ─────────────────── -->
<?php foreach ($moderadores as $membro): ?>
  <?php $atividade = $atividadePorAdmin[(int) $membro['id']] ?? []; ?>
  <div class="offcanvas offcanvas-start admin-offcanvas admin-atividade-offcanvas"
       tabindex="-1" id="drawerAtividade<?= (int) $membro['id'] ?>"
       aria-labelledby="drawerAtividadeLabel<?= (int) $membro['id'] ?>">
    <div class="offcanvas-header">
      <div class="admin-atividade-head">
        <span class="admin-avatar admin-avatar-lg admin-avatar-drawer" aria-hidden="true"><?= htmlspecialchars(adminIniciais((string) $membro['nome'])) ?></span>
        <div>
          <h5 class="offcanvas-title" id="drawerAtividadeLabel<?= (int) $membro['id'] ?>">
            <?= htmlspecialchars($membro['nome']) ?>
          </h5>
          <span class="admin-atividade-sub">Moderador · <?= htmlspecialchars(adminEstadoLabel((string) $membro['estado'])) ?></span>
          <span class="admin-atividade-sub">
            Último acesso: <?= !empty($membro['ultimo_acesso_em']) ? fmtDataHora($membro['ultimo_acesso_em']) : 'Nunca acessou' ?>
          </span>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>

    <div class="offcanvas-body admin-atividade-body">
      <div class="admin-segmentado" role="group" aria-label="Filtrar atividade"
           data-segmentar-atividade>
        <button type="button" class="admin-segmento is-active" data-segmento="tudo" aria-pressed="true">Tudo</button>
        <button type="button" class="admin-segmento" data-segmento="decisoes" aria-pressed="false">Decisões</button>
        <button type="button" class="admin-segmento" data-segmento="noticias" aria-pressed="false">Notícias</button>
        <button type="button" class="admin-segmento" data-segmento="conta" aria-pressed="false">Conta</button>
      </div>

      <?php if (empty($atividade)): ?>
        <div class="admin-vazio admin-vazio-sm admin-vazio-drawer">
          <i class="bi bi-activity" aria-hidden="true"></i>
          <strong>Nenhuma atividade registrada</strong>
          <span>As ações aparecem aqui depois do primeiro acesso.</span>
        </div>
      <?php else: ?>
        <ol class="admin-timeline admin-atividade-linha">
          <?php foreach ($atividade as $log): ?>
            <?php
            $segmento  = segmentoAtividadeAdmin((string) $log['acao']);
            $ator      = trim((string) ($log['admin_nome'] ?? '')) ?: 'Equipe ONGHelp';
            $daPessoa  = (int) ($log['admin_id'] ?? 0) === (int) $membro['id'];
            ?>
            <li data-segmento-item="<?= htmlspecialchars($segmento) ?>">
              <strong class="admin-atividade-acao"><?= htmlspecialchars(resumoAtividadeAdmin($log)) ?></strong>
              <?php if (!empty($log['detalhe'])): ?>
                <span class="admin-atividade-detalhe"><?= htmlspecialchars($log['detalhe']) ?></span>
              <?php endif; ?>
              <?php if (!$daPessoa): ?>
                <span class="admin-atividade-detalhe">por <?= htmlspecialchars($ator) ?></span>
              <?php endif; ?>
              <time class="admin-atividade-data" datetime="<?= htmlspecialchars(date('c', strtotime((string) $log['criado_em'])), ENT_QUOTES, 'UTF-8') ?>">
                <?= fmtDataHora($log['criado_em']) ?>
              </time>
            </li>
          <?php endforeach; ?>
        </ol>
        <p class="admin-atividade-vazio d-none" data-atividade-vazio>Nenhum registro neste filtro.</p>
      <?php endif; ?>
    </div>

    <div class="admin-atividade-footer">
      <a href="logs.php?pessoa=<?= (int) $membro['id'] ?>" class="admin-link-terciario admin-atividade-link">
        Ver no log completo <span aria-hidden="true">→</span>
      </a>
    </div>
  </div>
<?php endforeach; ?>

<!-- ── Modal: adicionar moderador ────────────────────────── -->
<div class="modal fade" id="modalCriarModerador" tabindex="-1" aria-labelledby="modalCriarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered admin-modal-dialog">
    <div class="modal-content admin-modal">
      <form method="POST" action="equipe.php">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="criar_moderador">

        <div class="modal-header">
          <h2 class="modal-title admin-modal-titulo" id="modalCriarTitulo">Adicionar moderador</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="admin-label" for="novoModeradorNome">Nome de exibição</label>
            <input type="text" class="form-control" name="nome" id="novoModeradorNome"
                   maxlength="160" required autocomplete="off" placeholder="Ex.: Maria Souza">
          </div>

          <div class="mb-3">
            <label class="admin-label" for="novoModeradorEmail">E-mail</label>
            <input type="email" class="form-control" name="email" id="novoModeradorEmail"
                   maxlength="160" required autocomplete="off" placeholder="maria@exemplo.com">
          </div>

          <div class="admin-modal-aviso">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            A pessoa receberá uma senha temporária válida por 48 horas e criará a própria senha no primeiro acesso.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link admin-btn-terciario" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar conta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: redefinir senha ────────────────────────────── -->
<div class="modal fade" id="modalRedefinir" tabindex="-1" aria-labelledby="modalRedefinirTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable admin-modal-dialog admin-modal-dialog-sm">
    <div class="modal-content admin-modal">
      <form method="POST" action="equipe.php">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="redefinir_senha">
        <input type="hidden" name="id" id="redefinirId" value="">

        <div class="modal-header">
          <h2 class="modal-title admin-modal-titulo" id="modalRedefinirTitulo">Redefinir senha</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="admin-modal-texto mb-0">
            Redefinir a senha de <strong id="redefinirNome">esta pessoa</strong>?
            A senha atual deixa de valer e as sessões dela serão encerradas.
          </p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link admin-btn-terciario" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Redefinir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: desativar ──────────────────────────────────── -->
<div class="modal fade" id="modalDesativar" tabindex="-1" aria-labelledby="modalDesativarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered admin-modal-dialog admin-modal-dialog-sm">
    <div class="modal-content admin-modal">
      <form method="POST" action="equipe.php">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="desativar">
        <input type="hidden" name="id" id="desativarId" value="">

        <div class="modal-header">
          <h2 class="modal-title admin-modal-titulo" id="modalDesativarTitulo">Desativar conta</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="admin-modal-texto mb-0">
            Desativar <strong id="desativarNome">esta pessoa</strong>?
            A pessoa perde o acesso imediatamente. O histórico de ações é mantido.
          </p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link admin-btn-terciario" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Desativar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: reativar ───────────────────────────────────── -->
<div class="modal fade" id="modalReativar" tabindex="-1" aria-labelledby="modalReativarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered admin-modal-dialog admin-modal-dialog-sm">
    <div class="modal-content admin-modal">
      <form method="POST" action="equipe.php">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="reativar">
        <input type="hidden" name="id" id="reativarId" value="">

        <div class="modal-header">
          <h2 class="modal-title admin-modal-titulo" id="modalReativarTitulo">Reativar conta</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="admin-modal-texto mb-0">
            Reativar <strong id="reativarNome">esta pessoa</strong>?
            Será gerada uma nova senha temporária válida por 48 horas, exibida uma única vez.
            A pessoa deverá concluir o primeiro acesso antes de usar o painel.
          </p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link admin-btn-terciario" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Reativar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: senha do administrador ─────────────────────── -->
<div class="modal fade" id="modalSenhaAdmin" tabindex="-1" aria-labelledby="modalSenhaAdminTitulo"
     aria-hidden="true" <?= $abrirSenha ? 'data-auto-open="true"' : '' ?>>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable admin-modal-dialog">
    <div class="modal-content admin-modal">
      <form method="POST" action="equipe.php" id="formSenhaAdmin"
            data-password-form data-email="<?= htmlspecialchars($admin['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="alterar_senha_admin">

        <div class="modal-header">
          <h2 class="modal-title admin-modal-titulo" id="modalSenhaAdminTitulo">Alterar senha do administrador</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="admin-modal-texto">
            A troca invalida as demais sessões abertas da conta administradora.
          </p>

          <div class="mb-3">
            <label class="admin-label" for="senhaAtualAdmin">Senha atual</label>
            <input type="password" class="form-control" name="senha_atual" id="senhaAtualAdmin"
                   autocomplete="current-password" required>
          </div>

          <div class="mb-3">
            <label class="admin-label" for="senhaNovaAdmin">Nova senha</label>
            <div class="auth-input-wrap">
              <input type="password" class="form-control" name="senha_nova" id="senhaNovaAdmin"
                     autocomplete="new-password" required>
              <button class="auth-eye" type="button"
                      data-password-toggle="senhaNovaAdmin" aria-label="Mostrar senha" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="admin-label" for="senhaConfirmaAdmin">Confirme a nova senha</label>
            <div class="auth-input-wrap">
              <input type="password" class="form-control" name="senha_confirmacao" id="senhaConfirmaAdmin"
                     autocomplete="new-password" required>
              <button class="auth-eye" type="button"
                      data-password-toggle="senhaConfirmaAdmin" aria-label="Mostrar senha" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <ul class="admin-senha-checklist" aria-label="Requisitos da nova senha">
            <li data-check="minimo"><i class="bi bi-circle" aria-hidden="true"></i> Mínimo de 10 caracteres</li>
            <li data-check="atual"><i class="bi bi-circle" aria-hidden="true"></i> Diferente da senha atual</li>
            <li data-check="email"><i class="bi bi-circle" aria-hidden="true"></i> Não contém seu e-mail</li>
            <li data-check="iguais"><i class="bi bi-circle" aria-hidden="true"></i> As duas senhas são iguais</li>
          </ul>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link admin-btn-terciario" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Alterar senha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal: credenciais exibidas uma única vez ─────────── -->
<?php if ($credenciais): ?>
  <?php
  $nomeCredencial = (string) ($credenciais['nome'] ?? '');
  $tituloCredencial = ($credenciais['tipo'] ?? 'criada') === 'criada'
      ? 'Conta de ' . $nomeCredencial . ' criada'
      : 'Nova senha gerada para ' . $nomeCredencial;
  $enderecoPainel = urlPainelAdmin();
  $mensagemWhats = 'Olá, ' . $nomeCredencial . '! Sua conta de moderador no ONGHelp foi criada.'
      . ' Acesso: ' . $enderecoPainel
      . ' · E-mail: ' . (string) ($credenciais['email'] ?? '')
      . ' · Senha temporária: ' . (string) ($credenciais['senha'] ?? '')
      . ' (vale por 48 horas). No primeiro acesso você vai criar sua própria senha.'
      . ' Não compartilhe com ninguém.';
  ?>
  <div class="modal fade" id="modalCredenciais" tabindex="-1" aria-labelledby="modalCredenciaisTitulo"
       aria-hidden="true" data-auto-open="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable admin-modal-dialog">
      <div class="modal-content admin-modal admin-modal-credenciais">
        <div class="modal-header admin-modal-header-centro">
          <span class="admin-credencial-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
          <h2 class="modal-title admin-modal-titulo" id="modalCredenciaisTitulo"><?= htmlspecialchars($tituloCredencial) ?></h2>
        </div>

        <div class="modal-body">
          <div class="admin-credencial-caixa">
            <div class="admin-credencial-linha">
              <span>E-mail</span>
              <div class="admin-credencial-valor">
                <code id="credencialEmail"><?= htmlspecialchars($credenciais['email'] ?? '') ?></code>
                <button type="button" class="admin-copy" data-copiar="<?= htmlspecialchars($credenciais['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-copiar-toast="E-mail copiado"
                        aria-label="Copiar e-mail" title="Copiar e-mail">
                  <i class="bi bi-clipboard" aria-hidden="true"></i>
                </button>
              </div>
            </div>
            <div class="admin-credencial-linha">
              <span>Senha temporária</span>
              <div class="admin-credencial-valor">
                <code class="admin-credencial-senha" id="credencialSenha"><?= htmlspecialchars($credenciais['senha'] ?? '') ?></code>
                <button type="button" class="admin-copy" data-copiar="<?= htmlspecialchars($credenciais['senha'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-copiar-toast="Senha copiada"
                        aria-label="Copiar senha temporária" title="Copiar senha temporária">
                  <i class="bi bi-clipboard" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="admin-modal-aviso mt-3">
            <i class="bi bi-eye-slash" aria-hidden="true"></i>
            <span><strong>Copie agora. Esta senha não será exibida novamente. Vale por 48 horas.</strong></span>
          </div>

          <label class="admin-label mt-3" for="mensagemWhatsApp">Prévia da mensagem de WhatsApp</label>
          <textarea id="mensagemWhatsApp" class="form-control admin-wa-preview" rows="5" readonly
                    data-mensagem-whatsapp><?= htmlspecialchars($mensagemWhats) ?></textarea>
        </div>

        <div class="modal-footer admin-modal-footer-credenciais">
          <button type="button" class="btn btn-primary" data-copiar-mensagem="mensagemWhatsApp">
            <i class="bi bi-whatsapp" aria-hidden="true"></i> Copiar mensagem para WhatsApp
          </button>
          <button type="button" class="btn btn-outline-secondary"
                  data-copiar="<?= htmlspecialchars($credenciais['senha'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            Copiar só a senha
          </button>
          <button type="button" class="btn btn-link admin-btn-terciario" data-bs-dismiss="modal">Concluir</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="admin-toast" data-admin-toast role="status" aria-live="polite">
  <i class="bi bi-check-circle" aria-hidden="true"></i>
  <span data-admin-toast-texto>Mensagem copiada</span>
</div>

<?php
$admin_script = 'admin-equipe.js';
include '../includes/admin_footer.php';
?>
