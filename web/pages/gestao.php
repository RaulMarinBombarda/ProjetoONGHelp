<?php
// ============================================================
// pages/gestao.php — gestão financeira e de projetos da ONG
// ============================================================
//
// livro-caixa (incluir, editar e excluir movimentações, com
// categoria, projeto e comprovante opcional) e projetos (incluir,
// editar e excluir, com situação, período e meta)
//
// editar é um link (?editar_mov=ID ou ?editar_proj=ID) que reabre a
// página com o modal já preenchido pelo servidor, assim funciona sem
// JavaScript e o modal volta aberto quando o envio tem erro
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/modules/gestao.php';

// só ONG logada e aprovada
exigirLoginOng();
csrfExigir();

$id   = (int) $_SESSION['user_id'];
$nome = $_SESSION['user_nome'];

// filtro do livro-caixa (?tipo=receita|despesa), mantido nos redirects
$filtrosTipo = ['todas' => 'Todas', 'receita' => 'Receitas', 'despesa' => 'Despesas'];
$tipoFiltro  = (string) ($_GET['tipo'] ?? 'todas');
if (!isset($filtrosTipo[$tipoFiltro])) {
    $tipoFiltro = 'todas';
}

/** endereço da própria página, com o filtro atual e um parâmetro extra */
function urlGestao(string $tipoFiltro, array $extra = []): string
{
    $params = ($tipoFiltro !== 'todas' ? ['tipo' => $tipoFiltro] : []) + $extra;

    return 'gestao.php' . ($params ? '?' . http_build_query($params) : '');
}

$stmtIdsProjetos = $pdo->prepare('SELECT id FROM projetos WHERE usuario_id = ?');
$stmtIdsProjetos->execute([$id]);
$idsProjetos = array_map('intval', $stmtIdsProjetos->fetchAll(PDO::FETCH_COLUMN));

// toda ação responde com redirect (POST/redirect/GET), atualizar a
// página nunca repete um lançamento e a mensagem vai pelo flash
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao     = (string) ($_POST['acao'] ?? '');
    $erroAcao = '';
    $okAcao   = '';
    $voltarPara = [];   // parâmetro extra do redirect (reabre o modal de edição no erro)

    if ($acao === 'add_mov' || $acao === 'edit_mov') {
        $movId = (int) ($_POST['mov_id'] ?? 0);
        $atual = null;

        if ($acao === 'edit_mov') {
            $stmt = $pdo->prepare('SELECT * FROM movimentacoes WHERE id = ? AND usuario_id = ?');
            $stmt->execute([$movId, $id]);
            $atual = $stmt->fetch() ?: null;
        }

        $lido = movimentacaoDoPost($_POST, $idsProjetos);

        if ($acao === 'edit_mov' && !$atual) {
            $erroAcao = 'Movimentação não encontrada.';
        } elseif ($lido['erro'] !== '') {
            $erroAcao = $lido['erro'];
        } else {
            // o arquivo só é gravado depois que o resto do formulário passou
            $envio = comprovanteSalvarUpload($_FILES['comprovante'] ?? null, $id);

            if (!$envio['ok']) {
                $erroAcao = $envio['erro'];
            } else {
                $d = $lido['dados'];
                $comprovante = $envio['arquivo'] ?? ($atual && empty($_POST['remover_comprovante']) ? $atual['comprovante'] : null);

                if ($acao === 'add_mov') {
                    $pdo->prepare(
                        'INSERT INTO movimentacoes (usuario_id, tipo, categoria, projeto_id, descricao, valor, data_mov, comprovante)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$id, $d['tipo'], $d['categoria'], $d['projeto_id'], $d['descricao'], $d['valor'], $d['data_mov'], $comprovante]);
                    $okAcao = 'Movimentação cadastrada com sucesso!';
                } else {
                    $pdo->prepare(
                        'UPDATE movimentacoes
                         SET tipo = ?, categoria = ?, projeto_id = ?, descricao = ?, valor = ?, data_mov = ?,
                             comprovante = ?, atualizado_em = NOW()
                         WHERE id = ? AND usuario_id = ?'
                    )->execute([$d['tipo'], $d['categoria'], $d['projeto_id'], $d['descricao'], $d['valor'], $d['data_mov'], $comprovante, $movId, $id]);

                    // o arquivo antigo sai do disco quando foi trocado ou removido
                    if (!empty($atual['comprovante']) && $atual['comprovante'] !== $comprovante) {
                        comprovanteApagar($atual['comprovante']);
                    }
                    $okAcao = 'Movimentação atualizada.';
                }
            }
        }

        if ($erroAcao !== '' && $atual) {
            $voltarPara = ['editar_mov' => $movId];
        }
    } elseif ($acao === 'del_mov') {
        $stmt = $pdo->prepare('SELECT comprovante FROM movimentacoes WHERE id = ? AND usuario_id = ?');
        $stmt->execute([(int) ($_POST['mov_id'] ?? 0), $id]);
        $mov = $stmt->fetch();

        if ($mov) {
            $pdo->prepare('DELETE FROM movimentacoes WHERE id = ? AND usuario_id = ?')
                ->execute([(int) $_POST['mov_id'], $id]);
            comprovanteApagar($mov['comprovante']);
            $okAcao = 'Movimentação excluída.';
        } else {
            $erroAcao = 'Movimentação não encontrada.';
        }
    } elseif ($acao === 'add_proj' || $acao === 'edit_proj') {
        $projId = (int) ($_POST['proj_id'] ?? 0);
        $lido   = projetoDoPost($_POST);

        if ($acao === 'edit_proj' && !in_array($projId, $idsProjetos, true)) {
            $erroAcao = 'Projeto não encontrado.';
        } elseif ($lido['erro'] !== '') {
            $erroAcao = $lido['erro'];
            if ($acao === 'edit_proj') {
                $voltarPara = ['editar_proj' => $projId];
            }
        } else {
            $d = $lido['dados'];
            $valores = [$d['nome'], $d['descricao'], $d['status'], $d['indicador'], $d['meta'], $d['unidade'], $d['data_inicio'], $d['data_fim']];

            if ($acao === 'add_proj') {
                $pdo->prepare(
                    'INSERT INTO projetos (nome, descricao, status, indicador, meta, unidade, data_inicio, data_fim, usuario_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([...$valores, $id]);
                $okAcao = 'Projeto cadastrado com sucesso!';
            } else {
                $pdo->prepare(
                    'UPDATE projetos
                     SET nome = ?, descricao = ?, status = ?, indicador = ?, meta = ?, unidade = ?,
                         data_inicio = ?, data_fim = ?, atualizado_em = NOW()
                     WHERE id = ? AND usuario_id = ?'
                )->execute([...$valores, $projId, $id]);
                $okAcao = 'Projeto atualizado.';
            }
        }
    } elseif ($acao === 'del_proj') {
        // as movimentações e notícias ligadas ficam, só perdem o vínculo (ON DELETE SET NULL)
        $stmt = $pdo->prepare('DELETE FROM projetos WHERE id = ? AND usuario_id = ?');
        $stmt->execute([(int) ($_POST['proj_id'] ?? 0), $id]);
        if ($stmt->rowCount() > 0) {
            $okAcao = 'Projeto excluído.';
        } else {
            $erroAcao = 'Projeto não encontrado.';
        }
    } else {
        $erroAcao = 'Ação inválida.';
    }

    flashSet($erroAcao !== '' ? 'gestao_erro' : 'gestao_sucesso', $erroAcao !== '' ? $erroAcao : $okAcao);
    header('Location: ' . urlGestao($tipoFiltro, $voltarPara));
    exit;
}

$erro    = flashGet('gestao_erro');
$sucesso = flashGet('gestao_sucesso');

// 100 mais recentes, com o nome do projeto ligado
$stmtMov = $pdo->prepare("
    SELECT m.*, p.nome AS projeto_nome
    FROM   movimentacoes m
    LEFT   JOIN projetos p ON p.id = m.projeto_id
    WHERE  m.usuario_id = ?
    ORDER  BY m.data_mov DESC, m.id DESC
    LIMIT  100
");
$stmtMov->execute([$id]);
$movimentacoes = $stmtMov->fetchAll();

$projetos = projetosDaOng($pdo, $id);

// resumo financeiro: totais de todas as movimentações, não só das 100 listadas
$totais = $pdo->prepare("
    SELECT COUNT(*) AS qtd,
           COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor END), 0) AS receitas,
           COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor END), 0) AS despesas
    FROM movimentacoes WHERE usuario_id = ?
");
$totais->execute([$id]);
$totais = $totais->fetch();
$totalReceitas = (float) $totais['receitas'];
$totalDespesas = (float) $totais['despesas'];
$totalMov      = (int) $totais['qtd'];

$saldo       = $totalReceitas - $totalDespesas;
$saldoClasse = $saldo > 0 ? 'is-pos' : ($saldo < 0 ? 'is-neg' : 'is-zero');
$saldoSinal  = $saldo > 0 ? '+ ' : ($saldo < 0 ? '− ' : '');
$saldoTexto  = $saldoSinal . fmtDinheiro(abs($saldo));

$movimentacoesVisiveis = $tipoFiltro === 'todas'
    ? $movimentacoes
    : array_values(array_filter($movimentacoes, fn (array $mov): bool => $mov['tipo'] === $tipoFiltro));
$projetosAtivos = count(array_filter($projetos, fn (array $p): bool => $p['status'] === 'andamento'));
$textoAjuda  = 'Doações recebidas via PIX devem ser lançadas aqui como receita, com a descrição correspondente. O portal de transparência reflete o valor como qualquer outra entrada.';

// modais de edição pedidos pela URL (só registros da própria ONG)
$editarMov = null;
if (isset($_GET['editar_mov']) && ctype_digit((string) $_GET['editar_mov'])) {
    $stmt = $pdo->prepare('SELECT * FROM movimentacoes WHERE id = ? AND usuario_id = ?');
    $stmt->execute([(int) $_GET['editar_mov'], $id]);
    $editarMov = $stmt->fetch() ?: null;
}
$editarProj = null;
if (isset($_GET['editar_proj']) && ctype_digit((string) $_GET['editar_proj'])) {
    foreach ($projetos as $proj) {
        if ((int) $proj['id'] === (int) $_GET['editar_proj']) {
            $editarProj = $proj;
        }
    }
}

/**
 * campos da movimentação usados nos modais de inclusão e de edição,
 * $prefixo deixa os ids únicos na página e $mov traz os valores atuais
 */
$camposMovimentacao = static function (string $prefixo, array $mov) use ($projetos): void {
    $tipo = $mov['tipo'] ?? 'receita';
    $valor = isset($mov['valor']) ? number_format((float) $mov['valor'], 2, ',', '.') : '';
    ?>
          <fieldset class="gst-type-field">
            <legend class="gst-label">Tipo</legend>
            <div class="gst-type-seg">
              <input type="radio" name="tipo" id="<?= $prefixo ?>Receita" value="receita"<?= $tipo === 'receita' ? ' checked' : '' ?>>
              <label for="<?= $prefixo ?>Receita">Receita</label>
              <input type="radio" name="tipo" id="<?= $prefixo ?>Despesa" value="despesa"<?= $tipo === 'despesa' ? ' checked' : '' ?>>
              <label for="<?= $prefixo ?>Despesa">Despesa</label>
            </div>
          </fieldset>

          <div class="gst-modal-field">
            <label class="gst-label" for="<?= $prefixo ?>Descricao">Descrição</label>
            <input type="text" name="descricao" id="<?= $prefixo ?>Descricao" class="form-control" maxlength="255"
                   placeholder="Ex.: Doação via PIX" value="<?= e($mov['descricao'] ?? '') ?>" required autocomplete="off">
          </div>

          <div class="gst-modal-row">
            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Valor">Valor (R$)</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" name="valor" id="<?= $prefixo ?>Valor" class="form-control"
                       placeholder="0,00" inputmode="decimal" value="<?= e($valor) ?>" required>
              </div>
            </div>

            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Data">Data</label>
              <input type="date" name="data_mov" id="<?= $prefixo ?>Data" class="form-control" min="2000-01-01" max="<?= date('Y-m-d') ?>"
                     value="<?= e($mov['data_mov'] ?? date('Y-m-d')) ?>" required>
            </div>
          </div>

          <div class="gst-modal-row">
            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Categoria">Categoria</label>
              <?php seletorCategoria($prefixo . 'Categoria', $tipo, (string) ($mov['categoria'] ?? '')); ?>
            </div>

            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Projeto">Projeto <span class="gst-opcional">(opcional)</span></label>
              <?php seletorProjeto($prefixo . 'Projeto', $projetos, (int) ($mov['projeto_id'] ?? 0)); ?>
            </div>
          </div>

          <div class="gst-modal-field">
            <label class="gst-label" for="<?= $prefixo ?>Comprovante">
              <?= !empty($mov['comprovante']) ? 'Trocar comprovante' : 'Comprovante' ?> <span class="gst-opcional">(opcional)</span>
            </label>
            <?php $urlComprovante = comprovanteUrl($mov['comprovante'] ?? null); ?>
            <?php if ($urlComprovante !== ''): ?>
              <div class="gst-comprovante-atual">
                <a href="<?= e($urlComprovante) ?>" target="_blank" rel="noopener">
                  <i class="bi bi-paperclip" aria-hidden="true"></i> Ver comprovante atual
                </a>
                <label class="gst-remover">
                  <input type="checkbox" name="remover_comprovante" value="1"> Remover
                </label>
              </div>
            <?php endif; ?>
            <input type="file" name="comprovante" id="<?= $prefixo ?>Comprovante" class="form-control"
                   accept=".pdf,image/jpeg,image/png,image/webp" aria-describedby="<?= $prefixo ?>ComprovanteAjuda">
            <p class="gst-ajuda" id="<?= $prefixo ?>ComprovanteAjuda">
              PDF ou foto de até <?= (int) round(ONGHELP_COMPROVANTE_MAX_BYTES / 1024 / 1024) ?> MB. O arquivo fica público no
              portal de transparência, então cubra dados pessoais de doadores e funcionários antes de enviar.
            </p>
          </div>
    <?php
};

/**
 * seleção de categoria com os dois grupos, o gestao.js mostra só o
 * grupo do tipo marcado, sem JavaScript os dois aparecem e o servidor
 * confere se a categoria é do tipo escolhido
 */
function seletorCategoria(string $idCampo, string $tipo, string $atual): void
{
    echo '<select name="categoria" id="' . e($idCampo) . '" class="form-select" data-categoria-movimentacao>';
    foreach (['receita' => 'Receitas', 'despesa' => 'Despesas'] as $tipoGrupo => $rotuloGrupo) {
        echo '<optgroup label="' . $rotuloGrupo . '" data-grupo="' . $tipoGrupo . '">';
        foreach (categoriasMovimentacao($tipoGrupo) as $valor => $rotulo) {
            $marcado = $tipoGrupo === $tipo && $valor === ($atual !== '' ? $atual : 'outra');
            echo '<option value="' . e($valor) . '"' . ($marcado ? ' selected' : '') . '>' . e($rotulo) . '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select>';
}

function seletorProjeto(string $idCampo, array $projetos, int $atual): void
{
    echo '<select name="projeto_id" id="' . e($idCampo) . '" class="form-select">';
    echo '<option value="">Sem projeto</option>';
    foreach ($projetos as $proj) {
        echo '<option value="' . (int) $proj['id'] . '"' . ((int) $proj['id'] === $atual ? ' selected' : '') . '>'
            . e($proj['nome']) . ($proj['status'] === 'concluido' ? ' (concluído)' : '') . '</option>';
    }
    echo '</select>';
}

/** campos do projeto usados nos modais de inclusão e de edição */
$camposProjeto = static function (string $prefixo, array $proj): void {
    ?>
          <div class="gst-modal-field">
            <label class="gst-label" for="<?= $prefixo ?>Nome">Nome do projeto</label>
            <input type="text" name="nome" id="<?= $prefixo ?>Nome" class="form-control" maxlength="160"
                   placeholder="Ex.: Reflorestamento" value="<?= e($proj['nome'] ?? '') ?>" required>
          </div>

          <div class="gst-modal-field">
            <label class="gst-label" for="<?= $prefixo ?>Descricao">Descrição <span class="gst-opcional">(opcional)</span></label>
            <textarea name="descricao" id="<?= $prefixo ?>Descricao" class="form-control" rows="3"
                      maxlength="<?= ONGHELP_PROJETO_DESCRICAO_MAX ?>"
                      placeholder="O que o projeto faz, para quem e onde"><?= e($proj['descricao'] ?? '') ?></textarea>
          </div>

          <div class="gst-modal-field">
            <label class="gst-label" for="<?= $prefixo ?>Status">Situação</label>
            <select name="status" id="<?= $prefixo ?>Status" class="form-select">
              <?php foreach (ONGHELP_STATUS_PROJETO as $valor => $rotulo): ?>
                <option value="<?= $valor ?>"<?= ($proj['status'] ?? 'andamento') === $valor ? ' selected' : '' ?>><?= $rotulo ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="gst-modal-row">
            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Indicador">Número do resultado</label>
              <input type="number" name="indicador" id="<?= $prefixo ?>Indicador" class="form-control"
                     value="<?= (int) ($proj['indicador'] ?? 0) ?>" min="0" step="1">
            </div>

            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Unidade">Descrição do número</label>
              <input type="text" name="unidade" id="<?= $prefixo ?>Unidade" class="form-control" maxlength="80"
                     placeholder="Ex.: mudas plantadas" value="<?= e($proj['unidade'] ?? '') ?>" required>
            </div>
          </div>

          <div class="gst-modal-field">
            <label class="gst-label" for="<?= $prefixo ?>Meta">Meta <span class="gst-opcional">(opcional)</span></label>
            <input type="number" name="meta" id="<?= $prefixo ?>Meta" class="form-control" min="1" step="1"
                   placeholder="Ex.: 2000" value="<?= !empty($proj['meta']) ? (int) $proj['meta'] : '' ?>"
                   aria-describedby="<?= $prefixo ?>MetaAjuda">
            <p class="gst-ajuda" id="<?= $prefixo ?>MetaAjuda">Com meta, o perfil público mostra quanto dela já foi alcançado.</p>
          </div>

          <div class="gst-modal-row">
            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Inicio">Início <span class="gst-opcional">(opcional)</span></label>
              <input type="date" name="data_inicio" id="<?= $prefixo ?>Inicio" class="form-control" min="2000-01-01"
                     value="<?= e($proj['data_inicio'] ?? '') ?>">
            </div>

            <div class="gst-modal-field">
              <label class="gst-label" for="<?= $prefixo ?>Fim">Término <span class="gst-opcional">(opcional)</span></label>
              <input type="date" name="data_fim" id="<?= $prefixo ?>Fim" class="form-control" min="2000-01-01"
                     value="<?= e($proj['data_fim'] ?? '') ?>">
            </div>
          </div>
    <?php
};

// ── HTML ──────────────────────────────────────────────────
$page_title = 'Gestão Financeira e Projetos';
$active_nav = 'gestao';
include __DIR__ . '/../includes/layout/header.php';
?>

<?php if ($erro !== '' || $sucesso !== ''): ?>
<div class="app-toast" role="<?= $erro !== '' ? 'alert' : 'status' ?>" aria-live="<?= $erro !== '' ? 'assertive' : 'polite' ?>">
  <div class="alert <?= $erro !== '' ? 'alert-danger' : 'alert-success' ?> alert-dismissible d-flex align-items-center gap-2" role="alert">
    <i class="bi <?= $erro !== '' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' ?>" aria-hidden="true"></i>
    <span><?= htmlspecialchars($erro !== '' ? $erro : $sucesso) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
</div>
<?php endif; ?>

<section class="gestao-page gst-page">
  <div class="container">

    <!-- cabeçalho -->
    <header class="gst-head">
      <div class="gst-head-copy">
        <span class="gst-eyebrow">Painel da <?= htmlspecialchars($nome) ?></span>
        <h1 class="gst-title">Gestão Financeira e Projetos</h1>
        <p class="gst-sub">
          Cadastre as receitas e despesas da sua organização e gerencie seus projetos.
        </p>
      </div>

      <div class="gst-head-actions">
        <a href="minhas-noticias.php" class="btn btn-outline-secondary">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> Publicar notícia
        </a>
        <a href="transparencia.php?ong=<?= $id ?>" class="btn btn-outline-secondary gst-portal-link">
          Ver no Portal de Transparência
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    </header>

    <!-- faixa de KPIs -->
    <div class="gst-kpi-band glass-1" aria-label="Resumo financeiro e de projetos">
      <div class="gst-kpi">
        <span class="gst-kpi-label">Receitas</span>
        <strong class="gst-kpi-value is-receita"><?= fmtDinheiro($totalReceitas) ?></strong>
      </div>
      <div class="gst-kpi">
        <span class="gst-kpi-label">Despesas</span>
        <strong class="gst-kpi-value is-despesa"><?= fmtDinheiro($totalDespesas) ?></strong>
      </div>
      <div class="gst-kpi">
        <span class="gst-kpi-label">Saldo</span>
        <strong class="gst-kpi-value <?= $saldoClasse ?>"><?= $saldoTexto ?></strong>
      </div>
      <div class="gst-kpi">
        <span class="gst-kpi-label">Projetos ativos</span>
        <strong class="gst-kpi-value"><?= $projetosAtivos ?></strong>
      </div>
    </div>

    <div class="gst-grid">

      <!-- ============================================================
           livro-caixa
           ============================================================ -->
      <section class="gst-panel glass-1 gst-ledger-panel" aria-labelledby="gstLedgerTitle">

        <div class="gst-panel-head">
          <div class="gst-panel-title-row">
            <h2 class="gst-panel-title" id="gstLedgerTitle">
              Movimentações recentes
              <span class="gst-count" id="gstLedgerCount">(<?= count($movimentacoesVisiveis) ?>)</span>
            </h2>
            <button type="button" class="gst-help"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="gst-tooltip"
                    title="<?= htmlspecialchars($textoAjuda, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="Sobre o lançamento de movimentações">?</button>
          </div>

          <?php if ($totalMov > 0): ?>
          <nav class="gst-filter" aria-label="Filtrar movimentações por tipo">
            <?php foreach ($filtrosTipo as $valorFiltro => $rotuloFiltro):
              $ativo = $valorFiltro === $tipoFiltro;
              $hrefFiltro = urlGestao($valorFiltro) . '#gstLedgerTitle';
            ?>
              <a class="gst-filter-btn<?= $ativo ? ' is-active' : '' ?>" href="<?= e($hrefFiltro) ?>"<?= $ativo ? ' aria-current="true"' : '' ?>><?= $rotuloFiltro ?></a>
            <?php endforeach; ?>
          </nav>
          <?php endif; ?>
        </div>

        <!-- lançamento rápido, desktop -->
        <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-quick-form" enctype="multipart/form-data" data-mov-form>
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="add_mov">

          <div class="gst-quick-row">
            <fieldset class="gst-type-field">
              <legend class="visually-hidden">Tipo da movimentação</legend>
              <div class="gst-type-seg">
                <input type="radio" name="tipo" id="gstTipoReceita" value="receita" checked>
                <label for="gstTipoReceita">Receita</label>
                <input type="radio" name="tipo" id="gstTipoDespesa" value="despesa">
                <label for="gstTipoDespesa">Despesa</label>
              </div>
            </fieldset>

            <div class="gst-quick-desc">
              <label class="visually-hidden" for="gstDescricao">Descrição</label>
              <input type="text" name="descricao" id="gstDescricao" class="form-control"
                     placeholder="Descrição" maxlength="255" required autocomplete="off">
            </div>

            <div class="gst-quick-value">
              <label class="visually-hidden" for="gstValor">Valor (R$)</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" name="valor" id="gstValor" class="form-control"
                       placeholder="0,00" inputmode="decimal" required>
              </div>
            </div>

            <div class="gst-quick-date">
              <label class="visually-hidden" for="gstData">Data</label>
              <input type="date" name="data_mov" id="gstData" class="form-control" min="2000-01-01" max="<?= date('Y-m-d') ?>"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div class="gst-quick-row gst-quick-row-detalhes">
            <div class="gst-quick-cat">
              <label class="visually-hidden" for="gstCategoria">Categoria</label>
              <?php seletorCategoria('gstCategoria', 'receita', ''); ?>
            </div>

            <div class="gst-quick-proj">
              <label class="visually-hidden" for="gstProjeto">Projeto (opcional)</label>
              <?php seletorProjeto('gstProjeto', $projetos, 0); ?>
            </div>

            <div class="gst-quick-file">
              <label class="gst-quick-file-btn" for="gstComprovante" title="PDF ou foto, fica público no portal de transparência">
                <i class="bi bi-paperclip" aria-hidden="true"></i>
                <span data-arquivo-nome>Comprovante (opcional)</span>
              </label>
              <input type="file" name="comprovante" id="gstComprovante" class="visually-hidden"
                     accept=".pdf,image/jpeg,image/png,image/webp">
            </div>

            <button type="submit" class="btn btn-primary gst-quick-submit">
              <i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar
            </button>
          </div>
        </form>

        <!-- lançamento, mobile -->
        <button type="button" class="btn btn-primary gst-mobile-add"
                data-bs-toggle="modal" data-bs-target="#modalNovaMov">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> Nova movimentação
        </button>

        <?php if ($totalMov === 0): ?>
          <div class="gst-ledger-empty">
            <span class="gst-empty-icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            <p>Nenhuma movimentação cadastrada ainda.</p>
          </div>
        <?php else: ?>
          <div class="gst-ledger-scroll">
            <table class="table gst-ledger-table">
              <caption class="visually-hidden">Movimentações financeiras recentes</caption>
              <colgroup>
                <col class="gst-col-data">
                <col class="gst-col-desc">
                <col class="gst-col-tipo">
                <col class="gst-col-valor">
                <col class="gst-col-acao">
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">Data</th>
                  <th scope="col">Descrição</th>
                  <th scope="col">Tipo</th>
                  <th scope="col" class="num">Valor</th>
                  <th scope="col"><span class="visually-hidden">Ações</span></th>
                </tr>
              </thead>
              <tbody id="gstLedgerBody">
                <?php foreach ($movimentacoesVisiveis as $mov):
                  $ehReceita = $mov['tipo'] === 'receita';
                ?>
                <tr class="gst-mov-row">
                  <td class="gst-td-data">
                    <time datetime="<?= htmlspecialchars((string) $mov['data_mov']) ?>"><?= fmtData((string) $mov['data_mov']) ?></time>
                  </td>
                  <td class="gst-td-desc">
                    <?= htmlspecialchars($mov['descricao']) ?>
                    <span class="gst-mov-detalhes"><?= detalhesMovimentacaoHtml($mov) ?></span>
                  </td>
                  <td class="gst-td-tipo">
                    <span class="<?= $ehReceita ? 'tipo-receita' : 'tipo-despesa' ?>">
                      <i class="bi <?= $ehReceita ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill' ?>" aria-hidden="true"></i>
                      <?= $ehReceita ? 'Receita' : 'Despesa' ?>
                    </span>
                  </td>
                  <td class="gst-td-valor <?= $ehReceita ? 'is-receita' : 'is-despesa' ?>">
                    <?= $ehReceita ? '+ ' : '− ' ?><?= fmtDinheiro((float) $mov['valor']) ?>
                  </td>
                  <td class="gst-td-acao">
                    <a class="gst-icon-btn" href="<?= e(urlGestao($tipoFiltro, ['editar_mov' => (int) $mov['id']])) ?>"
                       aria-label="Editar movimentação <?= e($mov['descricao']) ?>" title="Editar movimentação">
                      <i class="bi bi-pencil" aria-hidden="true"></i>
                    </a>
                    <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-delete-form">
                      <?= csrfField() ?>
                      <input type="hidden" name="acao" value="del_mov">
                      <input type="hidden" name="mov_id" value="<?= (int) $mov['id'] ?>">
                      <button type="submit" class="gst-trash"
                              data-confirmar="Excluir esta movimentação? Essa ação não pode ser desfeita."
                              data-confirmar-titulo="Excluir movimentação?"
                              data-confirmar-botao="Excluir"
                              aria-label="Excluir movimentação <?= e($mov['descricao']) ?>"
                              title="Excluir movimentação">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>

                <?php if ($movimentacoesVisiveis === []): ?>
                <tr class="gst-filter-empty">
                  <td colspan="5">Nenhuma movimentação deste tipo.</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="gst-ledger-summary">
            <span><strong class="is-pos">+ <?= fmtDinheiro($totalReceitas) ?></strong> em receitas</span>
            <span class="gst-summary-dot" aria-hidden="true">·</span>
            <span><strong class="is-neg">− <?= fmtDinheiro($totalDespesas) ?></strong> em despesas</span>
          </div>
        <?php endif; ?>

      </section>

      <!-- ============================================================
           projetos
           ============================================================ -->
      <aside class="gst-panel glass-1 gst-projects-panel" aria-labelledby="gstProjectsTitle">
        <div class="gst-panel-head gst-projects-head">
          <h2 class="gst-panel-title" id="gstProjectsTitle">Projetos</h2>
          <button type="button" class="btn btn-primary btn-sm gst-new-project"
                  data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Novo projeto
          </button>
        </div>

        <?php if (empty($projetos)): ?>
          <div class="gst-projects-empty">
            <span class="gst-empty-icon" aria-hidden="true"><i class="bi bi-kanban"></i></span>
            <p>Você ainda não tem projetos</p>
            <button type="button" class="btn btn-primary btn-sm"
                    data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
              <i class="bi bi-plus-lg" aria-hidden="true"></i> Novo projeto
            </button>
          </div>
        <?php else: ?>
          <ul class="gst-projects-list">
            <?php foreach ($projetos as $proj):
              $progresso = progressoProjeto($proj);
              $periodo   = periodoProjeto($proj);
            ?>
            <li class="gst-project">
              <div class="gst-project-main">
                <div class="gst-project-topo">
                  <span class="status-projeto is-<?= e($proj['status']) ?>"><?= e(rotuloStatusProjeto($proj['status'])) ?></span>
                  <?php if ($periodo !== ''): ?>
                    <span class="gst-project-periodo"><?= e($periodo) ?></span>
                  <?php endif; ?>
                </div>
                <div class="gst-project-metric">
                  <span class="gst-project-number"><?= number_format((float) $proj['indicador'], 0, ',', '.') ?></span>
                  <span class="gst-project-unit"><?= htmlspecialchars($proj['unidade']) ?></span>
                </div>
                <div class="gst-project-name"><?= htmlspecialchars($proj['nome']) ?></div>
                <?php if ($progresso !== null): ?>
                  <div class="meta-projeto">
                    <span class="meta-projeto-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progresso ?>"
                          aria-label="<?= $progresso ?>% da meta" style="--progresso:<?= $progresso ?>%;"><span></span></span>
                    <span class="meta-projeto-texto"><?= $progresso ?>% da meta de <?= number_format((float) $proj['meta'], 0, ',', '.') ?></span>
                  </div>
                <?php endif; ?>
                <?php if ((float) $proj['total_aplicado'] > 0): ?>
                  <div class="gst-project-gasto"><?= fmtDinheiro((float) $proj['total_aplicado']) ?> em despesas ligadas</div>
                <?php endif; ?>
              </div>

              <div class="gst-project-acoes">
                <a class="gst-icon-btn" href="<?= e(urlGestao($tipoFiltro, ['editar_proj' => (int) $proj['id']])) ?>"
                   aria-label="Editar projeto <?= e($proj['nome']) ?>" title="Editar projeto">
                  <i class="bi bi-pencil" aria-hidden="true"></i>
                </a>
                <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-delete-form">
                  <?= csrfField() ?>
                  <input type="hidden" name="acao" value="del_proj">
                  <input type="hidden" name="proj_id" value="<?= (int) $proj['id'] ?>">
                  <button type="submit" class="gst-trash"
                          data-confirmar="Excluir este projeto? As movimentações e notícias ligadas a ele continuam, só perdem o vínculo."
                          data-confirmar-titulo="Excluir projeto?"
                          data-confirmar-botao="Excluir"
                          aria-label="Excluir projeto <?= htmlspecialchars($proj['nome']) ?>"
                          title="Excluir projeto">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                  </button>
                </form>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </aside>

    </div>
  </div>
</section>

<!-- modal: nova movimentação (no celular, no lugar do lançamento rápido) -->
<div class="modal fade" id="modalNovaMov" tabindex="-1" aria-labelledby="modalNovaMovTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-gestao">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="modalNovaMovTitulo">Nova movimentação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-modal-form" enctype="multipart/form-data" data-mov-form>
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="add_mov">
          <?php $camposMovimentacao('gstNova', []); ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($editarMov): ?>
<!-- modal: editar movimentação (aberto pelo link ?editar_mov=ID) -->
<div class="modal fade" id="modalEditarMov" tabindex="-1" aria-labelledby="modalEditarMovTitulo" aria-hidden="true" data-auto-open>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-gestao">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="modalEditarMovTitulo">Editar movimentação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-modal-form" enctype="multipart/form-data" data-mov-form>
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="edit_mov">
          <input type="hidden" name="mov_id" value="<?= (int) $editarMov['id'] ?>">
          <?php $camposMovimentacao('gstEditar', $editarMov); ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg" aria-hidden="true"></i> Salvar alterações
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- modal: novo projeto -->
<div class="modal fade" id="modalNovoProjeto" tabindex="-1" aria-labelledby="modalNovoProjetoTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-gestao">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="modalNovoProjetoTitulo">Novo projeto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-modal-form">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="add_proj">
          <?php $camposProjeto('gstProj', []); ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar projeto
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($editarProj): ?>
<!-- modal: editar projeto (aberto pelo link ?editar_proj=ID) -->
<div class="modal fade" id="modalEditarProjeto" tabindex="-1" aria-labelledby="modalEditarProjetoTitulo" aria-hidden="true" data-auto-open>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-gestao">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="modalEditarProjetoTitulo">Editar projeto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="POST" action="<?= e(urlGestao($tipoFiltro)) ?>" class="gst-modal-form">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="edit_proj">
          <input type="hidden" name="proj_id" value="<?= (int) $editarProj['id'] ?>">
          <?php $camposProjeto('gstEditarProj', $editarProj); ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg" aria-hidden="true"></i> Salvar alterações
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$page_scripts = ['site/gestao.js'];
include __DIR__ . '/../includes/layout/footer.php';
?>
