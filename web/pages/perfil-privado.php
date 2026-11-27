<?php
// ============================================================
// pages/perfil-privado.php — painel da ONG logada
// projeto ONGHelp · Projeto Integrador 2026
// ============================================================
//
// seções (?secao=): visão geral, relatório anual, editar perfil, foto e
// aparência, verificação e PIX, segurança (troca de senha com as regras
// de includes/core/senha.php) e conta (o pedido de exclusão leva a
// pages/excluir-conta.php, que tem a confirmação definitiva)
//
// todo valor exibido passa por htmlspecialchars() ou e()
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/modules/noticias-fotos.php';

// ── protege: só ONG aprovada e logada acessa ─────────────────
exigirLoginOng();
csrfExigir();

$id      = (int) $_SESSION['user_id'];
$erro    = '';
$sucesso = '';


// ── busca dados atuais do usuário ────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND status = 'aprovada'");
$stmt->execute([$id]);
$user = $stmt->fetch();

// se a conta não existir mais ou tiver sido suspensa/recusada,
// encerra a sessão, o status é revalidado a cada carregamento
if (!$user) {
    encerrarLogin('user');
    header('Location: login.php');
    exit;
}

// mantém a sessão alinhada com a foto atual do banco (o cabeçalho usa
// $_SESSION['user_foto'] para desenhar o avatar sem consultar a cada página)
$_SESSION['user_foto'] = $user['foto_perfil'] ?? null;

// ── estatísticas da organização ────────────────────────────
$statsPerfil = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS total_receitas,
        COALESCE(COUNT(DISTINCT id), 0) AS total_movimentacoes,
        (SELECT COUNT(*) FROM projetos WHERE usuario_id = ?) AS total_projetos
    FROM movimentacoes WHERE usuario_id = ?
");
$statsPerfil->execute([$id, $id]);
$stats = $statsPerfil->fetch();

// ── processa formulários (POST) ────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'editar') {
        $nome      = trim($_POST['nome']      ?? '');
        $email     = mb_strtolower(trim($_POST['email'] ?? ''));
        $area      = trim($_POST['area']      ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $cidade    = trim($_POST['cidade']    ?? '');
        $estado    = trim($_POST['estado']    ?? '');
        $canalTipo = trim($_POST['canal_tipo']  ?? '');
        $canalValor = trim($_POST['canal_valor'] ?? '');
        if (!$nome || !$email || !$area) {
            $erro = 'Nome, e-mail e área de atuação são obrigatórios.';
        } elseif ($falha = validarDadosOng([
            'nome' => $nome, 'email' => $email, 'area' => $area, 'descricao' => $descricao,
            'cidade' => $cidade, 'estado' => $estado, 'canal_tipo' => $canalTipo, 'canal_valor' => $canalValor,
        ])) {
            $erro = $falha[1];
        } else {
            $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $check->execute([$email, $id]);

            if ($check->fetch()) {
                $erro = 'Este e-mail já está sendo usado por outra conta.';
            } else {
                // PIX, CNPJ e responsável legal são imutáveis pela ONG,
                // a chave PIX é o próprio CNPJ, conferido na aprovação;
                // qualquer correção passa pela Equipe ONGHelp
                $pdo->prepare("
                    UPDATE usuarios
                    SET nome=?, email=?, area=?, descricao=?, canal_tipo=?, canal_valor=?, cidade=?, estado=?
                    WHERE id=?
                ")->execute([$nome, $email, $area, $descricao, $canalTipo, $canalValor, $cidade ?: 'Brasil', $estado ?: null, $id]);

                $_SESSION['user_nome'] = $nome;
                $_SESSION['user_area'] = $area;
                $sucesso = 'Dados atualizados com sucesso!';

                $stmt2 = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt2->execute([$id]);
                $user = $stmt2->fetch();
            }
        }
    } elseif ($acao === 'senha') {
        // sem trim(): a senha vale exatamente como foi digitada, como no cadastro
        $senhaAtual    = (string) ($_POST['senha_atual'] ?? '');
        $senha         = (string) ($_POST['senha'] ?? '');
        $senha_confirm = (string) ($_POST['senha_confirm'] ?? '');

        $hashAtual = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $hashAtual->execute([$id]);

        $hashAtual = (string) $hashAtual->fetchColumn();
        $requisitosSenha = requisitosSenha($senha, $senha_confirm, (string) $user['email'], SENHA_MINIMO_ONG);
        $requisitosSenha['diferente_atual'] = $senha !== '' && !password_verify($senha, $hashAtual);

        if ($senhaAtual === '' || !password_verify($senhaAtual, $hashAtual)) {
            $erro = 'A senha atual não confere.';
        } elseif ($senha === '') {
            $erro = 'Informe a nova senha.';
        } elseif (!senhaValida($requisitosSenha)) {
            $erro = senhaPrimeiroErro($requisitosSenha, SENHA_MINIMO_ONG);
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $id]);
            $sucesso = 'Senha atualizada com sucesso!';
        }
    } elseif ($acao === 'tema_salvar') {
        // só cores da paleta; a cor da própria área volta a ser "sem escolha",
        // assim o perfil acompanha a área se ela mudar depois
        $corBanner = strtoupper(trim($_POST['cor_tema'] ?? ''));
        if (!corBannerValida($corBanner)) {
            $erro = 'Escolha uma das cores da paleta para o banner.';
        } else {
            $corDb = $corBanner === corCausa($user) ? null : $corBanner;
            $pdo->prepare("UPDATE usuarios SET cor_tema = ? WHERE id = ?")->execute([$corDb, $id]);
            $user['cor_tema'] = $corDb;
            $sucesso = 'Cor do banner atualizada! Veja no perfil público.';
        }
    } elseif ($acao === 'tema_reset') {
        $pdo->prepare("UPDATE usuarios SET cor_tema = NULL WHERE id = ?")->execute([$id]);
        $user['cor_tema'] = null;
        $sucesso = 'O banner voltou à cor da sua área de atuação.';
    } elseif ($acao === 'foto_salvar') {
        $pasta   = ONGHELP_UPLOADS_DIR . '/perfis';
        $arquivo = $_FILES['foto'] ?? null;

        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $erro = 'Selecione uma imagem para enviar.';
        } elseif (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $erro = 'Não foi possível enviar a imagem. Tente novamente.';
        } elseif (($arquivo['size'] ?? 0) > 2 * 1024 * 1024) {
            $erro = 'A imagem deve ter no máximo 2 MB.';
        } else {
            $mime      = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
            $extensoes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($extensoes[$mime]) || @getimagesize($arquivo['tmp_name']) === false) {
                $erro = 'Envie uma imagem JPG, PNG ou WebP válida.';
            } elseif (!is_dir($pasta) && !mkdir($pasta, 0775, true)) {
                $erro = 'Não foi possível preparar a pasta de fotos. Tente novamente.';
            } else {
                $novoArquivo = 'ong_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $extensoes[$mime];

                if (!move_uploaded_file($arquivo['tmp_name'], $pasta . '/' . $novoArquivo)) {
                    $erro = 'Não foi possível salvar a imagem. Tente novamente.';
                } else {
                    // remove EXIF/GPS e outros metadados da foto (mesmo
                    // tratamento das fotos de notícias)
                    if ($extensoes[$mime] === 'jpg') {
                        noticiaFotoLimparMetadadosJpeg($pasta . '/' . $novoArquivo);
                    }

                    $fotoAntiga = $user['foto_perfil'] ?? null;

                    $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")
                        ->execute([$novoArquivo, $id]);

                    if ($fotoAntiga && basename($fotoAntiga) === $fotoAntiga
                        && is_file($pasta . '/' . $fotoAntiga)) {
                        @unlink($pasta . '/' . $fotoAntiga);
                    }

                    $user['foto_perfil']   = $novoArquivo;
                    $_SESSION['user_foto'] = $novoArquivo;
                    $sucesso = 'Foto de perfil atualizada!';
                }
            }
        }
    } elseif ($acao === 'foto_remover') {
        $pasta      = ONGHELP_UPLOADS_DIR . '/perfis';
        $fotoAntiga = $user['foto_perfil'] ?? null;

        $pdo->prepare("UPDATE usuarios SET foto_perfil = NULL WHERE id = ?")->execute([$id]);

        if ($fotoAntiga && basename($fotoAntiga) === $fotoAntiga
            && is_file($pasta . '/' . $fotoAntiga)) {
            @unlink($pasta . '/' . $fotoAntiga);
        }

        $user['foto_perfil']   = null;
        $_SESSION['user_foto'] = null;
        $sucesso = 'Foto removida. O avatar padrão voltou a ser exibido.';
    }
}

// ── relatório anual ─────────────────────────────────────────
$anoAtual = (int) date('Y');

$stmtFaixa = $pdo->prepare("
    SELECT MIN(YEAR(data_mov)) AS min_ano, MAX(YEAR(data_mov)) AS max_ano
    FROM movimentacoes
    WHERE usuario_id = ?
");
$stmtFaixa->execute([$id]);
$faixa = $stmtFaixa->fetch() ?: [];

$anoMin = (int) ($faixa['min_ano'] ?? $anoAtual);
$anoMax = max($anoAtual, (int) ($faixa['max_ano'] ?? $anoAtual));
$anoMin = max(2000, min(2100, $anoMin));
$anoMax = max(2000, min(2100, $anoMax));

if ($anoMin > $anoMax) {
    $anoMin = $anoAtual;
    $anoMax = $anoAtual;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
if ($ano === null || $ano === false || $ano < $anoMin || $ano > $anoMax) {
    $ano = max($anoMin, min($anoAtual, $anoMax));
}
$anoAnterior = $ano - 1;
$anosDisponiveis = range($anoMax, $anoMin);

$stmtMensal = $pdo->prepare("
    SELECT tipo, MONTH(data_mov) AS mes, SUM(valor) AS total
    FROM movimentacoes
    WHERE usuario_id = ? AND data_mov BETWEEN ? AND ?
    GROUP BY tipo, mes
");

$carregarMensal = function (int $anoRef) use ($pdo, $id, $stmtMensal): array {
    $mensal = [
        'receita' => array_fill(1, 12, 0.0),
        'despesa' => array_fill(1, 12, 0.0),
    ];

    $stmtMensal->execute([$id, "$anoRef-01-01", "$anoRef-12-31"]);
    foreach ($stmtMensal->fetchAll() as $linha) {
        $tipo = $linha['tipo'];
        $mes  = (int) $linha['mes'];
        if (isset($mensal[$tipo]) && $mes >= 1 && $mes <= 12) {
            $mensal[$tipo][$mes] = (float) $linha['total'];
        }
    }

    return $mensal;
};

$balancete         = balanceteDoAno($carregarMensal($ano));
$balanceteAnterior = balanceteDoAno($carregarMensal($anoAnterior));
$semMovimento      = ((float) $balancete['total_receita'] === 0.0
                      && (float) $balancete['total_despesa'] === 0.0);

$comparativo = [
    'receitas' => [
        'label'    => 'Receitas',
        'atual'    => (float) $balancete['total_receita'],
        'anterior' => (float) $balanceteAnterior['total_receita'],
    ],
    'despesas' => [
        'label'    => 'Despesas',
        'atual'    => (float) $balancete['total_despesa'],
        'anterior' => (float) $balanceteAnterior['total_despesa'],
    ],
    'saldo' => [
        'label'    => 'Saldo',
        'atual'    => (float) $balancete['total_saldo'],
        'anterior' => (float) $balanceteAnterior['total_saldo'],
    ],
];

foreach ($comparativo as &$item) {
    $item['diferenca'] = $item['atual'] - $item['anterior'];
    $item['percentual'] = $item['anterior'] != 0.0
        ? ($item['diferenca'] / abs($item['anterior'])) * 100
        : null;
}
unset($item);

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

// ── variáveis de apresentação (tema e cabeçalho) ────────────
// a lógica de salvamento acima permanece intacta; aqui apenas
// resolvemos os valores que a interface precisa exibir
$coresArea = coresArea();
[$corBanner, $corBannerDark] = temaOng($user, $coresArea);
$corDaArea = corCausa($user, $coresArea);
$dataAprovacao = (string) ($user['aprovado_em'] ?: ($user['verificada_em'] ?? ''));

// ── seção ativa do painel (?secao=) ─────────────────────────
// cada item do menu é um link para a mesma página com outra seção,
// os formulários usam action="", então voltam para a seção de onde
// foram enviados
$secoesPerfil = [
    'visao-geral'       => ['Visão geral', 'bi-grid-1x2'],
    'relatorio'         => ['Relatório anual', 'bi-graph-up-arrow'],
    'editar-perfil'     => ['Editar perfil', 'bi-pencil-square'],
    'foto-e-aparencia'  => ['Foto e aparência', 'bi-camera'],
    'verificacao-e-pix' => ['Verificação e PIX', 'bi-patch-check'],
    'seguranca'         => ['Segurança', 'bi-shield-lock'],
    'conta'             => ['Conta', 'bi-exclamation-triangle'],
];
$secaoAtiva = (string) ($_GET['secao'] ?? 'visao-geral');
if (!isset($secoesPerfil[$secaoAtiva])) {
    $secaoAtiva = 'visao-geral';
}

function urlSecaoPerfil(string $secao): string
{
    return 'perfil-privado.php' . ($secao === 'visao-geral' ? '' : '?secao=' . rawurlencode($secao)) . '#priv-conteudo';
}

$page_title = 'Meu Perfil';
$active_nav = '';
include __DIR__ . '/../includes/layout/header.php';
?>

<section class="perfil-privado"
         style="--priv-banner:<?= htmlspecialchars($corBanner) ?>;--priv-banner-dark:<?= htmlspecialchars($corBannerDark) ?>;--priv-causa:<?= htmlspecialchars($corDaArea) ?>;">
  <div class="container">

    <!-- ── cabeçalho DE perfil ── -->
    <header class="priv-header">
      <div class="priv-cover" aria-hidden="true"></div>

      <div class="priv-header-body">
        <div class="priv-avatar-wrap">
          <img src="<?= htmlspecialchars(fotoPerfilUrl($user['foto_perfil'] ?? null, '../', $user['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
               alt="Foto de perfil de <?= htmlspecialchars($user['nome']) ?>"
               class="priv-avatar">
          <a href="<?= urlSecaoPerfil('foto-e-aparencia') ?>" class="priv-camera"
             aria-label="Alterar foto e aparência">
            <i class="bi bi-camera" aria-hidden="true"></i>
          </a>
        </div>

        <div class="priv-identity">
          <div class="priv-name-row">
            <h1 class="priv-name"><?= htmlspecialchars($user['nome']) ?></h1>
            <?= badgeVerificado($user, true) ?>
          </div>
          <p class="priv-meta">
            <span><?= htmlspecialchars($user['area']) ?></span>
            <span class="priv-meta-dot" aria-hidden="true">·</span>
            <span>Membro desde <?= date('Y', strtotime($user['criado_em'])) ?></span>
            <span class="priv-meta-dot" aria-hidden="true">·</span>
            <span>CNPJ <?= htmlspecialchars(mascaraCnpj($user['cnpj'])) ?></span>
          </p>
        </div>

        <div class="priv-header-actions">
          <a href="perfil-publico.php?id=<?= $id ?>" class="btn-portal">
            <i class="bi bi-person" aria-hidden="true"></i> Ver perfil público
          </a>
          <a href="transparencia.php?ong=<?= $id ?>" class="btn-portal">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Ver no portal de transparência
          </a>
        </div>
      </div>

      <div class="priv-counters">
        <div class="priv-counter">
          <span class="priv-counter-value"><?= number_format($stats['total_projetos'], 0, ',', '.') ?></span>
          <span class="priv-counter-label">Projetos ativos</span>
        </div>
        <div class="priv-counter">
          <span class="priv-counter-value"><?= number_format($stats['total_movimentacoes'], 0, ',', '.') ?></span>
          <span class="priv-counter-label">Movimentações</span>
        </div>
        <div class="priv-counter">
          <span class="priv-counter-value is-arrecadado">R$ <?= number_format($stats['total_receitas'], 0, ',', '.') ?></span>
          <span class="priv-counter-label">Total arrecadado</span>
        </div>
      </div>
    </header>

    <!-- ── configurações ── -->
    <div class="priv-layout">

      <div class="priv-menu-col">
        <nav class="priv-menu" aria-label="Configurações do perfil">
          <?php foreach ($secoesPerfil as $chaveSecao => [$rotuloSecao, $iconeSecao]): ?>
            <a href="<?= urlSecaoPerfil($chaveSecao) ?>"
               class="priv-menu-item<?= $chaveSecao === 'conta' ? ' priv-menu-danger' : '' ?><?= $chaveSecao === $secaoAtiva ? ' is-active' : '' ?>"<?= $chaveSecao === $secaoAtiva ? ' aria-current="page"' : '' ?>>
              <i class="bi <?= $iconeSecao ?>" aria-hidden="true"></i><span><?= $rotuloSecao ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="priv-content" id="priv-conteudo">

        <!-- ── visão geral ── -->
        <section class="priv-panel" id="priv-panel-visao-geral"
                 aria-labelledby="priv-title-visao-geral"<?= $secaoAtiva === 'visao-geral' ? '' : ' hidden' ?>>
          <h2 class="priv-panel-title" id="priv-title-visao-geral">Visão geral</h2>

          <div class="priv-kpi-strip">
            <div class="priv-kpi">
              <span class="priv-kpi-value"><?= number_format($stats['total_projetos'], 0, ',', '.') ?></span>
              <span class="priv-kpi-label">Projetos ativos</span>
            </div>
            <div class="priv-kpi">
              <span class="priv-kpi-value"><?= number_format($stats['total_movimentacoes'], 0, ',', '.') ?></span>
              <span class="priv-kpi-label">Movimentações</span>
            </div>
            <div class="priv-kpi">
              <span class="priv-kpi-value is-arrecadado">R$ <?= number_format($stats['total_receitas'], 0, ',', '.') ?></span>
              <span class="priv-kpi-label">Total arrecadado</span>
            </div>
          </div>

          <h3 class="priv-subtitle">Notas</h3>
          <div class="priv-notes">
            <p class="priv-note">
              <i class="bi bi-patch-check" aria-hidden="true"></i>
              O selo indica que a Equipe ONGHelp conferiu o cadastro. Não é auditoria financeira.
            </p>
            <p class="priv-note">
              <i class="bi bi-lock" aria-hidden="true"></i>
              CNPJ e PIX são imutáveis após a aprovação. A chave PIX é o próprio CNPJ
              (<?= htmlspecialchars($user['pix_chave']) ?>). Para corrigir, fale com a Equipe ONGHelp.
            </p>
            <p class="priv-note">
              <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
              Os totais são os mesmos exibidos no Portal de Transparência e nos arquivos exportados.
            </p>
          </div>
        </section>

        <!-- ── relatório anual ── -->
        <section class="priv-panel" id="priv-panel-relatorio"
                 aria-labelledby="priv-title-relatorio"<?= $secaoAtiva === 'relatorio' ? '' : ' hidden' ?>>
          <div class="priv-panel-head">
            <div>
              <h2 class="priv-panel-title" id="priv-title-relatorio">Relatório anual</h2>
              <p class="priv-panel-desc">Balancete de 12 meses e comparativo com o ano anterior.</p>
            </div>

            <form method="get" action="perfil-privado.php#priv-conteudo" class="priv-year-form">
              <input type="hidden" name="secao" value="relatorio">
              <label for="ano" class="priv-year-label">Ano</label>
              <select name="ano" id="ano" class="form-select form-select-sm" data-auto-submit>
                <?php foreach ($anosDisponiveis as $anoOpcao): ?>
                  <option value="<?= $anoOpcao ?>" <?= $anoOpcao === $ano ? 'selected' : '' ?>><?= $anoOpcao ?></option>
                <?php endforeach; ?>
              </select>
              <noscript>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Ver</button>
              </noscript>
            </form>
          </div>

          <?php if ($semMovimento): ?>
            <div class="priv-empty-note">
              <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
              <div>
                <strong>Nenhuma movimentação registrada em <?= $ano ?>.</strong>
                <div class="mt-1">Selecione outro ano ou cadastre uma movimentação para gerar o relatório.</div>
              </div>
            </div>
          <?php else: ?>

            <div class="priv-report-kpis">
              <div class="priv-report-kpi">
                <div class="priv-report-kpi-label">
                  Receitas <i class="bi bi-cash-coin" aria-hidden="true"></i>
                </div>
                <div class="priv-report-kpi-value is-receita">
                  <?= fmtDinheiro((float) $balancete['total_receita']) ?>
                </div>
                <small>Total recebido no ano</small>
              </div>
              <div class="priv-report-kpi">
                <div class="priv-report-kpi-label">
                  Despesas <i class="bi bi-receipt" aria-hidden="true"></i>
                </div>
                <div class="priv-report-kpi-value is-despesa">
                  <?= fmtDinheiro((float) $balancete['total_despesa']) ?>
                </div>
                <small>Total gasto no ano</small>
              </div>
              <?php $saldoAno = (float) $balancete['total_saldo']; ?>
              <div class="priv-report-kpi">
                <div class="priv-report-kpi-label">
                  Saldo acumulado do ano <i class="bi bi-wallet2" aria-hidden="true"></i>
                </div>
                <div class="priv-report-kpi-value <?= $saldoAno < 0 ? 'is-negativo' : ($saldoAno > 0 ? 'is-positivo' : '') ?>">
                  <?= fmtSaldoTabela($saldoAno) ?>
                </div>
                <small>Receitas menos despesas</small>
              </div>
            </div>

            <h3 class="priv-subtitle">Comparativo com <?= $anoAnterior ?></h3>
            <div class="priv-table-wrap">
              <table class="priv-table">
                <thead>
                  <tr>
                    <th>Indicador</th>
                    <th class="num"><?= $ano ?></th>
                    <th class="num"><?= $anoAnterior ?></th>
                    <th class="num">Diferença</th>
                    <th class="num">Variação</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($comparativo as $item): ?>
                    <tr>
                      <td class="fw-600"><?= htmlspecialchars($item['label']) ?></td>
                      <td class="num"><?= fmtDinheiroTabela($item['atual']) ?></td>
                      <td class="num"><?= fmtDinheiroTabela($item['anterior']) ?></td>
                      <td class="num <?= $item['diferenca'] > 0 ? 'text-success' : ($item['diferenca'] < 0 ? 'text-danger' : '') ?>">
                        <?= fmtSaldoTabela($item['diferenca']) ?>
                      </td>
                      <td class="num <?= $item['percentual'] === null ? '' : ($item['percentual'] > 0 ? 'text-success' : ($item['percentual'] < 0 ? 'text-danger' : '')) ?>">
                        <?php if ($item['percentual'] === null): ?>
                          <span class="zero" title="Sem base de comparação">—</span>
                        <?php else: ?>
                          <?= ($item['percentual'] > 0 ? '+' : '') . number_format($item['percentual'], 2, ',', '.') ?>%
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <h3 class="priv-subtitle">Balancete mensal</h3>
            <div class="priv-table-wrap">
              <table class="priv-table">
                <thead>
                  <tr>
                    <th>Mês</th>
                    <th class="num">Receitas</th>
                    <th class="num">Despesas</th>
                    <th class="num">Saldo do mês</th>
                    <th class="num">Saldo acumulado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($balancete['linhas'] as $mesNum => $linha): ?>
                    <tr>
                      <td class="fw-600"><?= $meses[$mesNum] ?></td>
                      <td class="num"><?= fmtDinheiroTabela((float) $linha['receita']) ?></td>
                      <td class="num"><?= fmtDinheiroTabela((float) $linha['despesa']) ?></td>
                      <td class="num <?= $linha['saldo'] < 0 ? 'text-danger' : '' ?>">
                        <?= fmtSaldoTabela((float) $linha['saldo']) ?>
                      </td>
                      <td class="num <?= $linha['acumulado'] < 0 ? 'text-danger' : '' ?>">
                        <?= fmtSaldoTabela((float) $linha['acumulado']) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td>Total do ano</td>
                    <td class="num"><?= fmtDinheiroTabela((float) $balancete['total_receita']) ?></td>
                    <td class="num"><?= fmtDinheiroTabela((float) $balancete['total_despesa']) ?></td>
                    <td class="num <?= $balancete['total_saldo'] < 0 ? 'text-danger' : '' ?>">
                      <?= fmtSaldoTabela((float) $balancete['total_saldo']) ?>
                    </td>
                    <td class="num <?= $balancete['acumulado_final'] < 0 ? 'text-danger' : '' ?>">
                      <?= fmtSaldoTabela((float) $balancete['acumulado_final']) ?>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="priv-report-actions">
              <a href="actions/relatorio-anual.php?ong=<?= $id ?>&ano=<?= $ano ?>"
                 target="_blank" rel="noopener"
                 class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> Baixar PDF
              </a>
              <a href="actions/exportar-csv.php?ong=<?= $id ?>&ano=<?= $ano ?>"
                 target="_blank" rel="noopener"
                 class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Baixar CSV
              </a>
            </div>
            <p class="priv-help mt-3 mb-0">
              Os totais são os mesmos exibidos no Portal de Transparência e nos arquivos exportados.
            </p>

          <?php endif; ?>
        </section>

        <!-- ── editar perfil ── -->
        <section class="priv-panel" id="priv-panel-editar-perfil"
                 aria-labelledby="priv-title-editar-perfil"<?= $secaoAtiva === 'editar-perfil' ? '' : ' hidden' ?>>
          <h2 class="priv-panel-title" id="priv-title-editar-perfil">Editar perfil</h2>
          <p class="priv-panel-desc">
            As informações marcadas com <span class="priv-required">*</span> são obrigatórias.
          </p>

          <form method="POST" action="" id="privEditarForm" data-priv-dirty-form>
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="editar">

            <h3 class="priv-subtitle">Identidade</h3>
            <div class="priv-form-grid">
              <div class="priv-field priv-field-full">
                <label class="form-label" for="perfilNome">Nome da organização <span class="priv-required">*</span></label>
                <input type="text" id="perfilNome" name="nome" class="form-control" required
                       value="<?= htmlspecialchars($user['nome']) ?>">
              </div>
              <div class="priv-field priv-field-full">
                <label class="form-label" for="perfilDescricao">Descrição / Missão</label>
                <textarea name="descricao" id="perfilDescricao" class="form-control" rows="3"
                          placeholder="Conte um pouco sobre a história, o propósito e o impacto da sua organização…"><?= htmlspecialchars($user['descricao'] ?? '') ?></textarea>
                <small class="priv-help">
                  Aparece no Portal de Transparência. Use para sensibilizar doadores.
                </small>
              </div>
            </div>

            <h3 class="priv-subtitle">Contato e canal</h3>
            <div class="priv-form-grid">
              <div class="priv-field">
                <label class="form-label" for="perfilEmail">E-mail <span class="priv-required">*</span></label>
                <input type="email" id="perfilEmail" name="email" class="form-control" required
                       value="<?= htmlspecialchars($user['email']) ?>">
              </div>
              <div class="priv-field">
                <label class="form-label" for="perfilArea">Área de atuação <span class="priv-required">*</span></label>
                <select name="area" id="perfilArea" class="form-select" required>
                  <?php
                  foreach (ONGHELP_AREAS as $op):
                    $sel = ($user['area'] === $op) ? 'selected' : '';
                  ?>
                    <option <?= $sel ?>><?= htmlspecialchars($op) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="priv-field priv-field-full">
                <label class="form-label" for="perfilCanalValor">Canal público <span class="priv-required">*</span></label>
                <div class="priv-canal-grid">
                  <select name="canal_tipo" id="perfilCanalTipo" class="form-select" required aria-label="Tipo do canal público">
                    <?php foreach (ONGHELP_TIPOS_CANAL as $valorCanal => $rotuloCanal): ?>
                      <option value="<?= $valorCanal ?>" <?= ($user['canal_tipo'] ?? '') === $valorCanal ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rotuloCanal) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="canal_valor" id="perfilCanalValor" class="form-control" required
                         placeholder="Site, @Instagram, Facebook ou telefone"
                         value="<?= htmlspecialchars($user['canal_valor'] ?? '') ?>">
                </div>
                <small class="priv-help">
                  Aparece no perfil público. Alterações substanciais podem ser revisadas pela Equipe ONGHelp.
                </small>
              </div>
            </div>

            <h3 class="priv-subtitle">Localização</h3>
            <div class="priv-form-grid">
              <div class="priv-field">
                <label class="form-label" for="perfilCidade">Cidade</label>
                <input type="text" name="cidade" id="perfilCidade" class="form-control"
                       placeholder="Ex.: Taquaritinga"
                       value="<?= htmlspecialchars($user['cidade'] ?? '') ?>">
              </div>
              <div class="priv-field">
                <label class="form-label" for="perfilEstado">Estado</label>
                <select name="estado" id="perfilEstado" class="form-select">
                  <option value="">Selecione</option>
                  <?php
                  $estadoAtual = $user['estado'] ?? '';
                  foreach (ONGHELP_ESTADOS as $sigla => $nomeEstado):
                    $sel = $estadoAtual === $sigla ? 'selected' : '';
                  ?>
                    <option value="<?= $sigla ?>" <?= $sel ?>><?= $nomeEstado ?> (<?= $sigla ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <h3 class="priv-subtitle">Dados bloqueados</h3>
            <div class="priv-form-grid">
              <div class="priv-field">
                <label class="form-label" for="perfilCnpj">CNPJ</label>
                <div class="priv-locked">
                  <span class="priv-locked-icon"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                  <input type="text" id="perfilCnpj" class="form-control"
                         value="<?= htmlspecialchars(mascaraCnpj($user['cnpj'])) ?>" disabled>
                </div>
              </div>
              <div class="priv-field">
                <label class="form-label" for="perfilPix">Chave PIX (CNPJ)</label>
                <div class="priv-locked">
                  <span class="priv-locked-icon"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                  <input type="text" id="perfilPix" class="form-control"
                         value="<?= htmlspecialchars($user['pix_chave']) ?>" disabled>
                </div>
              </div>
              <div class="priv-field priv-field-full">
                <small class="priv-help">
                  CNPJ e PIX são imutáveis após a aprovação. A chave PIX é o próprio CNPJ
                  (<?= htmlspecialchars($user['pix_chave']) ?>). Para corrigir, fale com a Equipe ONGHelp.
                </small>
              </div>
            </div>

          </form>
        </section>

        <!-- ── foto e aparência ── -->
        <section class="priv-panel" id="priv-panel-foto-e-aparencia"
                 aria-labelledby="priv-title-foto-e-aparencia"<?= $secaoAtiva === 'foto-e-aparencia' ? '' : ' hidden' ?>>
          <h2 class="priv-panel-title" id="priv-title-foto-e-aparencia">Foto e aparência</h2>

          <div class="priv-photo-theme">
            <div class="priv-photo-col">
              <h3 class="priv-subtitle">Foto de perfil</h3>
              <div class="priv-photo-preview-wrap">
                <img src="<?= htmlspecialchars(fotoPerfilUrl($user['foto_perfil'] ?? null, '../', $user['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                     alt="Foto de perfil atual" class="priv-photo-preview" data-priv-photo-preview>
              </div>

              <form method="POST" action="" enctype="multipart/form-data" class="priv-photo-form">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="foto_salvar">
                <input type="file" name="foto" id="fotoPerfilInput"
                       class="priv-file-input" required
                       accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <div class="priv-file-row">
                  <label for="fotoPerfilInput" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-image" aria-hidden="true"></i> Escolher imagem
                  </label>
                  <span class="priv-file-name" data-priv-photo-name>Nenhum arquivo escolhido</span>
                </div>
                <small class="priv-help">JPG, PNG ou WebP, até 2 MB.</small>
                <button type="submit" class="btn btn-primary rounded-pill px-4 mt-3"
                        data-priv-photo-submit disabled>
                  <i class="bi bi-upload" aria-hidden="true"></i> Enviar foto
                </button>
              </form>

              <?php if (!empty($user['foto_perfil'])): ?>
                <form method="POST" action="" class="priv-photo-remove">
                  <?= csrfField() ?>
                  <input type="hidden" name="acao" value="foto_remover">
                  <button type="submit" class="priv-savebar-discard">
                    <i class="bi bi-trash3" aria-hidden="true"></i> Remover foto
                  </button>
                </form>
              <?php endif; ?>

              <p class="priv-help">
                A foto aparece no menu, no seu perfil e no Portal de Transparência.
              </p>
            </div>

            <div class="priv-theme-col">
              <h3 class="priv-subtitle">Cor do banner</h3>
              <p class="priv-help mb-3">
                Escolha a cor do banner do seu perfil público. Ela também aparece no seu cartão
                na lista de ONGs. O anel da foto segue sempre a cor
                da sua área de atuação, que identifica a causa em todo o site.
              </p>

              <div class="priv-theme-preview" data-priv-preview aria-hidden="true">
                <div class="priv-preview-cover">
                  <span class="priv-preview-circle priv-preview-circle-lg"></span>
                  <span class="priv-preview-circle priv-preview-circle-sm"></span>
                </div>
                <div class="priv-preview-body">
                  <span class="priv-preview-avatar">
                    <img src="<?= htmlspecialchars(fotoPerfilUrl($user['foto_perfil'] ?? null, '../', $user['area'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="">
                  </span>
                  <span class="priv-preview-copy">
                    <strong><?= htmlspecialchars($user['nome']) ?></strong>
                    <small>
                      <?= htmlspecialchars($user['area']) ?> · Membro desde <?= date('Y', strtotime($user['criado_em'])) ?>
                    </small>
                  </span>
                </div>
                <div class="priv-preview-card">
                  <span></span><span></span>
                </div>
              </div>

              <div class="priv-swatches" role="group" aria-label="Paleta de cores do banner">
                <?php foreach (ONGHELP_CORES_BANNER as $c => $nomeCor):
                  $selecionada = $c === $corBanner;
                  $rotuloCor = $nomeCor . ($c === $corDaArea ? ' (cor da sua área)' : '');
                ?>
                  <button type="button" class="btn-cor-tema priv-swatch<?= $selecionada ? ' is-selected' : '' ?><?= $c === $corDaArea ? ' is-area' : '' ?>"
                          data-cor="<?= $c ?>" data-nome="<?= htmlspecialchars($rotuloCor) ?>" title="<?= htmlspecialchars($rotuloCor) ?>"
                          aria-label="Cor <?= htmlspecialchars($rotuloCor) ?>"
                          aria-pressed="<?= $selecionada ? 'true' : 'false' ?>"
                          style="--swatch:<?= $c ?>;"></button>
                <?php endforeach; ?>
              </div>
              <p class="priv-cor-atual" aria-live="polite">
                Cor escolhida: <strong data-priv-cor-nome><?= htmlspecialchars(ONGHELP_CORES_BANNER[$corBanner] ?? '') ?><?= $corBanner === $corDaArea ? ' (cor da sua área)' : '' ?></strong>
              </p>

              <form method="POST" action="" class="priv-theme-form">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="tema_salvar">
                <input type="hidden" name="cor_tema" id="corTemaInput" value="<?= htmlspecialchars($corBanner) ?>">
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                  <i class="bi bi-check" aria-hidden="true"></i> Salvar cor
                </button>
              </form>

              <?php if (!empty($user['cor_tema'])): ?>
                <form method="POST" action="" class="priv-theme-reset">
                  <?= csrfField() ?>
                  <input type="hidden" name="acao" value="tema_reset">
                  <button type="submit" class="priv-savebar-discard">Voltar à cor da área</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- ── verificação E PIX ── -->
        <section class="priv-panel" id="priv-panel-verificacao-e-pix"
                 aria-labelledby="priv-title-verificacao-e-pix"<?= $secaoAtiva === 'verificacao-e-pix' ? '' : ' hidden' ?>>
          <h2 class="priv-panel-title" id="priv-title-verificacao-e-pix">Verificação e PIX</h2>
          <p class="priv-panel-desc">
            O selo indica que a Equipe ONGHelp conferiu o cadastro. Não é auditoria financeira.
          </p>

          <div class="priv-verif-grid">
            <ol class="priv-timeline">
              <li class="priv-step is-done">
                <span class="priv-step-marker"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                <span class="priv-step-copy">
                  <span class="priv-step-title">Cadastro enviado</span>
                  <span class="priv-step-meta"><?= fmtData($user['criado_em']) ?></span>
                </span>
              </li>
              <li class="priv-step is-done">
                <span class="priv-step-marker"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                <span class="priv-step-copy">
                  <span class="priv-step-title">Em análise</span>
                </span>
              </li>
              <li class="priv-step is-current">
                <span class="priv-step-marker"><i class="bi bi-patch-check-fill" aria-hidden="true"></i></span>
                <span class="priv-step-copy">
                  <span class="priv-step-title">Aprovada em <?= fmtData($dataAprovacao) ?></span>
                </span>
              </li>
            </ol>

            <div class="priv-verif-info">
              <div class="priv-badge-row">
                <?= badgeVerificado($user) ?>
                <span class="badge"><?= statusLabel($user['status']) ?></span>
              </div>

              <div class="priv-info-list">
                <div class="priv-info-row">
                  <span>Cadastro aprovado</span>
                  <strong><?= fmtData((string) ($user['aprovado_em'] ?? '')) ?></strong>
                </div>
                <div class="priv-info-row">
                  <span>Chave PIX (CNPJ)</span>
                  <strong><?= htmlspecialchars($user['pix_chave']) ?></strong>
                </div>
              </div>

              <div class="priv-info-note">
                <i class="bi bi-qr-code" aria-hidden="true"></i>
                O QR Code do perfil público é sempre gerado a partir desta chave verificada.
                Não existe upload de imagem.
              </div>
            </div>
          </div>
        </section>

        <!-- ── segurança ── -->
        <section class="priv-panel" id="priv-panel-seguranca"
                 aria-labelledby="priv-title-seguranca"<?= $secaoAtiva === 'seguranca' ? '' : ' hidden' ?>>
          <h2 class="priv-panel-title" id="priv-title-seguranca">Segurança</h2>
          <p class="priv-panel-desc">Para trocar a senha, confirme a senha atual e escolha uma nova que atenda aos requisitos abaixo.</p>

          <form method="POST" action="" class="priv-security-form"<?= senhaAtributosFormulario(SENHA_MINIMO_ONG, (string) $user['email']) ?>>
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="senha">
            <div class="priv-form-grid">
              <div class="priv-field priv-field-full">
                <label class="form-label" for="senhaAtual">Senha atual</label>
                <div class="auth-input-wrap">
                  <input type="password" name="senha_atual" class="form-control"
                         id="senhaAtual" autocomplete="current-password" required>
                  <button class="auth-eye" type="button"
                          data-password-toggle="senhaAtual" aria-label="Mostrar senha" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="priv-field">
                <label class="form-label" for="novaSenha">Nova senha</label>
                <div class="auth-input-wrap">
                  <input type="password" name="senha" class="form-control"
                         id="novaSenha" placeholder="Mínimo <?= SENHA_MINIMO_ONG ?> caracteres" autocomplete="new-password"
                         minlength="<?= SENHA_MINIMO_ONG ?>" maxlength="<?= SENHA_MAXIMO_BYTES ?>" aria-describedby="perfilSenhaRequisitos" required>
                  <button class="auth-eye" type="button"
                          data-password-toggle="novaSenha" aria-label="Mostrar senha" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="priv-field">
                <label class="form-label" for="confirmSenha">Confirmar nova senha</label>
                <div class="auth-input-wrap">
                  <input type="password" name="senha_confirm" class="form-control"
                         id="confirmSenha" placeholder="Repita a nova senha" autocomplete="new-password"
                         minlength="<?= SENHA_MINIMO_ONG ?>" maxlength="<?= SENHA_MAXIMO_BYTES ?>" required>
                  <button class="auth-eye" type="button"
                          data-password-toggle="confirmSenha" aria-label="Mostrar senha" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="priv-field priv-field-full" id="perfilSenhaRequisitos">
                <?= senhaChecklist(
                    [
                        'minimo' => 'minimo', 'letra_numero' => 'letra_numero', 'diferente_atual' => 'atual',
                        'sem_email' => 'email', 'nao_comum' => 'comum', 'iguais' => 'iguais',
                    ],
                    SENHA_MINIMO_ONG,
                    [],
                    ['lista' => 'auth-senha-checklist priv-senha-checklist']
                ) ?>
              </div>
            </div>
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-4 mt-4">
              <i class="bi bi-check" aria-hidden="true"></i> Atualizar senha
            </button>
          </form>
        </section>

        <!-- ── conta ── -->
        <section class="priv-panel priv-conta" id="priv-panel-conta"
                 aria-labelledby="priv-title-conta"<?= $secaoAtiva === 'conta' ? '' : ' hidden' ?>>
          <h2 class="priv-panel-title" id="priv-title-conta">Encerramento da conta</h2>
          <p class="priv-panel-desc">
            Todos os dados serão removidos permanentemente: perfil, movimentações, comprovantes, projetos e notícias.
            <strong>Ação irreversível.</strong>
          </p>
          <button type="button" class="btn btn-outline-danger rounded-pill px-4"
                  data-bs-toggle="modal" data-bs-target="#modalExcluirConta">
            <i class="bi bi-trash" aria-hidden="true"></i> Solicitar exclusão
          </button>
        </section>

      </div>
    </div>

    <div class="priv-savebar" data-priv-savebar hidden role="region" aria-label="Alterações não salvas">
      <span class="priv-savebar-text">Você tem alterações não salvas</span>
      <div class="priv-savebar-actions">
        <button type="button" class="priv-savebar-discard" data-priv-discard>Descartar</button>
        <button type="submit" form="privEditarForm" class="btn btn-primary rounded-pill px-4">
          Salvar alterações
        </button>
      </div>
    </div>
  </div>
</section>

<!-- ── modal DE exclusão ── -->
<div class="modal fade" id="modalExcluirConta" tabindex="-1"
     aria-labelledby="modalExcluirContaTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content priv-modal">
      <div class="modal-header priv-modal-header">
        <h2 class="modal-title priv-modal-title" id="modalExcluirContaTitulo">Solicitar exclusão</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="priv-modal-warning">
          Todos os dados serão removidos permanentemente: perfil, movimentações, comprovantes, projetos e notícias.
          <strong>Ação irreversível.</strong>
        </p>
        <label for="confirmarExclusao" class="priv-field-label">Digite o nome da ONG para confirmar</label>
        <input type="text" id="confirmarExclusao" class="form-control"
               data-priv-delete-input data-ong-nome="<?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?>"
               autocomplete="off">
        <small class="priv-help">
          Digite exatamente: <strong><?= htmlspecialchars($user['nome']) ?></strong>
        </small>
      </div>
      <div class="modal-footer priv-modal-footer">
        <button type="button" class="priv-savebar-discard" data-bs-dismiss="modal">Cancelar</button>
        <a href="excluir-conta.php" class="btn btn-danger rounded-pill px-4 disabled"
           data-priv-delete-confirm aria-disabled="true" tabindex="-1">
          Solicitar exclusão
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ── feedback ── -->
<?php if ($erro || $sucesso): ?>
  <div class="priv-toast-stack" aria-live="polite" aria-atomic="true">
    <div class="priv-toast <?= $erro ? 'is-erro' : 'is-sucesso' ?>"
         data-priv-toast role="<?= $erro ? 'alert' : 'status' ?>">
      <i class="bi <?= $erro ? 'bi-exclamation-circle' : 'bi-check-circle' ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($erro ?: $sucesso) ?></span>
      <button type="button" class="priv-toast-close" data-priv-toast-close aria-label="Fechar aviso">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
    </div>
  </div>
<?php endif; ?>

<?php
$page_scripts = ['core/senha.js', 'site/perfil-privado.js'];
include __DIR__ . '/../includes/layout/footer.php';
?>