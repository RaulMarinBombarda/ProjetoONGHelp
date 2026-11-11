<?php
// ============================================================
// pages/gestao.php — Gestão financeira e de projetos
// Projeto ONGHelp · Projeto Integrador 2026
// ============================================================

require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/session.php';
require_once '../includes/csrf.php';

// ── Protege: só usuário logado ────────────────────────────
exigirLoginOng();
csrfExigir();

$id      = (int) $_SESSION['user_id'];
$nome    = $_SESSION['user_nome'];
$erro    = '';
$sucesso = '';

// ── Processa formulários ──────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ── Nova movimentação ──
    if ($acao === 'add_mov') {
        $tipo     = $_POST['tipo'] ?? '';
        $descricao = trim($_POST['descricao'] ?? '');
        $valor    = valorParaDecimal($_POST['valor'] ?? '0');
        $data_mov = $_POST['data_mov'] ?? '';

        if (!in_array($tipo, ['receita', 'despesa'])) {
            $erro = 'Selecione o tipo (receita ou despesa).';
        } elseif ($descricao === '') {
            $erro = 'Informe a descrição da movimentação.';
        } elseif ($valor <= 0) {
            $erro = 'Informe um valor maior que zero.';
        } elseif ($data_mov === '') {
            $erro = 'Informe a data.';
        } else {
            $pdo->prepare("INSERT INTO movimentacoes (usuario_id, tipo, descricao, valor, data_mov) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, $tipo, $descricao, $valor, $data_mov]);
            $sucesso = 'Movimentação cadastrada com sucesso!';
        }
    }

    // ── Excluir movimentação ──
    elseif ($acao === 'del_mov') {
        $mov_id = (int) ($_POST['mov_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM movimentacoes WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$mov_id, $id]);
        $sucesso = $stmt->rowCount() > 0
            ? 'Movimentação excluída.'
            : 'Movimentação não encontrada.';
    }

    // ── Novo projeto ──
    elseif ($acao === 'add_proj') {
        $nomeProj  = trim($_POST['nome'] ?? '');
        $indicador = (int) ($_POST['indicador'] ?? 0);
        $unidade   = trim($_POST['unidade'] ?? '');

        if ($nomeProj === '') {
            $erro = 'Informe o nome do projeto.';
        } elseif ($unidade === '') {
            $erro = 'Informe o que aparece junto do número (ex: escolas parceiras).';
        } else {
            $pdo->prepare("INSERT INTO projetos (usuario_id, nome, indicador, unidade) VALUES (?, ?, ?, ?)")
                ->execute([$id, $nomeProj, $indicador, $unidade]);
            $sucesso = 'Projeto cadastrado com sucesso!';
        }
    }

    // ── Excluir projeto ──
    elseif ($acao === 'del_proj') {
        $proj_id = (int) ($_POST['proj_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM projetos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$proj_id, $id]);
        $sucesso = $stmt->rowCount() > 0
            ? 'Projeto excluído.'
            : 'Projeto não encontrado.';
    }
}

// ── Busca dados existentes ────────────────────────────────
$movimentacoes = $pdo->prepare("
    SELECT * FROM movimentacoes
    WHERE usuario_id = ?
    ORDER BY data_mov DESC, id DESC
    LIMIT 100
");
$movimentacoes->execute([$id]);
$movimentacoes = $movimentacoes->fetchAll();

$projetos = $pdo->prepare("
    SELECT * FROM projetos
    WHERE usuario_id = ?
    ORDER BY nome ASC
");
$projetos->execute([$id]);
$projetos = $projetos->fetchAll();

// ── Resumo financeiro do painel ───────────────────────────
$totalReceitas = 0.0;
$totalDespesas = 0.0;

foreach ($movimentacoes as $mov) {
    if ($mov['tipo'] === 'receita') {
        $totalReceitas += (float) $mov['valor'];
    } else {
        $totalDespesas += (float) $mov['valor'];
    }
}

$saldo       = $totalReceitas - $totalDespesas;
$saldoClasse = $saldo > 0 ? 'is-pos' : ($saldo < 0 ? 'is-neg' : 'is-zero');
$saldoSinal  = $saldo > 0 ? '+ ' : ($saldo < 0 ? '− ' : '');
$saldoTexto  = $saldoSinal . fmtDinheiro(abs($saldo));
$totalMov    = count($movimentacoes);
$totalProjetos = count($projetos);
$textoAjuda  = 'Doações recebidas via PIX devem ser lançadas aqui como receita, com a descrição correspondente. O portal de transparência reflete o valor como qualquer outra entrada.';

// ── HTML ──────────────────────────────────────────────────
$page_title = 'Gestão Financeira e Projetos';
$active_nav = 'gestao';
include '../includes/header.php';
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

    <!-- Cabeçalho -->
    <header class="gst-head">
      <div class="gst-head-copy">
        <span class="gst-eyebrow">Painel da <?= htmlspecialchars($nome) ?></span>
        <h1 class="gst-title">Gestão Financeira e Projetos</h1>
        <p class="gst-sub">
          Cadastre as receitas e despesas da sua organização e gerencie seus projetos ativos.
        </p>
      </div>

      <div class="gst-head-actions">
        <a href="noticias.php" class="btn btn-outline-secondary">
          <i class="bi bi-plus-lg" aria-hidden="true"></i> Publicar notícia
        </a>
        <a href="transparencia.php?ong=<?= $id ?>" class="btn btn-outline-secondary gst-portal-link">
          Ver no Portal de Transparência
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    </header>

    <!-- Faixa de KPIs -->
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
        <strong class="gst-kpi-value"><?= $totalProjetos ?></strong>
      </div>
    </div>

    <div class="gst-grid">

      <!-- ============================================================
           LIVRO-CAIXA
           ============================================================ -->
      <section class="gst-panel glass-1 gst-ledger-panel" aria-labelledby="gstLedgerTitle">

        <div class="gst-panel-head">
          <div class="gst-panel-title-row">
            <h2 class="gst-panel-title" id="gstLedgerTitle">
              Movimentações recentes
              <span class="gst-count" id="gstLedgerCount" aria-live="polite" aria-atomic="true">(<?= $totalMov ?>)</span>
            </h2>
            <button type="button" class="gst-help"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="gst-tooltip"
                    title="<?= htmlspecialchars($textoAjuda, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="Sobre o lançamento de movimentações">?</button>
          </div>

          <?php if ($totalMov > 0): ?>
          <div class="gst-filter" role="group" aria-label="Filtrar movimentações por tipo">
            <button type="button" class="gst-filter-btn is-active" data-gst-filter="todas" aria-pressed="true">Todas</button>
            <button type="button" class="gst-filter-btn" data-gst-filter="receita" aria-pressed="false">Receitas</button>
            <button type="button" class="gst-filter-btn" data-gst-filter="despesa" aria-pressed="false">Despesas</button>
          </div>
          <?php endif; ?>
        </div>

        <!-- Lançamento rápido — desktop -->
        <form method="POST" action="" class="gst-quick-form">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="add_mov">

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
                   placeholder="Descrição" required autocomplete="off">
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
            <input type="date" name="data_mov" id="gstData" class="form-control"
                   value="<?= date('Y-m-d') ?>" required>
          </div>

          <button type="submit" class="btn btn-primary gst-quick-submit">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar
          </button>
        </form>

        <!-- Lançamento rápido — mobile -->
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
                  <th scope="col"><span class="visually-hidden">Ação</span></th>
                </tr>
              </thead>
              <tbody id="gstLedgerBody">
                <?php foreach ($movimentacoes as $mov):
                  $ehReceita = $mov['tipo'] === 'receita';
                ?>
                <tr class="gst-mov-row" data-gst-tipo="<?= htmlspecialchars($mov['tipo']) ?>">
                  <td class="gst-td-data">
                    <time datetime="<?= htmlspecialchars((string) $mov['data_mov']) ?>"><?= fmtData((string) $mov['data_mov']) ?></time>
                  </td>
                  <td class="gst-td-desc"><?= htmlspecialchars($mov['descricao']) ?></td>
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
                    <form method="POST" action="" class="gst-delete-form">
                      <?= csrfField() ?>
                      <input type="hidden" name="acao" value="del_mov">
                      <input type="hidden" name="mov_id" value="<?= (int) $mov['id'] ?>">
                      <button type="submit" class="gst-trash"
                              data-confirmar-exclusao
                              data-confirmar-titulo="Excluir movimentação?"
                              data-confirmar-texto="Excluir esta movimentação? Essa ação não pode ser desfeita."
                              aria-label="Excluir movimentação"
                              title="Excluir movimentação">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>

                <tr class="gst-filter-empty" hidden>
                  <td colspan="5">Nenhuma movimentação deste tipo.</td>
                </tr>
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
           PROJETOS
           ============================================================ -->
      <aside class="gst-panel glass-1 gst-projects-panel" aria-labelledby="gstProjectsTitle">
        <div class="gst-panel-head gst-projects-head">
          <h2 class="gst-panel-title" id="gstProjectsTitle">Projetos ativos</h2>
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
            <?php foreach ($projetos as $proj): ?>
            <li class="gst-project">
              <div class="gst-project-main">
                <div class="gst-project-metric">
                  <span class="gst-project-number"><?= number_format((float) $proj['indicador'], 0, ',', '.') ?></span>
                  <span class="gst-project-unit"><?= htmlspecialchars($proj['unidade']) ?></span>
                </div>
                <div class="gst-project-name"><?= htmlspecialchars($proj['nome']) ?></div>
              </div>

              <form method="POST" action="" class="gst-delete-form">
                <?= csrfField() ?>
                <input type="hidden" name="acao" value="del_proj">
                <input type="hidden" name="proj_id" value="<?= (int) $proj['id'] ?>">
                <button type="submit" class="gst-trash"
                        data-confirmar-exclusao
                        data-confirmar-titulo="Excluir projeto?"
                        data-confirmar-texto="Excluir este projeto? Essa ação não pode ser desfeita."
                        aria-label="Excluir projeto <?= htmlspecialchars($proj['nome']) ?>"
                        title="Excluir projeto">
                  <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
              </form>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </aside>

    </div>
  </div>
</section>

<!-- Modal: nova movimentação (mobile) -->
<div class="modal fade" id="modalNovaMov" tabindex="-1" aria-labelledby="modalNovaMovTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-gestao">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="modalNovaMovTitulo">Nova movimentação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="POST" action="" class="gst-modal-form">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="add_mov">

          <fieldset class="gst-type-field">
            <legend class="gst-label">Tipo</legend>
            <div class="gst-type-seg">
              <input type="radio" name="tipo" id="gstTipoReceitaModal" value="receita" checked>
              <label for="gstTipoReceitaModal">Receita</label>
              <input type="radio" name="tipo" id="gstTipoDespesaModal" value="despesa">
              <label for="gstTipoDespesaModal">Despesa</label>
            </div>
          </fieldset>

          <div class="gst-modal-field">
            <label class="gst-label" for="gstDescricaoModal">Descrição</label>
            <input type="text" name="descricao" id="gstDescricaoModal" class="form-control"
                   placeholder="Ex: Doação via PIX" required autocomplete="off">
          </div>

          <div class="gst-modal-row">
            <div class="gst-modal-field">
              <label class="gst-label" for="gstValorModal">Valor (R$)</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" name="valor" id="gstValorModal" class="form-control"
                       placeholder="0,00" inputmode="decimal" required>
              </div>
            </div>

            <div class="gst-modal-field">
              <label class="gst-label" for="gstDataModal">Data</label>
              <input type="date" name="data_mov" id="gstDataModal" class="form-control"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal: novo projeto -->
<div class="modal fade" id="modalNovoProjeto" tabindex="-1" aria-labelledby="modalNovoProjetoTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-gestao">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="modalNovoProjetoTitulo">Novo projeto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="POST" action="" class="gst-modal-form">
          <?= csrfField() ?>
          <input type="hidden" name="acao" value="add_proj">

          <div class="gst-modal-field">
            <label class="gst-label" for="gstProjNome">Nome do projeto</label>
            <input type="text" name="nome" id="gstProjNome" class="form-control"
                   placeholder="Ex: Reflorestamento" required>
          </div>

          <div class="gst-modal-row">
            <div class="gst-modal-field">
              <label class="gst-label" for="gstProjIndicador">Número do resultado</label>
              <input type="number" name="indicador" id="gstProjIndicador" class="form-control"
                     value="0" min="0" step="1">
            </div>

            <div class="gst-modal-field">
              <label class="gst-label" for="gstProjUnidade">Descrição do número</label>
              <input type="text" name="unidade" id="gstProjUnidade" class="form-control"
                     placeholder="Ex: mudas plantadas" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Cadastrar projeto
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal de confirmação para exclusões -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="modalConfirmarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-confirmar">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title" id="modalConfirmarTitulo">
          <i class="bi bi-exclamation-triangle text-danger me-2" aria-hidden="true"></i><span id="modalConfirmarTituloTexto">Excluir movimentação?</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="modalConfirmarTexto">Excluir esta movimentação? Essa ação não pode ser desfeita.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill px-3" id="btnConfirmarExclusao">
          <i class="bi bi-trash" aria-hidden="true"></i> Excluir
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$page_script = 'gestao.js';
include '../includes/footer.php';
?>
