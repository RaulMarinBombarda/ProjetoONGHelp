<?php
require_once __DIR__ . '/../includes/bootstrap.php';

csrfExigir();

$erro      = '';
$campoErro = '';   // campo que recebe o destaque de erro

// depois de um envio bem-sucedido a página é recarregada por GET
// (padrão POST/redirect/GET): atualizar a página não reenvia o
// formulário e o formulário preenchido não fica mais na tela
$enviado = isset($_GET['enviado']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $dados = [
        'nome'              => trim($_POST['nome'] ?? ''),
        'email'             => mb_strtolower(trim($_POST['email'] ?? '')),
        'area'              => trim($_POST['area'] ?? ''),
        'cidade'            => trim($_POST['cidade'] ?? ''),
        'estado'            => trim($_POST['estado'] ?? ''),
        'canal_tipo'        => trim($_POST['canal_tipo'] ?? ''),
        'canal_valor'       => trim($_POST['canal_valor'] ?? ''),
        'responsavel_nome'  => trim($_POST['responsavel_nome'] ?? ''),
        'responsavel_cargo' => trim($_POST['responsavel_cargo'] ?? ''),
    ];
    $cnpj      = somenteDigitos($_POST['cnpj'] ?? '');
    // a senha não passa por trim(): o que a pessoa digita é o que vale
    $senha     = (string) ($_POST['senha'] ?? '');
    $senhaConf = (string) ($_POST['senha_confirm'] ?? '');

    $obrigatorios = ['nome' => $dados['nome'], 'cnpj' => $cnpj, 'email' => $dados['email'], 'senha' => $senha,
        'area' => $dados['area'], 'responsavel_nome' => $dados['responsavel_nome'],
        'responsavel_cargo' => $dados['responsavel_cargo'], 'canal_tipo' => $dados['canal_tipo'],
        'canal_valor' => $dados['canal_valor']];
    $faltando = array_key_first(array_filter($obrigatorios, static fn (string $v): bool => $v === ''));

    if ($faltando !== null) {
        [$campoErro, $erro] = [$faltando, 'Preencha todos os campos obrigatórios.'];
    } elseif (!validarCnpjFormato($cnpj)) {
        [$campoErro, $erro] = ['cnpj', 'Informe o CNPJ com 14 dígitos.'];
    } elseif ($falha = validarDadosOng($dados)) {
        [$campoErro, $erro] = $falha;
    } elseif (!senhaValida($requisitosSenha = requisitosSenha($senha, $senhaConf, $dados['email'], SENHA_MINIMO_ONG))) {
        // só a confirmação diferente aponta para o segundo campo
        $campoErro = senhaValida(array_diff_key($requisitosSenha, ['iguais' => true])) ? 'senha_confirm' : 'senha';
        $erro = senhaPrimeiroErro($requisitosSenha, SENHA_MINIMO_ONG);
    } elseif (!isset($_POST['responsavel_autorizacao'])) {
        [$campoErro, $erro] = ['responsavel_autorizacao', 'Confirme que você responde legalmente pela ONG ou tem autorização para fazer o cadastro.'];
    } elseif (!isset($_POST['termos'])) {
        [$campoErro, $erro] = ['termos', 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.'];
    } else {
        // e-mail ou CNPJ já usados, o REPLACE normaliza CNPJs antigos
        // gravados com máscara, para a duplicidade não passar
        $stmt = $pdo->prepare(
            "SELECT id FROM usuarios
             WHERE email = ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', ''), ' ', '') = ?"
        );
        $stmt->execute([$dados['email'], $cnpj]);

        if ($stmt->fetch()) {
            [$campoErro, $erro] = ['email', 'Já existe uma conta com este e-mail ou CNPJ.'];
        } else {
            try {
                // a chave PIX é o próprio CNPJ, sem máscara, o cadastro
                // nasce pendente: nada é público até a aprovação manual
                $pdo->prepare(
                    "INSERT INTO usuarios
                        (nome, cnpj, email, senha, area, pix_chave, cidade, estado,
                         responsavel_nome, responsavel_cargo, responsavel_autorizacao,
                         canal_tipo, canal_valor, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 'pendente')"
                )->execute([
                    $dados['nome'], $cnpj, $dados['email'], password_hash($senha, PASSWORD_DEFAULT),
                    $dados['area'], $cnpj, $dados['cidade'] ?: 'Brasil', $dados['estado'] ?: null,
                    $dados['responsavel_nome'], $dados['responsavel_cargo'],
                    $dados['canal_tipo'], $dados['canal_valor'],
                ]);
                header('Location: cadastro.php?enviado=1');
                exit;
            } catch (PDOException $e) {
                // dois envios simultâneos podem passar na consulta acima;
                // o UNIQUE do banco barra o segundo (erro 1062)
                error_log('[ONGHelp] Cadastro recusado pelo banco: ' . $e->getMessage());
                $erro = str_contains($e->getMessage(), '1062')
                    ? 'Já existe uma conta com este e-mail ou CNPJ.'
                    : 'Não foi possível concluir o cadastro agora. Tente novamente em alguns instantes.';
                $campoErro = 'email';
            }
        }
    }
}

// etapa que abre quando a página volta com erro
$etapaPorCampo = [
    'area' => 2, 'responsavel_nome' => 2, 'responsavel_cargo' => 2, 'cidade' => 2, 'estado' => 2,
    'canal_tipo' => 3, 'canal_valor' => 3, 'responsavel_autorizacao' => 3, 'termos' => 3,
];
$etapaInicial = $etapaPorCampo[$campoErro] ?? 1;

$campoInvalido = static function (string $campo) use ($campoErro, $erro): bool {
    // "Já existe uma conta" destaca os dois campos que identificam a ONG
    return $campo === $campoErro || ($campoErro === 'email' && $campo === 'cnpj' && str_starts_with($erro, 'Já existe'));
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Criar Conta | ONGHelp</title>
<?php include __DIR__ . '/../includes/layout/head-assets.php'; ?>
</head>
<body class="login-body">

<div class="site-bg" aria-hidden="true">
  <span class="site-orb site-orb-1"></span>
  <span class="site-orb site-orb-2"></span>
  <span class="site-orb site-orb-3"></span>
</div>

<div class="login-shell cadastro-shell">

  <!-- ========= esquerda ========= -->
  <?php include __DIR__ . '/../includes/components/auth-lateral.php'; ?>

  <!-- ========= direita ========= -->
  <main class="login-main cadastro-main" id="conteudo">
    <a href="../index.php" class="login-back">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao site
    </a>

    <a class="login-mobile-brand" href="../index.php" aria-label="ONGHelp — página inicial">
      <img src="<?= asset('img/icone-onghelp.png') ?>" alt="" width="32" height="32">
      <span>ONGHelp</span>
    </a>

    <section class="login-panel cadastro-panel glass-1" aria-labelledby="cadastroTitulo">
      <nav class="login-tabs" aria-label="Acesso à conta">
        <a href="login.php" class="login-tab">Entrar</a>
        <a href="cadastro.php" class="login-tab active" aria-current="page">Criar conta</a>
      </nav>

      <h2 id="cadastroTitulo">Cadastre sua ONG!</h2>
      <?php if (!$enviado): ?>
      <p class="login-subtitle">São 3 etapas rápidas. Antes de ficar público, o cadastro passa por uma análise manual da Equipe ONGHelp.</p>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="login-note login-note--danger" id="cadastroErro" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($erro) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($enviado): ?>
        <div class="cadastro-enviado" role="status">
          <span class="cadastro-enviado-icone" aria-hidden="true"><i class="bi bi-send-check"></i></span>
          <h3>Cadastro enviado!</h3>
          <p>A Equipe ONGHelp vai analisar os dados da sua ONG e avisar pelo e-mail informado. Você poderá entrar assim que o cadastro for aprovado.</p>
          <div class="cadastro-enviado-acoes">
            <a href="../index.php" class="login-submit">Voltar ao site</a>
            <a href="login.php" class="cadastro-back-step">Ir para o login</a>
          </div>
        </div>
      <?php else: ?>

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
            data-cadastro-form data-initial-step="<?= $etapaInicial ?>" data-step="<?= $etapaInicial ?>"<?= senhaAtributosFormulario(SENHA_MINIMO_ONG) ?>
            data-submit-loading>
        <?= csrfField() ?>

        <!-- ========= etapa 1 — conta ========= -->
        <section class="cadastro-step<?= $etapaInicial === 1 ? ' is-active' : '' ?>" data-step="1" aria-labelledby="etapa1Titulo">
          <h3 class="visually-hidden" id="etapa1Titulo">Etapa 1: Conta</h3>

          <div class="cadastro-field<?= $campoInvalido('nome') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroNome">Nome da ONG</label>
            <input type="text" name="nome" id="cadastroNome" class="auth-input"
                   placeholder="Ex.: Instituto Esperança" maxlength="160"
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
                   placeholder="contato@suaong.org.br" maxlength="160"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
            <p class="cadastro-error" data-error-for="email" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('senha') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="senhaCad">Senha</label>
            <div class="auth-input-wrap">
              <input type="password" name="senha" class="auth-input" id="senhaCad"
                     placeholder="••••••••" autocomplete="new-password" minlength="<?= SENHA_MINIMO_ONG ?>" maxlength="<?= SENHA_MAXIMO_BYTES ?>"
                     aria-describedby="cadastroSenhaRequisitos" required>
              <button class="auth-eye" type="button"
                      data-password-toggle="senhaCad" aria-label="Mostrar senha" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div id="cadastroSenhaRequisitos">
              <?= senhaChecklist(
                  ['minimo' => 'minimo', 'letra_numero' => 'letra_numero', 'sem_email' => 'email', 'nao_comum' => 'comum'],
                  SENHA_MINIMO_ONG,
                  $requisitosSenha ?? [],
                  ['lista' => 'auth-senha-checklist cadastro-senha-checklist']
              ) ?>
            </div>
            <p class="cadastro-error" data-error-for="senha" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('senha_confirm') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="senhaCad2">Confirmar senha</label>
            <div class="auth-input-wrap">
              <input type="password" name="senha_confirm" class="auth-input" id="senhaCad2"
                     placeholder="••••••••" autocomplete="new-password" minlength="<?= SENHA_MINIMO_ONG ?>" maxlength="<?= SENHA_MAXIMO_BYTES ?>" required>
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

        <!-- ========= etapa 2 — organização ========= -->
        <section class="cadastro-step<?= $etapaInicial === 2 ? ' is-active' : '' ?>" data-step="2" aria-labelledby="etapa2Titulo">
          <h3 class="visually-hidden" id="etapa2Titulo">Etapa 2: Organização</h3>

          <div class="cadastro-field<?= $campoInvalido('area') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroArea">Área de atuação</label>
            <select name="area" id="cadastroArea" class="auth-select" required>
              <option value="" disabled <?= empty($_POST['area']) ? 'selected' : '' ?>>Selecione uma área</option>
              <?php
              foreach (ONGHELP_AREAS as $op):
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
                   placeholder="Nome completo" maxlength="160"
                   value="<?= htmlspecialchars($_POST['responsavel_nome'] ?? '') ?>" required>
            <p class="cadastro-help">Quem responde legalmente pela ONG, como a presidência ou a diretoria.</p>
            <p class="cadastro-error" data-error-for="responsavel_nome" aria-live="polite"></p>
          </div>

          <div class="cadastro-field<?= $campoInvalido('responsavel_cargo') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroCargo">Cargo</label>
            <input type="text" name="responsavel_cargo" id="cadastroCargo" class="auth-input"
                   placeholder="Ex.: Presidente" maxlength="80"
                   value="<?= htmlspecialchars($_POST['responsavel_cargo'] ?? '') ?>" required>
            <p class="cadastro-error" data-error-for="responsavel_cargo" aria-live="polite"></p>
          </div>

          <div class="cadastro-grid cadastro-grid--60-40">
            <div class="cadastro-field<?= $campoInvalido('cidade') ? ' is-invalid' : '' ?>">
              <label class="auth-label" for="cadastroCidade">Cidade</label>
              <input type="text" name="cidade" id="cadastroCidade" class="auth-input"
                     placeholder="Ex.: Taquaritinga" maxlength="100"
                     value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>">
              <p class="cadastro-error" data-error-for="cidade" aria-live="polite"></p>
            </div>

            <div class="cadastro-field<?= $campoInvalido('estado') ? ' is-invalid' : '' ?>">
              <label class="auth-label" for="cadastroEstado">Estado</label>
              <select name="estado" id="cadastroEstado" class="auth-select">
                <option value="">Selecione</option>
                <?php
                foreach (ONGHELP_ESTADOS as $sigla => $nomeEstado):
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

        <!-- ========= etapa 3 — canal e confirmação ========= -->
        <section class="cadastro-step<?= $etapaInicial === 3 ? ' is-active' : '' ?>" data-step="3" aria-labelledby="etapa3Titulo">
          <h3 class="visually-hidden" id="etapa3Titulo">Etapa 3: Canal e confirmação</h3>

          <div class="cadastro-field<?= $campoInvalido('canal_tipo') ? ' is-invalid' : '' ?>">
            <label class="auth-label" for="cadastroCanalTipo">Canal público</label>
            <select name="canal_tipo" id="cadastroCanalTipo" class="auth-select" data-canal-tipo required>
              <option value="" disabled <?= empty($_POST['canal_tipo']) ? 'selected' : '' ?>>Selecione</option>
              <?php foreach (ONGHELP_TIPOS_CANAL as $valor => $rotulo): ?>
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
                   placeholder="Site, @Instagram, Facebook ou telefone" maxlength="255" data-canal-valor
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
                Declaro que respondo legalmente pela ONG ou que tenho autorização
                de quem responde para fazer este cadastro.
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
            <span>Enviar cadastro</span>
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
          </button>
        </section>
      </form>
      <?php endif; ?>

      <?php if (!$enviado): ?>
      <p class="login-signup">
        Já tem uma conta?
        <a href="login.php">Entrar</a>
      </p>
      <?php endif; ?>
    </section>
  </main>

</div>

<?php
$page_scripts = ['core/senha.js', 'site/cadastro.js'];
include __DIR__ . '/../includes/layout/scripts.php';
?>
</body>
</html>
