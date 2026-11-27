<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/core/rate-limit.php';

csrfExigir();

// se já estiver logado, redireciona direto
if (isset($_SESSION['user_id'])) {
    header('Location: perfil-privado.php');
    exit;
}

$erro  = '';
// minúsculas como no cadastro: o banco já ignora maiúsculas na busca, mas o
// limite de tentativas conta por e-mail, e "Ong@x" e "ong@X" seriam dois
$email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $senha = $_POST['senha'] ?? '';

    // a senha não passa por trim(): espaço no início ou no fim faz
    // parte do que foi cadastrado, só o e-mail é normalizado
    if (!$email || !$senha) {
        $erro = 'Preencha o e-mail e a senha.';
    } elseif (loginBloqueado($pdo, 'ong', $email, ipCliente())) {
        $erro = 'Muitas tentativas de login. Aguarde 15 minutos e tente novamente.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // e-mail sem conta também paga o custo do bcrypt, como no login da
        // equipe, para o tempo de resposta não revelar quem tem cadastro
        $confere = $user
            ? password_verify($senha, (string) $user['senha'])
            : password_verify($senha, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)) && false;

        if ($user && $confere) {
            if ($user['status'] === 'aprovada') {
                registrarTentativa($pdo, 'ong', $email, ipCliente(), true);
                marcarLoginOng((int) $user['id'], $user['nome'], $user['area'], $user['foto_perfil'] ?? null);
                header('Location: perfil-privado.php');
                exit;
            }

            registrarTentativa($pdo, 'ong', $email, ipCliente(), true);

            // a senha está certa, mas a conta ainda não pode entrar,
            // a mensagem é específica para a pessoa saber o que fazer,
            // sem revelar o status para quem não tem a senha
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
$mensagemEncerrada = isset($_GET['encerrada']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrar | ONGHelp</title>
<?php include __DIR__ . '/../includes/layout/head-assets.php'; ?>
</head>
<body class="login-body">

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<div class="login-shell">

  <!-- ========= esquerda ========= -->
  <?php include __DIR__ . '/../includes/components/auth-lateral.php'; ?>

  <!-- ========= direita ========= -->
  <main class="login-main" id="conteudo">
    <a href="../index.php" class="login-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao site
    </a>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="<?= asset('img/icone-onghelp.png') ?>" alt="" width="32" height="32">
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

      <?php if ($mensagemEncerrada): ?>
        <div class="login-note login-note--danger" role="status">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span>O acesso da sua ONG foi encerrado porque a conta não está mais ativa. Fale com a Equipe ONGHelp.</span>
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

<?php include __DIR__ . '/../includes/layout/scripts.php'; ?>
</body>
</html>
