<?php
// ============================================================
// admin/verificar.php — Análise detalhada de um cadastro
// ============================================================

require_once '../includes/admin_auth.php';
require_once '../includes/noticias.php';

$admin = exigirPermissao($pdo, 'ong.analisar');

$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare(
    "SELECT u.*, a.nome AS verificado_por_nome
     FROM   usuarios u
     LEFT   JOIN admins a ON a.id = u.verificado_por
     WHERE  u.id = ?"
);
$stmt->execute([$id]);
$ong = $stmt->fetch();

if (!$ong) {
    flashSet('admin_erro', 'Cadastro não encontrado.');
    header('Location: index.php');
    exit;
}

// ── Bloqueio e liberação de publicações de notícias ─────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrfExigir();

    $acaoPublicacoes = trim((string) ($_POST['acao_publicacoes'] ?? ''));
    $motivoPublicacoes = trim((string) ($_POST['motivo'] ?? ''));

    if (!in_array($acaoPublicacoes, ['bloquear_publicacoes', 'liberar_publicacoes'], true)) {
        flashSet('admin_erro', 'Ação de publicações inválida.');
        header('Location: verificar.php?id=' . $id);
        exit;
    }

    try {
        if ($acaoPublicacoes === 'bloquear_publicacoes') {
            if ($motivoPublicacoes === '') {
                throw new InvalidArgumentException('Informe o motivo do bloqueio.');
            }

            $motivoPublicacoes = mb_substr($motivoPublicacoes, 0, 500, 'UTF-8');

            $pdo->beginTransaction();

            bloquearPublicacoesNoticias($pdo, $id, $motivoPublicacoes, (int) $admin['id']);

            registrarLogAdmin(
                $pdo,
                'publicacoes_bloqueadas',
                'ong',
                $id,
                (string) $ong['nome'],
                'Bloqueio pela análise da ONG. Motivo: ' . $motivoPublicacoes
            );

            $pdo->commit();
            flashSet('admin_sucesso', 'Publicações da ONG bloqueadas. Ela verá o motivo em Minhas notícias.');
        } else {
            $bloqueioAtual = bloqueioPublicacaoNoticias($pdo, $id);

            if (!$bloqueioAtual) {
                flashSet('admin_aviso', 'Não há bloqueio de publicações ativo nesta ONG.');
                header('Location: verificar.php?id=' . $id);
                exit;
            }

            $pdo->beginTransaction();

            liberarPublicacoesNoticias($pdo, $id, (int) $admin['id']);

            registrarLogAdmin(
                $pdo,
                'publicacoes_liberadas',
                'ong',
                $id,
                (string) $ong['nome'],
                'Liberação pela análise da ONG.'
            );

            $pdo->commit();
            flashSet('admin_sucesso', 'Publicações da ONG liberadas.');
        }
    } catch (InvalidArgumentException $erroPublicacoes) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flashSet('admin_erro', $erroPublicacoes->getMessage());
    } catch (Throwable $erroPublicacoes) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[ONGHelp] Publicações da ONG: ' . $erroPublicacoes->getMessage());
        flashSet('admin_erro', 'Não foi possível alterar as publicações da ONG. Tente novamente.');
    }

    header('Location: verificar.php?id=' . $id);
    exit;
}

$bloqueioPublicacoes = bloqueioPublicacaoNoticias($pdo, $id);

// ── Histórico de verificações ───────────────────────────────
$stmtHist = $pdo->prepare("
    SELECT v.*, a.nome AS admin_nome
    FROM   verificacoes v
    LEFT   JOIN admins a ON a.id = v.admin_id
    WHERE  v.usuario_id = ?
    ORDER  BY v.criado_em DESC, v.id DESC
");
$stmtHist->execute([$id]);
$historico = $stmtHist->fetchAll();

// Autoria e data da última decisão. As decisões antigas, anteriores às
// contas individuais, continuam atribuídas à conta compartilhada.
$verificadorDecisao = trim((string) ($ong['verificado_por_nome'] ?? '')) ?: 'Equipe ONGHelp';
$dataDecisao = (string) ($ong['verificada_em'] ?: ($ong['aprovado_em'] ?: ($historico[0]['criado_em'] ?? '')));

$bloqueantes = checklistBloqueantes();
$atencao     = checklistAtencao();

$cnpjDigitos = somenteDigitos($ong['cnpj']);
$consultaCnpj = 'https://solucoes.receita.fazenda.gov.br/servicos/cnpjreva/cnpjreva_solicitacao.asp';
$consultaGoogle = 'https://www.google.com/search?q=' . urlencode($ong['nome'] . ' ' . $cnpjDigitos);
$mapaOsc = 'https://mapaosc.ipea.gov.br/';
$linkCanal = canalUrl((string) $ong['canal_tipo'], (string) $ong['canal_valor']);

$admin_page_title    = 'Analisar cadastro';
$admin_page_subtitle = 'Cadastro #' . (int) $ong['id'] . ' · ' . statusLabel($ong['status']);
$admin_active        = [
    'pendente' => 'pendentes',
    'aprovada' => 'aprovadas',
    'recusada' => 'recusadas',
    'suspensa' => 'suspensas',
][$ong['status']] ?? '';
include '../includes/admin_header.php';
?>

<div class="admin-analise-topo">
  <a href="index.php" class="admin-back-link">
    <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar para a fila
  </a>
  <div class="admin-analise-status">
    <span class="admin-status <?= statusClass($ong['status']) ?>">
      <?= statusLabel($ong['status']) ?>
    </span>
    <?php if ($ong['status'] !== 'pendente' && $dataDecisao !== ''): ?>
      <span class="admin-status-autor">por <?= htmlspecialchars($verificadorDecisao) ?> · <?= fmtData($dataDecisao) ?></span>
    <?php endif; ?>
  </div>
</div>

<?php if ($ong['status'] === 'pendente'): ?>
  <div class="alert alert-warning d-flex align-items-start gap-2">
    <i class="bi bi-hourglass-split mt-1" aria-hidden="true"></i>
    <div>
      <strong>Cadastro aguardando análise.</strong>
      Confira os dados nas fontes oficiais antes de aprovar. Nada deste cadastro está público.
    </div>
  </div>
<?php elseif ($ong['status'] === 'aprovada' && empty($ong['email_aprovacao_enviado_em'])): ?>
  <div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-envelope-exclamation mt-1" aria-hidden="true"></i>
    <div>
      <strong>Aprovação registrada, e-mail ainda não enviado.</strong>
      Envie manualmente pelo Gmail compartilhado e marque abaixo. Nunca envie a senha.
    </div>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- ── Coluna esquerda: dados ── -->
  <div class="col-xl-8">

    <div class="admin-card">
      <div class="admin-card-header">
        <div>
          <p class="admin-eyebrow">Identificação</p>
          <h2 class="admin-ong-titulo"><?= htmlspecialchars($ong['nome']) ?></h2>
          <p class="admin-card-subtitle">
            <?= htmlspecialchars($ong['area']) ?> · <?= htmlspecialchars($ong['cidade'] ?: 'Brasil') ?><?= $ong['estado'] ? ' · ' . htmlspecialchars($ong['estado']) : '' ?>
          </p>
        </div>
        <span class="admin-id">#<?= (int) $ong['id'] ?></span>
      </div>

      <div class="admin-dados-grid">
        <div class="admin-dado">
          <span class="admin-dado-rotulo">CNPJ</span>
          <span class="admin-dado-valor admin-identificador">
            <span><?= htmlspecialchars(mascaraCnpj($ong['cnpj'])) ?></span>
            <button type="button" class="admin-copy" data-copiar="<?= htmlspecialchars($cnpjDigitos) ?>" aria-label="Copiar CNPJ" title="Copiar CNPJ">
              <i class="bi bi-clipboard" aria-hidden="true"></i>
            </button>
          </span>
        </div>
        <div class="admin-dado">
          <span class="admin-dado-rotulo">E-mail de acesso</span>
          <span class="admin-dado-valor admin-identificador">
            <span><?= htmlspecialchars($ong['email']) ?></span>
            <button type="button" class="admin-copy" data-copiar="<?= htmlspecialchars($ong['email']) ?>" aria-label="Copiar e-mail" title="Copiar e-mail">
              <i class="bi bi-clipboard" aria-hidden="true"></i>
            </button>
          </span>
        </div>
        <div class="admin-dado">
          <span class="admin-dado-rotulo">Cadastro</span>
          <span class="admin-dado-valor"><?= fmtDataHora($ong['criado_em']) ?></span>
        </div>
        <div class="admin-dado">
          <span class="admin-dado-rotulo">Última verificação</span>
          <span class="admin-dado-valor"><?= $ong['verificada_em'] ? fmtDataHora($ong['verificada_em']) : '—' ?></span>
        </div>
        <div class="admin-dado">
          <span class="admin-dado-rotulo">Aprovada em</span>
          <span class="admin-dado-valor"><?= $ong['aprovado_em'] ? fmtDataHora($ong['aprovado_em']) : '—' ?></span>
        </div>
        <div class="admin-dado">
          <span class="admin-dado-rotulo">E-mail de aprovação</span>
          <span class="admin-dado-valor">
            <?= $ong['email_aprovacao_enviado_em'] ? fmtDataHora($ong['email_aprovacao_enviado_em']) : 'não enviado' ?>
          </span>
        </div>
      </div>

      <?php if (!empty($ong['descricao'])): ?>
        <div class="admin-descricao">
          <span class="admin-dado-rotulo">Descrição enviada pela ONG</span>
          <p><?= nl2br(htmlspecialchars($ong['descricao'])) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="admin-card mt-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Responsável</h2>
          <p class="admin-card-subtitle">Dados privados, visíveis apenas neste painel.</p>
        </div>
      </div>

      <div class="admin-dados-grid">
        <div class="admin-dado">
          <span class="admin-dado-rotulo">Responsável legal</span>
          <span class="admin-dado-valor"><?= htmlspecialchars($ong['responsavel_nome'] ?: '—') ?></span>
        </div>
        <div class="admin-dado">
          <span class="admin-dado-rotulo">Cargo</span>
          <span class="admin-dado-valor"><?= htmlspecialchars($ong['responsavel_cargo'] ?: '—') ?></span>
        </div>
        <div class="admin-dado admin-dado-wide">
          <span class="admin-dado-rotulo">Declaração de autorização</span>
          <span class="admin-dado-valor">
            <?php if (!empty($ong['responsavel_autorizacao'])): ?>
              <span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Aceita no cadastro</span>
            <?php else: ?>
              <span class="text-danger"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Não aceita</span>
            <?php endif; ?>
          </span>
        </div>
      </div>

      <?php if (!empty($ong['motivo_recusa'])): ?>
        <div class="admin-descricao mt-4">
          <span class="admin-dado-rotulo">Motivo registrado na última recusa/suspensão</span>
          <p><?= nl2br(htmlspecialchars($ong['motivo_recusa'])) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="admin-card mt-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Localização e canal público</h2>
          <p class="admin-card-subtitle">Confira os dados nas fontes oficiais antes de decidir.</p>
        </div>
      </div>

      <section class="admin-secao">
        <h3 class="admin-secao-titulo"><i class="bi bi-geo-alt" aria-hidden="true"></i> Localização</h3>
        <div class="admin-dados-grid">
          <div class="admin-dado">
            <span class="admin-dado-rotulo">Cidade</span>
            <span class="admin-dado-valor"><?= htmlspecialchars($ong['cidade'] ?: '—') ?></span>
          </div>
          <div class="admin-dado">
            <span class="admin-dado-rotulo">UF</span>
            <span class="admin-dado-valor"><?= htmlspecialchars($ong['estado'] ?: '—') ?></span>
          </div>
        </div>
      </section>

      <section class="admin-secao">
        <h3 class="admin-secao-titulo"><i class="bi bi-link-45deg" aria-hidden="true"></i> Canal público</h3>
        <div class="admin-dados-grid">
          <div class="admin-dado">
            <span class="admin-dado-rotulo">Tipo</span>
            <span class="admin-dado-valor"><?= htmlspecialchars(ucfirst($ong['canal_tipo'])) ?></span>
          </div>
          <div class="admin-dado admin-dado-wide">
            <span class="admin-dado-rotulo">Canal</span>
            <span class="admin-dado-valor admin-identificador">
              <?php if ($linkCanal !== ''): ?>
                <a href="<?= htmlspecialchars($linkCanal) ?>" target="_blank" rel="noopener noreferrer">
                  <?= htmlspecialchars($ong['canal_valor'] ?: '—') ?>
                  <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                </a>
              <?php else: ?>
                <span><?= htmlspecialchars($ong['canal_valor'] ?: '—') ?></span>
              <?php endif; ?>
              <?php if (!empty($ong['canal_valor'])): ?>
                <button type="button" class="admin-copy" data-copiar="<?= htmlspecialchars($ong['canal_valor']) ?>" aria-label="Copiar canal público" title="Copiar canal público">
                  <i class="bi bi-clipboard" aria-hidden="true"></i>
                </button>
              <?php endif; ?>
            </span>
          </div>
        </div>
      </section>
    </div>

    <div class="admin-card mt-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">PIX</h2>
          <p class="admin-card-subtitle">A chave precisa ser o próprio CNPJ da ONG.</p>
        </div>
      </div>

      <div class="admin-dados-grid">
        <div class="admin-dado admin-dado-wide">
          <span class="admin-dado-rotulo">Chave PIX</span>
          <span class="admin-dado-valor admin-identificador">
            <span><?= htmlspecialchars($ong['pix_chave']) ?></span>
            <button type="button" class="admin-copy" data-copiar="<?= htmlspecialchars($ong['pix_chave']) ?>" aria-label="Copiar chave PIX" title="Copiar chave PIX">
              <i class="bi bi-clipboard" aria-hidden="true"></i>
            </button>
            <?php if (somenteDigitos($ong['pix_chave']) === $cnpjDigitos): ?>
              <span class="admin-status status-aprovada"><i class="bi bi-check" aria-hidden="true"></i> igual ao CNPJ</span>
            <?php else: ?>
              <span class="admin-status status-recusada">divergente do CNPJ</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>

    <div class="admin-card mt-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Fontes para conferência</h2>
          <p class="admin-card-subtitle">Abra em outra aba e confira um item por vez.</p>
        </div>
      </div>
      <div class="admin-fontes">
        <a href="<?= htmlspecialchars($consultaCnpj) ?>" target="_blank" rel="noopener noreferrer" class="admin-fonte">
          <i class="bi bi-building-check"></i>
          <span><strong>CNPJ na Receita</strong><small>Situação, razão social, natureza e endereço</small></span>
          <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <a href="<?= htmlspecialchars($consultaGoogle) ?>" target="_blank" rel="noopener noreferrer" class="admin-fonte">
          <i class="bi bi-google"></i>
          <span><strong>Buscar no Google</strong><small>Nome, CNPJ, notícias e reclamações</small></span>
          <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <?php if ($linkCanal !== ''): ?>
          <a href="<?= htmlspecialchars($linkCanal) ?>" target="_blank" rel="noopener noreferrer" class="admin-fonte">
            <i class="bi bi-globe2"></i>
            <span><strong>Canal público informado</strong><small>Site, rede social ou telefone</small></span>
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
        <?php else: ?>
          <div class="admin-fonte">
            <i class="bi bi-globe2"></i>
            <span>
              <strong>Canal público informado</strong>
              <small><?= htmlspecialchars($ong['canal_valor'] ?: '—') ?> (<?= htmlspecialchars($ong['canal_tipo']) ?>)</small>
            </span>
          </div>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($mapaOsc) ?>" target="_blank" rel="noopener noreferrer" class="admin-fonte">
          <i class="bi bi-geo-alt"></i>
          <span><strong>Mapa das OSCs</strong><small>Base pública do Ipea</small></span>
          <i class="bi bi-box-arrow-up-right"></i>
        </a>
      </div>
    </div>

    <?php if (!empty($historico)): ?>
      <div class="admin-card mt-4">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Histórico de verificações</h2>
            <p class="admin-card-subtitle"><?= count($historico) ?> registro(s)</p>
          </div>
        </div>
        <ul class="admin-historico">
          <?php foreach ($historico as $h): ?>
            <?php
            $json = json_decode((string) $h['checklist_json'], true) ?: [];
            $marcados = count(array_filter($json['bloqueantes'] ?? []));
            ?>
            <li>
              <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="admin-status <?= $h['resultado'] === 'aprovado' ? 'status-aprovada' : ($h['resultado'] === 'suspenso' ? 'status-suspensa' : 'status-recusada') ?>">
                  <?= ucfirst($h['resultado']) ?>
                </span>
                <span class="admin-log-meta"><?= fmtDataHora($h['criado_em']) ?></span>
              </div>
              <div class="admin-historico-meta">
                <?= $marcados ?>/<?= count($bloqueantes) ?> bloqueantes · <?= htmlspecialchars($h['admin_nome'] ?: 'Equipe ONGHelp') ?>
              </div>
              <?php if (!empty($h['observacoes'])): ?>
                <p class="admin-historico-obs"><?= nl2br(htmlspecialchars($h['observacoes'])) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($ong['status'] === 'aprovada'): ?>
      <div class="admin-card mt-4">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">E-mail de aprovação</h2>
            <p class="admin-card-subtitle">Envio manual pelo Gmail compartilhado.</p>
          </div>
        </div>
        <p class="admin-texto">
          Modelo: informe à ONG que o cadastro foi aprovado, o endereço de login e que use
          a senha definida no cadastro. <strong>Nunca envie senha por e-mail.</strong>
        </p>
        <?php if (empty($ong['email_aprovacao_enviado_em'])): ?>
          <form method="POST" action="acao.php">
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="marcar_email_enviado">
            <input type="hidden" name="id" value="<?= (int) $ong['id'] ?>">
            <button type="submit" class="btn btn-outline-primary w-100 btn-sm">
              <i class="bi bi-envelope-check" aria-hidden="true"></i> Marcar e-mail como enviado
            </button>
          </form>
        <?php else: ?>
          <p class="admin-dica admin-dica-esquerda mb-0">
            <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
            Marcado como enviado em <?= fmtDataHora($ong['email_aprovacao_enviado_em']) ?>.
          </p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($ong['status'] === 'suspensa'): ?>
      <div class="admin-card mt-4">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Reativar perfil</h2>
            <p class="admin-card-subtitle">Volta para aprovada com nova data de verificação.</p>
          </div>
        </div>
        <form method="POST" action="acao.php">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="reativar">
          <input type="hidden" name="id" value="<?= (int) $ong['id'] ?>">
          <button type="submit" class="btn btn-success w-100 btn-sm">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reativar cadastro
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div class="admin-card mt-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Correções da equipe</h2>
          <p class="admin-card-subtitle">Use apenas quando a ONG não consegue corrigir sozinha.</p>
        </div>
      </div>

      <form method="POST" action="acao.php" class="mb-3">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="alterar_pix">
        <input type="hidden" name="id" value="<?= (int) $ong['id'] ?>">
        <label class="admin-label" for="pix_chave">Chave PIX (deve ser o CNPJ)</label>
        <div class="input-group input-group-sm mb-2">
          <input type="text" name="pix_chave" id="pix_chave" class="form-control"
                 value="<?= htmlspecialchars($ong['pix_chave']) ?>" required>
          <button type="submit" class="btn btn-outline-primary">Salvar PIX</button>
        </div>
        <p class="admin-dica admin-dica-esquerda mb-0">A chave é validada contra o CNPJ <?= htmlspecialchars(mascaraCnpj($ong['cnpj'])) ?>.</p>
      </form>

      <hr class="my-3">

      <form method="POST" action="acao.php">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="alterar_responsavel">
        <input type="hidden" name="id" value="<?= (int) $ong['id'] ?>">
        <label class="admin-label" for="responsavel_nome">Responsável legal</label>
        <input type="text" name="responsavel_nome" id="responsavel_nome" class="form-control form-control-sm mb-2"
               value="<?= htmlspecialchars($ong['responsavel_nome']) ?>" required>
        <label class="admin-label" for="responsavel_cargo">Cargo</label>
        <div class="input-group input-group-sm">
          <input type="text" name="responsavel_cargo" id="responsavel_cargo" class="form-control"
                 value="<?= htmlspecialchars($ong['responsavel_cargo']) ?>" required>
          <button type="submit" class="btn btn-outline-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Coluna direita: checklist e ações ── -->
  <div class="col-xl-4">

    <div class="admin-card admin-publicacoes-card mb-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Publicações</h2>
          <p class="admin-card-subtitle">Notícias e denúncias da ONG.</p>
        </div>
        <span class="admin-status <?= $bloqueioPublicacoes ? 'status-recusada' : 'status-aprovada' ?>">
          <?= $bloqueioPublicacoes ? 'Bloqueadas' : 'Liberadas' ?>
        </span>
      </div>

      <?php if ($bloqueioPublicacoes): ?>
        <p class="admin-publicacoes-estado">
          <i class="bi bi-lock-fill" aria-hidden="true"></i>
          Bloqueadas desde <strong><?= fmtDataHora((string) $bloqueioPublicacoes['criado_em']) ?></strong>
          por <strong><?= htmlspecialchars(trim((string) ($bloqueioPublicacoes['criado_por_nome'] ?? '')) ?: 'Equipe ONGHelp') ?></strong>.
        </p>
        <p class="admin-publicacoes-motivo">
          <span>Motivo</span>
          <?= htmlspecialchars((string) $bloqueioPublicacoes['motivo']) ?>
        </p>
        <button type="button" class="btn btn-outline-success w-100 btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalLiberarPublicacoes">
          <i class="bi bi-unlock" aria-hidden="true"></i> Liberar publicações
        </button>
      <?php else: ?>
        <p class="admin-publicacoes-estado">
          <i class="bi bi-unlock" aria-hidden="true"></i>
          Liberadas para publicação.
        </p>
        <p class="admin-dica admin-dica-esquerda">
          A ONG pode publicar notícias enquanto o cadastro estiver aprovado.
        </p>
        <button type="button" class="btn btn-outline-danger w-100 btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalBloquearPublicacoes">
          <i class="bi bi-slash-circle" aria-hidden="true"></i> Bloquear publicações
        </button>
      <?php endif; ?>
    </div>

    <div class="admin-decisao-sticky">
      <div class="admin-card admin-decisao-card">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Painel de decisão</h2>
            <p class="admin-card-subtitle">Confira os bloqueantes e registre a decisão.</p>
          </div>
        </div>

        <?php if ($ong['status'] === 'pendente'): ?>
          <p class="admin-decisao-autor">
            Você está analisando como <strong><?= htmlspecialchars((string) $admin['nome']) ?></strong>.
          </p>
        <?php elseif ($dataDecisao !== ''): ?>
          <p class="admin-decisao-autor">
            Decidida por <strong><?= htmlspecialchars($verificadorDecisao) ?></strong>
            em <?= fmtDataHora($dataDecisao) ?>.
          </p>
        <?php endif; ?>

        <form method="POST" action="acao.php" id="formAprovar">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="aprovar">
          <input type="hidden" name="id" value="<?= (int) $ong['id'] ?>">

          <div class="admin-checklist-grupo">
            <div class="admin-checklist-titulo">
              <i class="bi bi-shield-exclamation" aria-hidden="true"></i> Verificações bloqueantes
            </div>
            <?php foreach ($bloqueantes as $chave => $rotulo): ?>
              <div class="form-check admin-check">
                <input class="form-check-input check-bloqueante" type="checkbox"
                       name="checklist[bloqueantes][<?= $chave ?>]" value="1"
                       id="bloq_<?= $chave ?>">
                <label class="form-check-label" for="bloq_<?= $chave ?>"><?= htmlspecialchars($rotulo) ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="admin-progress">
            <div class="admin-progress-head">
              <span>Progresso da análise</span>
              <strong id="contadorBloqueantes">0 de <?= count($bloqueantes) ?> verificações</strong>
            </div>
            <div class="admin-progress-track" id="progressoBloqueantes" role="progressbar"
                 aria-label="Verificações bloqueantes concluídas"
                 aria-valuemin="0" aria-valuemax="<?= count($bloqueantes) ?>" aria-valuenow="0">
              <span class="admin-progress-bar" id="barraBloqueantes"></span>
            </div>
          </div>

          <details class="admin-atencao">
            <summary>
              <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
              Sinais de atenção
              <span>opcional</span>
            </summary>
            <p class="admin-atencao-nota">Não impedem a aprovação.</p>
            <?php foreach ($atencao as $chave => $rotulo): ?>
              <div class="form-check admin-check">
                <input class="form-check-input" type="checkbox"
                       name="checklist[atencao][<?= $chave ?>]" value="1"
                       id="at_<?= $chave ?>">
                <label class="form-check-label" for="at_<?= $chave ?>"><?= htmlspecialchars($rotulo) ?></label>
              </div>
            <?php endforeach; ?>
          </details>
        </form>

        <form method="POST" action="acao.php" id="formDecisao" class="admin-motivo">
          <?= csrfField() ?>
          <input type="hidden" name="id" value="<?= (int) $ong['id'] ?>">
          <label class="admin-label" for="motivo">Motivo</label>
          <textarea name="motivo" id="motivo" class="form-control" rows="3" required
                    placeholder="Ex.: CNPJ inativo na Receita; responsável não confirmou autorização…"></textarea>
          <p class="admin-dica admin-dica-esquerda">Obrigatório para suspender ou recusar.</p>
        </form>

        <div class="admin-observacoes">
          <label class="admin-label" for="observacoes">Observações da análise (opcional)</label>
          <textarea name="observacoes" id="observacoes" class="form-control" rows="2"
                    form="formAprovar" placeholder="O que foi conferido, contato feito, dúvidas…"></textarea>
        </div>

        <div class="admin-decisao-acoes">
          <button type="submit" class="btn btn-primary w-100" id="btnAprovar" form="formAprovar" disabled>
            <i class="bi bi-patch-check" aria-hidden="true"></i> Aprovar
          </button>
          <div class="admin-decisao-secundarias">
            <button type="submit" class="btn btn-secondary" name="acao" value="suspender" form="formDecisao">
              <i class="bi bi-pause-circle" aria-hidden="true"></i> Suspender
            </button>
            <button type="submit" class="btn btn-outline-danger" name="acao" value="recusar" form="formDecisao">
              <i class="bi bi-x-octagon" aria-hidden="true"></i> Recusar
            </button>
          </div>
        </div>
        <p class="admin-dica" id="dicaAprovar">
          A aprovação só é liberada com todos os bloqueantes marcados. Em caso de dúvida, recuse com motivo.
        </p>
      </div>
    </div>

  </div>
</div>

<?php if ($bloqueioPublicacoes): ?>
  <div class="modal fade" id="modalLiberarPublicacoes" tabindex="-1" aria-labelledby="modalLiberarPublicacoesTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="verificar.php?id=<?= (int) $ong['id'] ?>">
          <?= csrfField() ?>
          <input type="hidden" name="acao_publicacoes" value="liberar_publicacoes">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="modalLiberarPublicacoesTitulo">Liberar publicações</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">
              As publicações de <strong><?= htmlspecialchars((string) $ong['nome']) ?></strong>
              voltam a ficar disponíveis. A ONG poderá publicar notícias novamente.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success">Liberar publicações</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="modal fade" id="modalBloquearPublicacoes" tabindex="-1" aria-labelledby="modalBloquearPublicacoesTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="verificar.php?id=<?= (int) $ong['id'] ?>">
          <?= csrfField() ?>
          <input type="hidden" name="acao_publicacoes" value="bloquear_publicacoes">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="modalBloquearPublicacoesTitulo">Bloquear publicações</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p>
              A ONG <strong><?= htmlspecialchars((string) $ong['nome']) ?></strong> não poderá
              publicar novas notícias até que o bloqueio seja liberado.
            </p>
            <label class="admin-label" for="motivoBloqueioPublicacoes">Motivo do bloqueio</label>
            <textarea name="motivo" id="motivoBloqueioPublicacoes" class="form-control" rows="3"
                      maxlength="500" required
                      placeholder="Explique o motivo. A ONG verá este texto em Minhas notícias."></textarea>
            <p class="admin-dica admin-dica-esquerda mb-0">
              A ONG verá este motivo em Minhas notícias.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger">Bloquear publicações</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
$admin_script = 'admin-verificar.js';
include '../includes/admin_footer.php';
?>
