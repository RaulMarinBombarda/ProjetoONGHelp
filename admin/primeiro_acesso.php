<?php
// ============================================================
// admin/primeiro_acesso.php — Senha pessoal + aceite das regras
// ============================================================

require_once '../includes/admin_auth.php';

$admin = exigirPrimeiroAcesso($pdo);

csrfExigir();

$erro       = '';
$aceitou    = !empty($_POST['aceite_regras']);
$requisitos = [
    'minimo'               => false,
    'diferente_temporaria' => false,
    'sem_email'            => false,
    'iguais'               => false,
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

    if (!senhaEquipeValida($requisitos)) {
        $erro = 'Revise os requisitos da senha antes de continuar.';
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
            destruirSessao();
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
$rotulos = requisitosSenhaEquipeRotulos();
$podeEnviar = senhaEquipeValida($requisitos) && $aceitou;

$regras = regrasUsoEquipe();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Primeiro acesso | Equipe ONGHelp</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="icon" href="../assets/img/brand/favicon.ico?v=2" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/brand/favicon-32x32.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/brand/apple-touch-icon.png?v=2">
  <meta name="theme-color" content="#1d4052">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css?v=38">
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
      <img src="../assets/img/brand/icone-onghelp.png" alt="" width="36" height="36">
      <span>ONGHelp</span>
    </a>

    <div class="admin-login-aside-body">
      <span class="admin-login-eyebrow">Área restrita</span>
      <h1>Acesso da equipe</h1>
      <p>Painel de verificação e moderação do ONGHelp.</p>
    </div>

    <div class="login-aside-footer">
      &copy; <?= date('Y') ?> ONGHelp — Projeto Acadêmico de Engenharia de Software
    </div>
  </aside>

  <main class="login-main">
    <button type="submit" form="formSairPrimeiroAcesso" class="login-back admin-login-sair">
      <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair
    </button>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="../assets/img/brand/icone-onghelp.png" alt="" width="32" height="32">
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
            id="formPrimeiroAcesso"
            data-password-form
            data-lock-submit="1"
            data-email="<?= htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8') ?>"
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

        <ul class="auth-senha-checklist admin-primeiro-checklist" aria-label="Requisitos da senha">
          <li class="auth-senha-item <?= $requisitos['minimo'] ? 'is-ok' : '' ?>" data-check="minimo">
            <i class="bi <?= $requisitos['minimo'] ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($rotulos['minimo']) ?></span>
          </li>
          <li class="auth-senha-item <?= $requisitos['diferente_temporaria'] ? 'is-ok' : '' ?>" data-check="temporaria">
            <i class="bi <?= $requisitos['diferente_temporaria'] ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($rotulos['diferente_temporaria']) ?></span>
          </li>
          <li class="auth-senha-item <?= $requisitos['sem_email'] ? 'is-ok' : '' ?>" data-check="email">
            <i class="bi <?= $requisitos['sem_email'] ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($rotulos['sem_email']) ?></span>
          </li>
          <li class="auth-senha-item <?= $requisitos['iguais'] ? 'is-ok' : '' ?>" data-check="iguais">
            <i class="bi <?= $requisitos['iguais'] ? 'bi-check-circle-fill' : 'bi-circle' ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($rotulos['iguais']) ?></span>
          </li>
        </ul>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ui.js?v=2"></script>
<script src="../assets/js/admin-senha.js?v=4"></script>
</body>
</html>
