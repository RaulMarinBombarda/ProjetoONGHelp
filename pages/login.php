<?php
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/csrf.php';
require_once '../includes/rate_limit.php';

csrfExigir();

// Se já estiver logado, redireciona direto
if (isset($_SESSION['user_id'])) {
    header('Location: perfilprivado.php');
    exit;
}

$erro  = '';
$email = trim($_POST['email'] ?? '');

$totalOngsAtivas = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'aprovada'")->fetchColumn();
if ($totalOngsAtivas === 0) {
    $textoOngs = 'Nenhuma ONG utiliza ainda';
} elseif ($totalOngsAtivas === 1) {
    $textoOngs = '1 ONG já utiliza';
} else {
    $textoOngs = $totalOngsAtivas . ' ONGs já utilizam';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $senha = $_POST['senha'] ?? '';

    // A senha não passa por trim(): espaço no início ou no fim faz
    // parte do que foi cadastrado. Só o e-mail é normalizado.
    if (!$email || !$senha) {
        $erro = 'Preencha o e-mail e a senha.';
    } elseif (loginBloqueado($pdo, 'ong', $email, ipCliente())) {
        $erro = 'Muitas tentativas de login. Aguarde 15 minutos e tente novamente.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            if ($user['status'] === 'aprovada') {
                registrarTentativa($pdo, 'ong', $email, ipCliente(), true);
                marcarLoginOng((int) $user['id'], $user['nome'], $user['area'], $user['foto_perfil'] ?? null);
                header('Location: perfilprivado.php');
                exit;
            }

            registrarTentativa($pdo, 'ong', $email, ipCliente(), true);

            // A senha está certa, mas a conta ainda não pode entrar.
            // A mensagem é específica para a pessoa saber o que fazer,
            // sem revelar o status para quem não tem a senha.
            if ($user['status'] === 'pendente') {
                $erro = 'Seu cadastro está em análise pela Equipe ONGHelp. Você poderá entrar assim que for aprovado.';
            } elseif ($user['status'] === 'recusada') {
                $erro = 'Seu cadastro não foi aprovado. Entre em contato com a Equipe ONGHelp para mais informações.';
            } elseif ($user['status'] === 'suspensa') {
                $erro = 'Sua conta está temporariamente suspensa. Entre em contato com a Equipe ONGHelp.';
            } else {
                $erro = 'Sua conta não está disponível para acesso no momento.';
            }
        } else {
            registrarTentativa($pdo, 'ong', $email, ipCliente(), false);
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}

$mensagemExpirada = isset($_GET['expirada']);
$mensagemSaiu     = isset($_GET['saiu']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrar | ONGHelp</title>
  <link rel="icon" href="../assets/img/brand/favicon.ico?v=2" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/brand/favicon-32x32.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/brand/apple-touch-icon.png?v=2">
  <meta name="theme-color" content="#1d4052">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css?v=38">
</head>
<body class="login-body">

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<div class="login-shell">

  <!-- ========= ESQUERDA ========= -->
  <aside class="login-aside">
    <a class="login-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="../assets/img/brand/icone-onghelp.png" alt="" class="brand-icon" width="36" height="36">
      <span>ONGHelp</span>
    </a>

    <div class="login-aside-body">
      <h1>Gestão e transparência para quem transforma o Brasil</h1>
      <p><?= htmlspecialchars($textoOngs) ?> o ONGHelp para publicar relatórios, divulgar seu canal de doações e conquistar a confiança dos seus apoiadores.</p>

      <div class="login-benefits glass-dark">
        <ul class="login-benefits-list">
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Perfil público após aprovação
          </li>
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Portal de transparência financeira
          </li>
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Divulgação de doações via PIX
          </li>
          <li class="login-benefit">
            <span class="login-benefit-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            Dashboard de gestão de projetos
          </li>
        </ul>
      </div>
    </div>

    <div class="login-aside-footer">
      &copy; 2026 ONGHelp — Projeto Acadêmico de Engenharia de Software
    </div>
  </aside>

  <!-- ========= DIREITA ========= -->
  <main class="login-main">
    <a href="../index.php" class="login-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao site
    </a>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="../assets/img/brand/icone-onghelp.png" alt="" width="32" height="32">
      <span>ONGHelp</span>
    </a>

    <section class="login-panel glass-1" aria-labelledby="loginTitulo">
      <nav class="login-tabs" aria-label="Acesso à conta">
        <a href="login.php" class="login-tab active" aria-current="page">Entrar</a>
        <a href="cadastro.php" class="login-tab">Criar conta</a>
      </nav>

      <h2 id="loginTitulo">Bem-vindo de volta!</h2>
      <p class="login-subtitle">Entre com sua conta para acessar o painel da sua ONG.</p>

      <?php if ($mensagemSaiu): ?>
        <div class="login-note login-note--success" role="status">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          <span>Você saiu da sua conta com segurança.</span>
        </div>
      <?php endif; ?>

      <?php if ($mensagemExpirada): ?>
        <div class="login-note login-note--info" role="status">
          <i class="bi bi-clock-history" aria-hidden="true"></i>
          <span>Sua sessão expirou. Entre novamente para continuar.</span>
        </div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="login-note login-note--danger" id="loginErro" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($erro) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" class="login-form" data-submit-loading>
        <?= csrfField() ?>

        <div class="login-field">
          <label class="auth-label" for="emailLogin">E-mail</label>
          <input type="email" name="email" id="emailLogin" class="auth-input<?= $erro ? ' is-invalid' : '' ?>"
                 placeholder="sua@ong.org.br"
                 value="<?= htmlspecialchars($email) ?>" autocomplete="email" required
                 <?php if ($erro): ?>autofocus aria-invalid="true" aria-describedby="loginErro"<?php endif; ?>>
        </div>

        <div class="login-field">
          <label class="auth-label" for="senhaLogin">Senha</label>
          <div class="auth-input-wrap">
            <input type="password" name="senha" class="auth-input<?= $erro ? ' is-invalid' : '' ?>" id="senhaLogin"
                   placeholder="••••••••" autocomplete="current-password" required
                   <?php if ($erro): ?>aria-invalid="true" aria-describedby="loginErro"<?php endif; ?>>
            <button class="auth-eye" type="button"
                    data-password-toggle="senhaLogin" aria-label="Mostrar senha" aria-pressed="false">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="login-submit" data-submit-button>
          <span>Entrar na plataforma</span>
          <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        </button>

      </form>

      <p class="login-signup">
        Ainda não tem conta?
        <a href="cadastro.php">Cadastre sua ONG gratuitamente</a>
      </p>
    </section>
  </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ui.js?v=2"></script>
</body>
</html>
