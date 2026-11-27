<?php
// ============================================================
// admin/primeiro-acesso.php — senha pessoal + aceite das regras
// ============================================================

require_once __DIR__ . '/../includes/modules/admin-auth.php';

$admin = exigirPrimeiroAcesso($pdo);

csrfExigir();

$erro       = '';
$aceitou    = !empty($_POST['aceite_regras']);
$requisitos = [];

// itens da lista de requisitos: chave do servidor => data-check do senha.js
$itensSenha = [
    'minimo'               => 'minimo',
    'letra_numero'         => 'letra_numero',
    'diferente_temporaria' => 'temporaria',
    'sem_email'            => 'email',
    'nao_comum'            => 'comum',
    'iguais'               => 'iguais',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $senha       = (string) ($_POST['senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmacao'] ?? '');

    $requisitos = requisitosSenhaEquipe(
        $senha,
        $confirmacao,
        (string) $admin['email'],
        (string) $admin['senha']
    );

    if (!senhaValida($requisitos)) {
        $erro = senhaPrimeiroErro($requisitos, SENHA_MINIMO_EQUIPE);
    } elseif (!$aceitou) {
        $erro = 'Para concluir o primeiro acesso, aceite as regras de uso do painel.';
    } else {
        $novoToken = bin2hex(random_bytes(32));
        $novoHash  = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "UPDATE admins
             SET senha = ?,
                 estado = 'ativo',
                 senha_temporaria_expira_em = NULL,
                 sessao_token = ?,
                 ultimo_acesso_em = NOW()
             WHERE id = ? AND estado = 'aguardando'"
        );
        $stmt->execute([$novoHash, $novoToken, (int) $admin['id']]);

        if ($stmt->rowCount() !== 1) {
            encerrarLogin('admin');
            header('Location: login.php?temporaria_expirada=1');
            exit;
        }

        $_SESSION['admin_sessao_token']  = $novoToken;
        $_SESSION['admin_papel']         = (string) $admin['papel'];
        $_SESSION['admin_nome']          = (string) $admin['nome'];
        $_SESSION['admin_pendente']      = false;
        $_SESSION['admin_last_activity'] = time();
        unset($_SESSION['admin_temp_salt'], $_SESSION['admin_temp_digest']);

        registrarLogAdmin(
            $pdo,
            'primeiro_acesso_concluido',
            'admin',
            (int) $admin['id'],
            (string) $admin['nome'],
            'Senha pessoal definida e aceite das regras de uso registrado.'
        );

        flashSet('admin_sucesso', 'Primeiro acesso concluído. Sua senha pessoal está ativa.');
        header('Location: index.php');
        exit;
    }
}

$sal     = (string) ($_SESSION['admin_temp_salt'] ?? '');
$digest  = (string) ($_SESSION['admin_temp_digest'] ?? '');
$podeEnviar = $requisitos !== [] && senhaValida($requisitos) && $aceitou;

$regras = regrasUsoEquipe();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Primeiro acesso | Equipe ONGHelp</title>
  <meta name="robots" content="noindex, nofollow">

<?php $css_area = 'admin'; include __DIR__ . '/../includes/layout/head-assets.php'; ?>
</head>
<body class="login-body admin-login-body">

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<div class="login-shell admin-login-shell">

  <aside class="login-aside admin-login-aside">
    <a class="login-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="<?= asset('img/icone-onghelp.png') ?>" alt="" width="36" height="36">
      <span>ONGHelp</span>
    </a>

    <div class="admin-login-aside-body">
      <span class="admin-login-eyebrow">Área restrita</span>
      <h1>Acesso da equipe</h1>
      <p>Painel de verificação e moderação do ONGHelp.</p>
    </div>

    <div class="login-aside-footer">
      &copy; <?= date('Y') ?> ONGHelp · Projeto Integrador
    </div>
  </aside>

  <main class="login-main" id="conteudo">
    <button type="submit" form="formSairPrimeiroAcesso" class="login-back admin-login-sair">
      <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair
    </button>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="<?= asset('img/icone-onghelp.png') ?>" alt="" width="32" height="32">
      <span>ONGHelp</span>
    </a>

    <section class="login-panel glass-1 admin-primeiro-panel" aria-labelledby="primeiroAcessoTitulo">
      <span class="admin-primeiro-eyebrow">Primeiro acesso</span>
      <h2 id="primeiroAcessoTitulo">Bem-vindo(a), <?= htmlspecialchars($admin['nome']) ?>!</h2>
      <p class="login-subtitle">Crie sua senha e confirme as regras de uso para entrar no painel.</p>

      <?php if ($erro): ?>
        <div class="login-note login-note--danger" id="primeiroAcessoErro" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($erro) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action=""
            id="formPrimeiroAcesso"<?= senhaAtributosFormulario(SENHA_MINIMO_EQUIPE, (string) $admin['email']) ?>
            data-lock-submit="1"
            data-temp-salt="<?= htmlspecialchars($sal, ENT_QUOTES, 'UTF-8') ?>"
            data-temp-digest="<?= htmlspecialchars($digest, ENT_QUOTES, 'UTF-8') ?>"
            data-require-temp="1"
            data-submit-loading>
        <?= csrfField() ?>

        <div class="login-field">
          <label class="auth-label" for="senhaNova">Nova senha</label>
          <div class="auth-input-wrap">
            <input type="password" name="senha" id="senhaNova" class="auth-input"
                   placeholder="Crie sua senha pessoal" autocomplete="new-password" required>
            <button class="auth-eye" type="button"
                    data-password-toggle="senhaNova" aria-label="Mostrar senha" aria-pressed="false">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="login-field">
          <label class="auth-label" for="senhaConfirmacao">Confirmar senha</label>
          <div class="auth-input-wrap">
            <input type="password" name="confirmacao" id="senhaConfirmacao" class="auth-input"
                   placeholder="Digite novamente" autocomplete="new-password" required>
            <button class="auth-eye" type="button"
                    data-password-toggle="senhaConfirmacao" aria-label="Mostrar senha" aria-pressed="false">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <?= senhaChecklist($itensSenha, SENHA_MINIMO_EQUIPE, $requisitos, ['lista' => 'auth-senha-checklist admin-primeiro-checklist']) ?>

        <hr class="admin-primeiro-divisor">

        <h3 class="admin-primeiro-bloco">Regras de uso do painel</h3>
        <div class="admin-regras-scroll" tabindex="0" aria-label="Regras de uso do painel">
          <ol class="admin-regras-lista">
            <?php foreach ($regras as $indice => $regra): ?>
              <li>
                <span class="admin-regra-numero" aria-hidden="true"><?= $indice + 1 ?></span>
                <span><?= htmlspecialchars($regra) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div class="form-check admin-regras-check">
          <input class="form-check-input" type="checkbox" name="aceite_regras" value="1"
                 id="aceiteRegras" <?= $aceitou ? 'checked' : '' ?> required>
          <label class="form-check-label" for="aceiteRegras">
            Li e aceito as regras de uso do painel
          </label>
        </div>

        <button type="submit" class="login-submit admin-primeiro-submit" data-submit-button <?= $podeEnviar ? '' : 'disabled' ?>>
          <span>Entrar no painel</span>
          <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        </button>
      </form>

      <form method="POST" action="logout.php" id="formSairPrimeiroAcesso" class="d-none">
        <?= csrfField() ?>
      </form>
    </section>
  </main>

</div>

<?php
$page_scripts = ['core/senha.js'];
include __DIR__ . '/../includes/layout/scripts.php';
?>
</body>
</html>
