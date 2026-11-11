<?php
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/csrf.php';

csrfExigir();

$erro    = '';
$sucesso = '';

// Lista fechada de áreas. É usada na validação do POST e de volta
// no <select> do formulário — uma única fonte para as duas.
$areas = ['Educação','Saúde','Animais','Meio Ambiente','Assistência Social','Cultura','Esporte','Outra'];

$tiposCanal = [
    'site'      => 'Site oficial',
    'instagram' => 'Instagram',
    'facebook'  => 'Facebook',
    'telefone'  => 'Telefone / WhatsApp',
    'outro'     => 'Outro',
];

$totalOngsAtivas = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'aprovada'")->fetchColumn();
if ($totalOngsAtivas === 0) {
    $textoOngs = 'Nenhuma ONG utiliza ainda';
} elseif ($totalOngsAtivas === 1) {
    $textoOngs = '1 ONG já utiliza';
} else {
    $textoOngs = $totalOngsAtivas . ' ONGs já utilizam';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nome         = trim($_POST['nome']         ?? '');
    $cnpjBruto    = trim($_POST['cnpj']         ?? '');
    $cnpj         = somenteDigitos($cnpjBruto);
    $email        = trim($_POST['email']        ?? '');
    $senha        = $_POST['senha']             ?? '';
    $senhaConf    = $_POST['senha_confirm']     ?? '';
    $area         = trim($_POST['area']         ?? '');
    $cidade       = trim($_POST['cidade']       ?? '');
    $estado       = trim($_POST['estado']       ?? '');
    $responsavel  = trim($_POST['responsavel_nome']  ?? '');
    $cargo        = trim($_POST['responsavel_cargo'] ?? '');
    $canalTipo    = trim($_POST['canal_tipo']   ?? '');
    $canalValor   = trim($_POST['canal_valor']  ?? '');
    $autorizacao  = isset($_POST['responsavel_autorizacao']);
    $termos       = isset($_POST['termos']);

    // A senha NÃO passa por trim(): o que a pessoa digita é o que
    // deve valer. Um trim() silencioso faria "minhasenha " e
    // "minhasenha" virarem a mesma senha sem ninguém perceber.

    if (!$nome || !$cnpjBruto || !$email || !$senha || !$area
        || !$responsavel || !$cargo || !$canalTipo || !$canalValor) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Sem esta conferência, um e-mail como "abc" era aceito e
        // a ONG ficava presa fora da própria conta — o e-mail é a
        // chave de login e não existe recuperação de senha.
        $erro = 'Informe um e-mail válido.';
    } elseif (!validarCnpjFormato($cnpj)) {
        $erro = 'Informe o CNPJ com 14 dígitos.';
    } elseif (!in_array($area, $areas, true)) {
        $erro = 'Selecione uma área de atuação válida.';
    } elseif (!validarCanalPublico($canalTipo, $canalValor)) {
        $erro = 'Informe um canal público válido (site, rede social ou telefone).';
    } elseif (!$autorizacao) {
        $erro = 'Confirme que você é o responsável legal ou que foi autorizado por ele.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $senhaConf) {
        $erro = 'As senhas não coincidem.';
    } elseif (!$termos) {
        $erro = 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.';
    } else {
        // Verifica se e-mail ou CNPJ já existe. O CNPJ entra
        // normalizado em 14 dígitos, e o UNIQUE do banco também
        // passa a valer sobre os dígitos: é isso que impede duas
        // contas para a mesma organização, uma com máscara e outra
        // sem.
        // A comparação do CNPJ normaliza o valor gravado. Contas novas
        // já entram com 14 dígitos, mas registros antigos podem ter
        // vindo com máscara; sem o REPLACE, a duplicidade passaria.
        $stmt = $pdo->prepare(
            "SELECT id FROM usuarios
             WHERE email = ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', ''), ' ', '') = ?"
        );
        $stmt->execute([$email, $cnpj]);

        if ($stmt->fetch()) {
            $erro = 'E-mail ou CNPJ já cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            try {
                // A chave PIX é o próprio CNPJ, sem máscara. O
                // cadastro nasce pendente: nada é público até a
                // aprovação manual da Equipe ONGHelp.
                $pdo->prepare(
                    "INSERT INTO usuarios
                        (nome, cnpj, email, senha, area, pix_chave, cidade, estado,
                         responsavel_nome, responsavel_cargo, responsavel_autorizacao,
                         canal_tipo, canal_valor, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')"
                )->execute([
                    $nome, $cnpj, $email, $hash, $area, $cnpj,
                    $cidade ?: 'Brasil', $estado ?: null,
                    $responsavel, $cargo, $autorizacao ? 1 : 0,
                    $canalTipo, $canalValor,
                ]);
                $sucesso = 'Cadastro enviado para análise da Equipe ONGHelp. Você receberá um e-mail quando for aprovado.';
            } catch (PDOException $e) {
                // A consulta acima é verificação-e-ação: entre o SELECT
                // e o INSERT o banco não fica travado, então dois
                // cadastros simultâneos com o mesmo e-mail passam
                // ambos na verificação e um deles morre aqui. O
                // detalhe vai para o log; o usuário recebe a mensagem
                // que corresponde ao caso.
                error_log('[ONGHelp] Cadastro recusado pelo banco: ' . $e->getMessage());

                if (strpos($e->getMessage(), '1062') !== false) {
                    $erro = 'E-mail ou CNPJ já cadastrado.';
                } else {
                    $erro = 'Não foi possível concluir o cadastro agora. Tente novamente em alguns instantes.';
                }
            }
        }
    }
}

// Mapeia a mensagem do servidor para o primeiro campo/etapa com erro.
// A validação em si continua sendo a mesma; isto só decide o que abrir
// e destacar quando a página volta com uma mensagem.
$etapaInicial  = 1;
$camposComErro = [];

if ($erro !== '') {
    $mapaErros = [
        'Informe um e-mail válido.' => ['email'],
        'Informe o CNPJ com 14 dígitos.' => ['cnpj'],
        'Selecione uma área de atuação válida.' => ['area'],
        'Informe um canal público válido (site, rede social ou telefone).' => ['canal_valor'],
        'Confirme que você é o responsável legal ou que foi autorizado por ele.' => ['responsavel_autorizacao'],
        'A senha deve ter pelo menos 8 caracteres.' => ['senha'],
        'As senhas não coincidem.' => ['senha_confirm'],
        'Você precisa aceitar os Termos de Uso e a Política de Privacidade.' => ['termos'],
        'E-mail ou CNPJ já cadastrado.' => ['email', 'cnpj'],
    ];

    if (isset($mapaErros[$erro])) {
        $camposComErro = $mapaErros[$erro];
    } elseif ($erro === 'Preencha todos os campos obrigatórios.') {
        foreach (['nome', 'cnpj', 'email', 'senha', 'area', 'responsavel_nome', 'responsavel_cargo', 'canal_tipo', 'canal_valor'] as $campoObrigatorio) {
            if (trim((string) ($_POST[$campoObrigatorio] ?? '')) === '') {
                $camposComErro = [$campoObrigatorio];
                break;
            }
        }
    }

    $etapaPorCampo = [
        'nome' => 1, 'cnpj' => 1, 'email' => 1, 'senha' => 1, 'senha_confirm' => 1,
        'area' => 2, 'responsavel_nome' => 2, 'responsavel_cargo' => 2,
        'canal_tipo' => 3, 'canal_valor' => 3, 'responsavel_autorizacao' => 3, 'termos' => 3,
    ];

    if ($camposComErro) {
        $etapaInicial = $etapaPorCampo[$camposComErro[0]] ?? 1;
    }
}

$campoInvalido = static function (string $campo) use ($camposComErro): bool {
    return in_array($campo, $camposComErro, true);
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Criar Conta | ONGHelp</title>
  <link rel="icon" href="../assets/img/brand/favicon.ico?v=2" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/brand/favicon-32x32.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/brand/apple-touch-icon.png?v=2">
  <meta name="theme-color" content="#1d4052">
  <script>document.documentElement.classList.add('js');</script>
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

<div class="login-shell cadastro-shell">

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
  <main class="login-main cadastro-main">
    <a href="../index.php" class="login-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao site
    </a>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="../assets/img/brand/icone-onghelp.png" alt="" width="32" height="32">
      <span>ONGHelp</span>
    </a>

    <section class="login-panel cadastro-panel glass-1" aria-labelledby="cadastroTitulo">
      <nav class="login-tabs" aria-label="Acesso à conta">
        <a href="login.php" class="login-tab">Entrar</a>
        <a href="cadastro.php" class="login-tab active" aria-current="page">Criar conta</a>
      </nav>

      <h2 id="cadastroTitulo">Cadastre sua ONG!</h2>
      <p class="login-subtitle">Preencha os dados abaixo. O cadastro passa por uma análise manual antes de ficar público.</p>

      <?php if ($erro): ?>
        <div class="login-note login-note--danger" id="cadastroErro" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($erro) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($sucesso): ?>
        <div class="login-note login-note--success" role="status">
          <i class="bi bi-check-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($sucesso) ?></span>
        </div>
      <?php endif; ?>

      <ol class="cadastro-steps" aria-label="Etapas do cadastro">
        <?php foreach (['Conta', 'Organização', 'Canal e confirmação'] as $indice => $rotuloEtapa): $numeroEtapa = $indice + 1; ?>
          <li class="cadastro-step-item<?= $numeroEtapa < $etapaInicial ? ' is-complete' : '' ?><?= $numeroEtapa === $etapaInicial ? ' is-active' : '' ?>"
              data-step-indicator="<?= $numeroEtapa ?>"
              <?= $numeroEtapa === $etapaInicial ? 'aria-current="step"' : '' ?>>
            <span class="cadastro-step-circle">
              <span class="cadastro-step-number" aria-hidden="true"><?= $numeroEtapa ?></span>
              <i class="bi bi-check-lg cadastro-step-check" aria-hidden="true"></i>
            </span>
            <span class="cadastro-step-label"><?= $rotuloEtapa ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="cadastro-step-counter" data-step-counter aria-live="polite">Etapa <?= $etapaInicial ?> de 3</p>

      <form method="POST" action="" class="cadastro-form"
            data-cadastro-form data-initial-step="<?= $etapaInicial ?>" data-step="<?= $etapaInicial ?>"
            data-submit-loading>
        <?= csrfField() ?>

        <!-- ========= ETAPA 1 — CONTA ========= -->
        <section class="cadastro-step<?= $etapaInicial === 1 ? ' is-active' : '' ?>" data-step="1" aria-labelledby="etapa1Titulo">
          <h3 class="visually-hidden" id="etapa1Titulo">Etapa 1: Conta</h3>

          <div class="cadastro-field<?= $campoInvalido('nome') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroNome">Nome da ONG</label>
            <input type="text" name="nome" id="cadastroNome" class="auth-input"
                   placeholder="Ex: Instituto Esperança"
                   value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" autocomplete="organization" required>
            <p class="cadastro-error" data-error-for="nome" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('cnpj') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroCnpj">CNPJ</label>
            <input type="text" name="cnpj" id="cadastroCnpj" class="auth-input"
                   placeholder="00.000.000/0001-00"
                   value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>"
                   inputmode="numeric" maxlength="18" autocomplete="off" data-cnpj required>
            <p class="cadastro-error" data-error-for="cnpj" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('email') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroEmail">E-mail</label>
            <input type="email" name="email" id="cadastroEmail" class="auth-input"
                   placeholder="sua@ong.org.br"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
            <p class="cadastro-error" data-error-for="email" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('senha') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="senhaCad">Senha</label>
            <div class="auth-input-wrap">
              <input type="password" name="senha" class="auth-input" id="senhaCad"
                     placeholder="••••••••" autocomplete="new-password" minlength="8" required>
              <button class="auth-eye" type="button"
                      data-password-toggle="senhaCad" aria-label="Mostrar senha" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <p class="cadastro-help cadastro-rule" data-password-rule>
              <i class="bi bi-check-lg" aria-hidden="true"></i>
              <span>Mínimo de 8 caracteres.</span>
            </p>
            <p class="cadastro-error" data-error-for="senha" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('senha_confirm') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="senhaCad2">Repetir a senha</label>
            <div class="auth-input-wrap">
              <input type="password" name="senha_confirm" class="auth-input" id="senhaCad2"
                     placeholder="••••••••" autocomplete="new-password" minlength="8" required>
              <button class="auth-eye" type="button"
                      data-password-toggle="senhaCad2" aria-label="Mostrar senha" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <p class="cadastro-help cadastro-rule cadastro-match" data-match-rule>
              <i class="bi bi-check-lg" aria-hidden="true"></i>
              <span>As senhas coincidem.</span>
            </p>
            <p class="cadastro-error" data-error-for="senha_confirm" aria-live="polite"></p>
          </div>

          <button type="button" class="login-submit cadastro-continue">Continuar</button>
        </section>

        <!-- ========= ETAPA 2 — ORGANIZAÇÃO ========= -->
        <section class="cadastro-step<?= $etapaInicial === 2 ? ' is-active' : '' ?>" data-step="2" aria-labelledby="etapa2Titulo">
          <h3 class="visually-hidden" id="etapa2Titulo">Etapa 2: Organização</h3>

          <div class="cadastro-field<?= $campoInvalido('area') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroArea">Área de atuação</label>
            <select name="area" id="cadastroArea" class="auth-select" required>
              <option value="" disabled <?= empty($_POST['area']) ? 'selected' : '' ?>>Selecione uma área</option>
              <?php
              foreach ($areas as $op):
                $sel = (($_POST['area'] ?? '') === $op) ? 'selected' : '';
              ?>
                <option <?= $sel ?>><?= $op ?></option>
              <?php endforeach; ?>
            </select>
            <p class="cadastro-error" data-error-for="area" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('responsavel_nome') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroResponsavel">Responsável legal</label>
            <input type="text" name="responsavel_nome" id="cadastroResponsavel" class="auth-input"
                   placeholder="Nome completo do presidente ou diretor"
                   value="<?= htmlspecialchars($_POST['responsavel_nome'] ?? '') ?>" required>
            <p class="cadastro-help">dirigente máximo</p>
            <p class="cadastro-error" data-error-for="responsavel_nome" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('responsavel_cargo') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroCargo">Cargo</label>
            <input type="text" name="responsavel_cargo" id="cadastroCargo" class="auth-input"
                   placeholder="Ex: Presidente"
                   value="<?= htmlspecialchars($_POST['responsavel_cargo'] ?? '') ?>" required>
            <p class="cadastro-error" data-error-for="responsavel_cargo" aria-live="polite"></p>
          </div>

          <div class="cadastro-grid cadastro-grid--60-40">
            <div class="cadastro-field<?= $campoInvalido('cidade') ? ' is-invalid' : '' ?>">
              <label class="auth-label" for="cadastroCidade">Cidade</label>
              <input type="text" name="cidade" id="cadastroCidade" class="auth-input"
                     placeholder="Ex: Taquaritinga"
                     value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>">
              <p class="cadastro-error" data-error-for="cidade" aria-live="polite"></p>
            </div>

            <div class="cadastro-field<?= $campoInvalido('estado') ? ' is-invalid' : '' ?>">
              <label class="auth-label" for="cadastroEstado">Estado</label>
              <select name="estado" id="cadastroEstado" class="auth-select">
                <option value="">Selecione</option>
                <?php
                $estados = [
                  'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas',
                  'BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal',
                  'ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão',
                  'MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais',
                  'PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco',
                  'PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte',
                  'RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima',
                  'SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe',
                  'TO'=>'Tocantins',
                ];
                foreach ($estados as $sigla => $nomeEstado):
                  $sel = ($_POST['estado'] ?? '') === $sigla ? 'selected' : '';
                ?>
                  <option value="<?= $sigla ?>" <?= $sel ?>><?= $nomeEstado ?> (<?= $sigla ?>)</option>
                <?php endforeach; ?>
              </select>
              <p class="cadastro-error" data-error-for="estado" aria-live="polite"></p>
            </div>
          </div>

          <button type="button" class="cadastro-back-step" data-step-back>
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
          </button>
          <button type="button" class="login-submit cadastro-continue">Continuar</button>
        </section>

        <!-- ========= ETAPA 3 — CANAL E CONFIRMAÇÃO ========= -->
        <section class="cadastro-step<?= $etapaInicial === 3 ? ' is-active' : '' ?>" data-step="3" aria-labelledby="etapa3Titulo">
          <h3 class="visually-hidden" id="etapa3Titulo">Etapa 3: Canal e confirmação</h3>

          <div class="cadastro-field<?= $campoInvalido('canal_tipo') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroCanalTipo">Canal público</label>
            <select name="canal_tipo" id="cadastroCanalTipo" class="auth-select" required>
              <option value="" disabled <?= empty($_POST['canal_tipo']) ? 'selected' : '' ?>>Selecione</option>
              <?php foreach ($tiposCanal as $valor => $rotulo): ?>
                <option value="<?= $valor ?>" <?= (($_POST['canal_tipo'] ?? '') === $valor) ? 'selected' : '' ?>>
                  <?= $rotulo ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="cadastro-error" data-error-for="canal_tipo" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('canal_valor') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroCanalValor">Endereço do canal</label>
            <input type="text" name="canal_valor" id="cadastroCanalValor" class="auth-input"
                   placeholder="Site, @Instagram, Facebook ou telefone"
                   value="<?= htmlspecialchars($_POST['canal_valor'] ?? '') ?>" required>
            <p class="cadastro-help">Usaremos este canal para confirmar que o cadastro é da própria ONG.</p>
            <p class="cadastro-error" data-error-for="canal_valor" aria-live="polite"></p>
          </div>

          <div class="info-note cadastro-info">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            A chave PIX da ONG será o próprio CNPJ informado. Após a aprovação, ela
            fica bloqueada e só pode ser alterada pela Equipe ONGHelp.
          </div>

          <div class="cadastro-field cadastro-field--check<?= $campoInvalido('responsavel_autorizacao') ? ' is-invalid' : '' ?>">
            <label class="cadastro-check" for="responsavel_autorizacao">
              <input type="checkbox" name="responsavel_autorizacao" id="responsavel_autorizacao"
                     class="cadastro-check-input"
                     <?= isset($_POST['responsavel_autorizacao']) ? 'checked' : '' ?> required>
              <span class="cadastro-check-box" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
              <span class="cadastro-check-text">
                Declaro que sou o responsável legal da ONG ou que fui autorizado por ele
                a realizar este cadastro.
              </span>
            </label>
            <p class="cadastro-error" data-error-for="responsavel_autorizacao" aria-live="polite"></p>
          </div>

          <div class="cadastro-field cadastro-field--check<?= $campoInvalido('termos') ? ' is-invalid' : '' ?>">
            <label class="cadastro-check" for="termos">
              <input type="checkbox" name="termos" id="termos"
                     class="cadastro-check-input"
                     <?= isset($_POST['termos']) ? 'checked' : '' ?> required>
              <span class="cadastro-check-box" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
              <span class="cadastro-check-text">
                Concordo com os <a href="termos.php">Termos de Uso</a> e a <a href="privacidade.php">Política de Privacidade</a> do ONGHelp.
              </span>
            </label>
            <p class="cadastro-error" data-error-for="termos" aria-live="polite"></p>
          </div>

          <button type="button" class="cadastro-back-step" data-step-back>
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
          </button>
          <button type="submit" class="login-submit cadastro-final" data-submit-button>
            <span>Criar conta gratuitamente</span>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
          </button>
        </section>
      </form>

      <p class="login-signup">
        Já tem uma conta?
        <a href="login.php">Entrar</a>
      </p>
    </section>
  </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/cadastro.js?v=1"></script>
<script src="../assets/js/ui.js?v=3"></script>
</body>
</html>
