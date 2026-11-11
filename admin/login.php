<?php
// ============================================================
// admin/login.php — Acesso único da equipe ONGHelp
// ============================================================

require_once '../includes/admin_auth.php';

csrfExigir();

// Uma sessão já aberta volta direto para o destino correto. Se o
// token não confere mais (senha redefinida, conta desativada), a
// sessão antiga é descartada antes de mostrar o formulário.
if (!empty($_SESSION['admin_id'])) {
    $contaSessao = adminContaSessao($pdo);

    if ($contaSessao && $contaSessao['estado'] === 'ativo') {
        header('Location: index.php');
        exit;
    }

    if ($contaSessao
        && $contaSessao['estado'] === 'aguardando'
        && !empty($_SESSION['admin_pendente'])) {
        header('Location: primeiro_acesso.php');
        exit;
    }

    destruirSessao();
}

$erro  = '';
$email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $senha = (string) ($_POST['senha'] ?? '');
    $ip    = ipCliente();

    if ($email === '' || $senha === '') {
        $erro = 'Preencha o e-mail e a senha.';
    } elseif (loginBloqueado($pdo, 'admin', $email, $ip)) {
        $erro = 'Muitas tentativas. Aguarde 15 minutos e tente novamente.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            $confere = password_verify($senha, (string) $admin['senha']);
        } else {
            // Hash fictício gerado a cada tentativa: nunca é o hash de
            // uma senha conhecida. O custo do bcrypt é pago mesmo
            // quando o e-mail não existe, para que o tempo de resposta
            // não revele se a conta está cadastrada.
            password_verify($senha, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));
            $confere = false;
        }

        if (!$admin || !$confere) {
            registrarTentativa($pdo, 'admin', $email, $ip, false);
            $erro = 'E-mail ou senha inválidos.';
        } elseif ($admin['estado'] === 'desativado') {
            registrarTentativa($pdo, 'admin', $email, $ip, true);
            $erro = 'Esta conta foi desativada. Fale com o administrador da equipe.';
        } elseif ($admin['estado'] === 'aguardando') {
            $expiraEm = trim((string) ($admin['senha_temporaria_expira_em'] ?? ''));

            if ($expiraEm === '' || strtotime($expiraEm) === false || strtotime($expiraEm) <= time()) {
                registrarTentativa($pdo, 'admin', $email, $ip, true);
                $erro = 'Sua senha temporária expirou. Peça ao administrador uma nova senha de primeiro acesso.';
            } else {
                registrarTentativa($pdo, 'admin', $email, $ip, true);

                $token = (string) $admin['sessao_token'];
                if ($token === '') {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE admins SET sessao_token = ? WHERE id = ?")
                        ->execute([$token, (int) $admin['id']]);
                }

                marcarLoginAdmin(
                    (int) $admin['id'],
                    (string) $admin['nome'],
                    (string) $admin['papel'],
                    $token,
                    true
                );

                // Digest efêmero, válido apenas nesta sessão, usado só
                // para o checklist ao vivo do primeiro acesso. A
                // validação definitiva continua sendo password_verify.
                $sal = bin2hex(random_bytes(16));
                $_SESSION['admin_temp_salt']   = $sal;
                $_SESSION['admin_temp_digest'] = hash('sha256', $sal . $senha);

                registrarLogAdmin(
                    $pdo,
                    'login_admin',
                    'admin',
                    (int) $admin['id'],
                    (string) $admin['nome'],
                    'Senha temporária confirmada; primeiro acesso iniciado.'
                );

                header('Location: primeiro_acesso.php');
                exit;
            }
        } elseif ($admin['estado'] === 'ativo') {
            registrarTentativa($pdo, 'admin', $email, $ip, true);

            $token = (string) $admin['sessao_token'];
            if ($token === '') {
                $token = bin2hex(random_bytes(32));
            }

            $pdo->prepare("UPDATE admins SET sessao_token = ?, ultimo_acesso_em = NOW() WHERE id = ?")
                ->execute([$token, (int) $admin['id']]);

            marcarLoginAdmin(
                (int) $admin['id'],
                (string) $admin['nome'],
                (string) $admin['papel'],
                $token,
                false
            );

            registrarLogAdmin(
                $pdo,
                'login_admin',
                'admin',
                (int) $admin['id'],
                (string) $admin['nome']
            );

            header('Location: index.php');
            exit;
        } else {
            registrarTentativa($pdo, 'admin', $email, $ip, false);
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}

$expirada          = isset($_GET['expirada']);
$encerrada         = isset($_GET['encerrada']);
$saiu              = isset($_GET['saiu']);
$temporariaExpirou = isset($_GET['temporaria_expirada']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acesso da equipe | ONGHelp</title>
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

  <!-- Lado esquerdo: identificação da área restrita -->
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

  <!-- Lado direito: formulário -->
  <main class="login-main">
    <a href="../index.php" class="login-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao site
    </a>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="../assets/img/brand/icone-onghelp.png" alt="" width="32" height="32">
      <span>ONGHelp</span>
    </a>

    <section class="login-panel glass-1 admin-login-panel" aria-labelledby="loginAdminTitulo">
      <h2 id="loginAdminTitulo">Entrar no painel</h2>
      <p class="login-subtitle">Use o e-mail e a senha da sua conta.</p>

      <?php if ($saiu): ?>
        <div class="login-note login-note--success" role="status">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          <span>Sessão encerrada com segurança.</span>
        </div>
      <?php endif; ?>

      <?php if ($expirada): ?>
        <div class="login-note login-note--info" role="status">
          <i class="bi bi-clock-history" aria-hidden="true"></i>
          <span>Sua sessão expirou. Entre novamente.</span>
        </div>
      <?php endif; ?>

      <?php if ($encerrada): ?>
        <div class="login-note login-note--danger" role="alert">
          <i class="bi bi-person-slash" aria-hidden="true"></i>
          <span>Sua conta foi desativada. Fale com o administrador da equipe.</span>
        </div>
      <?php endif; ?>

      <?php if ($temporariaExpirou): ?>
        <div class="login-note login-note--info" role="status">
          <i class="bi bi-hourglass-split" aria-hidden="true"></i>
          <span>Sua senha temporária expirou. Peça ao administrador uma nova senha de primeiro acesso.</span>
        </div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="login-note login-note--danger" id="loginAdminErro" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($erro) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" class="login-form" data-submit-loading>
        <?= csrfField() ?>

        <div class="login-field">
          <label class="auth-label" for="emailAdmin">E-mail</label>
          <input type="email" name="email" id="emailAdmin"
                 class="auth-input<?= $erro ? ' is-invalid' : '' ?>"
                 placeholder="voce@exemplo.com"
                 value="<?= htmlspecialchars($email) ?>"
                 autocomplete="username" required
                 <?php if ($erro): ?>autofocus aria-invalid="true" aria-describedby="loginAdminErro"<?php endif; ?>>
        </div>

        <div class="login-field">
          <label class="auth-label" for="senhaAdmin">Senha</label>
          <div class="auth-input-wrap">
            <input type="password" name="senha" id="senhaAdmin"
                   class="auth-input<?= $erro ? ' is-invalid' : '' ?>"
                   placeholder="••••••••" autocomplete="current-password" required
                   <?php if ($erro): ?>aria-invalid="true" aria-describedby="loginAdminErro"<?php endif; ?>>
            <button class="auth-eye" type="button"
                    data-password-toggle="senhaAdmin" aria-label="Mostrar senha" aria-pressed="false">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="login-submit" data-submit-button>
          <span>Entrar</span>
          <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        </button>
      </form>
    </section>
  </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ui.js?v=2"></script>
</body>
</html>
