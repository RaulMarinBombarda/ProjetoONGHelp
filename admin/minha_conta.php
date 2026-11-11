<?php
// ============================================================
// admin/minha_conta.php — Conta pessoal e segurança
// ============================================================

require_once '../includes/admin_auth.php';

$admin = exigirPermissao($pdo, 'conta.propria');
csrfExigir();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string) ($_POST['acao'] ?? '') === 'alterar_senha') {
    $atual       = (string) ($_POST['senha_atual'] ?? '');
    $nova        = (string) ($_POST['senha_nova'] ?? '');
    $confirmacao = (string) ($_POST['senha_confirmacao'] ?? '');

    if (!password_verify($atual, (string) $admin['senha'])) {
        flashSet('admin_erro', 'A senha atual não confere.');
        header('Location: minha_conta.php#seguranca');
        exit;
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
        header('Location: minha_conta.php#seguranca');
        exit;
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
        'Senha pessoal alterada; demais sessões invalidadas.'
    );

    flashSet('admin_sucesso', 'Sua senha foi alterada. As demais sessões abertas foram encerradas.');
    header('Location: minha_conta.php');
    exit;
}

$ehAdministrador = ($admin['papel'] ?? '') === 'administrador';

$atividade = [];
if (!$ehAdministrador) {
    $stmt = $pdo->prepare("
        SELECT l.*, a.nome AS admin_nome, a.papel AS admin_papel
        FROM   logs_admin l
        LEFT   JOIN admins a ON a.id = l.admin_id
        WHERE  l.admin_id = ?
           OR  (l.alvo_tipo = 'admin' AND l.alvo_id = ?)
        ORDER  BY l.criado_em DESC, l.id DESC
        LIMIT  10
    ");
    $stmt->execute([(int) $admin['id'], (int) $admin['id']]);
    $atividade = $stmt->fetchAll();
}

$admin_page_title    = 'Minha conta';
$admin_page_subtitle = 'Seus dados de acesso e a segurança da sua conta';
$admin_active        = 'minha_conta';
include '../includes/admin_header.php';
?>

<div class="admin-conta-grid">

  <section class="admin-card admin-conta-perfil admin-conta-dados-painel">
    <h2 class="admin-card-title">Seus dados</h2>

    <div class="admin-conta-identidade">
      <span class="admin-avatar admin-avatar-2xl" aria-hidden="true"><?= htmlspecialchars(adminIniciais((string) $admin['nome'])) ?></span>
      <h3><?= htmlspecialchars($admin['nome']) ?></h3>
      <span class="admin-estado is-admin"><?= htmlspecialchars(adminPapelLabel((string) $admin['papel'])) ?></span>
      <?php if (!empty($admin['conta_teste'])): ?>
        <span class="admin-teste-badge">Conta de teste</span>
      <?php endif; ?>
      <p class="admin-conta-email"><?= htmlspecialchars($admin['email']) ?></p>
    </div>

    <dl class="admin-conta-meta">
      <div>
        <dt>Membro desde</dt>
        <dd><?= fmtData($admin['criado_em']) ?></dd>
      </div>
      <div>
        <dt>Último acesso</dt>
        <dd><?= !empty($admin['ultimo_acesso_em']) ? fmtDataHora($admin['ultimo_acesso_em']) : 'Agora' ?></dd>
      </div>
    </dl>
  </section>

  <section class="admin-card admin-conta-seguranca" id="seguranca">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Alterar senha</h2>
        <p class="admin-card-subtitle">A troca encerra as demais sessões abertas desta conta.</p>
      </div>
      <span class="admin-conta-shield" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
    </div>

    <form method="POST" action="minha_conta.php"
          data-password-form data-email="<?= htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8') ?>">
      <?= csrfField() ?>
      <input type="hidden" name="acao" value="alterar_senha">

      <div class="mb-3">
        <label class="admin-label" for="contaSenhaAtual">Senha atual</label>
        <input type="password" class="form-control" name="senha_atual" id="contaSenhaAtual"
               autocomplete="current-password" required>
      </div>

      <div class="mb-3">
        <label class="admin-label" for="contaSenhaNova">Nova senha</label>
        <div class="auth-input-wrap">
          <input type="password" class="form-control" name="senha_nova" id="contaSenhaNova"
                 autocomplete="new-password" required>
          <button class="auth-eye" type="button"
                  data-password-toggle="contaSenhaNova" aria-label="Mostrar senha" aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="mb-3">
        <label class="admin-label" for="contaSenhaConfirmacao">Confirmar senha</label>
        <div class="auth-input-wrap">
          <input type="password" class="form-control" name="senha_confirmacao" id="contaSenhaConfirmacao"
                 autocomplete="new-password" required>
          <button class="auth-eye" type="button"
                  data-password-toggle="contaSenhaConfirmacao" aria-label="Mostrar senha" aria-pressed="false">
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

      <button type="submit" class="btn btn-primary admin-conta-submit">Atualizar senha</button>
    </form>

    <?php if ($ehAdministrador): ?>
      <div class="admin-conta-dica">
        <i class="bi bi-people" aria-hidden="true"></i>
        Esta conta é compartilhada. Avise apenas as pessoas autorizadas depois de alterar.
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if (!$ehAdministrador): ?>
  <section class="admin-card admin-conta-atividade">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Minhas ações recentes</h2>
        <p class="admin-card-subtitle">As últimas 10 ações registradas com a sua conta.</p>
      </div>
      <a href="logs.php?pessoa=<?= (int) $admin['id'] ?>" class="admin-link-terciario">Ver no log completo <span aria-hidden="true">→</span></a>
    </div>

    <?php if (empty($atividade)): ?>
      <div class="admin-vazio admin-vazio-sm">
        <i class="bi bi-activity" aria-hidden="true"></i>
        <strong>Nenhuma atividade ainda</strong>
        <span>Suas ações aparecem aqui assim que você começar a analisar cadastros.</span>
      </div>
    <?php else: ?>
      <ol class="admin-timeline admin-conta-atividade-lista">
        <?php foreach ($atividade as $log): ?>
          <li>
            <strong class="admin-atividade-acao"><?= htmlspecialchars(resumoAtividadeAdmin($log)) ?></strong>
            <?php if (!empty($log['detalhe'])): ?>
              <span class="admin-atividade-detalhe"><?= htmlspecialchars($log['detalhe']) ?></span>
            <?php endif; ?>
            <time class="admin-atividade-data" datetime="<?= htmlspecialchars(date('c', strtotime((string) $log['criado_em'])), ENT_QUOTES, 'UTF-8') ?>">
              <?= fmtDataHora($log['criado_em']) ?>
            </time>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php
$admin_script = 'admin-senha.js';
include '../includes/admin_footer.php';
?>
